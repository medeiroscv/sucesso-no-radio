<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$tipos = app_conteudo_tipos();
$stats = [
    'conteudos' => 0,
    'ativos' => 0,
    'clientes' => 0,
    'clientes_ativos' => 0,
    'textos_novos' => 0,
    'banners' => 0,
    'contatos_novos' => 0,
];
$porTipo = [];
try {
    $stats['conteudos'] = (int)$pdo->query('SELECT COUNT(*) FROM conteudos')->fetchColumn();
    $stats['ativos'] = (int)$pdo->query('SELECT COUNT(*) FROM conteudos WHERE ativo = 1')->fetchColumn();
    $stats['clientes'] = (int)$pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
    $stats['clientes_ativos'] = (int)$pdo->query('SELECT COUNT(*) FROM clientes WHERE ativo = 1')->fetchColumn();
    $stats['textos_novos'] = (int)$pdo->query('SELECT COUNT(*) FROM textos_gravacao WHERE lido = 0')->fetchColumn();
    $stats['banners'] = (int)$pdo->query('SELECT COUNT(*) FROM banners')->fetchColumn();
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
    <div class="stat"><span>Clientes</span><strong><?= $stats['clientes'] ?></strong><div class="muted"><?= $stats['clientes_ativos'] ?> ativos</div></div>
    <div class="stat"><span>Textos a gravar</span><strong><?= $stats['textos_novos'] ?></strong><div class="muted">não lidos</div></div>
    <div class="stat"><span>Contatos novos</span><strong><?= $stats['contatos_novos'] ?></strong></div>
    <div class="stat"><span>Banners</span><strong><?= $stats['banners'] ?></strong></div>
</div>

<div class="card" style="margin-top:16px;">
    <h3 style="margin-bottom:10px;">Atalhos</h3>
    <div class="actions" style="margin-bottom:8px;">
        <a class="btn btn-primary" href="clientes.php?novo=1">+ Novo cliente</a>
        <a class="btn btn-secondary" href="textos.php">Textos a gravar</a>
        <a class="btn btn-secondary" href="contatos.php">Contatos</a>
        <a class="btn btn-secondary" href="conteudos.php">Conteúdos / entregas</a>
        <a class="btn btn-secondary" href="../cliente/login.php" target="_blank">Área do cliente</a>
    </div>
</div>

<div class="card" style="margin-top:16px;">
    <h3 style="margin-bottom:10px;">Conteúdos</h3>
    <p class="muted" style="margin-bottom:14px;">Demonstrativos no site público · arquivos de entrega só na área do cliente.</p>
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
</div>
<?php admin_footer(); ?>
