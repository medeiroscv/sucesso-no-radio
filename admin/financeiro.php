<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/asaas.php';

$pdo = app_pdo();
$ok = $err = '';
$edit = null;

if (isset($_GET['pagar'])) {
    $id = intval($_GET['pagar']);
    finance_marcar_paga($id, 'Marcado como pago no admin');
    header('Location: financeiro.php?id=' . $id . '&ok=1');
    exit;
}

if (isset($_GET['cancelar'])) {
    $pdo->prepare("UPDATE faturas SET status='cancelada', updated_at=NOW() WHERE id=?")->execute([intval($_GET['cancelar'])]);
    header('Location: financeiro.php?ok=1');
    exit;
}

if (isset($_GET['emitir'])) {
    $id = intval($_GET['emitir']);
    try {
        $r = finance_emitir_pagamento($id);
        $msg = 'Meios de pagamento gerados.';
        if (!empty($r['erros'])) $msg .= ' Avisos: ' . implode(' | ', $r['erros']);
        header('Location: financeiro.php?id=' . $id . '&ok=1&msg=' . rawurlencode($msg));
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
        $_GET['id'] = $id;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_fatura'])) {
    $clienteId = intval($_POST['cliente_id'] ?? 0);
    $desc = trim((string)($_POST['descricao'] ?? 'Mensalidade'));
    $valor = (float)str_replace(',', '.', preg_replace('/[^\d,.]/', '', (string)($_POST['valor'] ?? '0')));
    $venc = trim((string)($_POST['vencimento'] ?? ''));
    $cent = (int)round($valor * 100);
    if ($clienteId <= 0 || $cent <= 0 || $venc === '') {
        $err = 'Cliente, valor e vencimento são obrigatórios.';
    } else {
        $pdo->prepare(
            "INSERT INTO faturas (cliente_id, descricao, valor_centavos, vencimento, status, created_at)
             VALUES (?,?,?,?,'aberta',NOW())"
        )->execute([$clienteId, $desc !== '' ? $desc : 'Mensalidade', $cent, $venc]);
        $fid = intval($pdo->lastInsertId());

        $emitir = !empty($_POST['emitir_agora']);
        if ($emitir && $fid > 0) {
            try {
                $r = finance_emitir_pagamento($fid);
                $ok = 'Fatura criada.';
                if (!empty($r['erros'])) $ok .= ' ' . implode(' ', $r['erros']);
            } catch (Throwable $e) {
                $ok = 'Fatura criada, mas emissão Asaas falhou: ' . $e->getMessage();
            }
            header('Location: financeiro.php?id=' . $fid . '&ok=1');
            exit;
        }
        header('Location: financeiro.php?id=' . $fid . '&ok=1');
        exit;
    }
}

if (isset($_GET['id'])) {
    $st = $pdo->prepare(
        'SELECT f.*, c.nome AS cliente_nome, c.email AS cliente_email, c.cpf AS cliente_cpf, c.whatsapp AS cliente_whatsapp
         FROM faturas f INNER JOIN clientes c ON c.id = f.cliente_id WHERE f.id = ?'
    );
    $st->execute([intval($_GET['id'])]);
    $edit = $st->fetch() ?: null;
    if ($edit && in_array($edit['status'], ['aberta', 'vencida'], true)
        && (!empty($edit['pix_txid']) || !empty($edit['boleto_charge_id']))) {
        $edit = finance_sync_fatura($edit);
    }
}

$lista = [];
try {
    $lista = $pdo->query(
        "SELECT f.*, c.nome AS cliente_nome FROM faturas f
         INNER JOIN clientes c ON c.id = f.cliente_id
         ORDER BY
           CASE f.status WHEN 'vencida' THEN 0 WHEN 'aberta' THEN 1 WHEN 'paga' THEN 2 ELSE 3 END,
           f.vencimento DESC, f.id DESC
         LIMIT 200"
    )->fetchAll() ?: [];
} catch (Throwable $e) { /* ok */ }

$clientes = [];
try {
    $clientes = $pdo->query('SELECT id, nome, email, cpf FROM clientes WHERE ativo = 1 ORDER BY nome')->fetchAll() ?: [];
} catch (Throwable $e) { /* ok */ }

if (isset($_GET['ok'])) $ok = isset($_GET['msg']) ? (string)$_GET['msg'] : 'Salvo.';

admin_header('Financeiro', 'financeiro');
admin_flash($ok, $err);
?>
<div class="card">
    <div class="actions" style="justify-content:space-between;width:100%;align-items:center;">
        <div>
            <strong>Faturas e cobranças</strong>
            <div class="muted" style="margin-top:4px;font-size:.85rem;">
                Asaas: <?= asaas_configured() ? 'API Key ok' : 'pendente' ?> ·
                <?= e(asaas_ambiente_label()) ?>
            </div>
        </div>
        <a class="btn btn-secondary btn-small" href="configuracoes.php?sec=financeiro">⚙️ Configurar financeiro / Asaas</a>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom:12px;">Nova fatura</h3>
    <form method="post">
        <input type="hidden" name="nova_fatura" value="1">
        <div class="field-row">
            <div class="field">
                <label>Cliente *</label>
                <select name="cliente_id" required>
                    <option value="">—</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= intval($c['id']) ?>"><?= e($c['nome']) ?> · <?= e($c['email']) ?><?= empty($c['cpf']) ? ' (sem CPF)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Valor (R$) *</label>
                <input name="valor" required placeholder="199,90" value="<?= e($_POST['valor'] ?? '') ?>">
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Vencimento *</label>
                <input type="date" name="vencimento" required value="<?= e($_POST['vencimento'] ?? date('Y-m-d', strtotime('+5 days'))) ?>">
            </div>
            <div class="field">
                <label>Descrição</label>
                <input name="descricao" value="<?= e($_POST['descricao'] ?? 'Mensalidade Sucesso no Rádio') ?>">
            </div>
        </div>
        <div class="field">
            <label><input type="checkbox" name="emitir_agora" value="1" checked> Gerar Pix e boleto agora no Asaas</label>
        </div>
        <button class="btn btn-primary" type="submit">Criar fatura</button>
    </form>
</div>

<?php if ($edit):
    $meta = app_fatura_status_meta($edit['status'] ?? 'aberta');
?>
<div class="card">
    <div class="actions" style="margin-bottom:12px;">
        <a class="btn btn-secondary btn-small" href="financeiro.php">← Lista</a>
        <?php if (in_array($edit['status'], ['aberta', 'vencida'], true)): ?>
            <a class="btn btn-primary btn-small" href="financeiro.php?emitir=<?= intval($edit['id']) ?>">Gerar/atualizar Pix e boleto</a>
            <a class="btn btn-secondary btn-small" href="financeiro.php?pagar=<?= intval($edit['id']) ?>" onclick="return confirm('Marcar como paga?')">Marcar paga</a>
            <a class="btn btn-danger btn-small" href="financeiro.php?cancelar=<?= intval($edit['id']) ?>" onclick="return confirm('Cancelar fatura?')">Cancelar</a>
        <?php endif; ?>
    </div>
    <h3>Fatura #<?= intval($edit['id']) ?>
        <span style="font-size:.78rem;font-weight:800;padding:4px 10px;border-radius:999px;color:<?= e($meta['color']) ?>;background:<?= e($meta['bg']) ?>;"><?= e($meta['label']) ?></span>
    </h3>
    <p class="muted"><?= e($edit['cliente_nome']) ?> · <?= e($edit['cliente_email']) ?> · CPF <?= e($edit['cliente_cpf'] ?: '—') ?></p>
    <p><strong><?= e($edit['descricao']) ?></strong> · <?= e(app_money_br(intval($edit['valor_centavos']))) ?> · venc. <?= e($edit['vencimento']) ?></p>

    <?php if (!empty($edit['pix_copia_cola'])): ?>
        <div style="margin-top:14px;padding:12px;background:#0f172a;border-radius:12px;border:1px solid var(--line);">
            <strong>Pix</strong>
            <?php if (!empty($edit['pix_qrcode'])): ?>
                <?php
                $src = $edit['pix_qrcode'];
                if (!str_starts_with($src, 'data:') && !str_starts_with($src, 'http')) {
                    $src = 'data:image/png;base64,' . $src;
                }
                ?>
                <p style="margin:10px 0;"><img src="<?= e($src) ?>" alt="QR Pix" style="max-width:200px;background:#fff;padding:8px;border-radius:8px;"></p>
            <?php endif; ?>
            <p class="muted" style="word-break:break-all;font-size:.85rem;"><?= e($edit['pix_copia_cola']) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($edit['boleto_url']) || !empty($edit['boleto_barcode'])): ?>
        <div style="margin-top:14px;padding:12px;background:#0f172a;border-radius:12px;border:1px solid var(--line);">
            <strong>Boleto</strong>
            <?php if (!empty($edit['boleto_barcode'])): ?><p class="muted" style="margin-top:8px;">Linha digitável: <?= e($edit['boleto_barcode']) ?></p><?php endif; ?>
            <?php if (!empty($edit['boleto_url'])): ?><p style="margin-top:8px;"><a class="btn btn-secondary btn-small" href="<?= e($edit['boleto_url']) ?>" target="_blank">Abrir boleto</a></p><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-bottom:12px;">Faturas</h3>
    <table>
        <thead>
            <tr><th>ID</th><th>Cliente</th><th>Descrição</th><th>Valor</th><th>Venc.</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $f):
            $m = app_fatura_status_meta($f['status'] ?? '');
        ?>
            <tr>
                <td>#<?= intval($f['id']) ?></td>
                <td><?= e($f['cliente_nome']) ?></td>
                <td><?= e($f['descricao']) ?></td>
                <td><?= e(app_money_br(intval($f['valor_centavos']))) ?></td>
                <td><?= e($f['vencimento']) ?></td>
                <td><span style="font-size:.72rem;font-weight:800;padding:3px 8px;border-radius:999px;color:<?= e($m['color']) ?>;background:<?= e($m['bg']) ?>;"><?= e($m['label']) ?></span></td>
                <td class="actions"><a class="btn btn-secondary btn-small" href="financeiro.php?id=<?= intval($f['id']) ?>">Abrir</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?><tr><td colspan="7" class="muted">Nenhuma fatura ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
