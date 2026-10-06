<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$stats = [
    'demonstrativos' => 0,
    'produtos' => 0,
    'produtos_servicos' => 0,
    'banners' => 0,
    'contatos_novos' => 0,
];
try {
    $stats['demonstrativos'] = (int)$pdo->query("SELECT COUNT(*) FROM conteudos WHERE area = 'demonstrativo'")->fetchColumn();
    $stats['produtos'] = (int)$pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
    $stats['produtos_servicos'] = (int)$pdo->query("SELECT COUNT(*) FROM produtos_servicos")->fetchColumn();
    $stats['banners'] = (int)$pdo->query('SELECT COUNT(*) FROM banners')->fetchColumn();
    $stats['contatos_novos'] = (int)$pdo->query('SELECT COUNT(*) FROM contatos WHERE lido = 0')->fetchColumn();
} catch (Throwable $e) { /* ok */ }

admin_header('Dashboard', 'dash');
?>
<div class="grid-stats">
    <div class="stat"><span>Demonstrativos</span><strong><?= $stats['demonstrativos'] ?></strong><div class="muted">catálogo público</div></div>
    <div class="stat"><span>Produtos e Serviços</span><strong><?= $stats['produtos_servicos'] ?></strong><div class="muted">catálogo comercial</div></div>
    <div class="stat"><span>Planos / preços</span><strong><?= $stats['produtos'] ?></strong><div class="muted">WHMCS</div></div>
    <div class="stat"><span>Contatos novos</span><strong><?= $stats['contatos_novos'] ?></strong></div>
    <div class="stat"><span>Banners</span><strong><?= $stats['banners'] ?></strong></div>
</div>

<div class="card" style="margin-top:16px;">
    <h3 style="margin-bottom:10px;">Atalhos</h3>
    <div class="actions">
        <a class="btn btn-primary" href="demonstrativos.php">Demonstrativos</a>
        <a class="btn btn-primary" href="produtos-servicos.php">Produtos e Serviços</a>
        <a class="btn btn-secondary" href="produtos.php">Planos / preços</a>
        <a class="btn btn-secondary" href="contatos.php">Contatos</a>
        <a class="btn btn-secondary" href="banners.php">Banners</a>
        <a class="btn btn-secondary" href="../" target="_blank">Ver site</a>
    </div>
</div>

<div class="card" style="margin-top:16px;">
    <h3 style="margin-bottom:8px;">Estrutura atual</h3>
    <ul class="muted" style="margin-left:18px;line-height:1.8;">
        <li><strong>Demonstrativos</strong> — catálogo público com capas, descrições e áudios.</li>
        <li><strong>Produtos e Serviços</strong> — catálogo comercial genérico para qualquer serviço ou produto.</li>
        <li><strong>Planos / preços</strong> — itens com contratação direcionada ao WHMCS.</li>
        <li><strong>Contatos e banners</strong> — conteúdo institucional do site.</li>
    </ul>
</div>
<?php admin_footer(); ?>
