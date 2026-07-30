<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/nextcloud.php';

$pdo = app_pdo();
$err = '';
$ok = '';

// ---- POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'test') {
        $result = nc_test();
        if ($result['ok']) {
            $ok = $result['msg'];
        } else {
            $err = $result['msg'];
        }
    } elseif ($action === 'save') {
        app_setting_set('nc_server', trim((string)($_POST['nc_server'] ?? '')));
        app_setting_set('nc_user', trim((string)($_POST['nc_user'] ?? '')));
        $pass = (string)($_POST['nc_pass'] ?? '');
        if ($pass !== '') {
            app_setting_set('nc_pass', $pass);
        } elseif (trim((string)($_POST['nc_keep_pass'] ?? '')) === '') {
            app_setting_set('nc_pass', '');
        }
        $ok = 'Configuracoes Nextcloud salvas.';
    }
}

$vals = [
    'nc_server' => app_setting('nc_server'),
    'nc_user' => app_setting('nc_user'),
    'nc_pass' => app_setting('nc_pass'),
];

admin_header('Nextcloud', 'nextcloud');
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="categorias.php">Gerenciar categorias</a>
</div>
<div class="card">
    <form method="post">
        <h3 style="margin-bottom:14px;">Conexão Nextcloud</h3>
        <p class="muted" style="margin-bottom:16px;">
            Configure o servidor Nextcloud para vincular pastas às categorias de conteúdo.
            Os arquivos serão listados diretamente na área do cliente.
        </p>
        <div class="field"><label>URL do servidor</label><input name="nc_server" value="<?= e($vals['nc_server']) ?>" placeholder="https://nextcloud.meudominio.com" style="width:100%;max-width:450px;"></div>
        <div class="field-row">
            <div class="field"><label>Usuário</label><input name="nc_user" value="<?= e($vals['nc_user']) ?>" placeholder="usuario" style="width:100%;"></div>
            <div class="field"><label>Senha (App Password)</label>
                <input type="password" name="nc_pass" value="" placeholder="<?= $vals['nc_pass'] ? '******** (deixe vazio para manter)' : 'Digite a senha' ?>" style="width:100%;">
                <input type="hidden" name="nc_keep_pass" value="1">
            </div>
        </div>
        <div class="actions" style="margin-top:18px;gap:12px;">
            <button class="btn btn-primary" type="submit" name="action" value="save">Salvar</button>
            <button class="btn btn-secondary" type="submit" name="action" value="test" style="background:var(--accent);">Testar conexão</button>
        </div>
    </form>
</div>

<?php if ($vals['nc_server'] && $vals['nc_user'] && $vals['nc_pass']): ?>
<div class="card" style="margin-top:18px;">
    <h3 style="margin-bottom:10px;">Pastas das categorias</h3>
    <p class="muted" style="margin-bottom:14px;">
        Edite cada <a href="categorias.php">categoria</a> e preencha o campo "Pasta Nextcloud"
        com o caminho da pasta dentro do Nextcloud (ex.: <code>Conteudos/Noticiarios</code>).
        O cliente verá o conteúdo dessa pasta ao acessar a categoria.
    </p>
    <table>
        <thead><tr><th>Categoria</th><th>Pasta Nextcloud</th><th></th></tr></thead>
        <tbody>
        <?php
        $cats = $pdo->query("SELECT id, nome, tipo, nc_pasta FROM categorias ORDER BY ordem, nome")->fetchAll();
        foreach ($cats as $cat):
        ?>
            <tr>
                <td><strong><?= e($cat['nome']) ?></strong> <span class="muted">(<?= e($cat['tipo']) ?>)</span></td>
                <td><?= $cat['nc_pasta'] ? '<code>' . e($cat['nc_pasta']) . '</code>' : '<span class="muted">—</span>' ?></td>
                <td><a class="btn btn-secondary btn-small" href="categorias.php?id=<?= intval($cat['id']) ?>">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
