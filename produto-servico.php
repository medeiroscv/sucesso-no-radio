<?php
require_once __DIR__ . '/includes/layout_public.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$item = app_produto_servico_by_slug($slug);

if (!$item) {
    http_response_code(404);
    layout_header('Produto ou serviço não encontrado', 'produtos-servicos');
    ?>
    <main class="container">
        <div class="page-title">
            <h1>Produto ou serviço não encontrado</h1>
            <p><a href="<?= e(app_url('produtos-servicos.php')) ?>">Voltar para Produtos e Serviços</a></p>
        </div>
    </main>
    <?php
    layout_footer();
    exit;
}

$recursos = app_produto_servico_recursos($item);
$whmcs = app_produto_servico_whmcs_url($item);
$demos = app_produto_servico_demos((int)$item['id'], $item);
$tipoLabel = ($item['tipo'] ?? 'servico') === 'produto' ? 'Produto' : 'Serviço';
$preco = app_produto_servico_preco($item);
$periodo = app_produto_servico_periodicidade_label((string)($item['periodicidade'] ?? 'sob_consulta'));
$capa = trim((string)($item['capa'] ?? ''));
$msg = trim((string)($item['whatsapp_msg'] ?? ''));
if ($msg === '') $msg = 'Olá! Quero saber mais sobre ' . $item['nome'];
$wa = preg_replace('/\D+/', '', app_setting('whatsapp', ''));

layout_header($item['nome'], 'produtos-servicos');
?>
<main class="container">
    <div class="page-title">
        <p class="muted"><a href="<?= e(app_url('produtos-servicos.php')) ?>">Produtos e Serviços</a><?php if (!empty($item['categoria'])): ?> · <?= e($item['categoria']) ?><?php endif; ?></p>
        <h1><?= e($item['nome']) ?></h1>
    </div>

    <section class="ps-detail">
        <div class="ps-detail-media">
            <?php if ($capa): ?>
                <img src="<?= e(app_url($capa)) ?>" alt="<?= e($item['nome']) ?>">
            <?php else: ?>
                <div class="ps-cover ps-detail-placeholder"><span>📡</span></div>
            <?php endif; ?>
        </div>

        <div class="ps-detail-content">
            <div class="ps-meta">
                <span class="chip"><?= e($tipoLabel) ?></span>
                <?php if (!empty($item['categoria'])): ?><span class="chip chip-soft"><?= e($item['categoria']) ?></span><?php endif; ?>
            </div>

            <?php if (!empty($item['resumo'])): ?><p class="ps-lead"><?= e($item['resumo']) ?></p><?php endif; ?>
            <?php if (!empty($item['descricao'])): ?><div class="ps-description"><?= nl2br(e($item['descricao'])) ?></div><?php endif; ?>

            <?php if ($recursos): ?>
                <h2>O que está incluído</h2>
                <ul class="ps-features">
                    <?php foreach ($recursos as $recurso): ?><li><?= e($recurso) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="ps-detail-price">
                <strong><?= e($preco) ?></strong>
                <?php if (!empty($item['exibir_preco']) && $periodo !== 'Sob consulta'): ?><span><?= e($periodo) ?></span><?php endif; ?>
            </div>

            <?php if ($demos): ?>
                <div id="demonstracoes" style="margin-top:22px;">
                    <h2 style="margin-bottom:10px;">Demonstrações e modelos</h2>
                    <div class="hero-actions" style="margin-top:0;">
                        <?php foreach ($demos as $i => $demo):
                            $demoTitulo = trim((string)($demo['titulo'] ?? ''));
                            if ($demoTitulo === '') $demoTitulo = 'Modelo ' . ($i + 1);
                        ?>
                            <a class="btn btn-ghost" href="<?= e($demo['url']) ?>" target="_blank" rel="noopener">Ver <?= e($demoTitulo) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="hero-actions">
                <?php if ($whmcs): ?>
                    <a class="btn btn-primary" href="<?= e($whmcs) ?>" target="_blank" rel="noopener"><?= e($item['botao_texto'] ?: 'Contratar') ?></a>
                <?php elseif ($wa): ?>
                    <a class="btn btn-primary" href="<?= e(wa_link($msg)) ?>" target="_blank" rel="noopener"><?= e($item['botao_texto'] ?: 'Solicitar orçamento') ?></a>
                <?php else: ?>
                    <a class="btn btn-primary" href="<?= e(app_url('contato.php')) ?>"><?= e($item['botao_texto'] ?: 'Entrar em contato') ?></a>
                <?php endif; ?>
                <?php if ($wa): ?><a class="btn btn-ghost" href="<?= e(wa_link('Olá! Tenho uma dúvida sobre ' . $item['nome'])) ?>" target="_blank" rel="noopener">Tirar dúvidas</a><?php endif; ?>
            </div>
        </div>
    </section>
</main>
<?php layout_footer(); ?>
