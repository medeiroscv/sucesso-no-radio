<?php
require_once __DIR__ . '/_layout.php';
cliente_require_auth();

$cli = cliente_atual();
$cliId = intval($cli['id'] ?? 0);
$items = app_cliente_produtos($cliId);

cliente_header('Meus Produtos', 'produtos');
?>
<p class="cliente-intro">
    Aqui estão os <strong>produtos avulsos e pacotes</strong> que você comprou.
    Faça o download dos arquivos liberados.
</p>

<?php if (!$items): ?>
    <div class="empty">
        Você ainda não comprou nenhum produto avulso ou pacote.
        <br><a class="btn btn-primary" style="margin-top:16px;" href="<?= e(app_url('precos.php')) ?>">Ver produtos disponíveis</a>
    </div>
<?php else: ?>
    <div class="cliente-list">
        <?php foreach ($items as $p):
            $prodId = intval($p['produto_id']);
            $entregas = app_produto_entregas($prodId);
        ?>
            <div class="cliente-list-item" style="cursor:default;flex-direction:column;align-items:stretch;gap:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <strong style="font-size:1.05rem;"><?= e($p['produto_nome']) ?></strong>
                    <span class="muted" style="font-size:.82rem;">liberado em <?= e(app_fmt_date($p['liberado_em'] ?? null)) ?></span>
                </div>
                <?php if (!$entregas): ?>
                    <div class="muted" style="font-size:.88rem;">Aguardando arquivos de entrega…</div>
                <?php else: ?>
                    <div style="display:grid;gap:6px;">
                        <?php foreach ($entregas as $d):
                            $temArquivo = !empty($d['arquivo']);
                            $temLink = !empty($d['link_url']);
                        ?>
                            <?php if ($temLink): ?>
                            <a href="<?= e($d['link_url']) ?>" target="_blank" rel="noopener" style="display:flex;align-items:center;justify-content:space-between;gap:10px;background:rgba(15,23,42,.6);border:1px solid var(--line);border-radius:8px;padding:8px 12px;text-decoration:none;color:inherit;cursor:pointer;">
                            <?php else: ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;background:rgba(15,23,42,.6);border:1px solid var(--line);border-radius:8px;padding:8px 12px;">
                            <?php endif; ?>
                                <span style="font-size:.92rem;"><?= e($d['titulo'] ?: 'Arquivo') ?></span>
                                <div style="display:flex;gap:6px;flex-shrink:0;">
                                    <?php if ($temArquivo): ?>
                                        <a class="btn btn-primary btn-small" href="<?= e(app_url($d['arquivo'])) ?>" download onclick="event.stopPropagation();">Baixar</a>
                                        <?php if (preg_match('/\.(mp3|m4a|wav|ogg)$/i', (string)$d['arquivo'])): ?>
                                            <button class="btn btn-ghost btn-small" type="button"
                                                    data-url="<?= e(app_url($d['arquivo'])) ?>"
                                                    onclick="event.stopPropagation();abrirPlayer(this.dataset.url);">Ouvir</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($temLink): ?>
                                        <span class="btn btn-secondary btn-small">Link</span>
                                    <?php endif; ?>
                                </div>
                            <?php if ($temLink): ?>
                            </a>
                            <?php else: ?>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

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
function closeModal(e){
  if(e&&e.target!==e.currentTarget)return;
  document.getElementById('contentModal').style.display='none';
  document.getElementById('modalBody').innerHTML='';
}
</script>

<?php cliente_footer(); ?>
