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
            $entregas = app_produto_entregas(intval($p['produto_id']));
            $qtd = count($entregas);
        ?>
            <a class="cliente-list-item" href="<?= e(app_url('cliente/produto.php?id=' . intval($p['produto_id']))) ?>">
                <div>
                    <strong><?= e($p['produto_nome']) ?></strong>
                    <div class="muted" style="font-size:.88rem;margin-top:4px;">
                        <?php if ($qtd > 0): ?>
                            <?= $qtd ?> arquivo(s) disponível(is) · liberado em <?= e(substr((string)($p['liberado_em'] ?? ''), 0, 10)) ?>
                        <?php else: ?>
                            Aguardando arquivos de entrega · liberado em <?= e(substr((string)($p['liberado_em'] ?? ''), 0, 10)) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cliente-list-meta">
                    <?php if ($qtd > 0): ?>
                        <span class="chip" style="background:rgba(34,197,94,.15);color:#22c55e;"><?= $qtd ?> arquivo(s)</span>
                    <?php endif; ?>
                    <span class="btn btn-ghost btn-small">Acessar</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php cliente_footer(); ?>
