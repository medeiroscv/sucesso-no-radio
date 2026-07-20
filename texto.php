<?php
require_once __DIR__ . '/includes/layout_public.php';

$s = site_settings_all();
$ativo = ($s['form_texto_ativo'] ?? '1') === '1';
$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ativo) {
    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
    $tituloTxt = trim((string)($_POST['titulo'] ?? ''));
    $texto = trim((string)($_POST['texto'] ?? ''));
    if ($nome === '' || $texto === '') {
        $err = 'Informe pelo menos o nome e o texto para gravação.';
    } else {
        try {
            $st = app_pdo()->prepare(
                'INSERT INTO textos_gravacao (nome, email, telefone, whatsapp, titulo, texto, created_at)
                 VALUES (?,?,?,?,?,?,NOW())'
            );
            $st->execute([$nome, $email, $telefone, $whatsapp, $tituloTxt, $texto]);
            $ok = 'Texto enviado com sucesso! Nossa equipe receberá para gravação.';
            $_POST = [];
        } catch (Throwable $e) {
            $err = 'Não foi possível enviar agora. Tente mais tarde ou use o WhatsApp.';
        }
    }
}

$titulo = $s['form_texto_titulo'] ?? 'Envio de texto para gravação';
$intro = $s['form_texto_intro'] ?? 'Envie o texto que deseja gravar.';
$instrucoes = $s['form_texto_instrucoes'] ?? '';
$btn = $s['form_texto_btn'] ?? 'Enviar texto';

layout_header($titulo, 'texto');
$base = app_base_path();
?>
<main class="container">
    <div class="page-title">
        <h1><?= e($titulo) ?></h1>
        <p><?= e($intro) ?></p>
    </div>
    <div class="forms-grid">
        <div class="form-card" style="max-width:720px;">
            <?php if (!$ativo): ?>
                <div class="alert alert-err">Este formulário está temporariamente desativado.</div>
            <?php else: ?>
                <?php if ($ok): ?><div class="alert alert-ok"><?= e($ok) ?></div><?php endif; ?>
                <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
                <form method="post">
                    <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($_POST['nome'] ?? '') ?>"></div>
                    <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"></div>
                    <div class="field-row-form">
                        <div class="field"><label>Telefone</label><input name="telefone" value="<?= e($_POST['telefone'] ?? '') ?>"></div>
                        <div class="field"><label>WhatsApp</label><input name="whatsapp" value="<?= e($_POST['whatsapp'] ?? '') ?>"></div>
                    </div>
                    <div class="field"><label>Título / referência</label><input name="titulo" value="<?= e($_POST['titulo'] ?? '') ?>" placeholder="Ex.: Programa X, campanha, vinheta..."></div>
                    <?php if ($instrucoes !== ''): ?>
                        <p class="muted" style="margin-bottom:8px;font-size:.9rem;"><?= e($instrucoes) ?></p>
                    <?php endif; ?>
                    <div class="field">
                        <label>Texto para gravação *</label>
                        <textarea name="texto" rows="12" required placeholder="Cole ou escreva aqui o texto completo que será gravado..."><?= e($_POST['texto'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= e($btn) ?></button>
                </form>
            <?php endif; ?>
        </div>
        <div class="hero-card">
            <h3>Como funciona</h3>
            <ul style="color:var(--muted);font-size:.95rem;margin:12px 0 0 18px;line-height:1.7;">
                <li>Preencha seus dados</li>
                <li>Envie o texto completo</li>
                <li>Nós recebemos e gravamos</li>
                <li>Retornamos pelo WhatsApp ou e-mail</li>
            </ul>
            <p style="color:var(--muted);margin:18px 0 10px;font-size:.9rem;">Dúvidas?</p>
            <a class="btn btn-wa" href="<?= e(wa_link('Olá! Quero enviar um texto para gravação.')) ?>" target="_blank">WhatsApp</a>
            <?php if (($s['form_contato_ativo'] ?? '1') === '1'): ?>
                <p style="margin-top:14px;"><a class="btn btn-ghost" href="<?= e(($base === '' ? '' : $base) . '/contato.php') ?>">Formulário de contato</a></p>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php layout_footer(); ?>
