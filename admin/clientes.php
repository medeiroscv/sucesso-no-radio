<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$ok = $err = '';
$edit = null;
$tipos = app_conteudo_tipos_cliente();

if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    try {
        $pdo->prepare('DELETE FROM cliente_tipos WHERE cliente_id = ?')->execute([$id]);
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
    $cpf = app_only_digits((string)($_POST['cpf'] ?? ''));
    $radio = trim((string)($_POST['radio'] ?? ''));
    $cidade = trim((string)($_POST['cidade'] ?? ''));
    $observacoes = trim((string)($_POST['observacoes'] ?? ''));
    $ativo = !empty($_POST['ativo']) ? 1 : 0;
    $acessoTotal = !empty($_POST['acesso_total']) ? 1 : 0;
    $senha = (string)($_POST['senha'] ?? '');
    $liberados = $_POST['tipos'] ?? [];
    if (!is_array($liberados)) $liberados = [];

    if ($nome === '' || $email === '') {
        $err = 'Nome e e-mail são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'E-mail inválido.';
    } elseif ($cpf !== '' && strlen($cpf) !== 11 && strlen($cpf) !== 14) {
        $err = 'CPF/CNPJ inválido.';
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
                            'UPDATE clientes SET nome=?, email=?, whatsapp=?, telefone=?, cpf=?, radio=?, cidade=?, observacoes=?, ativo=?, senha_hash=?, updated_at=NOW() WHERE id=?'
                        )->execute([$nome, $email, $whatsapp, $telefone, $cpf, $radio, $cidade, $observacoes, $ativo, $hash, $id]);
                    } else {
                        $pdo->prepare(
                            'UPDATE clientes SET nome=?, email=?, whatsapp=?, telefone=?, cpf=?, radio=?, cidade=?, observacoes=?, ativo=?, updated_at=NOW() WHERE id=?'
                        )->execute([$nome, $email, $whatsapp, $telefone, $cpf, $radio, $cidade, $observacoes, $ativo, $id]);
                    }
                } else {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $pdo->prepare(
                        'INSERT INTO clientes (nome,email,senha_hash,whatsapp,telefone,cpf,radio,cidade,observacoes,ativo,created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,NOW())'
                    )->execute([$nome, $email, $hash, $whatsapp, $telefone, $cpf, $radio, $cidade, $observacoes, $ativo]);
                    $id = intval($pdo->lastInsertId());
                }

                // Liberação de categorias
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
        'cpf' => $cpf,
        'radio' => $radio,
        'cidade' => $cidade,
        'observacoes' => $observacoes,
        'ativo' => $ativo,
        'acesso_total' => $acessoTotal,
    ];
    $tiposLiberados = array_values(array_filter(array_map('strval', $liberados), 'app_conteudo_tipo_valido'));
}

if ($edit === null && (isset($_GET['id']) || isset($_GET['novo']))) {
    if (!empty($_GET['id'])) {
        $st = $pdo->prepare('SELECT * FROM clientes WHERE id = ?');
        $st->execute([intval($_GET['id'])]);
        $edit = $st->fetch() ?: null;
        $tiposLiberados = $edit ? cliente_tipos_liberados(intval($edit['id'])) : [];
    } else {
        $edit = [
            'id' => 0, 'nome' => '', 'email' => '', 'whatsapp' => '', 'telefone' => '', 'cpf' => '',
            'radio' => '', 'cidade' => '', 'observacoes' => '', 'ativo' => 1, 'acesso_total' => 0,
        ];
        $tiposLiberados = [];
    }
}

$lista = [];
try {
    $lista = $pdo->query('SELECT * FROM clientes ORDER BY ativo DESC, nome ASC')->fetchAll() ?: [];
} catch (Throwable $e) { /* ok */ }

// contagem de categorias liberadas por cliente
$qtdLib = [];
try {
    foreach ($pdo->query('SELECT cliente_id, COUNT(*) AS n FROM cliente_tipos GROUP BY cliente_id') as $r) {
        $qtdLib[intval($r['cliente_id'])] = intval($r['n']);
    }
} catch (Throwable $e) { /* ok */ }

if (isset($_GET['ok'])) $ok = 'Salvo com sucesso.';
if (isset($_GET['err']) && $err === '') {
    $err = (string)$_GET['err'];
}

admin_header($edit !== null ? (!empty($edit['id']) ? 'Editar cliente' : 'Novo cliente') : 'Clientes', 'clientes');
admin_flash($ok, $err);

if ($edit !== null):
    if (!isset($tiposLiberados)) $tiposLiberados = [];
    $acessoTotalChecked = !empty($edit['acesso_total']);
?>
<div class="actions" style="margin-bottom:12px;justify-content:space-between;width:100%;">
    <a class="btn btn-secondary btn-small" href="clientes.php">← Lista de clientes</a>
    <?php if (!empty($edit['id'])): ?>
        <a class="btn btn-primary btn-small" href="impersonate-cliente.php?id=<?= intval($edit['id']) ?>" target="_blank" rel="noopener"
           title="Abre a área do cliente como este cadastro (para suporte e testes)">
            👁 Acessar como cliente
        </a>
    <?php endif; ?>
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
            <div class="field"><label>CPF (obrigatório para boleto/Pix)</label><input name="cpf" value="<?= e($edit['cpf'] ?? '') ?>" placeholder="000.000.000-00"></div>
            <div class="field"><label>WhatsApp</label><input name="whatsapp" value="<?= e($edit['whatsapp'] ?? '') ?>" placeholder="(00) 00000-0000"></div>
        </div>
        <div class="field-row">
            <div class="field"><label>Telefone</label><input name="telefone" value="<?= e($edit['telefone'] ?? '') ?>"></div>
            <div class="field"></div>
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
            <p class="muted" style="margin-top:6px;font-size:.82rem;">Ativo sozinho não libera categorias — marque abaixo.</p>
        </div>

        <h3 style="margin:22px 0 10px;">Liberar categorias</h3>
        <p class="muted" style="margin-bottom:12px;">
            Liberar a <strong>categoria inteira</strong> (ex.: Programetes). Tudo que estiver nela fica acessível (arquivos e detalhes).
            Sem a categoria marcada, o cliente pode ver os <strong>nomes</strong>, mas não acessa os arquivos.
        </p>
        <div class="field" style="margin-bottom:14px;">
            <label>
                <input type="checkbox" name="acesso_total" value="1" id="acessoTotal" <?= $acessoTotalChecked ? 'checked' : '' ?>>
                <strong>Acesso total</strong> — todas as categorias liberadas
            </label>
        </div>
        <div id="listaLiberacoes" style="<?= $acessoTotalChecked ? 'opacity:.45;pointer-events:none;' : '' ?>">
            <div style="display:grid;gap:10px;">
                <?php foreach ($tipos as $tKey => $tMeta):
                    $checked = in_array($tKey, $tiposLiberados, true);
                ?>
                    <label style="display:flex;align-items:center;gap:10px;background:#0f172a;border:1px solid var(--line);border-radius:12px;padding:12px 14px;font-weight:600;color:var(--text);cursor:pointer;">
                        <input type="checkbox" name="tipos[]" value="<?= e($tKey) ?>" <?= $checked ? 'checked' : '' ?>>
                        <span><?= $tMeta['icon'] ?> <?= e($tMeta['label']) ?></span>
                        <span class="muted" style="font-weight:500;font-size:.85rem;margin-left:auto;"><?= e($tMeta['desc']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
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
            $lib = !empty($c['acesso_total']) ? 'Todas as categorias' : ((int)($qtdLib[intval($c['id'])] ?? 0) . ' categoria(s)');
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
                    <a class="btn btn-primary btn-small" href="impersonate-cliente.php?id=<?= intval($c['id']) ?>" target="_blank" rel="noopener" title="Entrar na área do cliente como este usuário">
                        Acessar como
                    </a>
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
