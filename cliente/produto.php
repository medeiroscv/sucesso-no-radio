<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/billing.php';

cliente_require_auth();

$cli = cliente_atual();
$cliId = intval($cli['id'] ?? 0);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    layout_header('Produto não encontrado', 'cliente');
    echo '<main><div class="container"><div class="page-title"><h1>Produto não encontrado</h1></div>';
    echo '<div class="empty"><a href="' . e(app_url('cliente/produtos.php')) . '">Voltar para Meus Produtos</a></div></div></main>';
    layout_footer();
    exit;
}

// Verifica se o cliente possui este produto
$tem = app_cliente_produto_info($cliId, $id);
if (!$tem) {
    layout_header('Acesso negado', 'cliente');
    echo '<main><div class="container"><div class="page-title"><h1>Acesso negado</h1></div>';
    echo '<div class="empty">Você não possui este produto ou ele ainda não foi liberado.</div></div></main>';
    layout_footer();
    exit;
}

$prod = billing_produto_by_id($id);
if (!$prod) {
    layout_header('Produto não encontrado', 'cliente');
    echo '<main><div class="container"><div class="page-title"><h1>Produto não encontrado</h1></div>';
    echo '<div class="empty"><a href="' . e(app_url('cliente/produtos.php')) . '">Voltar para Meus Produtos</a></div></div></main>';
    layout_footer();
    exit;
}

$prod = billing_produto_normalize_row($prod);
$entregas = app_produto_entregas($id);
$liberadoEm = app_fmt_date($tem['liberado_em'] ?? null);

$title = 'Meu produto: ' . ($prod['nome'] ?? 'Produto');
// Override do header — queremos título diferente
cliente_header($title, 'produtos');
?>
<p class="cliente-intro">
    <strong><?= e($prod['nome']) ?></strong> — adquirido em <?= e($liberadoEm) ?>
</p>

<?php if (!$entregas): ?>
    <div class="empty">
        Ainda não há arquivos de entrega para este produto. A equipe está preparando o material.
    </div>
<?php else: ?>
    <div class="cliente-list">
        <?php foreach ($entregas as $d):
            $temArquivo = !empty($d['arquivo']);
            $temLink = !empty($d['link_url']);
        ?>
            <div class="cliente-list-item" style="cursor:default;">
                <div>
                    <strong><?= e($d['titulo'] ?: 'Arquivo') ?></strong>
                </div>
                <div class="cliente-list-meta">
                    <?php if ($temArquivo): ?>
                        <a class="btn btn-primary btn-small" href="<?= e(app_url($d['arquivo'])) ?>" download>
                            Baixar
                        </a>
                        <?php if (preg_match('/\.(mp3|m4a|wav|ogg)$/i', (string)$d['arquivo'])): ?>
                            <button class="btn btn-ghost btn-small" type="button"
                                    data-url="<?= e(app_url($d['arquivo'])) ?>"
                                    onclick="abrirPlayer(this.dataset.url)">
                                Ouvir
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($temLink): ?>
                        <a class="btn btn-secondary btn-small" href="<?= e($d['link_url']) ?>" target="_blank" rel="noopener">
                            Abrir link
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="actions" style="margin-top:20px;">
    <a class="btn btn-ghost" href="<?= e(app_url('cliente/produtos.php')) ?>">← Voltar para Meus Produtos</a>
</div>

<!-- Modal para ouvir áudio -->
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
