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
        // Mantém boleto pagável por 30 dias após o vencimento (evita “boleto cancelado” no dia seguinte)
        'daysAfterDueDateToRegistrationCancellation' => 30,
    ];
    $body = array_filter($body, static fn($v) => $v !== null && $v !== '');

    $res = asaas_request('POST', '/payments', $body);
    $pay = $res['data'];
    $paymentId = (string)($pay['id'] ?? '');
    if ($paymentId === '') {
        throw new RuntimeException('Asaas não retornou ID da cobrança boleto.');
    }

    $link = (string)($pay['bankSlipUrl'] ?? $pay['invoiceUrl'] ?? '');
    $pdf = $link;
    // Linha digitável completa (47 dígitos) — NUNCA usar nossoNumero (é só referência curta)
    $barcode = asaas_extrair_linha_digitavel($pay);

    // Registro do boleto pode demorar alguns segundos; tenta endpoint dedicado com retry
    if (!asaas_linha_digitavel_completa($barcode)) {
        $barcode = asaas_buscar_linha_digitavel($paymentId, 4);
    }
    if (!asaas_linha_digitavel_completa($barcode)) {
        // última tentativa: reconsulta a cobrança
        try {
            $pay2 = asaas_consultar_pagamento($paymentId);
            if (!empty($pay2['bankSlipUrl'])) {
                $link = (string)$pay2['bankSlipUrl'];
                $pdf = $link;
            }
            $barcode2 = asaas_extrair_linha_digitavel($pay2);
            if (asaas_linha_digitavel_completa($barcode2)) {
                $barcode = $barcode2;
            } elseif ($barcode === '' && $barcode2 !== '') {
                $barcode = $barcode2;
            }
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

/**
 * Linha digitável de boleto FEBRABAN: 47 dígitos (sem formatação).
 * Com espaços/pontos costuma ter ~54 caracteres — nunca é o nossoNumero (~8–11 dígitos).
 */
function asaas_linha_digitavel_completa(string $linha): bool {
    $digits = preg_replace('/\D+/', '', $linha) ?? '';
    return strlen($digits) >= 47;
}

/** Extrai identificationField do payload Asaas (ignora nossoNumero). */
function asaas_extrair_linha_digitavel(array $pay): string {
    $candidates = [
        $pay['identificationField'] ?? '',
        $pay['nossoNumero'] ?? '', // só se for a única opção; validamos tamanho depois
    ];
    // Às vezes vem aninhado
    if (!empty($pay['bankSlip']['identificationField'])) {
        array_unshift($candidates, $pay['bankSlip']['identificationField']);
    }
    foreach ($candidates as $i => $c) {
        $c = trim((string)$c);
        if ($c === '') continue;
        // nossoNumero nunca é linha digitável completa
        if ($i > 0 && !asaas_linha_digitavel_completa($c)) {
            continue;
        }
        if (asaas_linha_digitavel_completa($c)) {
            return asaas_formatar_linha_digitavel($c);
        }
        // guarda candidato “longo” mesmo sem 47 (pode vir parcial com formatação)
        if (strlen(preg_replace('/\D+/', '', $c) ?? '') >= 40) {
            return asaas_formatar_linha_digitavel($c);
        }
    }
    // tenta identificationField cru mesmo incompleto (melhor que vazio); evita nossoNumero
    $id = trim((string)($pay['identificationField'] ?? ''));
    return $id !== '' ? asaas_formatar_linha_digitavel($id) : '';
}

/** Formata 47 dígitos no padrão visual da linha digitável. */
function asaas_formatar_linha_digitavel(string $linha): string {
    $d = preg_replace('/\D+/', '', $linha) ?? '';
    if (strlen($d) < 47) {
        // devolve original se ainda não completa (sem inventar dígitos)
        return trim($linha);
    }
    $d = substr($d, 0, 47);
    // AAABC.CCCCX DDDDD.DDDDDY EEEEE.EEEEEZ K HHHHHHHHHHHHHH
    return substr($d, 0, 5) . '.' . substr($d, 5, 5) . ' '
        . substr($d, 10, 5) . '.' . substr($d, 15, 6) . ' '
        . substr($d, 21, 5) . '.' . substr($d, 26, 6) . ' '
        . substr($d, 32, 1) . ' '
        . substr($d, 33, 14);
}

/**
 * GET /v3/payments/{id}/identificationField com retentativas
 * (o Asaas às vezes só libera a linha após o registro do boleto).
 */
function asaas_buscar_linha_digitavel(string $paymentId, int $tentativas = 4): string {
    $paymentId = trim($paymentId);
    if ($paymentId === '') return '';
    $last = '';
    for ($i = 0; $i < max(1, $tentativas); $i++) {
        if ($i > 0) {
            usleep(700000); // 0,7s entre tentativas
        }
        try {
            $res = asaas_request('GET', '/payments/' . rawurlencode($paymentId) . '/identificationField');
            $data = $res['data'] ?? [];
            $line = trim((string)($data['identificationField'] ?? $data['identification_field'] ?? ''));
            // barCode = código de barras 44 posições (não é linha digitável) — só usa se não houver identificationField
            $bar = trim((string)($data['barCode'] ?? $data['barcode'] ?? ''));
            if ($line !== '') {
                $last = asaas_formatar_linha_digitavel($line);
                if (asaas_linha_digitavel_completa($last)) {
                    return $last;
                }
            } elseif ($bar !== '' && strlen(preg_replace('/\D+/', '', $bar) ?? '') >= 44) {
                // não converter barras→digitável aqui; guarda se for o único dado (raro)
                $last = $bar;
            }
        } catch (Throwable $e) {
            // continua tentando
        }
    }
    return $last;
}

/**
 * Se a linha digitável local estiver incompleta, busca de novo no Asaas e atualiza a fatura.
 */
function finance_refresh_linha_digitavel(array $fat): array {
    $id = intval($fat['id'] ?? 0);
    $chargeId = trim((string)($fat['boleto_charge_id'] ?? ''));
    $atual = trim((string)($fat['boleto_barcode'] ?? ''));
    if ($id <= 0 || $chargeId === '') return $fat;
    if (asaas_linha_digitavel_completa($atual)) {
        // só reformata se já completa sem pontuação
        $fmt = asaas_formatar_linha_digitavel($atual);
        if ($fmt !== $atual) {
            try {
                app_pdo()->prepare('UPDATE faturas SET boleto_barcode=?, updated_at=NOW() WHERE id=?')
                    ->execute([$fmt, $id]);
                $fat['boleto_barcode'] = $fmt;
            } catch (Throwable $e) { /* ok */ }
        }
        return $fat;
    }
    $linha = asaas_buscar_linha_digitavel($chargeId, 3);
    if ($linha === '') {
        try {
            $pay = asaas_consultar_pagamento($chargeId);
            $linha = asaas_extrair_linha_digitavel($pay);
            if (!empty($pay['bankSlipUrl']) && empty($fat['boleto_url'])) {
                $fat['boleto_url'] = (string)$pay['bankSlipUrl'];
                app_pdo()->prepare('UPDATE faturas SET boleto_url=?, boleto_pdf=?, updated_at=NOW() WHERE id=?')
                    ->execute([$fat['boleto_url'], $fat['boleto_url'], $id]);
            }
        } catch (Throwable $e) { /* ok */ }
    }
    if ($linha !== '' && (asaas_linha_digitavel_completa($linha) || strlen($linha) > strlen($atual))) {
        try {
            app_pdo()->prepare('UPDATE faturas SET boleto_barcode=?, updated_at=NOW() WHERE id=?')
                ->execute([$linha, $id]);
            $fat['boleto_barcode'] = $linha;
        } catch (Throwable $e) { /* ok */ }
    }
    return $fat;
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

/** Cobrança não pode mais ser paga (deletada, estornada, etc.). */
function asaas_status_inutil(string $status): bool {
    $status = strtoupper(trim($status));
    return in_array($status, [
        'DELETED',
        'REFUNDED',
        'REFUND_REQUESTED',
        'REFUND_IN_PROGRESS',
        'CHARGEBACK_REQUESTED',
        'CHARGEBACK_DISPUTE',
        'AWAITING_CHARGEBACK_REVERSAL',
    ], true);
}

/**
 * Cancela/remove cobrança no Asaas (best-effort ao regenerar meios).
 */
function asaas_cancelar_pagamento(string $paymentId): bool {
    $paymentId = trim($paymentId);
    if ($paymentId === '' || !str_starts_with($paymentId, 'pay_')) return false;
    try {
        asaas_request('DELETE', '/payments/' . rawurlencode($paymentId));
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Verifica se uma cobrança Asaas ainda serve para pagamento.
 * @return array{usable:bool,paid:bool,missing:bool,status:string,pay:array,reason:string}
 */
function asaas_charge_check(string $paymentId, string $tipo = 'any'): array {
    $paymentId = trim($paymentId);
    $base = [
        'usable' => false,
        'paid' => false,
        'missing' => false,
        'uncertain' => false,
        'status' => '',
        'pay' => [],
        'reason' => '',
    ];
    if ($paymentId === '' || !str_starts_with($paymentId, 'pay_')) {
        $base['missing'] = true;
        $base['reason'] = 'sem_id';
        return $base;
    }
    try {
        $pay = asaas_consultar_pagamento($paymentId);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (preg_match('/\b404\b|not found|não encontrad|nao encontrad|deleted/i', $msg)) {
            $base['missing'] = true;
            $base['reason'] = 'nao_encontrada';
            return $base;
        }
        // Rede/instabilidade: caller decide (não regenera se já houver QR/boleto local)
        $base['reason'] = 'erro_consulta';
        $base['uncertain'] = true;
        return $base;
    }

    $status = strtoupper((string)($pay['status'] ?? ''));
    $base['status'] = $status;
    $base['pay'] = $pay;

    if (asaas_status_pago($status)) {
        $base['paid'] = true;
        $base['usable'] = false;
        $base['reason'] = 'ja_paga';
        return $base;
    }
    if (asaas_status_inutil($status)) {
        $base['reason'] = 'status_' . strtolower($status);
        return $base;
    }
    if (!empty($pay['deleted'])) {
        $base['reason'] = 'flag_deleted';
        return $base;
    }

    $billing = strtoupper((string)($pay['billingType'] ?? ''));

    // Pix: precisa conseguir QR Code válido e não expirado
    if ($tipo === 'pix' || ($tipo === 'any' && $billing === 'PIX')) {
        try {
            $qr = asaas_request('GET', '/payments/' . rawurlencode($paymentId) . '/pixQrCode');
            $payload = (string)($qr['data']['payload'] ?? '');
            $img = (string)($qr['data']['encodedImage'] ?? '');
            if ($payload === '' && $img === '') {
                $base['reason'] = 'pix_qr_vazio';
                return $base;
            }
            $exp = (string)($qr['data']['expirationDate'] ?? '');
            if ($exp !== '') {
                $ts = strtotime($exp);
                // Só inválida se a expiração JÁ passou (não regenera “por precaução”)
                if ($ts !== false && $ts < time() - 30) {
                    $base['reason'] = 'pix_expirado';
                    return $base;
                }
            }
            $base['pay']['_pix_qr'] = $qr['data'];
        } catch (Throwable $e) {
            $base['reason'] = 'pix_qr_falhou';
            return $base;
        }
    }

    // Boleto: PENDING ou OVERDUE ainda podem ser pagáveis se houver URL/linha
    if ($tipo === 'boleto' || ($tipo === 'any' && $billing === 'BOLETO')) {
        $link = (string)($pay['bankSlipUrl'] ?? $pay['invoiceUrl'] ?? '');
        $barcode = asaas_extrair_linha_digitavel($pay);
        if ($link === '' && $barcode === '') {
            $base['reason'] = 'boleto_sem_dados';
            return $base;
        }
        // Se o Asaas cancelou o registro do boleto (CIP), não serve mais
        if (!empty($pay['postalService']) && empty($pay['bankSlipUrl']) && $barcode === '') {
            $base['reason'] = 'boleto_registro_cancelado';
            return $base;
        }
        // Anexa linha completa quando possível (para refresh no emitir)
        if ($barcode !== '') {
            $base['pay']['identificationField'] = $barcode;
        }
    }

    $base['usable'] = true;
    $base['reason'] = 'ok';
    return $base;
}

/**
 * Data de vencimento enviada ao Asaas na emissão/reemissão.
 * - Fatura futura: mantém o vencimento original
 * - Fatura vencida: usa hoje + 7 dias (cliente precisa de prazo real para pagar)
 */
function finance_due_date_for_emit(string $vencimentoYmd): string {
    $today = date('Y-m-d');
    if ($vencimentoYmd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vencimentoYmd)) {
        return date('Y-m-d', strtotime('+7 days'));
    }
    if ($vencimentoYmd < $today) {
        return date('Y-m-d', strtotime('+7 days'));
    }
    // Vence hoje ou amanhã: garante pelo menos 3 dias no Asaas para o QR/boleto
    if ($vencimentoYmd <= date('Y-m-d', strtotime('+1 day'))) {
        return date('Y-m-d', strtotime('+3 days'));
    }
    return $vencimentoYmd;
}

/**
 * Pix local vencido? Só renova se a data de expiração REAL já passou.
 * Sem data de expiração + QR/código presentes = considera válido (não regenera ao entrar na área).
 */
function finance_pix_local_expired(array $fat): bool {
    // Sem meios locais = trata como “precisa gerar” no garantir, não como “expirado”
    if (empty($fat['pix_txid']) && empty($fat['pix_copia_cola']) && empty($fat['pix_qrcode'])) {
        return false;
    }
    $exp = trim((string)($fat['pix_expira_em'] ?? ''));
    if ($exp === '') {
        // Sem expiração gravada: NÃO regenera preventivamente
        return false;
    }
    $ts = strtotime($exp);
    if ($ts === false) return false;
    // Só expira de fato depois do horário (margem de 30s)
    return $ts < time() - 30;
}

/**
 * Boleto local ainda utilizável?
 * Válido se há charge id + (URL ou linha digitável).
 * Fatura vencida no sistema NÃO invalida o boleto sozinha (Asaas pode aceitar após vencimento).
 */
function finance_boleto_local_presente(array $fat): bool {
    $id = trim((string)($fat['boleto_charge_id'] ?? ''));
    if ($id === '') return false;
    return !empty($fat['boleto_url']) || !empty($fat['boleto_barcode']);
}

/** Pix local presente e ainda não expirado. */
function finance_pix_local_valido(array $fat): bool {
    $tem = !empty($fat['pix_txid']) && (!empty($fat['pix_copia_cola']) || !empty($fat['pix_qrcode']));
    if (!$tem) return false;
    return !finance_pix_local_expired($fat);
}

function finance_clear_pix_fields(int $faturaId): void {
    app_pdo()->prepare(
        "UPDATE faturas SET pix_txid='', pix_loc_id='', pix_qrcode='', pix_copia_cola='', pix_expira_em=NULL, updated_at=NOW() WHERE id=?"
    )->execute([$faturaId]);
}

function finance_clear_boleto_fields(int $faturaId): void {
    app_pdo()->prepare(
        "UPDATE faturas SET boleto_charge_id='', boleto_url='', boleto_barcode='', boleto_pdf='', updated_at=NOW() WHERE id=?"
    )->execute([$faturaId]);
}

/** Marca faturas abertas com vencimento passado como vencidas. */
function finance_marcar_vencidas(?int $clienteId = null): void {
    try {
        if ($clienteId !== null && $clienteId > 0) {
            app_pdo()->prepare(
                "UPDATE faturas SET status = 'vencida', updated_at = NOW()
                 WHERE cliente_id = ? AND status = 'aberta' AND vencimento < CURRENT_DATE"
            )->execute([$clienteId]);
        } else {
            app_pdo()->exec(
                "UPDATE faturas SET status = 'vencida', updated_at = NOW()
                 WHERE status = 'aberta' AND vencimento < CURRENT_DATE"
            );
        }
    } catch (Throwable $e) { /* ok */ }
}

/**
 * Gera meios de pagamento (Pix e/ou boleto) e grava na fatura.
 * Se a cobrança no Asaas foi apagada/expirada/inválida, cria uma nova automaticamente.
 *
 * @param bool $force true = sempre gera novas cobranças (ignora reutilização)
 */
function finance_emitir_pagamento(int $faturaId, bool $force = false): array {
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

    $out = [
        'pix' => null,
        'boleto' => null,
        'erros' => [],
        'regenerated' => ['pix' => false, 'boleto' => false],
        'reused' => ['pix' => false, 'boleto' => false],
    ];
    $desc = (string)($fat['descricao'] ?: 'Mensalidade');
    $valor = intval($fat['valor_centavos']);
    // Vencimento “de pagamento” no Asaas (não altera o vencimento da fatura no sistema)
    $venc = finance_due_date_for_emit((string)$fat['vencimento']);
    $suffix = substr(bin2hex(random_bytes(3)), 0, 6);
    $extRef = 'fatura_' . $faturaId;
    $pdo = app_pdo();

    // ---------- PIX ----------
    // Só cria NOVO se: force, ausente, ou expirado de verdade.
    $existingPix = trim((string)($fat['pix_txid'] ?? ''));
    $pixOk = false;
    $localPixExpired = finance_pix_local_expired($fat);
    $pixLocalOk = finance_pix_local_valido($fat);

    if (!$force && $pixLocalOk) {
        // QR ainda válido: reutiliza SEM chamar criação e SEM cancelar
        $out['pix'] = [
            'payment_id' => $existingPix,
            'txid' => $existingPix,
            'qrcode' => (string)($fat['pix_qrcode'] ?? ''),
            'copia_cola' => (string)($fat['pix_copia_cola'] ?? ''),
            'expiracao' => (string)($fat['pix_expira_em'] ?? ''),
        ];
        $out['reused']['pix'] = true;
        $pixOk = true;
    } elseif (!$force && $existingPix !== '' && !$localPixExpired) {
        // Tem id mas dados locais fracos: confere no Asaas
        $chk = asaas_charge_check($existingPix, 'pix');
        if ($chk['paid']) {
            finance_marcar_paga($faturaId, 'Pago via Asaas (sync na emissão)');
            $out['pix'] = ['payment_id' => $existingPix, 'already_paid' => true];
            return $out;
        }
        if (!empty($chk['uncertain']) && (!empty($fat['pix_copia_cola']) || !empty($fat['pix_qrcode']))) {
            $out['pix'] = [
                'payment_id' => $existingPix,
                'txid' => $existingPix,
                'qrcode' => (string)($fat['pix_qrcode'] ?? ''),
                'copia_cola' => (string)($fat['pix_copia_cola'] ?? ''),
            ];
            $out['reused']['pix'] = true;
            $pixOk = true;
        } elseif ($chk['usable']) {
            $qrData = $chk['pay']['_pix_qr'] ?? [];
            $pix = [
                'payment_id' => $existingPix,
                'txid' => $existingPix,
                'loc_id' => '',
                'qrcode' => (string)($qrData['encodedImage'] ?? $fat['pix_qrcode'] ?? ''),
                'copia_cola' => (string)($qrData['payload'] ?? $fat['pix_copia_cola'] ?? ''),
                'expiracao' => (string)($qrData['expirationDate'] ?? ''),
            ];
            $out['pix'] = $pix;
            $out['reused']['pix'] = true;
            $pixOk = true;
            $expSql = null;
            if (!empty($pix['expiracao'])) {
                $ts = strtotime($pix['expiracao']);
                if ($ts) $expSql = date('Y-m-d H:i:s', $ts);
            }
            $pdo->prepare(
                'UPDATE faturas SET pix_qrcode=?, pix_copia_cola=?, pix_expira_em=COALESCE(?, pix_expira_em), updated_at=NOW() WHERE id=?'
            )->execute([$pix['qrcode'], $pix['copia_cola'], $expSql, $faturaId]);
        } else {
            // Expirado/removido no Asaas → aí sim gera novo
            asaas_cancelar_pagamento($existingPix);
            finance_clear_pix_fields($faturaId);
            $out['regenerated']['pix'] = true;
            $existingPix = '';
        }
    } elseif ($force || $localPixExpired) {
        if ($existingPix !== '') {
            asaas_cancelar_pagamento($existingPix);
        }
        finance_clear_pix_fields($faturaId);
        $out['regenerated']['pix'] = true;
        $existingPix = '';
    }

    if (!$pixOk) {
        try {
            $pix = asaas_criar_pix($cli, $valor, $desc, $venc, $extRef . '_pix_' . $suffix);
            $out['pix'] = $pix;
            $out['regenerated']['pix'] = true;
            $expSql = null;
            if (!empty($pix['expiracao'])) {
                $ts = strtotime((string)$pix['expiracao']);
                if ($ts) $expSql = date('Y-m-d H:i:s', $ts);
            }
            // Sem inventar expiração curta: se Asaas não mandar, deixa NULL (não força regen em 24h)
            $pdo->prepare(
                'UPDATE faturas SET pix_txid=?, pix_loc_id=?, pix_qrcode=?, pix_copia_cola=?, pix_expira_em=?, updated_at=NOW() WHERE id=?'
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

    // ---------- BOLETO ----------
    // Só cria NOVO se: force, ausente, ou Asaas disser que a cobrança morreu.
    // Boleto NÃO vencido (e mesmo OVERDUE com URL) → reutiliza.
    $cpf = app_only_digits((string)($cli['cpf'] ?? ''));
    $existingBol = trim((string)($fat['boleto_charge_id'] ?? ''));
    $bolOk = false;
    $bolLocalOk = finance_boleto_local_presente($fat);

    if ($cpf === '') {
        $out['erros'][] = 'Boleto: informe CPF/CNPJ do cliente.';
    } else {
        if (!$force && $bolLocalOk) {
            // Boleto presente: reutiliza SEM gerar outro
            $out['boleto'] = [
                'charge_id' => $existingBol,
                'barcode' => (string)($fat['boleto_barcode'] ?? ''),
                'link' => (string)($fat['boleto_url'] ?? ''),
                'pdf' => (string)(($fat['boleto_pdf'] ?? '') ?: ($fat['boleto_url'] ?? '')),
            ];
            $out['reused']['boleto'] = true;
            $bolOk = true;
        } elseif (!$force && $existingBol !== '') {
            $chk = asaas_charge_check($existingBol, 'boleto');
            if ($chk['paid']) {
                finance_marcar_paga($faturaId, 'Pago via Asaas (sync boleto)');
                $out['boleto'] = ['charge_id' => $existingBol, 'already_paid' => true];
                return $out;
            }
            if (!empty($chk['uncertain']) && (!empty($fat['boleto_url']) || !empty($fat['boleto_barcode']))) {
                $out['boleto'] = [
                    'charge_id' => $existingBol,
                    'barcode' => (string)$fat['boleto_barcode'],
                    'link' => (string)$fat['boleto_url'],
                    'pdf' => (string)($fat['boleto_pdf'] ?: $fat['boleto_url']),
                ];
                $out['reused']['boleto'] = true;
                $bolOk = true;
            } elseif ($chk['usable']) {
                $pay = $chk['pay'];
                $barcode = asaas_extrair_linha_digitavel($pay);
                if (!asaas_linha_digitavel_completa($barcode)) {
                    $barcode = asaas_buscar_linha_digitavel($existingBol, 3);
                }
                if (!asaas_linha_digitavel_completa($barcode) && asaas_linha_digitavel_completa((string)($fat['boleto_barcode'] ?? ''))) {
                    $barcode = asaas_formatar_linha_digitavel((string)$fat['boleto_barcode']);
                }
                $bol = [
                    'charge_id' => $existingBol,
                    'barcode' => $barcode !== '' ? $barcode : (string)($fat['boleto_barcode'] ?? ''),
                    'link' => (string)($pay['bankSlipUrl'] ?? $pay['invoiceUrl'] ?? $fat['boleto_url'] ?? ''),
                    'pdf' => (string)($pay['bankSlipUrl'] ?? $fat['boleto_pdf'] ?? $fat['boleto_url'] ?? ''),
                ];
                $out['boleto'] = $bol;
                $out['reused']['boleto'] = true;
                $bolOk = true;
                $pdo->prepare(
                    'UPDATE faturas SET boleto_url=?, boleto_barcode=?, boleto_pdf=?, updated_at=NOW() WHERE id=?'
                )->execute([$bol['link'], $bol['barcode'], $bol['pdf'], $faturaId]);
            } else {
                // Deletado / sem dados no Asaas → gera novo
                asaas_cancelar_pagamento($existingBol);
                finance_clear_boleto_fields($faturaId);
                $out['regenerated']['boleto'] = true;
                $existingBol = '';
            }
        } elseif ($force && $existingBol !== '') {
            asaas_cancelar_pagamento($existingBol);
            finance_clear_boleto_fields($faturaId);
            $out['regenerated']['boleto'] = true;
        }

        if (!$bolOk) {
            try {
                $bol = asaas_criar_boleto($cli, $valor, $desc, $venc, $extRef . '_boleto_' . $suffix);
                $out['boleto'] = $bol;
                $out['regenerated']['boleto'] = true;
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

/**
 * Garante meios válidos ao abrir a fatura (cliente/admin).
 *
 * REGRAS:
 * - Boleto/QR ainda válidos → NÃO gera novo (só reutiliza).
 * - Só regenera Pix se estiver ausente, expirado ou removido no Asaas.
 * - Só regenera boleto se estiver ausente, deletado ou sem dados no Asaas.
 * - Fatura “vencida” no sistema NÃO força novo boleto/QR por si só.
 *
 * @return array{fatura:array,regenerated:bool,message:string,detail?:array}
 */
function finance_garantir_meios_pagamento(array $fat, bool $force = false): array {
    $id = intval($fat['id'] ?? 0);
    if ($id <= 0 || !in_array($fat['status'] ?? '', ['aberta', 'vencida'], true)) {
        return ['fatura' => $fat, 'regenerated' => false, 'message' => ''];
    }
    if (!asaas_configured()) {
        return ['fatura' => $fat, 'regenerated' => false, 'message' => ''];
    }

    finance_marcar_vencidas(intval($fat['cliente_id'] ?? 0));
    $fat = app_fatura_by_id($id) ?: $fat;

    // Sync se já pago
    $fat = finance_sync_fatura($fat);
    if (($fat['status'] ?? '') === 'paga') {
        return ['fatura' => $fat, 'regenerated' => false, 'message' => 'Pagamento confirmado.'];
    }

    // Completa linha digitável se incompleta (NÃO recria boleto)
    $fat = finance_refresh_linha_digitavel($fat);

    if ($force) {
        try {
            $r = finance_emitir_pagamento($id, true);
            $fresh = app_fatura_by_id($id) ?: $fat;
            return [
                'fatura' => $fresh,
                'regenerated' => true,
                'message' => 'Novos meios de pagamento gerados.',
                'detail' => $r,
            ];
        } catch (Throwable $e) {
            return [
                'fatura' => $fat,
                'regenerated' => false,
                'message' => 'Não foi possível gerar meios: ' . $e->getMessage(),
            ];
        }
    }

    $needPix = false;
    $needBol = false;
    $reasons = [];

    $pixId = trim((string)($fat['pix_txid'] ?? ''));
    $bolId = trim((string)($fat['boleto_charge_id'] ?? ''));
    $hasPixData = !empty($fat['pix_copia_cola']) || !empty($fat['pix_qrcode']);
    $hasBolData = finance_boleto_local_presente($fat);

    // ---- Pix: só regenera se ausente ou REALMENTE expirado/inválido ----
    if ($pixId === '' || !$hasPixData) {
        $needPix = true;
        $reasons[] = 'pix_ausente';
    } elseif (finance_pix_local_expired($fat)) {
        $needPix = true;
        $reasons[] = 'pix_expirado';
    } else {
        // Local válido: não consulta Asaas só para “renovar”
        // (consulta leve só se quiser confirmar pagamento — já feito no sync)
    }

    // ---- Boleto: se presente e com dados, NÃO regenera ao entrar ----
    if (!$hasBolData) {
        $needBol = true;
        $reasons[] = 'boleto_ausente';
    }
    // Se tem boleto local, mantém. Só recria se force ou se emitir detectar deleted na API
    // quando precisar emitir o lado que falta (abaixo).

    // Ambos ok → não gera nada novo
    if (!$needPix && !$needBol) {
        return [
            'fatura' => $fat,
            'regenerated' => false,
            'message' => '',
            'reasons' => ['meios_validos'],
        ];
    }

    // Precisa de algo: emitir reutiliza o que ainda vale e só cria o que falta/expirou
    try {
        $r = finance_emitir_pagamento($id, false);
        $fresh = app_fatura_by_id($id) ?: $fat;
        $regen = !empty($r['regenerated']['pix']) || !empty($r['regenerated']['boleto']);
        $msg = '';
        if ($regen) {
            $parts = [];
            if (!empty($r['regenerated']['pix'])) $parts[] = 'Pix';
            if (!empty($r['regenerated']['boleto'])) $parts[] = 'boleto';
            $msg = 'Geramos um novo ' . implode(' e ', $parts)
                . ' porque o anterior estava ausente, expirado ou indisponível.';
        }
        if (!empty($r['erros']) && $msg === '') {
            $msg = implode(' | ', $r['erros']);
        }
        return [
            'fatura' => $fresh,
            'regenerated' => $regen,
            'message' => $msg,
            'detail' => $r,
            'reasons' => $reasons,
        ];
    } catch (Throwable $e) {
        return [
            'fatura' => $fat,
            'regenerated' => false,
            'message' => 'Não foi possível gerar os meios de pagamento: ' . $e->getMessage(),
        ];
    }
}

/**
 * Prepara faturas em aberto do cliente ao entrar no Financeiro.
 * Só mexe no que está ausente ou expirado — não recria boleto/QR válidos.
 *
 * @return array{ok:int,regenerated:int,paid:int,errors:list<string>}
 */
function finance_preparar_faturas_cliente(int $clienteId, int $limit = 15): array {
    $result = ['ok' => 0, 'regenerated' => 0, 'paid' => 0, 'errors' => []];
    if ($clienteId <= 0 || !asaas_configured()) return $result;

    finance_marcar_vencidas($clienteId);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $cacheKey = 'finance_prep_' . $clienteId;
    $last = intval($_SESSION[$cacheKey] ?? 0);
    $forceRefresh = !empty($_GET['renovar']) || !empty($_GET['refresh']);
    if (!$forceRefresh && $last > 0 && (time() - $last) < 90) {
        return $result;
    }

    try {
        $st = app_pdo()->prepare(
            "SELECT * FROM faturas
             WHERE cliente_id = ? AND status IN ('aberta','vencida')
             ORDER BY vencimento ASC, id ASC
             LIMIT " . max(1, min(30, $limit))
        );
        $st->execute([$clienteId]);
        $rows = $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return $result;
    }

    foreach ($rows as $fat) {
        try {
            // Atalho: se Pix e boleto locais ok, só confere se pagou (sem reemitir)
            $pixOk = finance_pix_local_valido($fat);
            $bolOk = finance_boleto_local_presente($fat);
            if ($pixOk && $bolOk && !$forceRefresh) {
                $synced = finance_sync_fatura($fat);
                $result['ok']++;
                if (($synced['status'] ?? '') === 'paga') $result['paid']++;
                continue;
            }

            $g = finance_garantir_meios_pagamento($fat, false);
            $result['ok']++;
            if (!empty($g['regenerated'])) $result['regenerated']++;
            if (($g['fatura']['status'] ?? '') === 'paga') $result['paid']++;
            if (!empty($g['message']) && str_contains((string)$g['message'], 'Não foi possível')) {
                $result['errors'][] = 'Fatura #' . intval($fat['id']) . ': ' . $g['message'];
            }
        } catch (Throwable $e) {
            $result['errors'][] = 'Fatura #' . intval($fat['id']) . ': ' . $e->getMessage();
        }
    }

    $_SESSION[$cacheKey] = time();
    return $result;
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
