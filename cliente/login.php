<?php
require_once __DIR__ . '/../includes/layout_public.php';
cliente_session_start();

$redirect = trim((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
if ($redirect !== '' && (str_contains($redirect, '://') || str_starts_with($redirect, '//') || str_contains($redirect, '..'))) {
    $redirect = '';
}

function cliente_redirect_after_login(string $redirect): string {
    if ($redirect === 'texto') {
        return app_url('cliente/texto.php');
    }
    if ($redirect === 'perfil') {
        return app_url('cliente/perfil.php');
    }
    if ($redirect !== '' && !str_contains($redirect, '://') && !str_starts_with($redirect, '//')) {
        $redirect = ltrim($redirect, '/');
        // contratar.php?produto=slug
        if (preg_match('#^contratar\.php(\?.*)?$#i', $redirect) || str_starts_with($redirect, 'contratar.php')) {
            return app_url('cliente/' . $redirect);
        }
        if (str_starts_with($redirect, 'cliente/')) {
            return app_url($redirect);
        }
        if (preg_match('#^[a-z0-9_\-\./]+\.php(\?.*)?$#i', $redirect)) {
            return app_url('cliente/' . $redirect);
        }
    }
    return cliente_home_url();
}

if (cliente_logado() && cliente_atual()) {
    header('Location: ' . cliente_redirect_after_login($redirect));
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');
    try {
        $st = app_pdo()->prepare('SELECT * FROM clientes WHERE LOWER(email) = ? AND ativo = 1 LIMIT 1');
        $st->execute([$email]);
        $cli = $st->fetch();
        if ($cli && password_verify($senha, $cli['senha_hash'])) {
            cliente_login_ok($cli);
            header('Location: ' . cliente_redirect_after_login($redirect));
            exit;
        }
        $erro = 'E-mail ou senha inválidos.';
    } catch (Throwable $e) {
        $erro = 'Não foi possível conectar. Tente novamente em instantes.';
    }
}

layout_header('Entrar · Área do cliente', 'cliente');
?>
<main>
    <div class="container">
        <div class="page-title">
            <h1>Área do cliente</h1>
            <p>Entre com seu e-mail e senha para acessar os conteúdos liberados e enviar textos.</p>
        </div>

        <div class="forms-grid" style="max-width:920px;">
            <div class="form-card">
                <?php if ($erro): ?><div class="alert alert-err"><?= e($erro) ?></div><?php endif; ?>
                <form method="post" action="">
                    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                    <div class="field">
                        <label for="email">E-mail</label>
                        <input id="email" type="email" name="email" required autocomplete="username"
                               placeholder="seu@email.com" value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="senha">Senha</label>
                        <input id="senha" type="password" name="senha" required autocomplete="current-password"
                               placeholder="••••••••">
                    </div>
                    <button class="btn btn-primary" type="submit" style="width:100%;">Entrar</button>
                </form>
                <p class="muted" style="margin-top:16px;text-align:center;font-size:.88rem;">
                    Não tem conta?
                    <a href="<?= e(app_url('cliente/cadastro.php' . ($redirect !== '' ? ('?produto=' . rawurlencode(
                        preg_match('/produto=([^&]+)/', $redirect, $m) ? urldecode($m[1]) : ''
                    )) : ''))) ?>">Cadastre-se</a>
                    · <a href="<?= e(app_url('precos.php')) ?>">Ver planos</a>
                    · <a href="<?= e(app_url('') ?: '/') ?>">Site</a>
                </p>
            </div>
            <div class="hero-card">
                <h3>O que você encontra aqui</h3>
                <ul style="color:var(--muted);margin:12px 0 0 18px;line-height:1.75;">
                    <li>Conteúdos liberados para a sua rádio</li>
                    <li>Arquivos de entrega atualizados</li>
                    <li>Envio de textos para gravação</li>
                    <li>Áudios gravados disponíveis para download</li>
                </ul>
            </div>
        </div>
    </div>
</main>
<?php layout_footer(); ?>
