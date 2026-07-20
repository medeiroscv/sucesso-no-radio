<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$tipos = app_conteudo_tipos();
$stats = [
    'conteudos' => 0,
    'ativos' => 0,
    'banners' => 0,
    'contatos' => 0,
    'contatos_novos' => 0,
];
$porTipo = [];
try {
    $stats['conteudos'] = (int)$pdo->query('SELECT COUNT(*) FROM conteudos')->fetchColumn();
    $stats['ativos'] = (int)$pdo->query('SELECT COUNT(*) FROM conteudos WHERE ativo = 1')->fetchColumn();
    $stats['banners'] = (int)$pdo->query('SELECT COUNT(*) FROM banners')->fetchColumn();
    $stats['contatos'] = (int)$pdo->query('SELECT COUNT(*) FROM contatos')->fetchColumn();
    $stats['contatos_novos'] = (int)$pdo->query('SELECT COUNT(*) FROM contatos WHERE lido = 0')->fetchColumn();
    foreach (array_keys($tipos) as $t) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM conteudos WHERE tipo = ?');
        $st->execute([$t]);
        $porTipo[$t] = (int)$st->fetchColumn();
    }
} catch (Throwable $e) {
    // tabela ainda não criada
}

admin_header('Dashboard', 'dash');
?>
<div class="grid-stats">
    <div class="stat"><span>Conteúdos</span><strong><?= $stats['conteudos'] ?></strong><div class="muted"><?= $stats['ativos'] ?> ativos</div></div>
    <?php foreach ($tipos as $key => $meta): ?>
        <div class="stat"><span><?= e($meta['label']) ?></span><strong><?= (int)($porTipo[$key] ?? 0) ?></strong></div>
    <?php endforeach; ?>
    <div class="stat"><span>Banners</span><strong><?= $stats['banners'] ?></strong></div>
    <div class="stat"><span>Contatos</span><strong><?= $stats['contatos'] ?></strong><div class="muted"><?= $stats['contatos_novos'] ?> novos</div></div>
</div>
<div class="card" style="margin-top:16px;">
    <h3 style="margin-bottom:10px;">Conteúdos</h3>
    <p class="muted" style="margin-bottom:14px;">Gerencie diários, semanais, informativos e programetes em um só lugar.</p>
    <div class="conteudo-hub">
        <?php foreach ($tipos as $key => $meta): ?>
            <a class="conteudo-hub-card" href="conteudos.php?tipo=<?= e($key) ?>">
                <div class="conteudo-hub-icon"><?= $meta['icon'] ?></div>
                <h3><?= e($meta['label']) ?></h3>
                <p><?= e($meta['desc']) ?></p>
                <div class="conteudo-hub-count"><?= (int)($porTipo[$key] ?? 0) ?> item(ns)</div>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="actions" style="margin-top:16px;">
        <a class="btn btn-primary" href="conteudos.php">Abrir Conteúdos</a>
        <a class="btn btn-secondary" href="../" target="_blank">Abrir site</a>
    </div>
</div>
<?php admin_footer(); ?>
