<?php
require_once __DIR__ . '/includes/layout_public.php';

$s = site_settings_all();
$ativo = ($s['form_contato_ativo'] ?? '1') === '1';
$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ativo) {
    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
    $mensagem = trim((string)($_POST['mensagem'] ?? ''));
    if ($nome === '' || $mensagem === '') {
        $err = 'Informe pelo menos nome e a mensagem / conteúdo.';
    } else {
        try {
            $st = app_pdo()->prepare(
                'INSERT INTO contatos (nome, email, telefone, whatsapp, cidade, radio, mensagem, created_at)
                 VALUES (?,?,?,?,?,?,?,NOW())'
            );
            $st->execute([$nome, $email, $telefone, $whatsapp, '', '', $mensagem]);
            $ok = 'Mensagem enviada! Em breve entraremos em contato.';
            $_POST = [];
        } catch (Throwable $e) {
            $err = 'Não foi possível enviar agora. Use o WhatsApp ou tente mais tarde.';
        }
    }
}

$titulo = $s['form_contato_titulo'] ?? 'Contato';
$intro = $s['form_contato_intro'] ?? ('Fale com a equipe da ' . ($s['site_nome'] ?? APP_NAME) . '.');
$btn = $s['form_contato_btn'] ?? 'Enviar mensagem';

layout_header($titulo, 'contato');
$base = app_base_path();
?>
<main class="container">
    <div class="page-title">
        <h1><?= e($titulo) ?></h1>
        <p><?= e($intro) ?></p>
    </div>
    <div class="forms-grid">
        <div class="form-card">
            <?php if (!$ativo): ?>
                <div class="alert alert-err">Este formulário está temporariamente desativado.</div>
            <?php else: ?>
                <?php if ($ok): ?><div class="alert alert-ok"><?= e($ok) ?></div><?php endif; ?>
                <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
                <form method="post">
                    <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($_POST['nome'] ?? '') ?>"></div>
                    <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"></div>
                    <div class="field-row-form">
                        <div class="field"><label>Telefone</label><input name="telefone" value="<?= e($_POST['telefone'] ?? '') ?>" placeholder="(00) 0000-0000"></div>
                        <div class="field"><label>WhatsApp</label><input name="whatsapp" value="<?= e($_POST['whatsapp'] ?? '') ?>" placeholder="(00) 00000-0000"></div>
                    </div>
                    <div class="field"><label>Mensagem / conteúdo *</label><textarea name="mensagem" rows="5" required placeholder="Escreva aqui o que deseja nos enviar..."><?= e($_POST['mensagem'] ?? '') ?></textarea></div>
                    <button class="btn btn-primary" type="submit"><?= e($btn) ?></button>
                </form>
            <?php endif; ?>
        </div>
        <div class="hero-card">
            <h3>Atendimento rápido</h3>
            <p style="color:var(--muted);margin:10px 0 16px;">Prefere falar agora? Chame no WhatsApp.</p>
            <a class="btn btn-wa" href="<?= e(wa_link('Olá! Vim pelo site e quero mais informações.')) ?>" target="_blank">Abrir WhatsApp</a>
            <p style="color:var(--muted);margin:18px 0 10px;font-size:.9rem;">Cliente? Envie textos para gravação na área restrita.</p>
            <a class="btn btn-ghost" href="<?= e(($base === '' ? '' : $base) . '/cliente/login.php?redirect=texto') ?>">Área do cliente</a>
        </div>
    </div>
</main>
<?php layout_footer(); ?>
