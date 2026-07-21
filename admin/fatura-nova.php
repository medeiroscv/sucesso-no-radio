<?php
/**
 * Nova fatura avulsa (tela separada da listagem de Financeiro).
 */
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/asaas.php';

$pdo = app_pdo();
$ok = $err = '';

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
        $msg = 'Fatura #' . $fid . ' criada.';
        if ($emitir && $fid > 0) {
            try {
                $r = finance_emitir_pagamento($fid);
                if (!empty($r['erros'])) {
                    $msg .= ' Avisos: ' . implode(' | ', $r['erros']);
                } else {
                    $msg .= ' Pix/boleto gerados.';
                }
            } catch (Throwable $e) {
                $msg .= ' Emissão Asaas falhou: ' . $e->getMessage();
            }
        }
        header('Location: financeiro.php?id=' . $fid . '&ok=1&msg=' . rawurlencode($msg));
        exit;
    }
}

$clientes = [];
try {
    $clientes = $pdo->query('SELECT id, nome, email, cpf FROM clientes WHERE ativo = 1 ORDER BY nome')->fetchAll() ?: [];
} catch (Throwable $e) { /* ok */ }

$postCliente = intval($_POST['cliente_id'] ?? 0);
$postValor = (string)($_POST['valor'] ?? '');
$postVenc = (string)($_POST['vencimento'] ?? date('Y-m-d', strtotime('+5 days')));
$postDesc = (string)($_POST['descricao'] ?? 'Mensalidade Sucesso no Rádio');
$postEmitir = !isset($_POST['nova_fatura']) || !empty($_POST['emitir_agora']);

admin_header('Nova fatura', 'financeiro');
admin_flash($ok, $err);
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="financeiro.php">← Voltar às faturas</a>
    <a class="btn btn-secondary btn-small" href="assinaturas.php">Assinaturas / recorrência</a>
</div>

<div class="card">
    <h3 style="margin-bottom:8px;">Nova fatura avulsa</h3>
    <p class="muted" style="margin-bottom:16px;font-size:.9rem;">
        Cria uma fatura pontual para o cliente. Para cobranças recorrentes, use
        <a href="assinaturas.php" style="color:var(--accent);font-weight:700;">Assinaturas</a>.
    </p>
    <form method="post">
        <input type="hidden" name="nova_fatura" value="1">
        <div class="field-row">
            <div class="field">
                <label>Cliente *</label>
                <select name="cliente_id" required>
                    <option value="">— selecione —</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= intval($c['id']) ?>" <?= $postCliente === intval($c['id']) ? 'selected' : '' ?>>
                            <?= e($c['nome']) ?> · <?= e($c['email']) ?><?= empty($c['cpf']) ? ' (sem CPF)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Valor (R$) *</label>
                <input name="valor" required placeholder="199,90" value="<?= e($postValor) ?>">
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Vencimento *</label>
                <input type="date" name="vencimento" required value="<?= e($postVenc) ?>">
            </div>
            <div class="field">
                <label>Descrição</label>
                <input name="descricao" value="<?= e($postDesc) ?>">
            </div>
        </div>
        <div class="field">
            <label>
                <input type="checkbox" name="emitir_agora" value="1" <?= $postEmitir ? 'checked' : '' ?>>
                Gerar Pix e boleto agora no Asaas
            </label>
        </div>
        <div class="actions" style="margin-top:8px;">
            <button class="btn btn-primary" type="submit">Criar fatura</button>
            <a class="btn btn-secondary" href="financeiro.php">Cancelar</a>
        </div>
    </form>
</div>
<?php
admin_footer();
