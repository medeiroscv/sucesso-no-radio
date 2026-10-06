<?php
require_once __DIR__ . '/includes/layout_public.php';

$itens = app_produtos_servicos_lista(true);
$categorias = [];
foreach ($itens as $item) {
    $cat = trim((string)($item['categoria'] ?? ''));
    if ($cat !== '') $categorias[$cat] = true;
}
$categorias = array_keys($categorias);
natcasesort($categorias);

$filtro = trim((string)($_GET['categoria'] ?? ''));
if ($filtro !== '') {
    $itens = array_values(array_filter($itens, fn($item) => strcasecmp(trim((string)($item['categoria'] ?? '')), $filtro) === 0));
}

layout_header('Produtos e Serviços', 'produtos-servicos');
?>
<main>
<section class="section" style="padding-top:34px;">
    <div class="container">
        <div class="page-title ps-page-title">
            <p class="ps-kicker">Soluções para sua emissora</p>
            <h1>Produtos e Serviços</h1>
            <p>Conheça soluções, serviços e produtos que podem complementar a operação, a programação e a presença digital da sua emissora.</p>
        </div>

        <?php if ($categorias): ?>
        <div class="ps-filters">
            <a class="ps-filter <?= $filtro === '' ? 'active' : '' ?>" href="<?= e(app_url('produtos-servicos.php')) ?>">Todos</a>
            <?php foreach ($categorias as $cat): ?>
                <a class="ps-filter <?= strcasecmp($filtro, $cat) === 0 ? 'active' : '' ?>" href="<?= e(app_url('produtos-servicos.php?categoria=' . rawurlencode($cat))) ?>"><?= e($cat) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!$itens): ?>
            <div class="empty">Nenhum produto ou serviço disponível nesta categoria.</div>
        <?php else: ?>
            <div class="ps-grid">
                <?php foreach ($itens as $item):
                    $detalhe = app_url('produto-servico.php?slug=' . rawurlencode((string)$item['slug']));
                    $capa = trim((string)($item['capa'] ?? ''));
                    $tipoLabel = ($item['tipo'] ?? 'servico') === 'produto' ? 'Produto' : 'Serviço';
                    $preco = app_produto_servico_preco($item);
                    $periodo = app_produto_servico_periodicidade_label((string)($item['periodicidade'] ?? 'sob_consulta'));
                    $demoUrl = app_produto_servico_demo_url($item);
                ?>
                <article class="ps-card">
                    <a class="ps-cover" href="<?= e($detalhe) ?>">
                        <?php if ($capa): ?>
                            <img src="<?= e(app_url($capa)) ?>" alt="<?= e($item['nome']) ?>" loading="lazy">
                        <?php else: ?>
                            <span>📡</span>
                        <?php endif; ?>
                    </a>
                    <div class="ps-body">
                        <div class="ps-meta">
                            <span class="chip"><?= e($tipoLabel) ?></span>
                            <?php if (!empty($item['categoria'])): ?><span class="chip chip-soft"><?= e($item['categoria']) ?></span><?php endif; ?>
                            <?php if (!empty($item['destaque'])): ?><span class="chip">Destaque</span><?php endif; ?>
                        </div>
                        <h2><a href="<?= e($detalhe) ?>"><?= e($item['nome']) ?></a></h2>
                        <?php if (!empty($item['resumo'])): ?><p class="ps-desc"><?= e($item['resumo']) ?></p><?php endif; ?>
                        <div class="ps-price">
                            <strong><?= e($preco) ?></strong>
                            <?php if (!empty($item['exibir_preco']) && $periodo !== 'Sob consulta'): ?><span><?= e($periodo) ?></span><?php endif; ?>
                        </div>
                        <div class="card-actions">
                            <a class="btn btn-primary btn-small" href="<?= e($detalhe) ?>">Ver detalhes</a>
                            <?php if ($demoUrl): ?>
                                <a class="btn btn-ghost btn-small" href="<?= e($demoUrl) ?>" target="_blank" rel="noopener">Ver demonstração</a>
                            <?php endif; ?>
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
