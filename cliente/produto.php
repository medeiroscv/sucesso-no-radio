<?php
require_once __DIR__ . '/../includes/layout_public.php';
require_once __DIR__ . '/../includes/billing.php';

cliente_session_start();
cliente_require_auth();

$cli = cliente_atual();
$cliId = intval($cli['id'] ?? 0);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    layout_header('Produto não encontrado', 'cliente');
    echo '<main><div class="container"><div class="page-title"><h1>Produto não encontrado</h1></div>';
    echo '<div class="empty"><a href="' . e(app_url('cliente/produtos.php')) . '">Voltar para Meus Produtos</a></div></div></main>';
    layout_footer();
    exit;
}

// Verifica se o cliente possui este produto
$tem = cliente_possui_produto($cliId, $id);
if (!$tem) {
    layout_header('Acesso negado', 'cliente');
    echo '<main><div class="container"><div class="page-title"><h1>Acesso negado</h1></div>';
    echo '<div class="empty">Você não possui este produto ou ele ainda não foi liberado.</div></div></main>';
    layout_footer();
    exit;
}

$prod = billing_produto_by_id($id);
if (!$prod) {
    layout_header('Produto não encontrado', 'cliente');
    echo '<main><div class="container"><div class="page-title"><h1>Produto não encontrado</h1></div>';
    echo '<div class="empty"><a href="' . e(app_url('cliente/produtos.php')) . '">Voltar para Meus Produtos</a></div></div></main>';
    layout_footer();
    exit;
}

$prod = billing_produto_normalize_row($prod);
$entregas = app_produto_entregas($id);
$liberadoEm = substr((string)($tem['liberado_em'] ?? ''), 0, 10);

$title = 'Meu produto: ' . ($prod['nome'] ?? 'Produto');
// Override do header — queremos título diferente
cliente_header($title, 'produtos');
?>
<p class="cliente-intro">
    <strong><?= e($prod['nome']) ?></strong> — adquirido em <?= e($liberadoEm) ?>
</p>

<?php if (!$entregas): ?>
    <div class="empty">
        Ainda não há arquivos de entrega para este produto. A equipe está preparando o material.
    </div>
<?php else: ?>
    <div class="cliente-list">
        <?php foreach ($entregas as $d): ?>
            <div class="cliente-list-item" style="cursor:default;">
                <div>
                    <strong><?= e($d['titulo'] ?: 'Arquivo') ?></strong>
                </div>
                <div class="cliente-list-meta">
                    <a class="btn btn-primary btn-small" href="<?= e(app_url($d['arquivo'])) ?>" download>
                        Baixar
                    </a>
                    <?php if (preg_match('/\.(mp3|m4a|wav|ogg)$/i', (string)$d['arquivo'])): ?>
                        <button class="btn btn-ghost btn-small" type="button"
                                onclick="var a=this.nextElementSibling;if(a){a.style.display=a.style.display==='none'?'block':'none';}this.textContent=this.textContent==='Ouvir'?'Fechar':'Ouvir';">
                            Ouvir
                        </button>
                        <div style="display:none;grid-column:1/-1;padding:12px 0 6px;">
                            <audio controls preload="none" style="width:100%;max-width:600px;">
                                <source src="<?= e(app_url($d['arquivo'])) ?>" type="audio/mpeg">
                            </audio>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="actions" style="margin-top:20px;">
    <a class="btn btn-ghost" href="<?= e(app_url('cliente/produtos.php')) ?>">← Voltar para Meus Produtos</a>
</div>

<?php cliente_footer(); ?>
