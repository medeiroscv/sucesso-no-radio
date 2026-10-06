<?php
require_once __DIR__ . '/includes/layout_public.php';

$produtos = app_produtos_vitrine();
$destaques = array_values(array_filter($produtos, fn($p) => !empty($p['destaque'])));
if (!$destaques) $destaques = array_slice($produtos, 0, 6);
$banners = [];
try {
    $banners = app_pdo()->query("SELECT * FROM banners WHERE ativo = 1 ORDER BY ordem ASC, id DESC LIMIT 5")->fetchAll() ?: [];
} catch (Throwable $e) { /* catálogo continua sem banners */ }
$s = site_settings_all();

function vitrine_card(array $p): void {
    $capa = trim((string)($p['capa'] ?? ''));
    $recursos = app_produto_recursos($p);
    $whmcs = app_produto_whmcs_url($p);
    $detalhe = app_url('produto.php?slug=' . rawurlencode((string)$p['slug']));
    $tipos = app_produto_tipos_vitrine();
    $tipo = $tipos[$p['tipo'] ?? ''] ?? ['label' => 'Produto'];
    ?>
    <article class="catalog-card">
        <a class="catalog-cover" href="<?= e($detalhe) ?>">
            <?php if ($capa): ?><img src="<?= e(app_url($capa)) ?>" alt="<?= e($p['nome']) ?>" loading="lazy"><?php else: ?><span>🎙</span><?php endif; ?>
        </a>
        <div class="catalog-body">
            <div class="catalog-badges"><span class="chip"><?= e($tipo['label']) ?></span><?php if (!empty($p['destaque'])): ?><span class="chip">Destaque</span><?php endif; ?></div>
            <h3><a href="<?= e($detalhe) ?>"><?= e($p['nome']) ?></a></h3>
            <?php if (!empty($p['descricao'])): ?><p class="catalog-desc"><?= e($p['descricao']) ?></p><?php endif; ?>
            <?php if ($recursos): ?><ul class="catalog-list"><?php foreach (array_slice($recursos,0,3) as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if (!empty($p['exibir_preco'])): ?><div class="catalog-price"><?= e(app_produto_preco_br((int)$p['valor_centavos'])) ?></div><?php endif; ?>
            <div class="card-actions">
                <a class="btn btn-ghost btn-small" href="<?= e($detalhe) ?>">Ver detalhes</a>
                <?php if ($whmcs): ?><a class="btn btn-primary btn-small" href="<?= e($whmcs) ?>" target="_blank" rel="noopener"><?= e($p['botao_texto'] ?: 'Contratar') ?></a><?php else: ?><a class="btn btn-primary btn-small" href="<?= e(wa_link($p['whatsapp_msg'] ?: ('Olá! Quero saber mais sobre ' . $p['nome']))) ?>" target="_blank" rel="noopener">Tenho interesse</a><?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

layout_header('', 'home');
?>
<main>
    <section class="hero container catalog-hero">
        <div class="catalog-hero-copy">
            <span class="catalog-eyebrow">Conteúdo profissional para emissoras</span>
            <h1><?= e($s['site_slogan'] ?? 'Tudo que sua rádio precisa em um só lugar') ?></h1>
            <p><?= e($s['sobre'] ?? 'Programas, informativos e conteúdos profissionais prontos para fortalecer a sua programação.') ?></p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="<?= e(app_url('produtos.php')) ?>">Conhecer produtos</a>
                <a class="btn btn-ghost" href="<?= e(wa_link('Olá! Quero ajuda para escolher os produtos para minha rádio.')) ?>" target="_blank" rel="noopener">Falar com a equipe</a>
            </div>
        </div>
    </section>

    <?php if ($banners): ?>
    <section class="section container">
        <?php foreach ($banners as $b): ?>
            <div class="destaque" style="margin-bottom:18px;">
                <div>
                    <h2><?= e($b['titulo'] ?: 'Destaque') ?></h2>
                    <?php if ($b['subtitulo']): ?><p style="color:var(--muted);margin-top:8px;"><?= e($b['subtitulo']) ?></p><?php endif; ?>
                    <?php if ($b['link']): ?><div class="hero-actions"><a class="btn btn-primary" href="<?= e($b['link']) ?>" target="_blank" rel="noopener"><?= e($b['botao_texto'] ?: 'Saiba mais') ?></a></div><?php endif; ?>
                </div>
                <?php if ($b['imagem']): ?><img src="<?= e(app_url($b['imagem'])) ?>" alt="<?= e($b['titulo']) ?>" loading="lazy"><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="section container">
        <div class="section-head">
            <div><span class="catalog-eyebrow">Vitrine</span><h2>Produtos em destaque</h2></div>
            <p>Conheça as soluções disponíveis e, quando decidir contratar, siga diretamente para o WHMCS.</p>
        </div>
        <?php if ($destaques): ?><div class="catalog-grid"><?php foreach ($destaques as $p) vitrine_card($p); ?></div><?php else: ?><div class="empty">Nenhum produto publicado na vitrine ainda.</div><?php endif; ?>
        <?php if (count($produtos) > count($destaques)): ?><div style="text-align:center;margin-top:24px;"><a class="btn btn-ghost" href="<?= e(app_url('produtos.php')) ?>">Ver todos os produtos</a></div><?php endif; ?>
    </section>

    <section class="section container">
        <div class="catalog-steps">
            <div><strong>1. Conheça</strong><p>Veja detalhes, benefícios, demonstrativos e condições de cada produto.</p></div>
            <div><strong>2. Escolha</strong><p>Compare as opções e selecione a solução mais adequada à programação da sua emissora.</p></div>
            <div><strong>3. Contrate</strong><p>O botão de contratação leva ao WHMCS, onde cadastro, pagamento e cobrança são processados.</p></div>
        </div>
    </section>

    <section class="section container">
        <div class="destaque">
            <div>
                <h2>Precisa montar uma solução sob medida?</h2>
                <p style="color:var(--muted);margin-top:8px;">Fale com a equipe e receba uma indicação objetiva dos produtos mais adequados para a sua rádio.</p>
                <div class="hero-actions"><a class="btn btn-primary" href="<?= e(wa_link('Olá! Quero uma indicação de produtos para minha rádio.')) ?>" target="_blank" rel="noopener">Conversar no WhatsApp</a></div>
            </div>
        </div>
    </section>
</main>
<?php layout_footer(); ?>
