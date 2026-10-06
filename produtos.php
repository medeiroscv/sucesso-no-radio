<?php
require_once __DIR__ . '/includes/layout_public.php';
$produtos = app_produtos_vitrine();
$tipos = app_produto_tipos_vitrine();
layout_header('Produtos', 'produtos');
?>
<main>
<section class="section">
    <div class="container">
        <div class="page-title catalog-page-title">
            <span class="catalog-eyebrow">Catálogo comercial</span>
            <h1>Produtos para sua emissora</h1>
            <p class="muted">Todos os itens abaixo são apresentados por este site. A contratação é concluída no WHMCS pelo link individual de cada produto.</p>
        </div>
        <?php if (!$produtos): ?>
            <div class="empty" style="text-align:center;">Nenhum produto publicado no momento.</div>
        <?php else: ?>
            <div class="catalog-grid">
            <?php foreach ($produtos as $p):
                $capa=trim((string)($p['capa']??''));
                $recursos=app_produto_recursos($p);
                $whmcs=app_produto_whmcs_url($p);
                $detalhe=app_url('produto.php?slug='.rawurlencode((string)$p['slug']));
                $tipo=$tipos[$p['tipo']??'']??['label'=>'Produto'];
            ?>
                <article class="catalog-card">
                    <a class="catalog-cover" href="<?= e($detalhe) ?>"><?php if($capa): ?><img src="<?= e(app_url($capa)) ?>" alt="<?= e($p['nome']) ?>" loading="lazy"><?php else: ?><span>🎙</span><?php endif; ?></a>
                    <div class="catalog-body">
                        <div class="catalog-badges"><span class="chip"><?= e($tipo['label']) ?></span><?php if(!empty($p['destaque'])): ?><span class="chip">Destaque</span><?php endif; ?></div>
                        <h2><a href="<?= e($detalhe) ?>"><?= e($p['nome']) ?></a></h2>
                        <?php if(!empty($p['descricao'])): ?><p class="catalog-desc"><?= e($p['descricao']) ?></p><?php endif; ?>
                        <?php if($recursos): ?><ul class="catalog-list"><?php foreach(array_slice($recursos,0,4) as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul><?php endif; ?>
                        <?php if(!empty($p['exibir_preco'])): ?><div class="catalog-price"><?= e(app_produto_preco_br((int)$p['valor_centavos'])) ?></div><?php endif; ?>
                        <div class="card-actions">
                            <a class="btn btn-ghost btn-small" href="<?= e($detalhe) ?>">Detalhes</a>
                            <?php if($whmcs): ?><a class="btn btn-primary btn-small" href="<?= e($whmcs) ?>" target="_blank" rel="noopener"><?= e($p['botao_texto'] ?: 'Contratar') ?></a><?php else: ?><a class="btn btn-primary btn-small" href="<?= e(wa_link($p['whatsapp_msg'] ?: ('Olá! Quero saber mais sobre '.$p['nome']))) ?>" target="_blank" rel="noopener">Tenho interesse</a><?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
</main>
<?php layout_footer(); ?>
