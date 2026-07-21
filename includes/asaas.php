<?php
/**
 * Integração Asaas — Pix + Boleto via API HTTP.
 * Documentação: https://docs.asaas.com/
 *
 * Credenciais: ENV (prioridade) ou Configurações → Financeiro no admin.
 * Auth: header access_token (API Key). Não usa certificado.
 */

require_once __DIR__ . '/env.php';

/** Lê setting do banco, com override por ENV se existir. */
function asaas_cfg(string $settingKey, string $envKey, string $default = ''): string {
    $env = app_env($envKey, '');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    if (function_exists('app_setting')) {
        return app_setting($settingKey, $default);
    }
    return $default;
}

function asaas_api_key(): string {
    // Remove espaços/quebras que às vezes colam no copy-paste
    $key = trim(asaas_cfg('asaas_api_key', 'ASAAS_API_KEY'));
    $key = preg_replace('/\s+/', '', $key) ?? $key;
    return $key;
}

/** Token opcional de autenticação do webhook (header asaas-access-token). */
function asaas_webhook_token(): string {
    return trim(asaas_cfg('asaas_webhook_token', 'ASAAS_WEBHOOK_TOKEN'));
}

function asaas_configured(): bool {
    return asaas_api_key() !== '';
}

/** Alias semântico: no Asaas, Pix e boleto usam a mesma API Key. */
function asaas_pix_configured(): bool {
    return asaas_configured();
}

/**
 * Detecta ambiente pela própria API Key (mais confiável que o checkbox).
 * @return 'sandbox'|'production'|null  null = não deu para inferir
 */
function asaas_key_environment(): ?string {
    $key = asaas_api_key();
    if ($key === '') return null;
    // Prefixo oficial Asaas
    if (str_contains($key, 'aact_hmlg') || str_starts_with($key, '$aact_hmlg')) {
        return 'sandbox';
    }
    if (str_contains($key, 'aact_prod') || str_starts_with($key, '$aact_prod')) {
        return 'production';
    }
    // Fallbacks comuns
    if (preg_match('/hmlg|sandbox|homolog/i', $key)) return 'sandbox';
    if (preg_match('/_prod_|production/i', $key)) return 'production';
    return null;
}

/**
 * true = sandbox (homologação), false = produção.
 * Prioridade: ambiente da API Key > ENV ASAAS_SANDBOX > setting do admin > default sandbox.
 */
function asaas_sandbox(): bool {
    $fromKey = asaas_key_environment();
    if ($fromKey === 'sandbox') return true;
    if ($fromKey === 'production') return false;

    $env = app_env('ASAAS_SANDBOX', null);
    if ($env !== null && $env !== false && $env !== '') {
        return app_env_bool('ASAAS_SANDBOX', true);
    }
    if (function_exists('app_setting')) {
        return app_setting('asaas_sandbox', '1') === '1';
    }
    return true;
}

function asaas_base_url(): string {
    return asaas_sandbox()
        ? 'https://api-sandbox.asaas.com/v3'
        : 'https://api.asaas.com/v3';
}

/** Rótulo amigável do ambiente efetivo. */
function asaas_ambiente_label(): string {
    return asaas_sandbox() ? 'Homologação (sandbox)' : 'Produção';
}

/**
 * Request HTTP à API Asaas.
 * @return array{code:int,data:array,raw:string}
 */
function asaas_request(string $method, string $path, ?array $body = null): array {
    $key = asaas_api_key();
    if ($key === '') {
        throw new RuntimeException('ASAAS_API_KEY não configurada.');
    }

    $url = asaas_base_url() . $path;
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'access_token: ' . $key,
        'User-Agent: SucessoNoRadio/1.0 (PHP; ' . (asaas_sandbox() ? 'sandbox' : 'production') . ')',
    ];

    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Asaas request error: ' . $cerr);
    }

    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        $data = [];
    }

    if ($code >= 400) {
        $msg = asaas_format_error($data, (string)$raw);
        // Mensagem clara para o erro mais comum (chave x ambiente)
        if ($code === 401 || stripos($msg, 'não pertence a este ambiente') !== false || stripos($msg, 'invalid_environment') !== false) {
            $keyEnv = asaas_key_environment();
            $usando = asaas_sandbox() ? 'sandbox (api-sandbox.asaas.com)' : 'produção (api.asaas.com)';
            $dica = 'A API Key não corresponde ao ambiente. ';
            if ($keyEnv === 'production') {
                $dica .= 'Sua chave é de PRODUÇÃO ($aact_prod_…). Desmarque “sandbox” no admin ou use a chave de sandbox.';
            } elseif ($keyEnv === 'sandbox') {
                $dica .= 'Sua chave é de SANDBOX ($aact_hmlg_…). Marque “sandbox” ou use a chave de produção.';
            } else {
                $dica .= 'Chave de sandbox começa com $aact_hmlg_ e de produção com $aact_prod_. Ambiente em uso agora: ' . $usando . '.';
            }
            throw new RuntimeException('Asaas HTTP ' . $code . ': ' . $msg . ' — ' . $dica);
        }
        throw new RuntimeException('Asaas HTTP ' . $code . ': ' . $msg);
    }

    return ['code' => $code, 'data' => $data, 'raw' => (string)$raw];
}

function asaas_format_error(array $data, string $raw): string {
    if (!empty($data['errors']) && is_array($data['errors'])) {
        $parts = [];
        foreach ($data['errors'] as $err) {
            if (is_array($err)) {
                $parts[] = (string)($err['description'] ?? $err['code'] ?? json_encode($err, JSON_UNESCAPED_UNICODE));
            } else {
                $parts[] = (string)$err;
            }
        }
        if ($parts) return implode(' | ', $parts);
    }
    if (!empty($data['message'])) return (string)$data['message'];
    return mb_substr($raw !== '' ? $raw : 'erro desconhecido', 0, 400);
}

/**
 * Garante cliente no Asaas e grava asaas_customer_id no banco local.
 */
function asaas_ensure_customer(array $cliente): string {
    $existing = trim((string)($cliente['asaas_customer_id'] ?? ''));
    if ($existing !== '') {
        return $existing;
    }

    $cpf = app_only_digits((string)($cliente['cpf'] ?? ''));
    if (strlen($cpf) !== 11 && strlen($cpf) !== 14) {
        throw new RuntimeException('CPF/CNPJ do cliente é obrigatório para cobranças no Asaas.');
    }

    $nome = trim((string)($cliente['nome'] ?? 'Cliente'));
    if ($nome === '') $nome = 'Cliente';

    $email = trim((string)($cliente['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = 'cliente' . intval($cliente['id'] ?? 0) . '@sem-email.local';
    }

    $fone = app_only_digits((string)($cliente['telefone'] ?? $cliente['whatsapp'] ?? ''));
    $mobile = $fone;
    if (strlen($mobile) >= 10 && strlen($mobile) <= 11) {
        // ok
    } elseif (strlen($mobile) > 11) {
        $mobile = substr($mobile, -11);
    } else {
        $mobile = '';
    }

    // Reutiliza cliente existente no Asaas (evita duplicatas)
    $foundId = asaas_find_customer_id($cpf, (string)($cliente['id'] ?? ''));
    if ($foundId !== '') {
        asaas_save_customer_id(intval($cliente['id'] ?? 0), $foundId);
        return $foundId;
    }

    $body = [
        'name' => mb_substr($nome, 0, 100),
        'cpfCnpj' => $cpf,
        'email' => $email,
        'externalReference' => (string)intval($cliente['id'] ?? 0),
        'notificationDisabled' => true, // notificações ficam no nosso sistema
    ];
    if ($mobile !== '') {
        $body['mobilePhone'] = $mobile;
        $body['phone'] = $mobile;
    }

    $res = asaas_request('POST', '/customers', $body);
    $id = (string)($res['data']['id'] ?? '');
    if ($id === '') {
        throw new RuntimeException('Asaas não retornou ID do cliente.');
    }
    asaas_save_customer_id(intval($cliente['id'] ?? 0), $id);
    return $id;
}

function asaas_find_customer_id(string $cpfCnpj, string $externalRef = ''): string {
    try {
        if ($cpfCnpj !== '') {
            $q = '/customers?cpfCnpj=' . rawurlencode($cpfCnpj) . '&limit=1';
            $res = asaas_request('GET', $q);
            $list = $res['data']['data'] ?? [];
            if (!empty($list[0]['id'])) {
                return (string)$list[0]['id'];
            }
        }
        if ($externalRef !== '' && $externalRef !== '0') {
            $q = '/customers?externalReference=' . rawurlencode($externalRef) . '&limit=1';
            $res = asaas_request('GET', $q);
            $list = $res['data']['data'] ?? [];
            if (!empty($list[0]['id'])) {
                return (string)$list[0]['id'];
            }
        }
    } catch (Throwable $e) {
        // segue para criar
    }
    return '';
}

function asaas_save_customer_id(int $clienteId, string $asaasId): void {
    if ($clienteId <= 0 || $asaasId === '') return;
    try {
        app_pdo()->prepare(
            'UPDATE clientes SET asaas_customer_id = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$asaasId, $clienteId]);
    } catch (Throwable $e) { /* ok */ }
}

/**
 * Cria cobrança Pix e obtém QR Code.
 * @return array{payment_id,txid,loc_id,qrcode,copia_cola,expiracao,invoice_url}
 */
function asaas_criar_pix(array $cliente, int $valorCentavos, string $descricao, string $vencimentoYmd, string $externalRef = ''): array {
    if (!asaas_configured()) {
        throw new RuntimeException('Asaas não configurado (API Key).');
    }
    $customerId = asaas_ensure_customer($cliente);
    $value = round($valorCentavos / 100, 2);
    if ($value < 0.01) {
        throw new RuntimeException('Valor inválido para cobrança.');
    }

    $body = [
        'customer' => $customerId,
        'billingType' => 'PIX',
        'value' => $value,
        'dueDate' => $vencimentoYmd,
        'description' => mb_substr($descricao !== '' ? $descricao : 'Mensalidade', 0, 500),
        'externalReference' => $externalRef !== '' ? $externalRef : null,
    ];
    $body = array_filter($body, static fn($v) => $v !== null && $v !== '');

    $res = asaas_request('POST', '/payments', $body);
    $pay = $res['data'];
    $paymentId = (string)($pay['id'] ?? '');
    if ($paymentId === '') {
        throw new RuntimeException('Asaas não retornou ID da cobrança Pix.');
    }

    $qr = asaas_request('GET', '/payments/' . rawurlencode($paymentId) . '/pixQrCode');
    $qrData = $qr['data'];
    $encoded = (string)($qrData['encodedImage'] ?? '');
    $payload = (string)($qrData['payload'] ?? '');
    $exp = (string)($qrData['expirationDate'] ?? '');

    return [
        'payment_id' => $paymentId,
        'txid' => $paymentId, // reutiliza coluna pix_txid
        'loc_id' => '',
        'qrcode' => $encoded,
        'copia_cola' => $payload,
        'expiracao' => $exp,
        'invoice_url' => (string)($pay['invoiceUrl'] ?? ''),
        'raw' => $pay,
    ];
}

/**
 * Cria boleto.
 * @return array{charge_id,barcode,link,pdf}
 */
function asaas_criar_boleto(array $cliente, int $valorCentavos, string $descricao, string $vencimentoYmd, string $externalRef = ''): array {
    if (!asaas_configured()) {
        throw new RuntimeException('Asaas não configurado (API Key).');
    }
    $customerId = asaas_ensure_customer($cliente);
    $value = round($valorCentavos / 100, 2);
    if ($value < 0.01) {
        throw new RuntimeException('Valor inválido para cobrança.');
    }

    $body = [
        'customer' => $customerId,
        'billingType' => 'BOLETO',
        'value' => $value,
        'dueDate' => $vencimentoYmd,
        'description' => mb_substr($descricao !== '' ? $descricao : 'Mensalidade', 0, 500),
        'externalReference' => $externalRef !== '' ? $externalRef : null,
    ];
    $body = array_filter($body, static fn($v) => $v !== null && $v !== '');

    $res = asaas_request('POST', '/payments', $body);
    $pay = $res['data'];
    $paymentId = (string)($pay['id'] ?? '');
    if ($paymentId === '') {
        throw new RuntimeException('Asaas não retornou ID da cobrança boleto.');
    }

    $barcode = (string)($pay['identificationField'] ?? $pay['nossoNumero'] ?? '');
    $link = (string)($pay['bankSlipUrl'] ?? $pay['invoiceUrl'] ?? '');
    $pdf = $link;

    // Linha digitável às vezes só vem em endpoint separado ou após registro
    if ($barcode === '') {
        try {
            $idField = asaas_request('GET', '/payments/' . rawurlencode($paymentId) . '/identificationField');
            $barcode = (string)($idField['data']['identificationField'] ?? $idField['data']['barCode'] ?? '');
        } catch (Throwable $e) { /* ok */ }
    }

    return [
        'charge_id' => $paymentId,
        'barcode' => $barcode,
        'link' => $link,
        'pdf' => $pdf,
        'raw' => $pay,
    ];
}

function asaas_consultar_pagamento(string $paymentId): array {
    $paymentId = trim($paymentId);
    if ($paymentId === '') return [];
    $res = asaas_request('GET', '/payments/' . rawurlencode($paymentId));
    return $res['data'];
}

/** Status Asaas que consideram a fatura paga/liberada. */
function asaas_status_pago(string $status): bool {
    $status = strtoupper(trim($status));
    return in_array($status, [
        'RECEIVED',
        'CONFIRMED',
        'RECEIVED_IN_CASH',
        'DUNNING_RECEIVED',
    ], true);
}

/**
 * Gera meios de pagamento (Pix e/ou boleto) e grava na fatura.
 */
function finance_emitir_pagamento(int $faturaId): array {
    $fat = app_fatura_by_id($faturaId);
    if (!$fat) throw new RuntimeException('Fatura não encontrada.');
    if (!in_array($fat['status'], ['aberta', 'vencida'], true)) {
        throw new RuntimeException('Só é possível emitir cobrança para faturas em aberto ou vencidas.');
    }

    $st = app_pdo()->prepare('SELECT * FROM clientes WHERE id = ?');
    $st->execute([intval($fat['cliente_id'])]);
    $cli = $st->fetch();
    if (!$cli) throw new RuntimeException('Cliente não encontrado.');

    if (!asaas_configured()) {
        throw new RuntimeException('Asaas não configurado. Vá em Configurações → Financeiro e informe a API Key.');
    }

    $out = ['pix' => null, 'boleto' => null, 'erros' => []];
    $desc = (string)($fat['descricao'] ?: 'Mensalidade');
    $valor = intval($fat['valor_centavos']);
    $venc = (string)$fat['vencimento'];
    if ($venc === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc)) {
        $venc = date('Y-m-d', strtotime('+3 days'));
    }
    // Asaas não aceita dueDate no passado em alguns fluxos — usa hoje se vencido
    if ($venc < date('Y-m-d')) {
        $venc = date('Y-m-d');
    }
    $extRef = 'fatura_' . $faturaId;
    $pdo = app_pdo();

    // Pix — reutiliza cobrança aberta se já existir
    $existingPix = trim((string)($fat['pix_txid'] ?? ''));
    if ($existingPix !== '' && str_starts_with($existingPix, 'pay_')) {
        try {
            $pay = asaas_consultar_pagamento($existingPix);
            $stPay = strtoupper((string)($pay['status'] ?? ''));
            if (asaas_status_pago($stPay)) {
                finance_marcar_paga($faturaId, 'Pago via Asaas (sync na emissão)');
                $out['pix'] = ['payment_id' => $existingPix, 'already_paid' => true];
                return $out;
            }
            if (!in_array($stPay, ['DELETED', 'REFUNDED', 'REFUND_REQUESTED'], true)) {
                $qr = asaas_request('GET', '/payments/' . rawurlencode($existingPix) . '/pixQrCode');
                $qrData = $qr['data'];
                $pix = [
                    'payment_id' => $existingPix,
                    'txid' => $existingPix,
                    'loc_id' => '',
                    'qrcode' => (string)($qrData['encodedImage'] ?? $fat['pix_qrcode'] ?? ''),
                    'copia_cola' => (string)($qrData['payload'] ?? $fat['pix_copia_cola'] ?? ''),
                    'expiracao' => (string)($qrData['expirationDate'] ?? ''),
                ];
                $out['pix'] = $pix;
                $pdo->prepare(
                    'UPDATE faturas SET pix_qrcode=?, pix_copia_cola=?, updated_at=NOW() WHERE id=?'
                )->execute([$pix['qrcode'], $pix['copia_cola'], $faturaId]);
            } else {
                $existingPix = ''; // recria
            }
        } catch (Throwable $e) {
            $existingPix = '';
        }
    }

    if ($out['pix'] === null) {
        try {
            $pix = asaas_criar_pix($cli, $valor, $desc, $venc, $extRef . '_pix');
            $out['pix'] = $pix;
            $expSql = null;
            if (!empty($pix['expiracao'])) {
                try {
                    $expSql = date('Y-m-d H:i:s', strtotime((string)$pix['expiracao']));
                } catch (Throwable $e) {
                    $expSql = null;
                }
            }
            $pdo->prepare(
                'UPDATE faturas SET pix_txid=?, pix_loc_id=?, pix_qrcode=?, pix_copia_cola=?, pix_expira_em=COALESCE(?, NOW() + INTERVAL \'3 days\'), updated_at=NOW() WHERE id=?'
            )->execute([
                $pix['txid'],
                $pix['loc_id'],
                $pix['qrcode'],
                $pix['copia_cola'],
                $expSql,
                $faturaId,
            ]);
        } catch (Throwable $e) {
            $out['erros'][] = 'Pix: ' . $e->getMessage();
        }
    }

    // Boleto (exige CPF) — reutiliza se ainda válido
    $cpf = app_only_digits((string)($cli['cpf'] ?? ''));
    $existingBol = trim((string)($fat['boleto_charge_id'] ?? ''));
    if ($cpf === '') {
        $out['erros'][] = 'Boleto: informe CPF/CNPJ do cliente.';
    } elseif ($existingBol !== '' && str_starts_with($existingBol, 'pay_') && empty($fat['boleto_url'])) {
        // tenta refrescar dados
        try {
            $pay = asaas_consultar_pagamento($existingBol);
            if (asaas_status_pago((string)($pay['status'] ?? ''))) {
                finance_marcar_paga($faturaId, 'Pago via Asaas (sync boleto)');
                $out['boleto'] = ['charge_id' => $existingBol, 'already_paid' => true];
                return $out;
            }
            $bol = [
                'charge_id' => $existingBol,
                'barcode' => (string)($pay['identificationField'] ?? $fat['boleto_barcode'] ?? ''),
                'link' => (string)($pay['bankSlipUrl'] ?? $pay['invoiceUrl'] ?? $fat['boleto_url'] ?? ''),
                'pdf' => (string)($pay['bankSlipUrl'] ?? $fat['boleto_pdf'] ?? ''),
            ];
            $out['boleto'] = $bol;
            $pdo->prepare(
                'UPDATE faturas SET boleto_url=?, boleto_barcode=?, boleto_pdf=?, updated_at=NOW() WHERE id=?'
            )->execute([$bol['link'], $bol['barcode'], $bol['pdf'], $faturaId]);
        } catch (Throwable $e) {
            $existingBol = '';
        }
    }

    if ($out['boleto'] === null && $cpf !== '') {
        if ($existingBol !== '' && str_starts_with($existingBol, 'pay_') && !empty($fat['boleto_url'])) {
            $out['boleto'] = [
                'charge_id' => $existingBol,
                'barcode' => (string)$fat['boleto_barcode'],
                'link' => (string)$fat['boleto_url'],
                'pdf' => (string)($fat['boleto_pdf'] ?: $fat['boleto_url']),
            ];
        } else {
            try {
                $bol = asaas_criar_boleto($cli, $valor, $desc, $venc, $extRef . '_boleto');
                $out['boleto'] = $bol;
                $pdo->prepare(
                    'UPDATE faturas SET boleto_charge_id=?, boleto_url=?, boleto_barcode=?, boleto_pdf=?, updated_at=NOW() WHERE id=?'
                )->execute([
                    $bol['charge_id'], $bol['link'], $bol['barcode'], $bol['pdf'], $faturaId,
                ]);
            } catch (Throwable $e) {
                $out['erros'][] = 'Boleto: ' . $e->getMessage();
            }
        }
    }

    if ($out['pix'] === null && $out['boleto'] === null) {
        throw new RuntimeException(implode(' | ', $out['erros'] ?: ['Falha ao emitir meios de pagamento.']));
    }

    return $out;
}

function finance_marcar_paga(int $faturaId, string $obs = ''): void {
    app_pdo()->prepare(
        "UPDATE faturas SET status='paga', pago_em=COALESCE(pago_em, NOW()), observacao=CASE WHEN ? <> '' THEN ? ELSE observacao END, updated_at=NOW() WHERE id=? AND status IN ('aberta','vencida')"
    )->execute([$obs, $obs, $faturaId]);
}

/**
 * Sincroniza status da fatura consultando cobranças Asaas (Pix e/ou boleto).
 */
function finance_sync_pix_fatura(array $fat): array {
    return finance_sync_fatura($fat);
}

function finance_sync_fatura(array $fat): array {
    if (!asaas_configured()) return $fat;
    if (!in_array($fat['status'] ?? '', ['aberta', 'vencida'], true)) return $fat;

    $ids = [];
    $pixId = trim((string)($fat['pix_txid'] ?? ''));
    $bolId = trim((string)($fat['boleto_charge_id'] ?? ''));
    if ($pixId !== '') $ids[] = $pixId;
    if ($bolId !== '' && $bolId !== $pixId) $ids[] = $bolId;

    foreach ($ids as $paymentId) {
        try {
            $pay = asaas_consultar_pagamento($paymentId);
            $status = (string)($pay['status'] ?? '');
            if (asaas_status_pago($status)) {
                finance_marcar_paga(intval($fat['id']), 'Pago via Asaas (' . $status . ')');
                return app_fatura_by_id(intval($fat['id'])) ?: $fat;
            }
        } catch (Throwable $e) { /* tenta o próximo id */ }
    }
    return $fat;
}

/**
 * Marca fatura paga a partir de um payment id Asaas (webhook).
 * @return int quantidade de faturas atualizadas
 */
function finance_marcar_paga_por_payment_id(string $paymentId, string $obs = 'Pago via webhook Asaas'): int {
    $paymentId = trim($paymentId);
    if ($paymentId === '') return 0;

    $pdo = app_pdo();
    $st = $pdo->prepare(
        "SELECT id FROM faturas
         WHERE status IN ('aberta','vencida')
           AND (pix_txid = ? OR boleto_charge_id = ?)
         LIMIT 5"
    );
    $st->execute([$paymentId, $paymentId]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // Fallback: externalReference fatura_N
    if (!$ids) {
        try {
            $pay = asaas_consultar_pagamento($paymentId);
            $ext = (string)($pay['externalReference'] ?? '');
            if (preg_match('/^fatura_(\d+)/', $ext, $m)) {
                $fid = intval($m[1]);
                if ($fid > 0) {
                    $chk = $pdo->prepare("SELECT id FROM faturas WHERE id = ? AND status IN ('aberta','vencida')");
                    $chk->execute([$fid]);
                    $ids = $chk->fetchAll(PDO::FETCH_COLUMN) ?: [];
                }
            }
        } catch (Throwable $e) { /* ok */ }
    }

    $ok = 0;
    foreach ($ids as $id) {
        finance_marcar_paga(intval($id), $obs);
        $ok++;
    }
    return $ok;
}
