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
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;">
                <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/conteudo.php?id=' . intval($item['id']))) ?>">← Raiz</a>
                <?php $parent = dirname($ncRel); if ($parent !== '.' && $parent !== $ncRel): ?>
                    <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/conteudo.php?id=' . intval($item['id']) . '&nc_path=' . rawurlencode($parent))) ?>">← Pasta anterior</a>
                <?php endif; ?>
            </div>
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
                            $isTxt = $ent['mimetype'] === 'text/plain';
                            $dl = nc_download_url($ent['path']);
                        ?>
                            <div class="cliente-list-item cliente-list-item-static">
                                <div style="flex:1;min-width:0;">
                                    <?php if ($isAudio): ?>
                                        <strong>🎵 <?= e($ent['name']) ?></strong>
                                    <?php elseif ($isTxt): ?>
                                        <strong>📄 <?= e($ent['name']) ?></strong>
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
                                </div>
                                <div class="cliente-list-meta" style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php if ($isAudio): ?>
                                        <button class="btn btn-secondary btn-small" data-url="<?= e($dl) ?>" onclick="abrirPlayer(this.dataset.url)">Ouvir</button>
                                        <a class="btn btn-primary btn-small" href="<?= e($dl) ?>" download>Baixar</a>
                                    <?php elseif ($isTxt): ?>
                                        <button class="btn btn-primary btn-small" data-url="<?= e($dl) ?>" onclick="abrirTexto(this.dataset.url)">Ver</button>
                                    <?php else: ?>
                                        <a class="btn btn-primary btn-small" href="<?= e($dl) ?>" download>Baixar</a>
                                    <?php endif; ?>
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
<!-- Modal para player e texto -->
<div id="contentModal" class="modal-overlay" style="display:none;" onclick="closeModal(event)">
  <div class="modal-card">
    <button class="modal-close" onclick="closeModal()">&times;</button>
    <div id="modalBody"></div>
  </div>
</div>

<style>
.modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.7);z-index:9999;display:flex;align-items:center;justify-content:center;}
.modal-card{background:var(--card);border-radius:12px;padding:24px;max-width:520px;width:90%;max-height:80vh;overflow:auto;position:relative;}
.modal-close{position:absolute;top:8px;right:12px;background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text);line-height:1;}
</style>

<script>
function abrirPlayer(url){
  document.getElementById('modalBody').innerHTML='<audio controls autoplay style="width:100%;"><source src="'+url+'" type="audio/mpeg"></audio>';
  document.getElementById('contentModal').style.display='flex';
}
function abrirTexto(url){
  fetch(url).then(function(r){return r.text();}).then(function(t){
    document.getElementById('modalBody').innerHTML='<pre style="white-space:pre-wrap;word-break:break-word;max-height:60vh;overflow-y:auto;margin:0;">'+escHtml(t)+'</pre>';
    document.getElementById('contentModal').style.display='flex';
  }).catch(function(){
    document.getElementById('modalBody').innerHTML='<p class="muted">Erro ao carregar o conteudo.</p>';
    document.getElementById('contentModal').style.display='flex';
  });
}
function closeModal(e){
  if(e&&e.target!==e.currentTarget)return;
  document.getElementById('contentModal').style.display='none';
  document.getElementById('modalBody').innerHTML='';
}
function escHtml(s){
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
<?php cliente_footer(); ?>
