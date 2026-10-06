<?php
require_once __DIR__ . '/_layout.php';
$pdo=app_pdo();
$stats=['produtos'=>0,'publicados'=>0,'destaques'=>0,'sem_whmcs'=>0,'banners'=>0,'contatos_novos'=>0];
try{
    $stats['produtos']=(int)$pdo->query('SELECT COUNT(*) FROM produtos')->fetchColumn();
    $stats['publicados']=(int)$pdo->query('SELECT COUNT(*) FROM produtos WHERE ativo=1 AND mostrar_site=1')->fetchColumn();
    $stats['destaques']=(int)$pdo->query('SELECT COUNT(*) FROM produtos WHERE ativo=1 AND mostrar_site=1 AND destaque=1')->fetchColumn();
    $stats['sem_whmcs']=(int)$pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo=1 AND mostrar_site=1 AND (whmcs_url IS NULL OR TRIM(whmcs_url)='')")->fetchColumn();
    $stats['banners']=(int)$pdo->query('SELECT COUNT(*) FROM banners WHERE ativo=1')->fetchColumn();
    $stats['contatos_novos']=(int)$pdo->query('SELECT COUNT(*) FROM contatos WHERE lido=0')->fetchColumn();
}catch(Throwable $e){/* ok */}

admin_header('Dashboard','dash');
?>
<div class="grid-stats">
    <div class="stat"><span>Produtos</span><strong><?= $stats['produtos'] ?></strong><div class="muted">cadastrados</div></div>
    <div class="stat"><span>Publicados</span><strong><?= $stats['publicados'] ?></strong><div class="muted">na vitrine</div></div>
    <div class="stat"><span>Destaques</span><strong><?= $stats['destaques'] ?></strong><div class="muted">na home</div></div>
    <div class="stat"><span>Sem WHMCS</span><strong><?= $stats['sem_whmcs'] ?></strong><div class="muted">usam WhatsApp como fallback</div></div>
    <div class="stat"><span>Banners ativos</span><strong><?= $stats['banners'] ?></strong></div>
    <div class="stat"><span>Contatos novos</span><strong><?= $stats['contatos_novos'] ?></strong></div>
</div>

<div class="card" style="margin-top:16px;">
    <h3 style="margin-bottom:10px;">Atalhos</h3>
    <div class="actions">
        <a class="btn btn-primary" href="produtos.php?novo=1">+ Novo produto</a>
        <a class="btn btn-secondary" href="produtos.php">Gerenciar produtos</a>
        <a class="btn btn-secondary" href="banners.php">Banners</a>
        <a class="btn btn-secondary" href="contatos.php">Contatos</a>
        <a class="btn btn-secondary" href="../produtos.php" target="_blank">Abrir vitrine</a>
    </div>
</div>

<div class="card" style="margin-top:16px;">
    <h3 style="margin-bottom:8px;">Novo fluxo comercial</h3>
    <ul class="muted" style="margin-left:18px;line-height:1.8;">
        <li>O site apresenta os produtos e seus demonstrativos.</li>
        <li>Cada produto pode ter uma URL própria do WHMCS.</li>
        <li>Ao clicar em contratar, o visitante é enviado ao WHMCS.</li>
        <li>Se o link do WHMCS estiver vazio, o site usa o WhatsApp como alternativa.</li>
        <li>Este projeto não precisa processar pagamentos, assinaturas ou faturas.</li>
    </ul>
</div>
<?php admin_footer(); ?>
