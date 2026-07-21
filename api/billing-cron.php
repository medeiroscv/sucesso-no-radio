<?php
/**
 * Cron de billing — gera faturas futuras e dispara cobranças agendadas.
 *
 * Uso:
 *   GET /api/billing-cron.php?token=SEU_TOKEN
 *
 * Token: env BILLING_CRON_TOKEN ou setting billing_cron_token
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/asaas.php';

header('Content-Type: application/json; charset=utf-8');

$expected = trim((string)(app_env('BILLING_CRON_TOKEN', '') ?: app_setting('billing_cron_token', '')));
$got = trim((string)($_GET['token'] ?? $_SERVER['HTTP_X_BILLING_TOKEN'] ?? ''));

if ($expected === '') {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'Configure BILLING_CRON_TOKEN (ou billing_cron_token no admin settings).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!hash_equals($expected, $got)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Garante schema
try {
    app_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$stats = billing_run(true);
echo json_encode([
    'ok' => true,
    'at' => date('c'),
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
