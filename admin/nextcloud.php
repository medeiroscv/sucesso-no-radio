<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/nextcloud.php';

$pdo = app_pdo();
$err = '';
$ok = '';

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
        $ok = 'Configurações Nextcloud salvas.';
    }
}

$vals = [
    'nc_server' => app_setting('nc_server'),
    'nc_user' => app_setting('nc_user'),
    'nc_pass' => app_setting('nc_pass'),
];

$ncOk = $vals['nc_server'] && $vals['nc_user'] && $vals['nc_pass'];

$ncPath = trim((string)($_GET['nc'] ?? ''));
$ncItens = $ncOk ? nc_listar($ncPath) : [];

admin_header('Nextcloud', 'nextcloud');
?>
<div class="card">
    <form method="post">
        <h3 style="margin-bottom:14px;">Conexão Nextcloud</h3>
        <p class="muted" style="margin-bottom:16px;">
            Configure o servidor Nextcloud. Tudo que estiver na raiz do usuário será exibido
            automaticamente na área do cliente.
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

<?php if ($ncOk): ?>
<div class="card" style="margin-top:18px;">
    <h3 style="margin-bottom:10px;">Arquivos no Nextcloud</h3>
    <p class="muted" style="margin-bottom:14px;">
        Esta é a raiz do seu Nextcloud. Tudo aqui será exibido para os clientes na área de Conteúdos.
        <?php if ($ncPath): ?>
            <br><a class="btn btn-ghost btn-small" href="nextcloud.php" style="margin-top:6px;">← Raiz</a>
        <?php endif; ?>
    </p>
    <?php if (!$ncItens): ?>
        <div class="empty">Pasta vazia ou sem arquivos.</div>
    <?php else: ?>
        <div style="display:grid;gap:4px;">
            <?php foreach ($ncItens as $item): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:6px 10px;border-radius:6px;background:<?= $item['type'] === 'folder' ? 'rgba(34,197,94,.06)' : 'transparent' ?>;border:1px solid var(--line);">
                    <span style="font-size:1.2rem;"><?= $item['type'] === 'folder' ? '📁' : (str_starts_with($item['mimetype'] ?? '', 'audio/') ? '🎵' : '📄') ?></span>
                    <span style="flex:1;">
                        <?php if ($item['type'] === 'folder'): ?>
                            <a href="nextcloud.php?nc=<?= rawurlencode($item['path']) ?>" style="color:var(--text);text-decoration:none;"><strong><?= e($item['name']) ?></strong></a>
                        <?php else: ?>
                            <strong><?= e($item['name']) ?></strong>
                        <?php endif; ?>
                    </span>
                    <span class="muted" style="font-size:.82rem;"><?= e($item['size_fmt']) ?></span>
                    <span class="muted" style="font-size:.78rem;"><?= e($item['mtime']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
