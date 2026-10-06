<?php
require_once __DIR__ . '/../includes/db.php';
$slug=trim((string)($_GET['produto']??''));
$dest=$slug!=='' ? app_url('produto.php?slug='.rawurlencode($slug)) : app_url('produtos.php');
header('Location: '.$dest, true, 302);
exit;
