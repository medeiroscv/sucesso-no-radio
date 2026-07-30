<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/nextcloud.php';
cliente_require_liberacao();

$cli = cliente_atual();
$id = intval($_GET['id'] ?? 0);
$item = app_conteudo_by_id($id, true);
if ($item && ($item['area'] ?? '') !== 'conteudo') {
    $item = null;
}

if (!$item || !cliente_pode_acessar_conteudo($id, $cli)) {
    http_response_code(404);
    cliente_header('Conteúdo não disponível', 'home');
    echo '<div class="empty">Este conteúdo não está liberado para o seu cadastro. <a href="' . e(cliente_home_url()) . '">Voltar</a></div>';
    cliente_footer();
    exit;
}

$tipos = app_conteudo_tipos();
$tipo = (string)$item['tipo'];
$tipoLabel = $tipos[$tipo]['label'] ?? $tipo;
$entregas = app_entregas($id, true);
$capa = !empty($item['capa']) ? app_url(ltrim($item['capa'], '/')) : '';

$usarNc = nc_configurado() && !empty($item['nc_folder']);
$showMeta = app_setting('nc_show_meta', '1') === '1';

cliente_header($item['titulo'], $tipo);
?>
<p class="cliente-intro" style="margin-bottom:12px;">
    <a href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($tipo))) ?>">← <?= e($tipoLabel) ?></a>
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

        <h3 style="margin:18px 0 12px;font-size:1.15rem;">Arquivos</h3>

        <?php if ($usarNc):
            $ncRel = trim((string)($_GET['nc_path'] ?? ''));
            $ncFull = $ncRel !== '' ? $item['nc_folder'] . '/' . $ncRel : $item['nc_folder'];
            $ncItens = nc_listar($ncFull);
        ?>
            <?php if ($ncRel !== ''): ?>
            <p class="muted" style="font-size:.85rem;margin-bottom:8px;">
                Pasta: <code><?= e($ncRel) ?></code>
                <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/conteudo.php?id=' . intval($item['id']))) ?>">← Raiz</a>
                <?php $parent = dirname($ncRel); if ($parent !== '.' && $parent !== $ncRel): ?>
                    <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/conteudo.php?id=' . intval($item['id']) . '&nc_path=' . rawurlencode($parent))) ?>">← Pasta anterior</a>
                <?php endif; ?>
            </p>
            <?php endif; ?>
            <?php if (!$ncItens): ?>
                <div class="empty" style="padding:24px;">Nenhum arquivo disponível nesta pasta.</div>
            <?php else: ?>
                <div class="cliente-list">
                    <?php foreach ($ncItens as $ent):
                        if ($ent['type'] === 'folder'): ?>
                            <a class="cliente-list-item" href="<?= e(app_url('cliente/conteudo.php?id=' . intval($item['id']) . '&nc_path=' . rawurlencode($ncRel ? $ncRel . '/' . $ent['name'] : $ent['name']))) ?>">
                                <div><strong>📁 <?= e($ent['name']) ?></strong><div class="muted" style="font-size:.82rem;margin-top:2px;">Pasta</div></div>
                                <div class="cliente-list-meta"><span class="btn btn-ghost btn-small">Abrir</span></div>
                            </a>
                        <?php else:
                            $isAudio = nc_is_audio($ent['mimetype']);
                            $dl = nc_download_url($ent['path']);
                        ?>
                            <div class="cliente-list-item cliente-list-item-static">
                                <div style="flex:1;min-width:0;">
                                    <?php if ($isAudio): ?>
                                        <strong>🎵 <?= e($ent['name']) ?></strong>
                                    <?php elseif (str_starts_with($ent['mimetype'], 'image/')): ?>
                                        <strong>🖼️ <?= e($ent['name']) ?></strong>
                                    <?php elseif ($ent['mimetype'] === 'application/pdf'): ?>
                                        <strong>📄 <?= e($ent['name']) ?></strong>
                                    <?php else: ?>
                                        <strong>📎 <?= e($ent['name']) ?></strong>
                                    <?php endif; ?>
                                    <?php if ($showMeta): ?>
                                    <div class="muted" style="font-size:.82rem;margin-top:2px;">
                                        <?= e($ent['size_fmt']) ?> · <?= e($ent['mtime']) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($isAudio): ?>
                                        <audio controls preload="none" style="width:100%;margin-top:8px;max-width:520px;">
                                            <source src="<?= e($dl) ?>" type="audio/mpeg">
                                        </audio>
                                    <?php endif; ?>
                                </div>
                                <div class="cliente-list-meta">
                                    <a class="btn btn-primary btn-small" href="<?= e($dl) ?>"<?= $isAudio ? '' : ' download' ?>>Baixar</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php elseif (!$entregas): ?>
            <div class="empty" style="padding:24px;">Nenhum arquivo de entrega publicado ainda para este conteúdo.</div>
        <?php else: ?>
            <div class="cliente-list">
                <?php foreach ($entregas as $ent):
                    $data = $ent['data_ref'] ? app_fmt_date($ent['data_ref']) : app_fmt_date($ent['created_at']);
                    $dl = app_url('cliente/download.php?id=' . intval($ent['id']));
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
