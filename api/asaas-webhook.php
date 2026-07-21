<?php
/**
 * Webhook Asaas — notifica pagamento.
 * Cadastre a URL: https://seu-dominio/api/asaas-webhook.php
 *
 * Eventos tratados: PAYMENT_RECEIVED, PAYMENT_CONFIRMED, PAYMENT_RECEIVED_IN_CASH
 * Token opcional: configure o mesmo valor em ASAAS_WEBHOOK_TOKEN e no painel Asaas
 * (header asaas-access-token).
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/asaas.php';

header('Content-Type: application/json; charset=utf-8');

// Valida token do webhook se configurado
$expected = asaas_webhook_token();
if ($expected !== '') {
    $got = '';
    // Headers comuns (Apache/nginx/CGI)
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (is_array($headers)) {
        foreach ($headers as $k => $v) {
            if (strtolower((string)$k) === 'asaas-access-token') {
                $got = (string)$v;
                break;
            }
        }
    }
    if ($got === '') {
        $got = (string)($_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '');
    }
    if (!hash_equals($expected, $got)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json'], JSON_UNESCAPED_UNICODE);
    exit;
}

$event = strtoupper((string)($data['event'] ?? ''));
$payment = $data['payment'] ?? null;
$paymentId = '';
if (is_array($payment) && !empty($payment['id'])) {
    $paymentId = (string)$payment['id'];
} elseif (!empty($data['id']) && str_starts_with((string)$data['id'], 'pay_')) {
    $paymentId = (string)$data['id'];
}

// Eventos que liberam acesso
$paidEvents = [
    'PAYMENT_RECEIVED',
    'PAYMENT_CONFIRMED',
    'PAYMENT_RECEIVED_IN_CASH',
    'PAYMENT_DUNNING_RECEIVED',
];

if ($paymentId === '' || !in_array($event, $paidEvents, true)) {
    // Responde 200 para não reenfileirar eventos irrelevantes
    echo json_encode([
        'ok' => true,
        'ignored' => true,
        'event' => $event,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$status = strtoupper((string)(is_array($payment) ? ($payment['status'] ?? '') : ''));
// Se o payload trouxer status, confere; senão confia no evento
if ($status !== '' && !asaas_status_pago($status) && $event !== 'PAYMENT_CONFIRMED' && $event !== 'PAYMENT_RECEIVED') {
    echo json_encode(['ok' => true, 'ignored' => true, 'status' => $status], JSON_UNESCAPED_UNICODE);
    exit;
}

$updated = finance_marcar_paga_por_payment_id(
    $paymentId,
    'Pago via webhook Asaas (' . $event . ')'
);

echo json_encode([
    'ok' => true,
    'event' => $event,
    'payment_id' => $paymentId,
    'updated' => $updated,
], JSON_UNESCAPED_UNICODE);
