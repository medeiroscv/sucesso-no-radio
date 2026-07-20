<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$stats = [
    'demonstrativos' => 0,
    'conteudos' => 0,
    'clientes' => 0,
    'clientes_ativos' => 0,
    'clientes_liberados' => 0,
    'textos_novos' => 0,
    'banners' => 0,
    'contatos_novos' => 0,
];
try {
    $stats['demonstrativos'] = (int)$pdo->query("SELECT COUNT(*) FROM conteudos WHERE area = 'demonstrativo'")->fetchColumn();
    $stats['conteudos'] = (int)$pdo->query("SELECT COUNT(*) FROM conteudos WHERE area = 'conteudo'")->fetchColumn();
    $stats['clientes'] = (int)$pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
    $stats['clientes_ativos'] = (int)$pdo->query('SELECT COUNT(*) FROM clientes WHERE ativo = 1')->fetchColumn();
    $stats['clientes_liberados'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM clientes WHERE ativo = 1 AND (acesso_total = 1 OR id IN (SELECT DISTINCT cliente_id FROM cliente_conteudos))"
    )->fetchColumn();
    $stats['textos_novos'] = (int)$pdo->query('SELECT COUNT(*) FROM textos_gravacao WHERE lido = 0')->fetchColumn();
    $stats['banners'] = (int)$pdo->query('SELECT COUNT(*) FROM banners')->fetchColumn();
    $stats['contatos_novos'] = (int)$pdo->query('SELECT COUNT(*) FROM contatos WHERE lido = 0')->fetchColumn();
} catch (Throwable $e) { /* ok */ }

admin_header('Dashboard', 'dash');
?>
<div class="grid-stats">
    <div class="stat"><span>Demonstrativos</span><strong><?= $stats['demonstrativos'] ?></strong><div class="muted">site público</div></div>
    <div class="stat"><span>Conteúdos</span><strong><?= $stats['conteudos'] ?></strong><div class="muted">produto / cliente</div></div>
    <div class="stat"><span>Clientes</span><strong><?= $stats['clientes'] ?></strong><div class="muted"><?= $stats['clientes_liberados'] ?> com liberação</div></div>
    <div class="stat"><span>Textos a gravar</span><strong><?= $stats['textos_novos'] ?></strong><div class="muted">não lidos</div></div>
    <div class="stat"><span>Contatos novos</span><strong><?= $stats['contatos_novos'] ?></strong></div>
    <div class="stat"><span>Banners</span><strong><?= $stats['banners'] ?></strong></div>
</div>

<div class="card" style="margin-top:16px;">
    <h3 style="margin-bottom:10px;">Atalhos</h3>
    <div class="actions">
        <a class="btn btn-primary" href="clientes.php?novo=1">+ Novo cliente</a>
        <a class="btn btn-secondary" href="demonstrativos.php">Demonstrativos</a>
        <a class="btn btn-secondary" href="conteudos.php">Conteúdos (produto)</a>
        <a class="btn btn-secondary" href="textos.php">Textos a gravar</a>
        <a class="btn btn-secondary" href="../cliente/login.php" target="_blank">Área do cliente</a>
    </div>
</div>

<div class="card" style="margin-top:16px;">
    <h3 style="margin-bottom:8px;">Como funciona</h3>
    <ul class="muted" style="margin-left:18px;line-height:1.8;">
        <li><strong>Demonstrativos</strong> — amostras na home do site (público).</li>
        <li><strong>Conteúdos</strong> — programas do produto (diários, semanais, informativos) para quem comprou.</li>
        <li><strong>Clientes</strong> — ativo só permite login; a liberação de conteúdos é manual (ou acesso total).</li>
        <li>Sem liberação, o cliente entra mas fica bloqueado (sem conteúdos e sem textos).</li>
    </ul>
</div>
<?php admin_footer(); ?>
