<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/efi.php';

cliente_require_auth('financeiro');
$cli = cliente_atual();
if (!$cli) {
    cliente_logout(true);
}

$cliId = intval($cli['id']);
$atraso = isset($_GET['atraso']) || !cliente_financeiro_em_dia($cli);
$finAtivo = app_finance_ativo();

// Sincroniza Pix abertas
$faturas = app_faturas_cliente($cliId, 30);
foreach ($faturas as $i => $f) {
    if (in_array($f['status'], ['aberta', 'vencida'], true) && !empty($f['pix_txid'])) {
        $faturas[$i] = finance_sync_pix_fatura($f);
    }
}
// recarrega após sync
$faturas = app_faturas_cliente($cliId, 30);
$emAberto = array_values(array_filter($faturas, fn($f) => in_array($f['status'], ['aberta', 'vencida'], true)));
$verId = intval($_GET['id'] ?? 0);
$ver = null;
if ($verId > 0) {
    foreach ($faturas as $f) {
        if (intval($f['id']) === $verId) {
            $ver = $f;
            break;
        }
    }
}

cliente_header('Financeiro', 'financeiro');

if (!$finAtivo):
?>
<div class="empty">O módulo financeiro ainda não está ativo. Em caso de dúvidas, fale com a equipe.</div>
<?php
cliente_footer();
exit;
endif;

if ($atraso):
?>
<div class="alert alert-err" style="margin-bottom:18px;">
    Há pagamento em atraso. Regularize abaixo para liberar conteúdos e textos novamente.
</div>
<?php endif; ?>

<p class="cliente-intro">
    Acompanhe suas faturas e pague com <strong>Pix (QR Code)</strong> ou <strong>boleto</strong>.
    Mantendo os pagamentos em dia, sua área de conteúdos permanece liberada.
</p>

<?php if ($ver):
    $m = app_fatura_status_meta($ver['status'] ?? '');
    $podePagar = in_array($ver['status'], ['aberta', 'vencida'], true);
?>
<div class="actions" style="margin-bottom:14px;">
    <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/financeiro.php')) ?>">← Todas as faturas</a>
</div>

<div class="form-card" style="max-width:720px;margin-bottom:28px;">
    <div class="actions" style="margin-bottom:10px;">
        <h3 style="margin:0;flex:1;"><?= e($ver['descricao'] ?: 'Fatura') ?> #<?= intval($ver['id']) ?></h3>
        <span style="font-size:.78rem;font-weight:800;padding:4px 10px;border-radius:999px;color:<?= e($m['color']) ?>;background:<?= e($m['bg']) ?>;"><?= e($m['label']) ?></span>
    </div>
    <p style="font-size:1.35rem;font-weight:800;margin:8px 0;"><?= e(app_money_br(intval($ver['valor_centavos']))) ?></p>
    <p class="muted">Vencimento: <?= e(date('d/m/Y', strtotime($ver['vencimento']))) ?>
        <?php if (!empty($ver['pago_em'])): ?> · Pago em <?= e(date('d/m/Y H:i', strtotime($ver['pago_em']))) ?><?php endif; ?>
    </p>

    <?php if ($podePagar): ?>
        <div class="forms-grid" style="margin-top:18px;grid-template-columns:1fr 1fr;">
            <div style="background:#0b1220;border:1px solid var(--line);border-radius:14px;padding:16px;">
                <h3 style="margin:0 0 10px;font-size:1.05rem;">Pix</h3>
                <?php if (!empty($ver['pix_qrcode']) || !empty($ver['pix_copia_cola'])): ?>
                    <?php if (!empty($ver['pix_qrcode'])):
                        $src = $ver['pix_qrcode'];
                        if (!str_starts_with($src, 'data:') && !str_starts_with($src, 'http')) {
                            $src = 'data:image/png;base64,' . $src;
                        }
                    ?>
                        <img src="<?= e($src) ?>" alt="QR Code Pix" style="width:min(220px,100%);background:#fff;padding:10px;border-radius:10px;margin:0 auto 12px;display:block;">
                    <?php endif; ?>
                    <?php if (!empty($ver['pix_copia_cola'])): ?>
                        <p class="muted" style="font-size:.8rem;margin-bottom:8px;">Pix Copia e Cola</p>
                        <textarea id="pixCopia" readonly rows="4" style="width:100%;font-size:.78rem;word-break:break-all;"><?= e($ver['pix_copia_cola']) ?></textarea>
                        <button type="button" class="btn btn-primary btn-small" style="margin-top:10px;width:100%;" onclick="navigator.clipboard.writeText(document.getElementById('pixCopia').value);this.textContent='Copiado!';">Copiar código Pix</button>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted">QR Code ainda não gerado. Aguarde a equipe ou atualize em instantes.</p>
                <?php endif; ?>
            </div>
            <div style="background:#0b1220;border:1px solid var(--line);border-radius:14px;padding:16px;">
                <h3 style="margin:0 0 10px;font-size:1.05rem;">Boleto</h3>
                <?php if (!empty($ver['boleto_barcode']) || !empty($ver['boleto_url'])): ?>
                    <?php if (!empty($ver['boleto_barcode'])): ?>
                        <p class="muted" style="font-size:.8rem;">Linha digitável</p>
                        <p style="font-size:.9rem;word-break:break-all;margin:8px 0 12px;font-weight:600;"><?= e($ver['boleto_barcode']) ?></p>
                        <button type="button" class="btn btn-ghost btn-small" style="width:100%;margin-bottom:10px;" onclick="navigator.clipboard.writeText('<?= e(addslashes($ver['boleto_barcode'])) ?>');this.textContent='Copiado!';">Copiar linha digitável</button>
                    <?php endif; ?>
                    <?php if (!empty($ver['boleto_url'])): ?>
                        <a class="btn btn-primary btn-small" style="width:100%;" href="<?= e($ver['boleto_url']) ?>" target="_blank" rel="noopener">Abrir / imprimir boleto</a>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted">Boleto ainda não gerado. Confirme se o CPF está cadastrado em Meus dados.</p>
                <?php endif; ?>
            </div>
        </div>
        <p class="muted" style="margin-top:14px;font-size:.85rem;">Após o pagamento Pix, a liberação costuma ser automática em poucos minutos. Boleto pode levar até 1–2 dias úteis; a equipe também pode confirmar manualmente.</p>
    <?php elseif (($ver['status'] ?? '') === 'paga'): ?>
        <div class="alert alert-ok" style="margin-top:14px;">Pagamento confirmado. Obrigado!</div>
    <?php endif; ?>
</div>
<?php else: ?>

<?php if ($emAberto): ?>
<section class="section" style="padding-top:0;">
    <div class="section-head">
        <h2>Em aberto</h2>
        <p>Pague para manter o acesso aos conteúdos.</p>
    </div>
    <div class="cliente-list">
        <?php foreach ($emAberto as $f):
            $m = app_fatura_status_meta($f['status']);
        ?>
            <a class="cliente-list-item" href="<?= e(app_url('cliente/financeiro.php?id=' . intval($f['id']))) ?>">
                <div>
                    <strong><?= e($f['descricao'] ?: 'Fatura') ?></strong>
                    <div class="muted" style="font-size:.88rem;margin-top:4px;">
                        Venc. <?= e(date('d/m/Y', strtotime($f['vencimento']))) ?>
                    </div>
                </div>
                <div class="cliente-list-meta">
                    <strong><?= e(app_money_br(intval($f['valor_centavos']))) ?></strong>
                    <span style="font-size:.72rem;font-weight:800;padding:4px 10px;border-radius:999px;color:<?= e($m['color']) ?>;background:<?= e($m['bg']) ?>;"><?= e($m['label']) ?></span>
                    <span class="btn btn-primary btn-small">Pagar</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php else: ?>
<div class="empty" style="margin-bottom:24px;">
    <strong style="color:var(--text);">Tudo em dia</strong><br>
    Não há faturas em aberto no momento.
</div>
<?php endif; ?>

<section class="section">
    <div class="section-head">
        <h2>Histórico</h2>
        <p>Últimas faturas da sua conta.</p>
    </div>
    <?php if (!$faturas): ?>
        <div class="empty">Nenhuma fatura registrada ainda.</div>
    <?php else: ?>
        <div class="cliente-list">
            <?php foreach ($faturas as $f):
                $m = app_fatura_status_meta($f['status']);
            ?>
                <a class="cliente-list-item" href="<?= e(app_url('cliente/financeiro.php?id=' . intval($f['id']))) ?>">
                    <div>
                        <strong><?= e($f['descricao'] ?: 'Fatura #' . $f['id']) ?></strong>
                        <div class="muted" style="font-size:.85rem;margin-top:4px;">Venc. <?= e(date('d/m/Y', strtotime($f['vencimento']))) ?></div>
                    </div>
                    <div class="cliente-list-meta">
                        <span><?= e(app_money_br(intval($f['valor_centavos']))) ?></span>
                        <span style="font-size:.72rem;font-weight:800;padding:4px 10px;border-radius:999px;color:<?= e($m['color']) ?>;background:<?= e($m['bg']) ?>;"><?= e($m['label']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php cliente_footer(); ?>
