<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

function site_settings_all(): array {
    try {
        $rows = app_pdo()->query('SELECT chave, valor FROM site_settings')->fetchAll();
        $out = [];
        foreach ($rows as $r) $out[$r['chave']] = $r['valor'];
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function layout_media_url(string $rel, string $base = ''): string {
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '') return '';
    if (function_exists('app_url')) {
        return app_url($rel);
    }
    return ($base === '' ? '' : $base) . '/' . $rel;
}

function layout_header(string $title = '', string $active = ''): void {
    if (function_exists('cliente_session_start')) {
        cliente_session_start();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $s = site_settings_all();
    $nome = $s['site_nome'] ?? APP_NAME;
    $wa = preg_replace('/\D+/', '', $s['whatsapp'] ?? '5561974002349');
    $pageTitle = $title !== '' ? ($title . ' · ' . $nome) : $nome;
    $base = app_base_path();
    $css = function_exists('app_url') ? app_url('assets/css/site.css') : (($base === '' ? '' : $base) . '/assets/css/site.css');
    $home = ($base === '' ? '/' : $base . '/');
    $logo = !empty($s['site_logo']) ? layout_media_url((string)$s['site_logo'], $base) : '';
    $favicon = !empty($s['site_favicon']) ? layout_media_url((string)$s['site_favicon'], $base) : '';
    $formContatoAtivo = ($s['form_contato_ativo'] ?? '1') === '1';
    $clienteLogado = function_exists('cliente_logado') && cliente_logado();
    $areaCliente = function_exists('cliente_home_url') ? cliente_home_url() : app_url('cliente/index.php');
    $loginCliente = app_url('cliente/login.php');
    $nomeCli = $_SESSION['cliente_nome'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($s['site_slogan'] ?? 'Programas e conteúdo para rádio') ?>">
    <title><?= e($pageTitle) ?></title>
    <?php if ($favicon): ?>
        <link rel="icon" href="<?= e($favicon) ?>" type="image/png">
        <link rel="apple-touch-icon" href="<?= e($favicon) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="<?= e($home) ?>">
            <?php if ($logo): ?>
                <img class="brand-logo" src="<?= e($logo) ?>" alt="<?= e($nome) ?>">
            <?php else: ?>
                <span class="brand-badge">🎙</span>
                <span><?= e($nome) ?></span>
            <?php endif; ?>
        </a>
        <nav class="nav-links">
            <a href="<?= e($home) ?>#diarios" class="<?= $active === 'diario' ? 'active' : '' ?>">Diários</a>
            <a href="<?= e($home) ?>#semanais" class="<?= $active === 'semanal' ? 'active' : '' ?>">Semanais</a>
            <a href="<?= e($home) ?>#informativos" class="<?= $active === 'informativo' ? 'active' : '' ?>">Informativos</a>
            <a href="<?= e($home) ?>#programetes" class="<?= $active === 'programete' ? 'active' : '' ?>">Programetes</a>
            <?php if ($formContatoAtivo): ?>
                <a href="<?= e(app_url('contato.php')) ?>" class="<?= $active === 'contato' ? 'active' : '' ?>">Contato</a>
            <?php endif; ?>
            <?php if ($clienteLogado): ?>
                <a href="<?= e($areaCliente) ?>" class="<?= in_array($active, ['cliente', 'home', 'texto', 'perfil', 'diario', 'semanal', 'informativo', 'programete'], true) && str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/cliente/') ? 'active' : '' ?>">Minha área</a>
            <?php else: ?>
                <a href="<?= e($loginCliente) ?>">Área do cliente</a>
            <?php endif; ?>
        </nav>
        <div class="nav-actions">
            <?php if ($clienteLogado): ?>
                <span class="nav-user muted">Olá, <?= e($nomeCli ?: 'cliente') ?></span>
                <a class="btn btn-primary btn-small" href="<?= e($areaCliente) ?>">Minha área</a>
                <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/logout.php')) ?>">Sair</a>
            <?php else: ?>
                <a class="btn btn-primary btn-small" href="<?= e($loginCliente) ?>">Entrar</a>
            <?php endif; ?>
            <a class="btn btn-wa btn-small" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
        </div>
    </div>
</header>
<?php
}

function layout_footer(): void {
    $s = site_settings_all();
    $nome = $s['site_nome'] ?? APP_NAME;
    $wa = preg_replace('/\D+/', '', $s['whatsapp'] ?? '');
    $base = app_base_path();
    $home = ($base === '' ? '/' : $base . '/');
    $logo = !empty($s['site_logo']) ? layout_media_url((string)$s['site_logo'], $base) : '';
    $formContatoAtivo = ($s['form_contato_ativo'] ?? '1') === '1';
    $clienteLogado = function_exists('cliente_logado') && cliente_logado();
    ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <?php if ($logo): ?>
                <img class="footer-logo" src="<?= e($logo) ?>" alt="<?= e($nome) ?>">
            <?php else: ?>
                <strong><?= e($nome) ?></strong>
            <?php endif; ?>
            <p><?= e($s['sobre'] ?? '') ?></p>
        </div>
        <div>
            <strong>Navegação</strong>
            <p><a href="<?= e($home) ?>">Início</a></p>
            <?php if ($formContatoAtivo): ?><p><a href="<?= e(app_url('contato.php')) ?>">Contato</a></p><?php endif; ?>
            <?php if ($clienteLogado): ?>
                <p><a href="<?= e(cliente_home_url()) ?>">Minha área</a></p>
            <?php else: ?>
                <p><a href="<?= e(app_url('cliente/login.php')) ?>">Área do cliente</a></p>
            <?php endif; ?>
            <p><a href="<?= e(app_url('admin/')) ?>">Área admin</a></p>
        </div>
        <div>
            <strong>Contato</strong>
            <?php if ($wa): ?><p>WhatsApp: <a href="https://wa.me/<?= e($wa) ?>" target="_blank"><?= e($wa) ?></a></p><?php endif; ?>
            <?php if (!empty($s['email'])): ?><p>E-mail: <?= e($s['email']) ?></p><?php endif; ?>
            <?php if (!empty($s['telefone'])): ?><p>Tel: <?= e($s['telefone']) ?></p><?php endif; ?>
        </div>
    </div>
    <div class="container" style="margin-top:22px;opacity:.7;">
        © <?= date('Y') ?> <?= e($nome) ?>. Conteúdo gerenciado pelo painel administrativo.
    </div>
</footer>
</body>
</html>
<?php
}

function wa_link(string $msg = ''): string {
    $wa = preg_replace('/\D+/', '', app_setting('whatsapp', '5561974002349'));
    $q = $msg !== '' ? ('?text=' . rawurlencode($msg)) : '';
    return 'https://wa.me/' . $wa . $q;
}
