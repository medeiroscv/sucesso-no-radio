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
    return function_exists('app_url') ? app_url($rel) : (($base === '' ? '' : $base) . '/' . $rel);
}

function layout_header(string $title = '', string $active = ''): void {
    $s = site_settings_all();
    $nome = $s['site_nome'] ?? APP_NAME;
    $pageTitle = $title !== '' ? ($title . ' · ' . $nome) : $nome;
    $base = app_base_path();
    $css = app_url('assets/css/site.css');
    $home = ($base === '' ? '/' : $base . '/');
    $logo = !empty($s['site_logo']) ? layout_media_url((string)$s['site_logo'], $base) : '';
    $favicon = !empty($s['site_favicon']) ? layout_media_url((string)$s['site_favicon'], $base) : '';
    $formContatoAtivo = ($s['form_contato_ativo'] ?? '1') === '1';
    $wa = preg_replace('/\D+/', '', $s['whatsapp'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($s['site_slogan'] ?? 'Conteúdo profissional para emissoras de rádio') ?>">
    <title><?= e($pageTitle) ?></title>
    <?php if ($favicon): ?><link rel="icon" href="<?= e($favicon) ?>" type="image/png"><?php endif; ?>
    <link rel="stylesheet" href="<?= e($css) ?>">
    <?= app_css_cores() ?>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e($home) ?>">
            <?php if ($logo): ?>
                <img class="brand-logo" src="<?= e($logo) ?>" alt="<?= e($nome) ?>" style="height:<?= (int)($s['logo_size'] ?? 64) ?>px;">
            <?php else: ?>
                <span class="brand-badge">🎙</span><span class="brand-text"><?= e($nome) ?></span>
            <?php endif; ?>
        </a>
        <nav class="nav-links" aria-label="Menu principal">
            <a href="<?= e($home) ?>" class="<?= $active === 'home' ? 'active' : '' ?>">Início</a>
            <a href="<?= e(app_url('produtos.php')) ?>" class="<?= $active === 'produtos' ? 'active' : '' ?>">Produtos</a>
            <?php if ($formContatoAtivo): ?><a href="<?= e(app_url('contato.php')) ?>" class="<?= $active === 'contato' ? 'active' : '' ?>">Contato</a><?php endif; ?>
            <?php if ($wa): ?><a class="btn btn-primary btn-small" href="<?= e(wa_link('Olá! Quero conhecer os produtos do ' . $nome)) ?>" target="_blank" rel="noopener">Falar com a equipe</a><?php endif; ?>
        </nav>
    </div>
</header>
<?php
}

function layout_footer(): void {
    $s = site_settings_all();
    $nome = $s['site_nome'] ?? APP_NAME;
    $wa = preg_replace('/\D+/', '', $s['whatsapp'] ?? '');
    $home = app_url('');
    $logo = !empty($s['site_logo']) ? layout_media_url((string)$s['site_logo']) : '';
    $formContatoAtivo = ($s['form_contato_ativo'] ?? '1') === '1';
    ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <?php if ($logo): ?><img class="footer-logo" src="<?= e($logo) ?>" alt="<?= e($nome) ?>" style="height:<?= (int)($s['logo_size'] ?? 64) ?>px;"><?php else: ?><strong><?= e($nome) ?></strong><?php endif; ?>
            <p><?= e($s['sobre'] ?? '') ?></p>
        </div>
        <div>
            <strong>Navegação</strong>
            <p><a href="<?= e($home) ?>">Início</a></p>
            <p><a href="<?= e(app_url('produtos.php')) ?>">Produtos</a></p>
            <?php if ($formContatoAtivo): ?><p><a href="<?= e(app_url('contato.php')) ?>">Contato</a></p><?php endif; ?>
        </div>
        <div>
            <strong>Contato</strong>
            <?php if ($wa): ?><p>WhatsApp: <a href="<?= e(wa_link()) ?>" target="_blank" rel="noopener"><?= e($wa) ?></a></p><?php endif; ?>
            <?php if (!empty($s['email'])): ?><p>E-mail: <?= e($s['email']) ?></p><?php endif; ?>
            <?php if (!empty($s['telefone'])): ?><p>Tel: <?= e($s['telefone']) ?></p><?php endif; ?>
        </div>
    </div>
    <div class="container" style="margin-top:22px;opacity:.7;text-align:center;">© <?= date('Y') ?> <?= e($nome) ?>. <?= e($s['footer_text'] ?? 'Todos os direitos reservados.') ?></div>
</footer>
</body>
</html>
<?php
}

function wa_link(string $msg = ''): string {
    $wa = preg_replace('/\D+/', '', app_setting('whatsapp', ''));
    return 'https://wa.me/' . $wa . ($msg !== '' ? ('?text=' . rawurlencode($msg)) : '');
}
