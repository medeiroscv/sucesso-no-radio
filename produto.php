<?php
require_once __DIR__ . '/includes/layout_public.php';
$slug=trim((string)($_GET['slug']??''));
$p=app_produto_publico_by_slug($slug);
if(!$p){
    http_response_code(404);
    layout_header('Produto não encontrado','produtos');
    echo '<main class="container"><div class="page-title"><h1>Produto não encontrado</h1><p><a href="'.e(app_url('produtos.php')).'">Voltar ao catálogo</a></p></div></main>';
    layout_footer(); exit;
}
$tipos=app_produto_tipos_vitrine();
$ciclos=app_produto_ciclos_vitrine();
$tipo=$tipos[$p['tipo']??'']??['label'=>'Produto'];
$ciclo=$ciclos[$p['ciclo']??'']??['label'=>''];
$recursos=app_produto_recursos($p);
$whmcs=app_produto_whmcs_url($p);
$capa=trim((string)($p['capa']??''));
$demos=app_produto_demonstrativos((int)$p['id']);
layout_header($p['nome'],'produtos');
?>
<main class="container">
    <div class="page-title">
        <p class="muted"><a href="<?= e(app_url('produtos.php')) ?>">Produtos</a> · <?= e($tipo['label']) ?></p>
        <h1><?= e($p['nome']) ?></h1>
    </div>
    <section class="product-detail">
        <div class="product-detail-media">
            <?php if($capa): ?><img src="<?= e(app_url($capa)) ?>" alt="<?= e($p['nome']) ?>"><?php else: ?><div class="catalog-cover product-placeholder"><span>🎙</span></div><?php endif; ?>
        </div>
        <div class="product-detail-copy">
            <div class="catalog-badges"><span class="chip"><?= e($tipo['label']) ?></span><?php if($ciclo['label']!==''): ?><span class="chip"><?= e($ciclo['label']) ?></span><?php endif; ?></div>
            <?php if(!empty($p['descricao'])): ?><p class="product-lead"><?= nl2br(e($p['descricao'])) ?></p><?php endif; ?>
            <?php if($recursos): ?><h2>O que está incluído</h2><ul class="catalog-list product-list"><?php foreach($recursos as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if(!empty($p['exibir_preco'])): ?><div class="catalog-price catalog-price-large"><?= e(app_produto_preco_br((int)$p['valor_centavos'])) ?></div><?php endif; ?>
            <div class="hero-actions">
                <?php if($whmcs): ?><a class="btn btn-primary" href="<?= e($whmcs) ?>" target="_blank" rel="noopener"><?= e($p['botao_texto'] ?: 'Contratar no WHMCS') ?></a><?php else: ?><a class="btn btn-primary" href="<?= e(wa_link($p['whatsapp_msg'] ?: ('Olá! Quero contratar '.$p['nome']))) ?>" target="_blank" rel="noopener">Solicitar contratação</a><?php endif; ?>
                <a class="btn btn-ghost" href="<?= e(wa_link('Olá! Tenho uma dúvida sobre '.$p['nome'])) ?>" target="_blank" rel="noopener">Tirar dúvidas</a>
            </div>
            <?php if(!$whmcs): ?><p class="muted" style="font-size:.85rem;">Link do WHMCS ainda não configurado para este produto. O contato será direcionado à equipe.</p><?php endif; ?>
        </div>
    </section>
    <?php if($demos): ?>
    <section class="section">
        <div class="section-head"><div><span class="catalog-eyebrow">Demonstrativos</span><h2>Ouça antes de contratar</h2></div></div>
        <div class="demo-public-grid">
            <?php foreach($demos as $d): ?><div class="demo-public-card"><strong><?= e($d['titulo'] ?: 'Demonstrativo') ?></strong><audio controls preload="none"><source src="<?= e(app_url($d['arquivo'])) ?>"></audio></div><?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>
<?php layout_footer(); ?>
