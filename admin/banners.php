<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$ok = $err = '';
$edit = null;
if (isset($_GET['del'])) {
    $pdo->prepare('DELETE FROM banners WHERE id = ?')->execute([intval($_GET['del'])]);
    header('Location: banners.php?ok=1');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $subtitulo = trim((string)($_POST['subtitulo'] ?? ''));
    $link = trim((string)($_POST['link'] ?? ''));
    $botao = trim((string)($_POST['botao_texto'] ?? 'Saiba mais'));
    $ordem = intval($_POST['ordem'] ?? 0);
    $ativo = !empty($_POST['ativo']) ? 1 : 0;
    $imgAtual = trim((string)($_POST['imagem_atual'] ?? ''));
    $imgNova = admin_upload('imagem', 'banners');
    if ($imgNova !== '') {
        if ($imgAtual !== '' && $imgAtual !== $imgNova) {
            admin_delete_local_upload($imgAtual);
        }
        $imagem = $imgNova;
    } else {
        $imagem = $imgAtual;
    }
    if ($id > 0) {
        $pdo->prepare('UPDATE banners SET titulo=?, subtitulo=?, imagem=?, link=?, botao_texto=?, ativo=?, ordem=? WHERE id=?')
            ->execute([$titulo, $subtitulo, $imagem, $link, $botao, $ativo, $ordem, $id]);
    } else {
        $pdo->prepare('INSERT INTO banners (titulo,subtitulo,imagem,link,botao_texto,ativo,ordem,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
            ->execute([$titulo, $subtitulo, $imagem, $link, $botao, $ativo, $ordem]);
    }
    header('Location: banners.php?ok=1');
    exit;
}
if (isset($_GET['id']) || isset($_GET['novo'])) {
    if (!empty($_GET['id'])) {
        $st = $pdo->prepare('SELECT * FROM banners WHERE id = ?');
        $st->execute([intval($_GET['id'])]);
        $edit = $st->fetch() ?: null;
    } else {
        $edit = ['id' => 0, 'titulo' => '', 'subtitulo' => '', 'imagem' => '', 'link' => '', 'botao_texto' => 'Saiba mais', 'ativo' => 1, 'ordem' => 0];
    }
}
$lista = $pdo->query('SELECT * FROM banners ORDER BY ordem, id DESC')->fetchAll();
if (isset($_GET['ok'])) $ok = 'Salvo.';
admin_header($edit ? 'Banner' : 'Banners', 'banners');
admin_flash($ok, $err);
if ($edit):
?>
<div class="card">
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= intval($edit['id']) ?>">
    <input type="hidden" name="imagem_atual" value="<?= htmlspecialchars($edit['imagem'] ?? '') ?>">
    <div class="field"><label>Título</label><input name="titulo" value="<?= htmlspecialchars($edit['titulo']) ?>"></div>
    <div class="field"><label>Subtítulo</label><textarea name="subtitulo" rows="2"><?= htmlspecialchars($edit['subtitulo']) ?></textarea></div>
    <div class="field"><label>Link do botão</label><input name="link" value="<?= htmlspecialchars($edit['link']) ?>" placeholder="https:// ou vazio = WhatsApp"></div>
    <div class="field-row">
        <div class="field"><label>Texto do botão</label><input name="botao_texto" value="<?= htmlspecialchars($edit['botao_texto']) ?>"></div>
        <div class="field"><label>Ordem</label><input type="number" name="ordem" value="<?= intval($edit['ordem']) ?>"></div>
    </div>
    <div class="field">
        <label>Imagem</label>
        <p class="muted" style="margin:4px 0 8px;">Convertida para JPEG e redimensionada (máx. 540×675) para ficar leve.</p>
        <?php if ($edit['imagem']): ?><img class="thumb" src="../<?= htmlspecialchars($edit['imagem']) ?>" alt=""><?php endif; ?>
        <input type="file" name="imagem" accept="image/*">
    </div>
    <div class="field"><label><input type="checkbox" name="ativo" value="1" <?= $edit['ativo'] ? 'checked' : '' ?>> Ativo</label></div>
    <div class="actions"><button class="btn btn-primary">Salvar</button><a class="btn btn-secondary" href="banners.php">Voltar</a></div>
</form>
</div>
<?php else: ?>
<div class="card">
    <a class="btn btn-primary" href="banners.php?novo=1">+ Banner</a>
    <table style="margin-top:14px;">
        <thead><tr><th>Img</th><th>Título</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $b): ?>
            <tr>
                <td><?php if ($b['imagem']): ?><img class="thumb" src="../<?= htmlspecialchars($b['imagem']) ?>"><?php endif; ?></td>
                <td><?= htmlspecialchars($b['titulo']) ?></td>
                <td><?= $b['ativo'] ? 'Ativo' : 'Inativo' ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="banners.php?id=<?= $b['id'] ?>">Editar</a>
                    <a class="btn btn-danger btn-small" href="banners.php?del=<?= $b['id'] ?>" onclick="return confirm('Excluir?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif;
admin_footer();
