<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/asaas.php';

$pdo = app_pdo();
$ok = $err = '';
$edit = null;

// Rodar motor de billing manualmente
if (isset($_GET['run_billing'])) {
    $stats = billing_run(true);
    $ok = 'Billing executado: ' . intval($stats['faturas_novas']) . ' fatura(s) nova(s), '
        . intval($stats['cobrancas']) . ' cobrança(s). '
        . (empty($stats['erros']) ? '' : 'Avisos: ' . implode(' | ', array_slice($stats['erros'], 0, 3)));
}

if (isset($_GET['cancelar'])) {
    $id = intval($_GET['cancelar']);
    $pdo->prepare("UPDATE assinaturas SET status='cancelada', cancelada_em=NOW(), updated_at=NOW() WHERE id=?")
        ->execute([$id]);
    header('Location: assinaturas.php?ok=1&msg=' . rawurlencode('Assinatura cancelada.'));
    exit;
}

if (isset($_GET['suspender'])) {
    $id = intval($_GET['suspender']);
    $pdo->prepare("UPDATE assinaturas SET status='suspensa', updated_at=NOW() WHERE id=?")->execute([$id]);
    header('Location: assinaturas.php?id=' . $id . '&ok=1');
    exit;
}

if (isset($_GET['ativar'])) {
    $id = intval($_GET['ativar']);
    $pdo->prepare("UPDATE assinaturas SET status='ativa', cancelada_em=NULL, updated_at=NOW() WHERE id=?")->execute([$id]);
    header('Location: assinaturas.php?id=' . $id . '&ok=1');
    exit;
}

if (isset($_GET['gerar_agora'])) {
    $id = intval($_GET['gerar_agora']);
    $stats = billing_run(false, $id);
    header('Location: assinaturas.php?id=' . $id . '&ok=1&msg=' . rawurlencode(
        'Processado. Faturas novas: ' . intval($stats['faturas_novas'])
    ));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_assinatura'])) {
    $clienteId = intval($_POST['cliente_id'] ?? 0);
    $produtoId = intval($_POST['produto_id'] ?? 0);
    $dia = max(1, min(28, intval($_POST['dia_vencimento'] ?? 10)));
    $valorRaw = trim((string)($_POST['valor'] ?? ''));
    $opts = [
        'dia_vencimento' => $dia,
        'observacao' => trim((string)($_POST['observacao'] ?? '')),
        'data_inicio' => trim((string)($_POST['data_inicio'] ?? date('Y-m-d'))) ?: date('Y-m-d'),
    ];
    if ($valorRaw !== '') {
        $valor = (float)str_replace(',', '.', preg_replace('/[^\d,.]/', '', $valorRaw));
        $opts['valor_centavos'] = (int)round($valor * 100);
    }
    if (!empty($_POST['proximo_vencimento'])) {
        $opts['proximo_vencimento'] = trim((string)$_POST['proximo_vencimento']);
    }
    $r = billing_criar_assinatura($clienteId, $produtoId, $opts);
    if (!empty($r['ok'])) {
        header('Location: assinaturas.php?id=' . intval($r['id']) . '&ok=1&msg=' . rawurlencode($r['message']));
        exit;
    }
    $err = $r['message'] ?? 'Falha ao criar assinatura.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_assinatura'])) {
    $id = intval($_POST['id'] ?? 0);
    $dia = max(1, min(28, intval($_POST['dia_vencimento'] ?? 10)));
    $prox = trim((string)($_POST['proximo_vencimento'] ?? ''));
    $valor = (float)str_replace(',', '.', preg_replace('/[^\d,.]/', '', (string)($_POST['valor'] ?? '0')));
    $cent = (int)round($valor * 100);
    $obs = trim((string)($_POST['observacao'] ?? ''));
    $status = (string)($_POST['status'] ?? 'ativa');
    if (!in_array($status, ['ativa', 'suspensa', 'cancelada', 'encerrada'], true)) $status = 'ativa';
    if ($id > 0 && $prox !== '' && $cent > 0) {
        $pdo->prepare(
            'UPDATE assinaturas SET dia_vencimento=?, proximo_vencimento=?, valor_centavos=?, observacao=?, status=?,
             cancelada_em=CASE WHEN ? = \'cancelada\' THEN COALESCE(cancelada_em, NOW()) ELSE cancelada_em END,
             updated_at=NOW() WHERE id=?'
        )->execute([$dia, $prox, $cent, $obs, $status, $status, $id]);
        header('Location: assinaturas.php?id=' . $id . '&ok=1');
        exit;
    }
    $err = 'Dados inválidos para salvar.';
}

if (isset($_GET['id'])) {
    $edit = billing_assinatura_by_id(intval($_GET['id']));
}

$lista = billing_assinaturas_lista(null, 300);
$produtos = billing_produtos_lista(true, false);
$clientes = [];
try {
    $clientes = $pdo->query('SELECT id, nome, email FROM clientes WHERE ativo = 1 ORDER BY nome')->fetchAll() ?: [];
} catch (Throwable $e) { /* ok */ }

if (isset($_GET['ok'])) $ok = isset($_GET['msg']) ? (string)$_GET['msg'] : 'Salvo.';

// Faturas da assinatura
$faturasAss = [];
if ($edit) {
    try {
        $st = $pdo->prepare(
            'SELECT * FROM faturas WHERE assinatura_id = ? ORDER BY vencimento DESC, id DESC LIMIT 50'
        );
        $st->execute([intval($edit['id'])]);
        $faturasAss = $st->fetchAll() ?: [];
    } catch (Throwable $e) { /* ok */ }
}

admin_header($edit ? 'Assinatura #' . intval($edit['id']) : 'Assinaturas', 'assinaturas');
admin_flash($ok, $err);
?>

<div class="card">
    <div class="actions" style="justify-content:space-between;width:100%;align-items:center;">
        <div>
            <strong>Assinaturas e recorrência</strong>
            <div class="muted" style="margin-top:4px;font-size:.85rem;">
                O sistema gera faturas futuras e cobra conforme a agenda de cada produto.
            </div>
        </div>
        <div class="actions">
            <a class="btn btn-secondary btn-small" href="produtos.php">Produtos / preços</a>
            <a class="btn btn-primary btn-small" href="assinaturas.php?run_billing=1"
               onclick="return confirm('Executar geração de faturas e cobranças agora?');">▶ Rodar billing agora</a>
        </div>
    </div>
</div>

<?php if ($edit):
    $meta = billing_status_assinatura_meta($edit['status'] ?? 'ativa');
    $valorBr = number_format(intval($edit['valor_centavos']) / 100, 2, ',', '.');
?>
<div class="actions" style="margin-bottom:12px;flex-wrap:wrap;">
    <a class="btn btn-secondary btn-small" href="assinaturas.php">← Lista</a>
    <a class="btn btn-primary btn-small" href="assinaturas.php?gerar_agora=<?= intval($edit['id']) ?>">Gerar fatura / processar agora</a>
    <?php if (($edit['status'] ?? '') === 'ativa'): ?>
        <a class="btn btn-secondary btn-small" href="assinaturas.php?suspender=<?= intval($edit['id']) ?>">Suspender</a>
        <a class="btn btn-danger btn-small" href="assinaturas.php?cancelar=<?= intval($edit['id']) ?>" onclick="return confirm('Cancelar assinatura?');">Cancelar</a>
    <?php elseif (in_array($edit['status'], ['suspensa', 'cancelada'], true)): ?>
        <a class="btn btn-primary btn-small" href="assinaturas.php?ativar=<?= intval($edit['id']) ?>">Reativar</a>
    <?php endif; ?>
    <a class="btn btn-secondary btn-small" href="clientes.php?id=<?= intval($edit['cliente_id']) ?>">Ver cliente</a>
</div>

<div class="card">
    <h3>Assinatura #<?= intval($edit['id']) ?>
        <span style="font-size:.78rem;font-weight:800;padding:4px 10px;border-radius:999px;color:<?= e($meta['color']) ?>;background:<?= e($meta['bg']) ?>;"><?= e($meta['label']) ?></span>
    </h3>
    <p class="muted" style="margin:8px 0;">
        <?= e($edit['cliente_nome']) ?> · <?= e($edit['cliente_email']) ?><br>
        Produto: <strong><?= e($edit['produto_nome']) ?></strong>
        (<?= e($edit['produto_tipo'] ?? '') ?> · <?= e($edit['produto_ciclo'] ?? '') ?>)
    </p>

    <form method="post" style="margin-top:14px;">
        <input type="hidden" name="editar_assinatura" value="1">
        <input type="hidden" name="id" value="<?= intval($edit['id']) ?>">
        <div class="field-row">
            <div class="field">
                <label>Valor (R$)</label>
                <input name="valor" value="<?= e($valorBr) ?>">
            </div>
            <div class="field">
                <label>Dia de vencimento (1–28)</label>
                <input type="number" name="dia_vencimento" min="1" max="28" value="<?= intval($edit['dia_vencimento']) ?>">
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Próximo vencimento</label>
                <input type="date" name="proximo_vencimento" value="<?= e((string)$edit['proximo_vencimento']) ?>">
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <?php foreach (billing_status_assinatura_meta() as $k => $m): ?>
                        <option value="<?= e($k) ?>" <?= ($edit['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field"><label>Observação</label><textarea name="observacao" rows="2"><?= e($edit['observacao'] ?? '') ?></textarea></div>
        <button class="btn btn-primary" type="submit">Salvar assinatura</button>
    </form>
</div>

<div class="card">
    <h3 style="margin-bottom:12px;">Faturas desta assinatura</h3>
    <table>
        <thead><tr><th>ID</th><th>Descrição</th><th>Valor</th><th>Venc.</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($faturasAss as $f):
            $fm = app_fatura_status_meta($f['status'] ?? '');
        ?>
            <tr>
                <td>#<?= intval($f['id']) ?></td>
                <td><?= e($f['descricao']) ?></td>
                <td><?= e(app_money_br(intval($f['valor_centavos']))) ?></td>
                <td><?= e($f['vencimento']) ?></td>
                <td><span style="font-size:.72rem;font-weight:800;padding:3px 8px;border-radius:999px;color:<?= e($fm['color']) ?>;background:<?= e($fm['bg']) ?>;"><?= e($fm['label']) ?></span></td>
                <td><a class="btn btn-secondary btn-small" href="financeiro.php?id=<?= intval($f['id']) ?>">Abrir</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$faturasAss): ?><tr><td colspan="6" class="muted">Nenhuma fatura gerada ainda. Use “Gerar fatura / processar agora”.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php else: ?>

<div class="card">
    <h3 style="margin-bottom:12px;">Nova assinatura</h3>
    <form method="post">
        <input type="hidden" name="nova_assinatura" value="1">
        <div class="field-row">
            <div class="field">
                <label>Cliente *</label>
                <select name="cliente_id" required>
                    <option value="">—</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= intval($c['id']) ?>"><?= e($c['nome']) ?> · <?= e($c['email']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Produto / plano *</label>
                <select name="produto_id" required>
                    <option value="">—</option>
                    <?php foreach ($produtos as $p): ?>
                        <option value="<?= intval($p['id']) ?>">
                            <?= e($p['nome']) ?> — <?= e(app_money_br(intval($p['valor_centavos']))) ?>
                            (<?= e($p['ciclo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Dia de vencimento (1–28)</label>
                <input type="number" name="dia_vencimento" min="1" max="28" value="10">
            </div>
            <div class="field">
                <label>Valor customizado (opcional)</label>
                <input name="valor" placeholder="Deixe vazio para usar o do produto">
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Data início</label>
                <input type="date" name="data_inicio" value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="field">
                <label>1º vencimento (opcional)</label>
                <input type="date" name="proximo_vencimento">
            </div>
        </div>
        <div class="field"><label>Observação</label><input name="observacao"></div>
        <button class="btn btn-primary" type="submit">Criar assinatura</button>
    </form>
</div>

<div class="card">
    <h3 style="margin-bottom:12px;">Assinaturas</h3>
    <table>
        <thead>
            <tr><th>ID</th><th>Cliente</th><th>Produto</th><th>Valor</th><th>Próx. venc.</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $a):
            $m = billing_status_assinatura_meta($a['status'] ?? '');
        ?>
            <tr>
                <td>#<?= intval($a['id']) ?></td>
                <td><?= e($a['cliente_nome']) ?></td>
                <td><?= e($a['produto_nome']) ?></td>
                <td><?= e(app_money_br(intval($a['valor_centavos']))) ?></td>
                <td><?= e($a['proximo_vencimento']) ?></td>
                <td><span style="font-size:.72rem;font-weight:800;padding:3px 8px;border-radius:999px;color:<?= e($m['color']) ?>;background:<?= e($m['bg']) ?>;"><?= e($m['label']) ?></span></td>
                <td class="actions"><a class="btn btn-secondary btn-small" href="assinaturas.php?id=<?= intval($a['id']) ?>">Abrir</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?><tr><td colspan="7" class="muted">Nenhuma assinatura. Crie um produto e vincule a um cliente.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3 style="margin-bottom:8px;">Automação (cron)</h3>
    <p class="muted" style="font-size:.88rem;margin-bottom:10px;">
        Chame diariamente (EasyPanel Cron / curl) para gerar faturas e cobranças sem depender de alguém abrir o admin:
    </p>
    <pre style="background:#0b1220;border:1px solid var(--line);border-radius:10px;padding:12px;font-size:.8rem;overflow:auto;">curl -s "https://SEU-DOMINIO/api/billing-cron.php?token=SEU_TOKEN"</pre>
    <p class="muted" style="font-size:.82rem;margin-top:8px;">
        Defina o token em Configurações do ambiente: <code>BILLING_CRON_TOKEN</code> ou setting <code>billing_cron_token</code>.
        O admin também roda o billing de forma leve ao acessar esta página (throttle).
    </p>
</div>
<?php
// throttle run when listing
billing_run_throttled(600);
endif;

admin_footer();
