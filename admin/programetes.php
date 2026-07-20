<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$ok = $err = '';
$edit = null;

if (isset($_GET['del'])) {
    $pdo->prepare('DELETE FROM programetes WHERE id = ?')->execute([intval($_GET['del'])]);
    header('Location: programetes.php?ok=1');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $insercoes = trim((string)($_POST['insercoes'] ?? ''));
    $ordem = intval($_POST['ordem'] ?? 0);
    $ativo = !empty($_POST['ativo']) ? 1 : 0;
    if ($titulo === '') {
        $err = 'Título obrigatório.';
    } else {
        if ($id > 0) {
            $pdo->prepare('UPDATE programetes SET titulo=?, descricao=?, insercoes=?, ordem=?, ativo=? WHERE id=?')
                ->execute([$titulo, $descricao, $insercoes, $ordem, $ativo, $id]);
        } else {
            $pdo->prepare('INSERT INTO programetes (titulo,descricao,insercoes,ordem,ativo,created_at) VALUES (?,?,?,?,?,NOW())')
                ->execute([$titulo, $descricao, $insercoes, $ordem, $ativo]);
        }
        header('Location: programetes.php?ok=1');
        exit;
    }
}
if (isset($_GET['id']) || isset($_GET['novo'])) {
    if (!empty($_GET['id'])) {
        $st = $pdo->prepare('SELECT * FROM programetes WHERE id = ?');
        $st->execute([intval($_GET['id'])]);
        $edit = $st->fetch() ?: null;
    } else {
        $edit = ['id' => 0, 'titulo' => '', 'descricao' => '', 'insercoes' => '1x/dia', 'ordem' => 0, 'ativo' => 1];
    }
}
$lista = $pdo->query('SELECT * FROM programetes ORDER BY ordem, id DESC')->fetchAll();
if (isset($_GET['ok'])) $ok = 'Salvo.';
admin_header($edit ? 'Programete' : 'Programetes', 'programetes');
admin_flash($ok, $err);
if ($edit):
?>
<div class="card">
<form method="post">
    <input type="hidden" name="id" value="<?= intval($edit['id']) ?>">
    <div class="field"><label>Título</label><input name="titulo" required value="<?= htmlspecialchars($edit['titulo']) ?>"></div>
    <div class="field"><label>Descrição</label><textarea name="descricao" rows="3"><?= htmlspecialchars($edit['descricao']) ?></textarea></div>
    <div class="field-row">
        <div class="field"><label>Inserções</label><input name="insercoes" value="<?= htmlspecialchars($edit['insercoes']) ?>"></div>
        <div class="field"><label>Ordem</label><input type="number" name="ordem" value="<?= intval($edit['ordem']) ?>"></div>
    </div>
    <div class="field"><label><input type="checkbox" name="ativo" value="1" <?= $edit['ativo'] ? 'checked' : '' ?>> Ativo</label></div>
    <div class="actions"><button class="btn btn-primary">Salvar</button><a class="btn btn-secondary" href="programetes.php">Voltar</a></div>
</form>
</div>
<?php else: ?>
<div class="card">
    <a class="btn btn-primary" href="programetes.php?novo=1">+ Novo</a>
    <table style="margin-top:14px;">
        <thead><tr><th>Título</th><th>Inserções</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['titulo']) ?></td>
                <td><?= htmlspecialchars($r['insercoes']) ?></td>
                <td><?= $r['ativo'] ? 'Ativo' : 'Inativo' ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="programetes.php?id=<?= $r['id'] ?>">Editar</a>
                    <a class="btn btn-danger btn-small" href="programetes.php?del=<?= $r['id'] ?>" onclick="return confirm('Excluir?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif;
admin_footer();
