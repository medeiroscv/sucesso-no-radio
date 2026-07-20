<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
cliente_session_start();

function cliente_header(string $title, string $active = ''): void {
    $s = [];
    try {
        $rows = app_pdo()->query('SELECT chave, valor FROM site_settings')->fetchAll();
        foreach ($rows as $r) $s[$r['chave']] = $r['valor'];
    } catch (Throwable $e) { /* ok */ }
    $nomeSite = $s['site_nome'] ?? APP_NAME;
    $nomeCli = $_SESSION['cliente_nome'] ?? 'Cliente';
    $base = app_base_path();
    $prefix = $base === '' ? '' : $base;
    $css = $prefix . '/assets/css/site.css';
    $cssCli = $prefix . '/assets/css/cliente.css';
    $logo = !empty($s['site_logo']) ? ($prefix . '/' . ltrim((string)$s['site_logo'], '/')) : '';
    $favicon = !empty($s['site_favicon']) ? ($prefix . '/' . ltrim((string)$s['site_favicon'], '/')) : '';
    $tipos = app_conteudo_tipos();
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> · Área do Cliente</title>
    <?php if ($favicon): ?><link rel="icon" href="<?= e($favicon) ?>" type="image/png"><?php endif; ?>
    <link rel="stylesheet" href="<?= e($css) ?>">
    <link rel="stylesheet" href="<?= e($cssCli) ?>">
</head>
<body class="cliente-body">
<header class="site-header cliente-header">
    <div class="container nav">
        <a class="brand" href="<?= e($prefix . '/cliente/') ?>">
            <?php if ($logo): ?>
                <img class="brand-logo" src="<?= e($logo) ?>" alt="<?= e($nomeSite) ?>">
            <?php else: ?>
                <span class="brand-badge">🎙</span>
                <span><?= e($nomeSite) ?></span>
            <?php endif; ?>
        </a>
        <nav class="nav-links cliente-nav">
            <a href="<?= e($prefix . '/cliente/') ?>" class="<?= $active === 'home' ? 'active' : '' ?>">Início</a>
            <?php foreach ($tipos as $key => $meta): ?>
                <a href="<?= e($prefix . '/cliente/conteudos.php?tipo=' . rawurlencode($key)) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($meta['label']) ?></a>
            <?php endforeach; ?>
            <a href="<?= e($prefix . '/cliente/texto.php') ?>" class="<?= $active === 'texto' ? 'active' : '' ?>">Enviar texto</a>
            <a href="<?= e($prefix . '/cliente/perfil.php') ?>" class="<?= $active === 'perfil' ? 'active' : '' ?>">Meus dados</a>
            <a href="<?= e($prefix . '/cliente/logout.php') ?>">Sair</a>
        </nav>
        <div class="cliente-user muted">Olá, <strong><?= e($nomeCli) ?></strong></div>
    </div>
</header>
<main class="container cliente-main">
    <div class="page-title" style="padding-top:28px;">
        <p class="cliente-badge">Área do cliente</p>
        <h1><?= e($title) ?></h1>
    </div>
<?php
}

function cliente_footer(): void {
    $base = app_base_path();
    $prefix = $base === '' ? '' : $base;
    ?>
</main>
<footer class="site-footer">
    <div class="container" style="opacity:.75;font-size:.9rem;">
        Área restrita · <a href="<?= e($prefix . '/') ?>">Voltar ao site público</a>
        · <a href="<?= e($prefix . '/cliente/logout.php') ?>">Sair</a>
    </div>
</footer>
</body>
</html>
<?php
}

function cliente_flash(?string $ok = null, ?string $err = null): void {
    if ($ok) echo '<div class="alert alert-ok">' . e($ok) . '</div>';
    if ($err) echo '<div class="alert alert-err">' . e($err) . '</div>';
}
