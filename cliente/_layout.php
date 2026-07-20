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
<div class="cliente-shell">
    <aside class="cliente-sidebar" id="clienteSidebar">
        <div class="cliente-sidebar-brand">
            <a href="<?= e($prefix . '/cliente/') ?>">
                <?php if ($logo): ?>
                    <img src="<?= e($logo) ?>" alt="<?= e($nomeSite) ?>">
                <?php else: ?>
                    <span class="brand-badge">🎙</span>
                    <strong><?= e($nomeSite) ?></strong>
                <?php endif; ?>
            </a>
            <div class="cliente-sidebar-label">Área do cliente</div>
        </div>
        <nav class="cliente-sidebar-nav">
            <a href="<?= e($prefix . '/cliente/') ?>" class="<?= $active === 'home' ? 'active' : '' ?>">🏠 Início</a>
            <div class="cliente-nav-group">Conteúdos</div>
            <?php foreach ($tipos as $key => $meta): ?>
                <a href="<?= e($prefix . '/cliente/conteudos.php?tipo=' . rawurlencode($key)) ?>" class="<?= $active === $key ? 'active' : '' ?>">
                    <?= $meta['icon'] ?> <?= e($meta['label']) ?>
                </a>
            <?php endforeach; ?>
            <div class="cliente-nav-group">Serviços</div>
            <a href="<?= e($prefix . '/cliente/texto.php') ?>" class="<?= $active === 'texto' ? 'active' : '' ?>">🎙️ Enviar texto</a>
            <a href="<?= e($prefix . '/cliente/perfil.php') ?>" class="<?= $active === 'perfil' ? 'active' : '' ?>">👤 Meus dados</a>
        </nav>
        <div class="cliente-sidebar-foot">
            <div class="cliente-user-chip">Olá, <strong><?= e($nomeCli) ?></strong></div>
            <a class="btn btn-ghost btn-small" href="<?= e($prefix . '/') ?>">Site público</a>
            <a class="btn btn-secondary btn-small" href="<?= e($prefix . '/cliente/logout.php') ?>">Sair</a>
        </div>
    </aside>

    <div class="cliente-content">
        <header class="cliente-topbar">
            <button type="button" class="cliente-menu-btn" id="clienteMenuBtn" aria-label="Abrir menu">☰</button>
            <div class="cliente-topbar-title">
                <span class="cliente-badge">Área do cliente</span>
                <h1><?= e($title) ?></h1>
            </div>
            <div class="cliente-topbar-user muted">Olá, <strong><?= e($nomeCli) ?></strong></div>
        </header>
        <main class="cliente-main">
<?php
}

function cliente_footer(): void {
    ?>
        </main>
        <footer class="cliente-footer">
            Área restrita · conteúdos liberados para o seu cadastro
        </footer>
    </div>
</div>
<div class="cliente-overlay" id="clienteOverlay" hidden></div>
<script>
(function () {
    var btn = document.getElementById('clienteMenuBtn');
    var side = document.getElementById('clienteSidebar');
    var overlay = document.getElementById('clienteOverlay');
    if (!btn || !side) return;
    function open() {
        side.classList.add('open');
        if (overlay) { overlay.hidden = false; }
        document.body.classList.add('cliente-menu-open');
    }
    function close() {
        side.classList.remove('open');
        if (overlay) { overlay.hidden = true; }
        document.body.classList.remove('cliente-menu-open');
    }
    btn.addEventListener('click', function () {
        if (side.classList.contains('open')) close(); else open();
    });
    if (overlay) overlay.addEventListener('click', close);
    side.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
            if (window.innerWidth <= 960) close();
        });
    });
})();
</script>
</body>
</html>
<?php
}

function cliente_flash(?string $ok = null, ?string $err = null): void {
    if ($ok) echo '<div class="alert alert-ok">' . e($ok) . '</div>';
    if ($err) echo '<div class="alert alert-err">' . e($err) . '</div>';
}
