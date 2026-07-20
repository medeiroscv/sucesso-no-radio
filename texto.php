<?php
/**
 * Formulário de texto só para clientes logados.
 * Redireciona para a área do cliente.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

cliente_session_start();
$base = app_base_path();
$prefix = $base === '' ? '' : $base;

if (cliente_logado() && cliente_atual()) {
    header('Location: ' . $prefix . '/cliente/texto.php');
    exit;
}

header('Location: ' . $prefix . '/cliente/login.php?redirect=texto');
exit;
