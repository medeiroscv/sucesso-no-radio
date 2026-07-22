<?php
/**
 * Checkout do produto (preços → login/cadastro → fatura no financeiro).
 * Uso: /cliente/contratar.php?produto=slug  ou  ?id=123
 */
require_once __DIR__ . '/../includes/layout_public.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/asaas.php';

cliente_session_start();

$slug = trim((string)($_GET['produto'] ?? $_GET['slug'] ?? ''));
$id = intval($_GET['id'] ?? 0);
$prod = null;
if ($slug !== '') {
    $prod = billing_produto_by_slug($slug);
} elseif ($id > 0) {
    $prod = billing_produto_by_id($id);
}

if (!$prod || empty($prod['ativo'])) {
    layout_header('Produto indisponível', 'precos');
    echo '<main><div class="container"><div class="page-title"><h1>Produto indisponível</h1></div>';
    echo '<div class="empty">Este plano não está disponível. <a href="' . e(app_url('precos.php')) . '">Ver preços</a></div></div></main>';
    layout_footer();
    exit;
}

$prodId = intval($prod['id']);
$slugSafe = (string)($prod['slug'] ?? $prodId);

// Não logado → cadastro (ou login) com retorno ao checkout
if (!cliente_logado() || !cliente_atual()) {
    header('Location: ' . app_url('cliente/cadastro.php?produto=' . rawurlencode($slugSafe)));
    exit;
}

$cli = cliente_atual();
$cliId = intval($cli['id']);

// Garante módulo financeiro ativo para o cliente ver a fatura
if (!app_finance_ativo()) {
    try {
        app_setting_set('finance_ativo', '1');
    } catch (Throwable $e) { /* ok */ }
}

$r = billing_checkout_produto($cliId, $prodId);
if (empty($r['ok']) || empty($r['fatura_id'])) {
    layout_header('Pedido', 'precos');
    echo '<main><div class="container"><div class="page-title"><h1>Não foi possível iniciar o pedido</h1></div>';
    echo '<div class="alert alert-err">' . e($r['message'] ?? 'Erro desconhecido') . '</div>';
    echo '<p><a class="btn btn-primary" href="' . e(app_url('precos.php')) . '">Voltar aos preços</a></p></div></main>';
    layout_footer();
    exit;
}

header('Location: ' . app_url('cliente/financeiro.php?id=' . intval($r['fatura_id']) . '&pedido=1'));
exit;
