<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
app_require_auth();

function admin_header(string $title, string $active = ''): void {
    $nome = $_SESSION['admin_nome'] ?? 'Admin';
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> · Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar">
        <div class="logo">🎙 Sucesso no Rádio</div>
        <nav>
            <a href="index.php" class="<?= $active === 'dash' ? 'active' : '' ?>">Dashboard</a>
            <a href="programas.php" class="<?= $active === 'programas' ? 'active' : '' ?>">Programas</a>
            <a href="programetes.php" class="<?= $active === 'programetes' ? 'active' : '' ?>">Programetes</a>
            <a href="banners.php" class="<?= $active === 'banners' ? 'active' : '' ?>">Banners</a>
            <a href="categorias.php" class="<?= $active === 'categorias' ? 'active' : '' ?>">Categorias</a>
            <a href="contatos.php" class="<?= $active === 'contatos' ? 'active' : '' ?>">Contatos</a>
            <a href="configuracoes.php" class="<?= $active === 'config' ? 'active' : '' ?>">Configurações</a>
            <a href="../" target="_blank">Ver site</a>
            <a href="logout.php">Sair</a>
        </nav>
    </aside>
    <div class="main">
        <div class="topbar">
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="muted">Olá, <?= htmlspecialchars($nome) ?></div>
        </div>
<?php
}

function admin_footer(): void {
    echo '</div></div></body></html>';
}

function admin_flash(?string $ok = null, ?string $err = null): void {
    if ($ok) echo '<div class="alert alert-ok">' . htmlspecialchars($ok) . '</div>';
    if ($err) echo '<div class="alert alert-err">' . htmlspecialchars($err) . '</div>';
}

function admin_upload(string $field, string $subdir): string {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return '';
    }
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return '';
    }
    $dir = dirname(__DIR__) . '/uploads/' . trim($subdir, '/');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) return '';
    return 'uploads/' . trim($subdir, '/') . '/' . $name;
}
