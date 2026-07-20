<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$ok = $err = '';
$edit = null;
$tipos = app_conteudo_tipos_cliente();

if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    try {
        $pdo->prepare('DELETE FROM cliente_conteudos WHERE cliente_id = ?')->execute([$id]);
    } catch (Throwable $e) { /* ok */ }
    $pdo->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
    header('Location: clientes.php?ok=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    $radio = trim((string)($_POST['radio'] ?? ''));
    $cidade = trim((string)($_POST['cidade'] ?? ''));
    $observacoes = trim((string)($_POST['observacoes'] ?? ''));
    $ativo = !empty($_POST['ativo']) ? 1 : 0;
    $acessoTotal = !empty($_POST['acesso_total']) ? 1 : 0;
    $senha = (string)($_POST['senha'] ?? '');
    $liberados = $_POST['conteudos'] ?? [];
    if (!is_array($liberados)) $liberados = [];

    if ($nome === '' || $email === '') {
        $err = 'Nome e e-mail são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'E-mail inválido.';
    } elseif ($id <= 0 && $senha === '') {
        $err = 'Informe a senha inicial do cliente.';
    } elseif ($senha !== '' && strlen($senha) < 6) {
        $err = 'A senha precisa ter pelo menos 6 caracteres.';
    } else {
        try {
            $st = $pdo->prepare('SELECT id FROM clientes WHERE LOWER(email) = ? AND id <> ? LIMIT 1');
            $st->execute([$email, $id]);
            if ($st->fetch()) {
                $err = 'Já existe um cliente com este e-mail.';
            } else {
                if ($id > 0) {
                    if ($senha !== '') {
                        $hash = password_hash($senha, PASSWORD_DEFAULT);
                        $pdo->prepare(
                            'UPDATE clientes SET nome=?, email=?, whatsapp=?, telefone=?, radio=?, cidade=?, observacoes=?, ativo=?, senha_hash=?, updated_at=NOW() WHERE id=?'
                        )->execute([$nome, $email, $whatsapp, $telefone, $radio, $cidade, $observacoes, $ativo, $hash, $id]);
                    } else {
                        $pdo->prepare(
                            'UPDATE clientes SET nome=?, email=?, whatsapp=?, telefone=?, radio=?, cidade=?, observacoes=?, ativo=?, updated_at=NOW() WHERE id=?'
                        )->execute([$nome, $email, $whatsapp, $telefone, $radio, $cidade, $observacoes, $ativo, $id]);
                    }
                } else {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $pdo->prepare(
                        'INSERT INTO clientes (nome,email,senha_hash,whatsapp,telefone,radio,cidade,observacoes,ativo,created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,NOW())'
                    )->execute([$nome, $email, $hash, $whatsapp, $telefone, $radio, $cidade, $observacoes, $ativo]);
                    $id = intval($pdo->lastInsertId());
                }

                // Liberação de conteúdos
                cliente_salvar_liberacoes($id, $liberados, (bool)$acessoTotal);

                header('Location: clientes.php?id=' . $id . '&ok=1');
                exit;
            }
        } catch (Throwable $e) {
            $err = 'Erro ao salvar: ' . $e->getMessage();
        }
    }

    $edit = [
        'id' => $id,
        'nome' => $nome,
        'email' => $email,
        'whatsapp' => $whatsapp,
        'telefone' => $telefone,
        'radio' => $radio,
        'cidade' => $cidade,
        'observacoes' => $observacoes,
        'ativo' => $ativo,
        'acesso_total' => $acessoTotal,
    ];
    $idsLiberados = array_map('intval', $liberados);
}

if ($edit === null && (isset($_GET['id']) || isset($_GET['novo']))) {
    if (!empty($_GET['id'])) {
        $st = $pdo->prepare('SELECT * FROM clientes WHERE id = ?');
        $st->execute([intval($_GET['id'])]);
        $edit = $st->fetch() ?: null;
        $idsLiberados = $edit ? cliente_conteudo_ids(intval($edit['id'])) : [];
    } else {
        $edit = [
            'id' => 0, 'nome' => '', 'email' => '', 'whatsapp' => '', 'telefone' => '',
            'radio' => '', 'cidade' => '', 'observacoes' => '', 'ativo' => 1, 'acesso_total' => 0,
        ];
        $idsLiberados = [];
    }
}

// Só conteúdos da área do cliente (não demonstrativos públicos)
$todosConteudos = [];
try {
    $todosConteudos = $pdo->query(
        "SELECT id, titulo, tipo, ativo FROM conteudos WHERE area = 'conteudo' ORDER BY tipo, ordem, titulo"
    )->fetchAll() ?: [];
} catch (Throwable $e) {
    $todosConteudos = [];
}
$porTipo = [];
foreach ($todosConteudos as $c) {
    $porTipo[$c['tipo']][] = $c;
}

$lista = [];
try {
    $lista = $pdo->query('SELECT * FROM clientes ORDER BY ativo DESC, nome ASC')->fetchAll() ?: [];
} catch (Throwable $e) { /* ok */ }

// contagem de liberados por cliente (lista)
$qtdLib = [];
try {
    foreach ($pdo->query('SELECT cliente_id, COUNT(*) AS n FROM cliente_conteudos GROUP BY cliente_id') as $r) {
        $qtdLib[intval($r['cliente_id'])] = intval($r['n']);
    }
} catch (Throwable $e) { /* ok */ }

if (isset($_GET['ok'])) $ok = 'Salvo com sucesso.';

admin_header($edit !== null ? (!empty($edit['id']) ? 'Editar cliente' : 'Novo cliente') : 'Clientes', 'clientes');
admin_flash($ok, $err);

if ($edit !== null):
    if (!isset($idsLiberados)) $idsLiberados = [];
    $acessoTotalChecked = !empty($edit['acesso_total']);
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="clientes.php">← Lista de clientes</a>
</div>
<div class="card">
    <form method="post">
        <input type="hidden" name="id" value="<?= intval($edit['id']) ?>">
        <h3 style="margin-bottom:12px;">Dados do cliente</h3>
        <div class="field-row">
            <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($edit['nome'] ?? '') ?>"></div>
            <div class="field"><label>E-mail (login) *</label><input type="email" name="email" required value="<?= e($edit['email'] ?? '') ?>"></div>
        </div>
        <div class="field-row">
            <div class="field"><label>WhatsApp</label><input name="whatsapp" value="<?= e($edit['whatsapp'] ?? '') ?>" placeholder="(00) 00000-0000"></div>
            <div class="field"><label>Telefone</label><input name="telefone" value="<?= e($edit['telefone'] ?? '') ?>"></div>
        </div>
        <div class="field-row">
            <div class="field"><label>Rádio / empresa</label><input name="radio" value="<?= e($edit['radio'] ?? '') ?>"></div>
            <div class="field"><label>Cidade</label><input name="cidade" value="<?= e($edit['cidade'] ?? '') ?>"></div>
        </div>
        <div class="field">
            <label><?= !empty($edit['id']) ? 'Nova senha (deixe em branco para manter)' : 'Senha inicial *' ?></label>
            <input type="password" name="senha" autocomplete="new-password" <?= empty($edit['id']) ? 'required' : '' ?> minlength="6">
        </div>
        <div class="field"><label>Observações internas</label><textarea name="observacoes" rows="2"><?= e($edit['observacoes'] ?? '') ?></textarea></div>
        <div class="field">
            <label><input type="checkbox" name="ativo" value="1" <?= !empty($edit['ativo']) ? 'checked' : '' ?>> Cliente ativo (pode logar)</label>
            <p class="muted" style="margin-top:6px;font-size:.82rem;">Ativo sozinho não libera conteúdos nem textos — use a liberação abaixo.</p>
        </div>

        <h3 style="margin:22px 0 10px;">Liberar conteúdos (produto)</h3>
        <p class="muted" style="margin-bottom:12px;">
            Cliente <strong>ativo</strong> só pode logar. Para ver conteúdos e enviar textos, é preciso liberar manualmente abaixo
            (ou marcar acesso total). Demonstrativos do site público não entram aqui.
        </p>
        <div class="field" style="margin-bottom:14px;">
            <label>
                <input type="checkbox" name="acesso_total" value="1" id="acessoTotal" <?= $acessoTotalChecked ? 'checked' : '' ?>>
                <strong>Acesso total</strong> — liberar todos os conteúdos do produto
            </label>
        </div>
        <div id="listaLiberacoes" style="<?= $acessoTotalChecked ? 'opacity:.45;pointer-events:none;' : '' ?>">
            <?php if (!$todosConteudos): ?>
                <p class="muted">Nenhum conteúdo cadastrado ainda. Cadastre em Conteúdos primeiro.</p>
            <?php else: ?>
                <?php foreach ($tipos as $tKey => $tMeta):
                    $itens = $porTipo[$tKey] ?? [];
                    if (!$itens) continue;
                ?>
                    <div style="margin-bottom:16px;background:#0f172a;border:1px solid var(--line);border-radius:12px;padding:12px 14px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:8px;flex-wrap:wrap;">
                            <strong><?= $tMeta['icon'] ?> <?= e($tMeta['label']) ?></strong>
                            <button type="button" class="btn btn-secondary btn-small" onclick="toggleGrupo(this)">Marcar todos</button>
                        </div>
                        <div style="display:grid;gap:6px;" class="grupo-checks">
                            <?php foreach ($itens as $item):
                                $cid = intval($item['id']);
                                $checked = in_array($cid, $idsLiberados, true);
                            ?>
                                <label style="font-weight:500;color:var(--text);display:flex;gap:8px;align-items:center;">
                                    <input type="checkbox" name="conteudos[]" value="<?= $cid ?>" <?= $checked ? 'checked' : '' ?>>
                                    <?= e($item['titulo']) ?>
                                    <?php if (empty($item['ativo'])): ?><span class="muted">(inativo)</span><?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="actions" style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">Salvar</button>
            <a class="btn btn-secondary" href="clientes.php">Cancelar</a>
        </div>
    </form>
</div>
<script>
(function () {
    var total = document.getElementById('acessoTotal');
    var box = document.getElementById('listaLiberacoes');
    if (total && box) {
        total.addEventListener('change', function () {
            if (total.checked) {
                box.style.opacity = '.45';
                box.style.pointerEvents = 'none';
            } else {
                box.style.opacity = '1';
                box.style.pointerEvents = '';
            }
        });
    }
    window.toggleGrupo = function (btn) {
        var group = btn.closest('div').parentElement.querySelector('.grupo-checks');
        if (!group) return;
        var checks = group.querySelectorAll('input[type=checkbox]');
        var allOn = true;
        checks.forEach(function (c) { if (!c.checked) allOn = false; });
        checks.forEach(function (c) { c.checked = !allOn; });
        btn.textContent = allOn ? 'Marcar todos' : 'Desmarcar todos';
    };
})();
</script>
<?php else: ?>
<div class="card">
    <div class="actions" style="margin-bottom:14px;justify-content:space-between;width:100%;">
        <p class="muted" style="margin:0;">Cadastre clientes e libere os conteúdos que cada um pode acessar.</p>
        <a class="btn btn-primary" href="clientes.php?novo=1">+ Novo cliente</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>WhatsApp</th>
                <th>Rádio</th>
                <th>Conteúdos</th>
                <th>Status</th>
                <th>Último acesso</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $c):
            $lib = !empty($c['acesso_total']) ? 'Todos' : ((int)($qtdLib[intval($c['id'])] ?? 0) . ' liberado(s)');
        ?>
            <tr>
                <td><strong><?= e($c['nome']) ?></strong></td>
                <td><?= e($c['email']) ?></td>
                <td><?= e($c['whatsapp'] ?: '—') ?></td>
                <td><?= e($c['radio'] ?: '—') ?></td>
                <td><?= e($lib) ?></td>
                <td><?= !empty($c['ativo']) ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-off">Inativo</span>' ?></td>
                <td class="muted"><?= $c['last_login'] ? e(substr((string)$c['last_login'], 0, 16)) : '—' ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="clientes.php?id=<?= intval($c['id']) ?>">Editar / Liberar</a>
                    <a class="btn btn-danger btn-small" href="clientes.php?del=<?= intval($c['id']) ?>" onclick="return confirm('Excluir este cliente?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?>
            <tr><td colspan="8" class="muted">Nenhum cliente ainda. Clique em <strong>+ Novo cliente</strong>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
endif;
admin_footer();
