<?php
/**
 * Checkout do produto:
 * 1) Login/cadastro se necessário
 * 2) Confirmação humana (checkbox) — sem gerar fatura ainda
 * 3) Só então cria fatura/boleto e vai ao financeiro
 *
 * Uso: /cliente/contratar.php?produto=slug  ou  ?id=123
 */
require_once __DIR__ . '/../includes/layout_public.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/asaas.php';

cliente_session_start();

$slug = trim((string)($_GET['produto'] ?? $_POST['produto'] ?? $_GET['slug'] ?? ''));
$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
$prod = null;
if ($slug !== '') {
    $prod = billing_produto_by_slug($slug);
} elseif ($id > 0) {
    $prod = billing_produto_by_id($id);
}

if (!$prod || empty($prod['ativo'])) {
    layout_header('Produto indisponível', 'precos');
    echo '<main><div class="container"><div class="page-title"><h1>Produto indisponível</h1></div>';
    echo '<div class="empty">Este plano não está disponível. <a href="' . e(app_url('precos.php')) . '">Ver preços</a></div></div></main>';
    layout_footer();
    exit;
}

$prod = billing_produto_normalize_row($prod);
$prodId = intval($prod['id']);
$slugSafe = (string)($prod['slug'] ?? $prodId);
$ciclos = billing_ciclos();
$tipos = billing_produto_tipos();
$cicloLabel = $ciclos[$prod['ciclo'] ?? '']['label'] ?? ($prod['ciclo'] ?? '');
$tipoLabel = $tipos[$prod['tipo'] ?? '']['label'] ?? ($prod['tipo'] ?? '');

// Não logado → cadastro com retorno a esta confirmação (ainda sem fatura)
if (!cliente_logado() || !cliente_atual()) {
    header('Location: ' . app_url('cliente/cadastro.php?produto=' . rawurlencode($slugSafe)));
    exit;
}

$cli = cliente_atual();
$cliId = intval($cli['id']);
$erro = '';

// Confirmação enviada → aí sim gera fatura e vai ao pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_pedido'])) {
    $confirmou = !empty($_POST['confirmo_compra']);
    $slugPost = trim((string)($_POST['produto'] ?? $slugSafe));
    if ($slugPost !== '' && $slugPost !== $slugSafe) {
        // segurança: produto do POST deve bater
        $p2 = billing_produto_by_slug($slugPost) ?: (ctype_digit($slugPost) ? billing_produto_by_id(intval($slugPost)) : null);
        if ($p2 && !empty($p2['ativo'])) {
            $prod = billing_produto_normalize_row($p2);
            $prodId = intval($prod['id']);
            $slugSafe = (string)($prod['slug'] ?? $prodId);
        }
    }

    if (!$confirmou) {
        $erro = 'Marque a caixinha para confirmar que deseja comprar este produto.';
    } else {
        if (!app_finance_ativo()) {
            try {
                app_setting_set('finance_ativo', '1');
            } catch (Throwable $e) { /* ok */ }
        }

        $r = billing_checkout_produto($cliId, $prodId);
        if (empty($r['ok']) || empty($r['fatura_id'])) {
            $erro = $r['message'] ?? 'Não foi possível criar o pedido. Tente novamente.';
        } else {
            header('Location: ' . app_url('cliente/financeiro.php?id=' . intval($r['fatura_id']) . '&pedido=1'));
            exit;
        }
    }
}

// Tela de confirmação (nenhuma fatura gerada ainda)
layout_header('Confirmar compra', 'precos');
?>
<main>
    <div class="container">
        <div class="page-title" style="max-width:640px;margin:0 auto 8px;">
            <p class="cliente-kicker">Confirmação</p>
            <h1>Confirme seu pedido</h1>
            <p class="muted">Nenhuma fatura ou boleto será gerado até você confirmar abaixo.</p>
        </div>

        <div class="form-card" style="max-width:520px;margin:0 auto 40px;">
            <?php if ($erro !== ''): ?>
                <div class="alert alert-err"><?= e($erro) ?></div>
            <?php endif; ?>

            <div style="background:#0b1220;border:1px solid var(--line);border-radius:14px;padding:16px 18px;margin-bottom:18px;">
                <p class="muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">
                    Você está prestes a comprar
                </p>
                <h2 style="font-size:1.25rem;margin:0 0 8px;"><?= e($prod['nome']) ?></h2>
                <p style="font-size:1.5rem;font-weight:800;margin:0 0 6px;">
                    <?= e(app_money_br(intval($prod['valor_centavos']))) ?>
                    <?php if (($prod['ciclo'] ?? '') !== 'unico'): ?>
                        <span class="muted" style="font-size:.9rem;font-weight:600;">· <?= e($cicloLabel) ?></span>
                    <?php endif; ?>
                </p>
                <p class="muted" style="font-size:.85rem;margin:0;">
                    <?= e($tipoLabel) ?>
                    <?php if (!empty($prod['descricao'])): ?>
                        · <?= e(mb_substr((string)$prod['descricao'], 0, 120)) ?><?= mb_strlen((string)$prod['descricao']) > 120 ? '…' : '' ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($prod['recursos_list'])): ?>
                    <ul style="margin:12px 0 0 18px;color:var(--muted);font-size:.9rem;line-height:1.6;">
                        <?php foreach (array_slice($prod['recursos_list'], 0, 6) as $rec): ?>
                            <li><?= e($rec) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <p class="muted" style="font-size:.9rem;margin-bottom:14px;">
                Olá, <strong><?= e($cli['nome'] ?? '') ?></strong>. Ao confirmar, geraremos sua fatura com Pix e boleto
                para este produto. O acesso só é liberado após o pagamento.
            </p>

            <form method="post" action="">
                <input type="hidden" name="confirmar_pedido" value="1">
                <input type="hidden" name="produto" value="<?= e($slugSafe) ?>">
                <input type="hidden" name="id" value="<?= $prodId ?>">

                <div class="field" style="background:#0b1220;border:1px solid var(--line);border-radius:12px;padding:14px 16px;">
                    <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;font-weight:600;color:var(--text);margin:0;">
                        <input type="checkbox" name="confirmo_compra" value="1" required
                               style="width:auto;margin-top:3px;flex-shrink:0;"
                               <?= !empty($_POST['confirmo_compra']) ? 'checked' : '' ?>>
                        <span>
                            Confirmo que desejo comprar o produto
                            <strong><?= e($prod['nome']) ?></strong>
                            no valor de
                            <strong><?= e(app_money_br(intval($prod['valor_centavos']))) ?></strong>
                            e estou ciente de que será gerada uma fatura para pagamento.
                        </span>
                    </label>
                </div>

                <div class="actions" style="margin-top:18px;flex-wrap:wrap;gap:10px;">
                    <button class="btn btn-primary" type="submit">
                        Confirmar e ir para o pagamento
                    </button>
                    <a class="btn btn-ghost" href="<?= e(app_url('precos.php')) ?>">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</main>
<?php
layout_footer();
