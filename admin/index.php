<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$stats = [
    'programas' => (int)$pdo->query('SELECT COUNT(*) FROM programas')->fetchColumn(),
    'programas_ativos' => (int)$pdo->query('SELECT COUNT(*) FROM programas WHERE ativo = 1')->fetchColumn(),
    'programetes' => (int)$pdo->query('SELECT COUNT(*) FROM programetes')->fetchColumn(),
    'banners' => (int)$pdo->query('SELECT COUNT(*) FROM banners')->fetchColumn(),
    'contatos' => (int)$pdo->query('SELECT COUNT(*) FROM contatos')->fetchColumn(),
    'contatos_novos' => (int)$pdo->query('SELECT COUNT(*) FROM contatos WHERE lido = 0')->fetchColumn(),
];
admin_header('Dashboard', 'dash');
?>
<div class="grid-stats">
    <div class="stat"><span>Programas</span><strong><?= $stats['programas'] ?></strong><div class="muted"><?= $stats['programas_ativos'] ?> ativos</div></div>
    <div class="stat"><span>Programetes</span><strong><?= $stats['programetes'] ?></strong></div>
    <div class="stat"><span>Banners</span><strong><?= $stats['banners'] ?></strong></div>
    <div class="stat"><span>Contatos</span><strong><?= $stats['contatos'] ?></strong><div class="muted"><?= $stats['contatos_novos'] ?> novos</div></div>
</div>
<div class="card" style="margin-top:16px;">
    <h3 style="margin-bottom:10px;">Como usar no EasyPanel</h3>
    <ol class="muted" style="margin-left:18px;line-height:1.8;">
        <li>Serviço <strong>Postgres</strong> + app com este Dockerfile.</li>
        <li>Variáveis <code>DB_*</code> ou <code>DATABASE_URL</code> + <code>BOOTSTRAP_ADMIN_*</code>.</li>
        <li>Volumes: <code>/var/www/html/uploads</code>, <code>/var/www/html/data</code>, <code>/var/www/html/config</code>.</li>
        <li>Cadastre categorias e programas — o site público atualiza sozinho.</li>
    </ol>
    <div class="actions" style="margin-top:14px;">
        <a class="btn btn-primary" href="programas.php?novo=1">+ Novo programa</a>
        <a class="btn btn-secondary" href="../" target="_blank">Abrir site</a>
    </div>
</div>
<?php admin_footer(); ?>
