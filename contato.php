<?php
require_once __DIR__ . '/includes/layout_public.php';

$ok = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    $cidade = trim((string)($_POST['cidade'] ?? ''));
    $radio = trim((string)($_POST['radio'] ?? ''));
    $mensagem = trim((string)($_POST['mensagem'] ?? ''));
    if ($nome === '' || $mensagem === '') {
        $err = 'Informe pelo menos nome e mensagem.';
    } else {
        try {
            $st = app_pdo()->prepare(
                'INSERT INTO contatos (nome, email, telefone, cidade, radio, mensagem, created_at)
                 VALUES (?,?,?,?,?,?,NOW())'
            );
            $st->execute([$nome, $email, $telefone, $cidade, $radio, $mensagem]);
            $ok = 'Mensagem enviada! Em breve entraremos em contato.';
        } catch (Throwable $e) {
            $err = 'Não foi possível enviar agora. Use o WhatsApp ou tente mais tarde.';
        }
    }
}

layout_header('Contato');
$base = app_base_path();
$s = site_settings_all();
?>
<main class="container">
    <div class="page-title">
        <h1>Contato</h1>
        <p>Fale com a equipe da <?= e($s['site_nome'] ?? APP_NAME) ?>.</p>
    </div>
    <div style="display:grid;grid-template-columns:1.1fr .9fr;gap:24px;margin-bottom:48px;">
        <div class="form-card">
            <?php if ($ok): ?><div class="alert alert-ok"><?= e($ok) ?></div><?php endif; ?>
            <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
            <form method="post">
                <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($_POST['nome'] ?? '') ?>"></div>
                <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"></div>
                <div class="field"><label>Telefone / WhatsApp</label><input name="telefone" value="<?= e($_POST['telefone'] ?? '') ?>"></div>
                <div class="field"><label>Cidade</label><input name="cidade" value="<?= e($_POST['cidade'] ?? '') ?>"></div>
                <div class="field"><label>Nome da rádio</label><input name="radio" value="<?= e($_POST['radio'] ?? '') ?>"></div>
                <div class="field"><label>Mensagem *</label><textarea name="mensagem" rows="4" required><?= e($_POST['mensagem'] ?? '') ?></textarea></div>
                <button class="btn btn-primary" type="submit">Enviar</button>
            </form>
        </div>
        <div class="hero-card">
            <h3>Atendimento rápido</h3>
            <p style="color:var(--muted);margin:10px 0 16px;">Prefere falar agora? Chame no WhatsApp.</p>
            <a class="btn btn-wa" href="<?= e(wa_link('Olá! Vim pelo site e quero mais informações.')) ?>" target="_blank">Abrir WhatsApp</a>
        </div>
    </div>
</main>
<style>@media(max-width:800px){main.container>div{grid-template-columns:1fr!important}}</style>
<?php layout_footer(); ?>
