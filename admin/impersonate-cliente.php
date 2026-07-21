<?php
/**
 * Acessa a área do cliente como o cliente escolhido (suporte / testes).
 * Uso: impersonate-cliente.php?id=123
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
app_require_auth();

$id = intval($_GET['id'] ?? 0);
$result = admin_impersonate_cliente($id);

if (empty($result['ok'])) {
    header('Location: clientes.php?err=' . rawurlencode((string)($result['message'] ?? 'Falha ao acessar como cliente')));
    exit;
}

header('Location: ' . app_url('cliente/'));
exit;
