<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/asaas.php';

cliente_require_auth('financeiro');
$cli = cliente_atual();
if (!$cli) {
    cliente_logout(true);
}

$cliId = intval($cli['id']);
$finAtivo = app_finance_ativo();
$regenMsg = '';
$regenErr = '';
$prep = ['ok' => 0, 'regenerated' => 0, 'paid' => 0, 'errors' => []];

// Ao entrar no financeiro: marca vencidas + garante Pix/boleto em TODAS as faturas abertas
if ($finAtivo && asaas_configured()) {
    finance_marcar_vencidas($cliId);
    $prep = finance_preparar_faturas_cliente($cliId, 20);
    if (!empty($prep['regenerated'])) {
        $regenMsg = 'Seus meios de pagamento foram atualizados automaticamente'
            . ($prep['regenerated'] > 1 ? ' em ' . $prep['regenerated'] . ' faturas' : '')
            . '. Use o Pix ou boleto exibido abaixo.';
    }
    if (!empty($prep['paid'])) {
        $regenMsg = trim($regenMsg . ' ' . ($prep['paid'] === 1
            ? 'Detectamos um pagamento confirmado.'
            : 'Detectamos pagamentos confirmados.'));
    }
    if (!empty($prep['errors'])) {
        $regenErr = implode(' ', array_slice($prep['errors'], 0, 2));
    }
}

$atraso = isset($_GET['atraso']) || !cliente_financeiro_em_dia($cli);

$faturas = app_faturas_cliente($cliId, 30);
$emAberto = array_values(array_filter($faturas, fn($f) => in_array($f['status'], ['aberta', 'vencida'], true)));
$verId = intval($_GET['id'] ?? 0);
$ver = null;

// Cliente pediu renovação forçada de uma fatura
if ($verId > 0 && isset($_GET['renovar']) && asaas_configured()) {
    $tmp = null;
    foreach ($faturas as $f) {
        if (intval($f['id']) === $verId) {
            $tmp = $f;
            break;
        }
    }
    if ($tmp && in_array($tmp['status'], ['aberta', 'vencida'], true)) {
        try {
            $g = finance_garantir_meios_pagamento($tmp, true);
            if (!empty($g['regenerated']) || !empty($g['fatura']['pix_copia_cola']) || !empty($g['fatura']['boleto_url'])) {
                $regenMsg = (string)($g['message'] ?: 'Novos meios de pagamento gerados (Pix e/ou boleto).');
            } else {
                $regenErr = (string)($g['message'] ?: 'Não foi possível renovar.');
            }
        } catch (Throwable $e) {
            $regenErr = $e->getMessage();
        }
        // limpa throttle da sessão para refletir na lista
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['finance_prep_' . $cliId]);
        }
        $faturas = app_faturas_cliente($cliId, 30);
        $emAberto = array_values(array_filter($faturas, fn($f) => in_array($f['status'], ['aberta', 'vencida'], true)));
    }
}

if ($verId > 0) {
    foreach ($faturas as $f) {
        if (intval($f['id']) === $verId) {
            $ver = $f;
            break;
        }
    }
    // Ao abrir detalhe: confere de novo (mesmo com throttle da lista)
    if ($ver && in_array($ver['status'], ['aberta', 'vencida'], true) && asaas_configured()) {
        $g = finance_garantir_meios_pagamento($ver, false);
        $ver = $g['fatura'];
        if (!empty($g['regenerated']) && $regenMsg === '') {
            $regenMsg = (string)($g['message'] ?? 'Meios de pagamento atualizados.');
        } elseif ($regenMsg === '' && !empty($g['message']) && str_contains((string)$g['message'], 'Não foi possível')) {
            $regenErr = (string)$g['message'];
        }
        // se ficou paga
        if (($ver['status'] ?? '') === 'paga' && $regenMsg === '') {
            $regenMsg = 'Pagamento confirmado. Obrigado!';
        }
    }
}

// Auto-abre a única fatura em aberto (menos cliques = menos falha)
if ($ver === null && count($emAberto) === 1 && !isset($_GET['lista'])) {
    header('Location: ' . app_url('cliente/financeiro.php?id=' . intval($emAberto[0]['id'])));
    exit;
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
    Há pagamento em atraso. Regularize abaixo — o Pix e o boleto são gerados/atualizados automaticamente para você.
</div>
<?php endif; ?>

<?php if ($regenMsg !== ''): ?>
    <div class="alert alert-ok" style="margin-bottom:14px;"><?= e($regenMsg) ?></div>
<?php endif; ?>
<?php if ($regenErr !== ''): ?>
    <div class="alert alert-err" style="margin-bottom:14px;"><?= e($regenErr) ?></div>
<?php endif; ?>

<p class="cliente-intro">
    Acompanhe suas faturas e pague com <strong>Pix (QR Code)</strong> ou <strong>boleto</strong>.
    Se algo expirar ou for removido, o sistema <strong>gera novos meios automaticamente</strong> quando você entra aqui.
</p>

<?php if ($ver):
    $m = app_fatura_status_meta($ver['status'] ?? '');
    $podePagar = in_array($ver['status'], ['aberta', 'vencida'], true);
    $pixOk = !empty($ver['pix_qrcode']) || !empty($ver['pix_copia_cola']);
    $bolOk = !empty($ver['boleto_barcode']) || !empty($ver['boleto_url']);
?>
<div class="actions" style="margin-bottom:14px;">
    <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/financeiro.php?lista=1')) ?>">← Todas as faturas</a>
</div>

<div class="form-card" style="max-width:720px;margin-bottom:28px;">
    <div class="actions" style="margin-bottom:10px;">
        <h3 style="margin:0;flex:1;"><?= e($ver['descricao'] ?: 'Fatura') ?> #<?= intval($ver['id']) ?></h3>
        <span style="font-size:.78rem;font-weight:800;padding:4px 10px;border-radius:999px;color:<?= e($m['color']) ?>;background:<?= e($m['bg']) ?>;"><?= e($m['label']) ?></span>
    </div>
    <p style="font-size:1.35rem;font-weight:800;margin:8px 0;"><?= e(app_money_br(intval($ver['valor_centavos']))) ?></p>
    <p class="muted">Vencimento da fatura: <?= e(date('d/m/Y', strtotime($ver['vencimento']))) ?>
        <?php if (!empty($ver['pago_em'])): ?> · Pago em <?= e(date('d/m/Y H:i', strtotime($ver['pago_em']))) ?><?php endif; ?>
    </p>
    <?php if ($podePagar && (string)$ver['vencimento'] < date('Y-m-d')): ?>
        <p class="muted" style="margin-top:6px;font-size:.85rem;color:#fbbf24;">
            Esta fatura está vencida no sistema, mas você ainda pode pagar. O Pix/boleto abaixo estão válidos para pagamento agora.
        </p>
    <?php endif; ?>

    <?php if ($podePagar): ?>
        <div class="forms-grid" style="margin-top:18px;grid-template-columns:1fr 1fr;">
            <div style="background:#0b1220;border:1px solid var(--line);border-radius:14px;padding:16px;">
                <h3 style="margin:0 0 10px;font-size:1.05rem;">Pix</h3>
                <?php if ($pixOk): ?>
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
                    <p class="muted">Gerando QR Code… Se não aparecer, toque em renovar abaixo.</p>
                <?php endif; ?>
            </div>
            <div style="background:#0b1220;border:1px solid var(--line);border-radius:14px;padding:16px;">
                <h3 style="margin:0 0 10px;font-size:1.05rem;">Boleto</h3>
                <?php if ($bolOk): ?>
                    <?php if (!empty($ver['boleto_barcode'])):
                        $linhaDig = (string)$ver['boleto_barcode'];
                        $linhaDigits = preg_replace('/\D+/', '', $linhaDig) ?? '';
                        $linhaIncompleta = strlen($linhaDigits) > 0 && strlen($linhaDigits) < 47;
                    ?>
                        <p class="muted" style="font-size:.8rem;">Linha digitável</p>
                        <p style="font-size:.88rem;word-break:break-all;margin:8px 0 12px;font-weight:600;letter-spacing:.02em;line-height:1.45;"><?= e($linhaDig) ?></p>
                        <button type="button" class="btn btn-ghost btn-small" style="width:100%;margin-bottom:10px;"
                            onclick="navigator.clipboard.writeText(<?= json_encode($linhaDigits !== '' ? $linhaDigits : $linhaDig, JSON_UNESCAPED_UNICODE) ?>);this.textContent='Copiado!';">
                            Copiar linha digitável
                        </button>
                        <?php if ($linhaIncompleta): ?>
                            <p class="muted" style="font-size:.78rem;color:#fbbf24;">Linha incompleta detectada. Toque em “Atualizar / gerar novo” ou abra o PDF do boleto.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($ver['boleto_url'])): ?>
                        <a class="btn btn-primary btn-small" style="width:100%;" href="<?= e($ver['boleto_url']) ?>" target="_blank" rel="noopener">Abrir / imprimir boleto</a>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted">Boleto indisponível no momento. Confirme o CPF em Meus dados ou renove abaixo.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="actions" style="margin-top:16px;flex-wrap:wrap;">
            <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/financeiro.php?id=' . intval($ver['id']) . '&renovar=1')) ?>">
                🔄 Atualizar / gerar novo Pix e boleto
            </a>
            <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/financeiro.php?id=' . intval($ver['id']) . '&refresh=1')) ?>">
                Verificar se o pagamento caiu
            </a>
        </div>
        <p class="muted" style="margin-top:14px;font-size:.85rem;">
            <strong>Dica:</strong> se o app do banco disser que o QR expirou, use <em>Atualizar / gerar novo</em> e escaneie o código novo.
            Não reutilize um QR ou boleto antigo salvo no celular.
            Após o Pix, a liberação costuma ser automática em poucos minutos.
        </p>
    <?php elseif (($ver['status'] ?? '') === 'paga'): ?>
        <div class="alert alert-ok" style="margin-top:14px;">Pagamento confirmado. Obrigado!</div>
    <?php else: ?>
        <div class="alert" style="margin-top:14px;background:rgba(148,163,184,.12);border:1px solid var(--line);padding:12px;border-radius:10px;color:var(--muted);">
            Esta fatura está <?= e($m['label']) ?>.
        </div>
    <?php endif; ?>
</div>
<?php else: ?>

<?php if ($emAberto): ?>
<section class="section" style="padding-top:0;">
    <div class="section-head">
        <h2>Em aberto</h2>
        <p>Toque na fatura para pagar com Pix ou boleto (sempre atualizados).</p>
    </div>
    <div class="cliente-list">
        <?php foreach ($emAberto as $f):
            $m = app_fatura_status_meta($f['status']);
            $temMeio = !empty($f['pix_copia_cola']) || !empty($f['boleto_url']) || !empty($f['boleto_barcode']);
        ?>
            <a class="cliente-list-item" href="<?= e(app_url('cliente/financeiro.php?id=' . intval($f['id']))) ?>">
                <div>
                    <strong><?= e($f['descricao'] ?: 'Fatura') ?></strong>
                    <div class="muted" style="font-size:.88rem;margin-top:4px;">
                        Venc. <?= e(date('d/m/Y', strtotime($f['vencimento']))) ?>
                        <?= $temMeio ? ' · Pix/boleto prontos' : ' · gerar meios ao abrir' ?>
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
