<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/nextcloud.php';
cliente_require_liberacao();

$cli = cliente_atual();
$cliId = intval($cli['id'] ?? 0);
$tipos = app_conteudo_tipos_cliente();
$tipo = trim((string)($_GET['tipo'] ?? 'diario'));
if (!isset($tipos[$tipo])) {
    $tipo = 'diario';
}
$meta = $tipos[$tipo];

$temAcesso = cliente_pode_acessar_tipo($tipo, $cli);
$ncOk = nc_configurado();
$pdo = app_pdo();
$lista = [];
if ($tipo !== '') {
    $st = $pdo->prepare('SELECT * FROM conteudos WHERE tipo = ? ORDER BY ordem ASC, id DESC');
    $st->execute([$tipo]);
    $lista = $st->fetchAll() ?: [];
}

// --- Navegação NC inline (sem redirect, sem conteudo.php) ---
$ncItemId = intval($_GET['nc_item'] ?? 0);
if (!$ncItemId && count($lista) === 1 && !empty($lista[0]['nc_folder']) && $ncOk) {
    $ncItemId = intval($lista[0]['id']);
}
$ncItem = null;
$ncRoot = '';
if ($ncItemId) {
    foreach ($lista as $p) {
        if (intval($p['id']) === $ncItemId && !empty($p['nc_folder'])) {
            $ncItem = $p;
            $ncRoot = $p['nc_folder'];
            break;
        }
    }
}
$ncRel = trim((string)($_GET['nc_path'] ?? ''));
$ncFull = $ncRel !== '' ? $ncRoot . '/' . $ncRel : $ncRoot;

$browsingNc = $ncItem && $ncOk;

cliente_header($browsingNc ? $ncItem['titulo'] : $meta['label'], $tipo);
?>
<p class="cliente-intro">
    <?= $browsingNc ? e($meta['label']) : e($meta['desc']) ?>
    <?php if (!$temAcesso): ?>
        <br><span class="chip" style="margin-top:8px;display:inline-block;background:rgba(251,191,36,.15);color:#fbbf24;border-color:rgba(251,191,36,.35);">
            Categoria sem liberação — você vê os nomes, mas não os arquivos
        </span>
    <?php endif; ?>
</p>

<div class="actions">
    <?php if ($browsingNc): ?>
        <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($tipo))) ?>">← <?= e($meta['label']) ?></a>
        <?php if ($ncRel !== ''): ?>
            <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($tipo) . '&nc_item=' . $ncItemId)) ?>">← Raiz</a>
            <?php $parent = dirname($ncRel); if ($parent !== '.' && $parent !== $ncRel): ?>
                <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($tipo) . '&nc_item=' . $ncItemId . '&nc_path=' . rawurlencode($parent))) ?>">← Pasta anterior</a>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <?php foreach ($tipos as $key => $m):
            $okTipo = cliente_pode_acessar_tipo($key, $cli);
        ?>
            <a class="btn btn-small <?= $key === $tipo ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($key))) ?>">
                <?= $m['icon'] ?> <?= e($m['label']) ?><?= $okTipo ? '' : ' 🔒' ?>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (!$lista): ?>
    <div class="empty">Nenhum item cadastrado nesta categoria ainda.</div>

<?php elseif ($browsingNc):
    // --- Navegador NC inline ---
    $ncItens = nc_listar($ncFull); ?>
    <p class="muted" style="font-size:.85rem;margin-bottom:8px;">
        Pasta: <code><?= e($ncRel ?: $ncRoot) ?></code>
    </p>
    <?php if (!$ncItens): ?>
        <div class="empty" style="padding:24px;">Nenhum arquivo disponível nesta pasta.</div>
    <?php else: ?>
        <div class="cliente-list">
            <?php foreach ($ncItens as $ent):
                $subPath = $ncRel ? $ncRel . '/' . $ent['name'] : $ent['name'];
                if ($ent['type'] === 'folder'): ?>
                    <a class="cliente-list-item" href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($tipo) . '&nc_item=' . $ncItemId . '&nc_path=' . rawurlencode($subPath))) ?>">
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
                            <?php if (app_setting('nc_show_meta', '1') === '1'): ?>
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

<?php elseif ($temAcesso && $ncOk):
    // Lista simplificada de pastas (sem cards)
    $comNc = array_filter($lista, fn($p) => !empty($p['nc_folder']));
    if (!$comNc): ?>
        <div class="empty" style="padding:24px;">Nenhum arquivo disponível.</div>
    <?php else: ?>
        <div class="cliente-list">
            <?php foreach ($comNc as $p): ?>
                <a class="cliente-list-item" href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($tipo) . '&nc_item=' . intval($p['id']))) ?>">
                    <div><strong>📁 <?= e($p['titulo']) ?></strong></div>
                    <div class="cliente-list-meta"><span class="btn btn-primary btn-small">Acessar</span></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="grid-cards">
        <?php foreach ($lista as $p):
            $capa = $p['capa'] ? app_url(ltrim($p['capa'], '/')) : '';
        ?>
            <article class="card" style="<?= $temAcesso ? '' : 'opacity:.92;' ?>">
                <?php if ($capa): ?>
                    <img class="card-cover" src="<?= e($capa) ?>" alt="<?= e($p['titulo']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="card-cover" style="display:grid;place-items:center;color:var(--muted);font-weight:700;font-size:2rem;"><?= $meta['icon'] ?></div>
                <?php endif; ?>
                <div class="card-body">
                    <h3><?= e($p['titulo']) ?></h3>
                    <div class="card-meta">
                        <?php if (!empty($p['duracao'])): ?><span class="chip"><?= e($p['duracao']) ?></span><?php endif; ?>
                        <?php if (!empty($p['blocos'])): ?><span class="chip"><?= e($p['blocos']) ?></span><?php endif; ?>
                        <?php if (!empty($p['dias'])): ?><span class="chip"><?= e($p['dias']) ?></span><?php endif; ?>
                        <?php if (!empty($p['insercoes'])): ?><span class="chip"><?= e($p['insercoes']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($temAcesso): ?>
                        <p class="card-desc"><?= e($p['resumo'] ?: mb_strimwidth(strip_tags($p['descricao'] ?? ''), 0, 140, '…')) ?></p>
                        <div class="card-actions">
                            <a class="btn btn-primary btn-small" href="<?= e(app_url('cliente/conteudo.php?id=' . intval($p['id']))) ?>">Acessar</a>
                        </div>
                    <?php else: ?>
                        <p class="card-desc muted">Conteúdo bloqueado. Solicite a liberação desta categoria à equipe.</p>
                        <div class="card-actions">
                            <span class="btn btn-ghost btn-small" style="opacity:.6;cursor:not-allowed;">Bloqueado</span>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

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
