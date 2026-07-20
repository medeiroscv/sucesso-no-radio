<?php
/**
 * Integração Efí Bank (ex-Gerencianet) — Pix + Boleto via HTTP.
 * Documentação: https://dev.efipay.com.br/  |  SDK: efipay/sdk-php-apis-efi
 *
 * Env:
 *  EFI_CLIENT_ID, EFI_CLIENT_SECRET, EFI_SANDBOX=true|false
 *  EFI_PIX_KEY (chave Pix da conta)
 *  EFI_CERT_PATH (caminho absoluto .p12 ou .pem — obrigatório para Pix)
 *  EFI_CERT_PASSWORD (opcional)
 */

require_once __DIR__ . '/env.php';

function efi_configured(): bool {
    return app_env('EFI_CLIENT_ID', '') !== '' && app_env('EFI_CLIENT_SECRET', '') !== '';
}

function efi_pix_configured(): bool {
    if (!efi_configured() || app_env('EFI_PIX_KEY', '') === '') return false;
    $cert = app_env('EFI_CERT_PATH', '');
    return $cert !== '' && is_file($cert);
}

function efi_sandbox(): bool {
    return app_env_bool('EFI_SANDBOX', true);
}

function efi_base_url(string $api = 'cobrancas'): string {
    $sand = efi_sandbox();
    if ($api === 'pix') {
        return $sand ? 'https://pix-h.api.efipay.com.br' : 'https://pix.api.efipay.com.br';
    }
    return $sand ? 'https://cobrancas-h.api.efipay.com.br' : 'https://cobrancas.api.efipay.com.br';
}

/** Token OAuth2 (cache em arquivo em data/). */
function efi_oauth_token(string $api = 'cobrancas'): string {
    $cacheKey = efi_sandbox() ? "efi_token_{$api}_h" : "efi_token_{$api}_p";
    $cacheFile = dirname(__DIR__) . '/data/' . $cacheKey . '.json';
    if (is_file($cacheFile)) {
        $j = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($j) && !empty($j['access_token']) && ($j['exp'] ?? 0) > time() + 30) {
            return (string)$j['access_token'];
        }
    }

    $url = efi_base_url($api) . '/oauth/token';
    $id = app_env('EFI_CLIENT_ID', '');
    $secret = app_env('EFI_CLIENT_SECRET', '');
    $ch = curl_init($url);
    $opts = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($id . ':' . $secret),
        ],
        CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials']),
        CURLOPT_TIMEOUT => 30,
    ];
    if ($api === 'pix') {
        $cert = app_env('EFI_CERT_PATH', '');
        $pwd = app_env('EFI_CERT_PASSWORD', '');
        if (str_ends_with(strtolower($cert), '.p12') || str_ends_with(strtolower($cert), '.pfx')) {
            $opts[CURLOPT_SSLCERTTYPE] = 'P12';
            $opts[CURLOPT_SSLCERT] = $cert;
            if ($pwd !== '') $opts[CURLOPT_SSLCERTPASSWD] = $pwd;
        } else {
            $opts[CURLOPT_SSLCERT] = $cert;
            if ($pwd !== '') $opts[CURLOPT_SSLCERTPASSWD] = $pwd;
        }
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $code >= 400) {
        throw new RuntimeException('EFI OAuth falhou (' . $code . '): ' . ($err ?: $raw));
    }
    $data = json_decode($raw, true);
    if (empty($data['access_token'])) {
        throw new RuntimeException('EFI OAuth sem access_token');
    }
    $ttl = intval($data['expires_in'] ?? 600);
    @file_put_contents($cacheFile, json_encode([
        'access_token' => $data['access_token'],
        'exp' => time() + max(60, $ttl - 60),
    ]));
    return (string)$data['access_token'];
}

function efi_request(string $api, string $method, string $path, ?array $body = null): array {
    $token = efi_oauth_token($api);
    $url = efi_base_url($api) . $path;
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ];
    $opts = [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 45,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    if ($api === 'pix') {
        $cert = app_env('EFI_CERT_PATH', '');
        $pwd = app_env('EFI_CERT_PASSWORD', '');
        if (str_ends_with(strtolower($cert), '.p12') || str_ends_with(strtolower($cert), '.pfx')) {
            $opts[CURLOPT_SSLCERTTYPE] = 'P12';
            $opts[CURLOPT_SSLCERT] = $cert;
            if ($pwd !== '') $opts[CURLOPT_SSLCERTPASSWD] = $pwd;
        } else {
            $opts[CURLOPT_SSLCERT] = $cert;
            if ($pwd !== '') $opts[CURLOPT_SSLCERTPASSWD] = $pwd;
        }
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    $data = json_decode((string)$raw, true);
    if ($raw === false) {
        throw new RuntimeException('EFI request error: ' . $cerr);
    }
    if ($code >= 400) {
        $msg = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : (string)$raw;
        throw new RuntimeException('EFI HTTP ' . $code . ': ' . $msg);
    }
    return is_array($data) ? $data : [];
}

/**
 * Cria cobrança Pix imediata + QR Code.
 * @return array{txid, loc_id, qrcode, copia_cola, expiracao}
 */
function efi_criar_pix(array $cliente, int $valorCentavos, string $descricao, int $expiracaoSeg = 86400): array {
    if (!efi_pix_configured()) {
        throw new RuntimeException('Pix EFI não configurado (EFI_PIX_KEY + certificado).');
    }
    $chave = app_env('EFI_PIX_KEY', '');
    $txid = substr(bin2hex(random_bytes(16)), 0, 26);
    $cpf = app_only_digits((string)($cliente['cpf'] ?? ''));
    $nome = trim((string)($cliente['nome'] ?? 'Cliente'));
    $valor = number_format($valorCentavos / 100, 2, '.', '');

    $body = [
        'calendario' => ['expiracao' => max(600, $expiracaoSeg)],
        'valor' => ['original' => $valor],
        'chave' => $chave,
        'solicitacaoPagador' => mb_substr($descricao, 0, 140),
    ];
    if (strlen($cpf) === 11) {
        $body['devedor'] = ['cpf' => $cpf, 'nome' => mb_substr($nome, 0, 200)];
    } elseif (strlen($cpf) === 14) {
        $body['devedor'] = ['cnpj' => $cpf, 'nome' => mb_substr($nome, 0, 200)];
    }

    $cob = efi_request('pix', 'PUT', '/v2/cob/' . $txid, $body);
    $locId = (string)($cob['loc']['id'] ?? $cob['location'] ?? '');
    if ($locId === '' && !empty($cob['locid'])) $locId = (string)$cob['locid'];

    $qr = [];
    if ($locId !== '') {
        $qr = efi_request('pix', 'GET', '/v2/loc/' . $locId . '/qrcode');
    }

    return [
        'txid' => (string)($cob['txid'] ?? $txid),
        'loc_id' => $locId,
        'qrcode' => (string)($qr['imagemQrcode'] ?? $qr['qrcode'] ?? ''),
        'copia_cola' => (string)($qr['qrcode'] ?? $cob['pixCopiaECola'] ?? ''),
        'expiracao' => max(600, $expiracaoSeg),
        'raw' => $cob,
    ];
}

/**
 * Cria boleto em 1 passo (API Cobranças).
 * @return array{charge_id, barcode, link, pdf}
 */
function efi_criar_boleto(array $cliente, int $valorCentavos, string $descricao, string $vencimentoYmd): array {
    if (!efi_configured()) {
        throw new RuntimeException('EFI_CLIENT_ID/SECRET não configurados.');
    }
    $cpf = app_only_digits((string)($cliente['cpf'] ?? ''));
    if (strlen($cpf) !== 11 && strlen($cpf) !== 14) {
        throw new RuntimeException('CPF/CNPJ do cliente é obrigatório para emitir boleto.');
    }
    $fone = app_only_digits((string)($cliente['telefone'] ?? $cliente['whatsapp'] ?? ''));
    if (strlen($fone) < 10) $fone = '11999999999';
    $email = trim((string)($cliente['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = 'cliente' . intval($cliente['id'] ?? 0) . '@email.com';
    }

    $customer = [
        'name' => mb_substr(trim((string)$cliente['nome']), 0, 80),
        'email' => $email,
        'phone_number' => $fone,
    ];
    if (strlen($cpf) === 11) $customer['cpf'] = $cpf;
    else $customer['cnpj'] = $cpf;

    $body = [
        'items' => [[
            'name' => mb_substr($descricao !== '' ? $descricao : 'Mensalidade', 0, 80),
            'amount' => 1,
            'value' => $valorCentavos,
        ]],
        'payment' => [
            'banking_billet' => [
                'expire_at' => $vencimentoYmd,
                'customer' => $customer,
                'message' => mb_substr('Sucesso no Rádio · ' . $descricao, 0, 80),
            ],
        ],
    ];

    $data = efi_request('cobrancas', 'POST', '/v1/charge/one-step', $body);
    $charge = $data['data'] ?? $data;
    $payment = $charge['payment'] ?? [];
    $billet = $payment['banking_billet'] ?? $charge['banking_billet'] ?? [];

    return [
        'charge_id' => (string)($charge['charge_id'] ?? $charge['chargeId'] ?? ''),
        'barcode' => (string)($billet['barcode'] ?? $billet['bar_code'] ?? ''),
        'link' => (string)($billet['link'] ?? $billet['pdf']['charge'] ?? ''),
        'pdf' => (string)($billet['pdf']['charge'] ?? $billet['link'] ?? ''),
        'raw' => $data,
    ];
}

function efi_consultar_pix(string $txid): array {
    return efi_request('pix', 'GET', '/v2/cob/' . rawurlencode($txid));
}

/**
 * Gera meios de pagamento (Pix e/ou boleto) e grava na fatura.
 */
function finance_emitir_pagamento(int $faturaId): array {
    $fat = app_fatura_by_id($faturaId);
    if (!$fat) throw new RuntimeException('Fatura não encontrada.');
    $st = app_pdo()->prepare('SELECT * FROM clientes WHERE id = ?');
    $st->execute([intval($fat['cliente_id'])]);
    $cli = $st->fetch();
    if (!$cli) throw new RuntimeException('Cliente não encontrado.');

    $out = ['pix' => null, 'boleto' => null, 'erros' => []];
    $desc = (string)($fat['descricao'] ?: 'Mensalidade');
    $valor = intval($fat['valor_centavos']);
    $venc = (string)$fat['vencimento'];

    if (efi_pix_configured()) {
        try {
            $pix = efi_criar_pix($cli, $valor, $desc, 3 * 86400);
            $out['pix'] = $pix;
            app_pdo()->prepare(
                'UPDATE faturas SET pix_txid=?, pix_loc_id=?, pix_qrcode=?, pix_copia_cola=?, pix_expira_em=NOW() + INTERVAL \'3 days\', updated_at=NOW() WHERE id=?'
            )->execute([
                $pix['txid'], $pix['loc_id'], $pix['qrcode'], $pix['copia_cola'], $faturaId,
            ]);
        } catch (Throwable $e) {
            $out['erros'][] = 'Pix: ' . $e->getMessage();
        }
    } else {
        $out['erros'][] = 'Pix não configurado (credenciais/certificado/chave).';
    }

    if (efi_configured() && app_only_digits((string)($cli['cpf'] ?? '')) !== '') {
        try {
            $bol = efi_criar_boleto($cli, $valor, $desc, $venc);
            $out['boleto'] = $bol;
            app_pdo()->prepare(
                'UPDATE faturas SET boleto_charge_id=?, boleto_url=?, boleto_barcode=?, boleto_pdf=?, updated_at=NOW() WHERE id=?'
            )->execute([
                $bol['charge_id'], $bol['link'], $bol['barcode'], $bol['pdf'], $faturaId,
            ]);
        } catch (Throwable $e) {
            $out['erros'][] = 'Boleto: ' . $e->getMessage();
        }
    } else {
        $out['erros'][] = 'Boleto: informe CPF do cliente e credenciais EFI.';
    }

    return $out;
}

function finance_marcar_paga(int $faturaId, string $obs = ''): void {
    app_pdo()->prepare(
        "UPDATE faturas SET status='paga', pago_em=NOW(), observacao=CASE WHEN ? <> '' THEN ? ELSE observacao END, updated_at=NOW() WHERE id=?"
    )->execute([$obs, $obs, $faturaId]);
}

/** Sincroniza status Pix na Efí (quando possível). */
function finance_sync_pix_fatura(array $fat): array {
    $txid = trim((string)($fat['pix_txid'] ?? ''));
    if ($txid === '' || !efi_pix_configured()) return $fat;
    try {
        $cob = efi_consultar_pix($txid);
        $status = strtoupper((string)($cob['status'] ?? ''));
        if (in_array($status, ['CONCLUIDA', 'CONCLUIDO', 'LIQUIDADO'], true) || !empty($cob['pix'])) {
            finance_marcar_paga(intval($fat['id']), 'Pago via Pix (EFI)');
            return app_fatura_by_id(intval($fat['id'])) ?: $fat;
        }
    } catch (Throwable $e) { /* ok */ }
    return $fat;
}
