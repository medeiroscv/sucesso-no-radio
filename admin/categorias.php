<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$ok = $err = '';
if (isset($_GET['del'])) {
    $pdo->prepare('DELETE FROM categorias WHERE id = ?')->execute([intval($_GET['del'])]);
    header('Location: categorias.php?ok=1');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $nome = trim((string)($_POST['nome'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    if ($slug === '') $slug = app_slug($nome);
    $tipo = trim((string)($_POST['tipo'] ?? 'programa'));
    $ordem = intval($_POST['ordem'] ?? 0);
    $ativo = !empty($_POST['ativo']) ? 1 : 0;
    if ($nome === '') {
        $err = 'Nome obrigatório';
    } else {
        if ($id > 0) {
            $pdo->prepare('UPDATE categorias SET nome=?, slug=?, tipo=?, ordem=?, ativo=? WHERE id=?')
                ->execute([$nome, $slug, $tipo, $ordem, $ativo, $id]);
        } else {
            $pdo->prepare('INSERT INTO categorias (nome,slug,tipo,ordem,ativo,created_at) VALUES (?,?,?,?,?,NOW())')
                ->execute([$nome, $slug, $tipo, $ordem, $ativo]);
        }
        header('Location: categorias.php?ok=1');
        exit;
    }
}
$edit = null;
if (isset($_GET['id'])) {
    $st = $pdo->prepare('SELECT * FROM categorias WHERE id = ?');
    $st->execute([intval($_GET['id'])]);
    $edit = $st->fetch() ?: null;
}
$lista = $pdo->query('SELECT * FROM categorias ORDER BY ordem, nome')->fetchAll();
if (isset($_GET['ok'])) $ok = 'Salvo.';
admin_header('Categorias', 'categorias');
admin_flash($ok, $err);
?>
<div class="card">
    <h3 style="margin-bottom:12px;"><?= $edit ? 'Editar' : 'Nova categoria' ?></h3>
    <form method="post">
        <input type="hidden" name="id" value="<?= intval($edit['id'] ?? 0) ?>">
        <div class="field-row">
            <div class="field"><label>Nome</label><input name="nome" required value="<?= htmlspecialchars($edit['nome'] ?? '') ?>"></div>
            <div class="field"><label>Slug</label><input name="slug" value="<?= htmlspecialchars($edit['slug'] ?? '') ?>"></div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Tipo</label>
                <select name="tipo">
                    <?php foreach (['programa','jornalismo','programete','campanha'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($edit['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Ordem</label><input type="number" name="ordem" value="<?= intval($edit['ordem'] ?? 0) ?>"></div>
        </div>
        <div class="field"><label><input type="checkbox" name="ativo" value="1" <?= ($edit['ativo'] ?? 1) ? 'checked' : '' ?>> Ativa</label></div>
        <button class="btn btn-primary">Salvar</button>
        <?php if ($edit): ?><a class="btn btn-secondary" href="categorias.php">Nova</a><?php endif; ?>
    </form>
</div>
<div class="card">
    <table>
        <thead><tr><th>Nome</th><th>Slug</th><th>Tipo</th><th>Ordem</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['nome']) ?></td>
                <td class="muted"><?= htmlspecialchars($c['slug']) ?></td>
                <td><?= htmlspecialchars($c['tipo']) ?></td>
                <td><?= intval($c['ordem']) ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="categorias.php?id=<?= $c['id'] ?>">Editar</a>
                    <a class="btn btn-danger btn-small" href="categorias.php?del=<?= $c['id'] ?>" onclick="return confirm('Excluir?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
