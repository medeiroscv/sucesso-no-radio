<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$ok = $err = '';
$edit = null;

if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
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
    $senha = (string)($_POST['senha'] ?? '');

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
            // e-mail único
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
    ];
}

if ($edit === null && (isset($_GET['id']) || isset($_GET['novo']))) {
    if (!empty($_GET['id'])) {
        $st = $pdo->prepare('SELECT * FROM clientes WHERE id = ?');
        $st->execute([intval($_GET['id'])]);
        $edit = $st->fetch() ?: null;
    } else {
        $edit = [
            'id' => 0, 'nome' => '', 'email' => '', 'whatsapp' => '', 'telefone' => '',
            'radio' => '', 'cidade' => '', 'observacoes' => '', 'ativo' => 1,
        ];
    }
}

$lista = [];
try {
    $lista = $pdo->query('SELECT * FROM clientes ORDER BY ativo DESC, nome ASC')->fetchAll() ?: [];
} catch (Throwable $e) { /* ok */ }

if (isset($_GET['ok'])) $ok = 'Salvo com sucesso.';

admin_header($edit !== null ? (!empty($edit['id']) ? 'Editar cliente' : 'Novo cliente') : 'Clientes', 'clientes');
admin_flash($ok, $err);

if ($edit !== null):
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="clientes.php">← Lista de clientes</a>
</div>
<div class="card">
    <form method="post">
        <input type="hidden" name="id" value="<?= intval($edit['id']) ?>">
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
        <div class="field"><label>Observações internas</label><textarea name="observacoes" rows="3"><?= e($edit['observacoes'] ?? '') ?></textarea></div>
        <div class="field"><label><input type="checkbox" name="ativo" value="1" <?= !empty($edit['ativo']) ? 'checked' : '' ?>> Cliente ativo (pode logar)</label></div>
        <div class="actions">
            <button class="btn btn-primary" type="submit">Salvar</button>
            <a class="btn btn-secondary" href="clientes.php">Cancelar</a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="card">
    <div class="actions" style="margin-bottom:14px;justify-content:space-between;width:100%;">
        <p class="muted" style="margin:0;">Cadastre os clientes que terão acesso à área restrita (conteúdos e envio de texto).</p>
        <a class="btn btn-primary" href="clientes.php?novo=1">+ Novo cliente</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>WhatsApp</th>
                <th>Rádio</th>
                <th>Status</th>
                <th>Último acesso</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $c): ?>
            <tr>
                <td><strong><?= e($c['nome']) ?></strong></td>
                <td><?= e($c['email']) ?></td>
                <td><?= e($c['whatsapp'] ?: '—') ?></td>
                <td><?= e($c['radio'] ?: '—') ?></td>
                <td><?= !empty($c['ativo']) ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-off">Inativo</span>' ?></td>
                <td class="muted"><?= $c['last_login'] ? e(substr((string)$c['last_login'], 0, 16)) : '—' ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="clientes.php?id=<?= intval($c['id']) ?>">Editar</a>
                    <a class="btn btn-danger btn-small" href="clientes.php?del=<?= intval($c['id']) ?>" onclick="return confirm('Excluir este cliente? Textos enviados permanecem com o histórico de contato.')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?>
            <tr><td colspan="7" class="muted">Nenhum cliente ainda. Clique em <strong>+ Novo cliente</strong>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
endif;
admin_footer();
