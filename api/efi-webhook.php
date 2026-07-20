<?php
/**
 * Webhook Pix EFI — notifica pagamento.
 * Cadastre a URL: https://seu-dominio/api/efi-webhook.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/efi.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

// Formatos comuns EFI / Pix: pix[].txid ou txid
$txids = [];
if (!empty($data['pix']) && is_array($data['pix'])) {
    foreach ($data['pix'] as $p) {
        if (!empty($p['txid'])) $txids[] = (string)$p['txid'];
    }
}
if (!empty($data['txid'])) $txids[] = (string)$data['txid'];
$txids = array_unique(array_filter($txids));

$pdo = app_pdo();
$ok = 0;
foreach ($txids as $txid) {
    $st = $pdo->prepare("SELECT id FROM faturas WHERE pix_txid = ? AND status IN ('aberta','vencida') LIMIT 1");
    $st->execute([$txid]);
    $id = intval($st->fetchColumn());
    if ($id > 0) {
        finance_marcar_paga($id, 'Pago via webhook Pix EFI');
        $ok++;
    }
}

echo json_encode(['ok' => true, 'updated' => $ok], JSON_UNESCAPED_UNICODE);
