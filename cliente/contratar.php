<?php
require_once __DIR__ . '/../includes/db.php';

$slug = trim((string)($_GET['produto'] ?? ''));
if ($slug !== '') {
    try {
        $st = app_pdo()->prepare('SELECT * FROM produtos WHERE slug = ? AND ativo = 1 LIMIT 1');
        $st->execute([$slug]);
        $produto = $st->fetch();
        if ($produto) {
            $whmcs = app_produto_whmcs_url($produto);
            if ($whmcs !== '') {
                header('Location: ' . $whmcs, true, 302);
                exit;
            }
        }
    } catch (Throwable $e) {
        // segue para a página de preços
    }
}

header('Location: ' . app_url('precos.php'), true, 302);
exit;
