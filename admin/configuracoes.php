<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$ok = $err = '';
$keys = ['site_nome', 'site_slogan', 'whatsapp', 'telefone', 'email', 'sobre'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = $pdo->prepare(
        "INSERT INTO site_settings (chave, valor, updated_at) VALUES (?, ?, NOW())
         ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = NOW()"
    );
    foreach ($keys as $k) {
        $st->execute([$k, trim((string)($_POST[$k] ?? ''))]);
    }
    $ok = 'Configurações salvas. O site público já reflete as mudanças.';
}
$vals = [];
foreach ($keys as $k) $vals[$k] = app_setting($k, '');
admin_header('Configurações do site', 'config');
admin_flash($ok, $err);
?>
<div class="card">
    <form method="post">
        <div class="field"><label>Nome do site</label><input name="site_nome" value="<?= htmlspecialchars($vals['site_nome']) ?>"></div>
        <div class="field"><label>Slogan</label><input name="site_slogan" value="<?= htmlspecialchars($vals['site_slogan']) ?>"></div>
        <div class="field"><label>WhatsApp (somente números, com DDI 55)</label><input name="whatsapp" value="<?= htmlspecialchars($vals['whatsapp']) ?>" placeholder="5561999999999"></div>
        <div class="field-row">
            <div class="field"><label>Telefone</label><input name="telefone" value="<?= htmlspecialchars($vals['telefone']) ?>"></div>
            <div class="field"><label>E-mail</label><input name="email" value="<?= htmlspecialchars($vals['email']) ?>"></div>
        </div>
        <div class="field"><label>Texto “Sobre”</label><textarea name="sobre" rows="4"><?= htmlspecialchars($vals['sobre']) ?></textarea></div>
        <button class="btn btn-primary" type="submit">Salvar</button>
    </form>
</div>
<?php admin_footer(); ?>
