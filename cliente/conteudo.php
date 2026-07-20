<?php
require_once __DIR__ . '/_layout.php';
cliente_require_auth();

$id = intval($_GET['id'] ?? 0);
$item = app_conteudo_by_id($id, true);
$base = app_base_path();
$prefix = $base === '' ? '' : $base;

if (!$item) {
    http_response_code(404);
    cliente_header('Conteúdo não encontrado', 'home');
    echo '<div class="empty">Conteúdo indisponível. <a href="' . e($prefix . '/cliente/') . '">Voltar</a></div>';
    cliente_footer();
    exit;
}

$tipos = app_conteudo_tipos();
$tipo = (string)$item['tipo'];
$tipoLabel = $tipos[$tipo]['label'] ?? $tipo;
$entregas = app_entregas($id, true);
$capa = !empty($item['capa']) ? ($prefix . '/' . ltrim($item['capa'], '/')) : '';

cliente_header($item['titulo'], $tipo);
?>
<p class="muted" style="margin-top:-8px;margin-bottom:16px;">
    <a href="<?= e($prefix . '/cliente/conteudos.php?tipo=' . rawurlencode($tipo)) ?>">← <?= e($tipoLabel) ?></a>
</p>

<div class="destaque cliente-detail">
    <div>
        <div class="card-meta" style="margin-bottom:12px;">
            <span class="chip"><?= e($tipoLabel) ?></span>
            <?php if (!empty($item['duracao'])): ?><span class="chip"><?= e($item['duracao']) ?></span><?php endif; ?>
            <?php if (!empty($item['blocos'])): ?><span class="chip"><?= e($item['blocos']) ?></span><?php endif; ?>
            <?php if (!empty($item['dias'])): ?><span class="chip"><?= e($item['dias']) ?></span><?php endif; ?>
            <?php if (!empty($item['insercoes'])): ?><span class="chip"><?= e($item['insercoes']) ?></span><?php endif; ?>
        </div>
        <?php if (!empty($item['resumo'])): ?>
            <p style="font-size:1.05rem;margin-bottom:12px;"><?= e($item['resumo']) ?></p>
        <?php endif; ?>
        <?php if (!empty($item['descricao'])): ?>
            <div style="color:var(--muted);white-space:pre-wrap;margin-bottom:18px;"><?= e($item['descricao']) ?></div>
        <?php endif; ?>

        <h3 style="margin:18px 0 12px;font-size:1.15rem;">Arquivos de entrega</h3>
        <?php if (!$entregas): ?>
            <div class="empty" style="padding:24px;">Nenhum arquivo de entrega publicado ainda para este conteúdo.</div>
        <?php else: ?>
            <div class="cliente-list">
                <?php foreach ($entregas as $ent):
                    $data = $ent['data_ref'] ?: substr((string)$ent['created_at'], 0, 10);
                    $dl = $prefix . '/cliente/download.php?id=' . intval($ent['id']);
                ?>
                    <div class="cliente-list-item cliente-list-item-static">
                        <div style="flex:1;min-width:0;">
                            <strong><?= e($ent['titulo'] ?: 'Arquivo') ?></strong>
                            <div class="muted" style="font-size:.85rem;margin-top:4px;">Ref.: <?= e($data) ?></div>
                            <audio controls preload="none" style="width:100%;margin-top:10px;max-width:520px;">
                                <source src="<?= e($dl) ?>" type="audio/mpeg">
                            </audio>
                        </div>
                        <div class="cliente-list-meta">
                            <a class="btn btn-primary btn-small" href="<?= e($dl) ?>&dl=1">Baixar</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($capa): ?>
        <img src="<?= e($capa) ?>" alt="<?= e($item['titulo']) ?>">
    <?php endif; ?>
</div>
<?php cliente_footer(); ?>
