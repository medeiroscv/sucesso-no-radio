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

function layout_header(string $title = '', string $active = ''): void {
    $s = site_settings_all();
    $nome = $s['site_nome'] ?? APP_NAME;
    $wa = preg_replace('/\D+/', '', $s['whatsapp'] ?? '5561974002349');
    $pageTitle = $title !== '' ? ($title . ' · ' . $nome) : $nome;
    $base = app_base_path();
    $css = ($base === '' ? '' : $base) . '/assets/css/site.css';
    $home = ($base === '' ? '/' : $base . '/');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($s['site_slogan'] ?? 'Programas e conteúdo para rádio') ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="<?= e($home) ?>">
            <span class="brand-badge">🎙</span>
            <span><?= e($nome) ?></span>
        </a>
        <nav class="nav-links">
            <a href="<?= e($home) ?>#programas">Programas</a>
            <a href="<?= e($home) ?>#fim-de-semana">Fim de semana</a>
            <a href="<?= e($home) ?>#jornalismo">Jornalismo</a>
            <a href="<?= e($home) ?>#programetes">Programetes</a>
            <a href="<?= e(($base === '' ? '' : $base) . '/contato.php') ?>">Contato</a>
        </nav>
        <a class="btn btn-wa btn-small" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
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
    ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <strong><?= e($nome) ?></strong>
            <p><?= e($s['sobre'] ?? '') ?></p>
        </div>
        <div>
            <strong>Navegação</strong>
            <p><a href="<?= e($home) ?>">Início</a></p>
            <p><a href="<?= e(($base === '' ? '' : $base) . '/contato.php') ?>">Contato</a></p>
            <p><a href="<?= e(($base === '' ? '' : $base) . '/admin/') ?>">Área admin</a></p>
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
