<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
cliente_session_start();

$base = app_base_path();
$prefix = $base === '' ? '' : $base;
$redirect = trim((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
// só paths relativos seguros
if ($redirect !== '' && (str_contains($redirect, '://') || str_starts_with($redirect, '//') || str_contains($redirect, '..'))) {
    $redirect = '';
}

if (cliente_logado() && cliente_atual()) {
    $dest = $redirect !== '' ? $redirect : ($prefix . '/cliente/');
    if (!str_starts_with($dest, 'http') && !str_starts_with($dest, '/')) {
        $dest = $prefix . '/cliente/' . ltrim($dest, '/');
    }
    header('Location: ' . $dest);
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
            if ($redirect === 'texto') {
                header('Location: ' . $prefix . '/cliente/texto.php');
            } elseif ($redirect !== '' && !str_contains($redirect, '://')) {
                $dest = str_starts_with($redirect, '/') ? $redirect : ($prefix . '/cliente/' . ltrim($redirect, '/'));
                header('Location: ' . $dest);
            } else {
                header('Location: ' . $prefix . '/cliente/');
            }
            exit;
        }
        $erro = 'E-mail ou senha inválidos.';
    } catch (Throwable $e) {
        $erro = 'Não foi possível conectar. Tente novamente em instantes.';
    }
}

$s = [];
try {
    foreach (app_pdo()->query('SELECT chave, valor FROM site_settings')->fetchAll() as $r) {
        $s[$r['chave']] = $r['valor'];
    }
} catch (Throwable $e) { /* ok */ }
$nomeSite = $s['site_nome'] ?? APP_NAME;
$logo = !empty($s['site_logo']) ? ($prefix . '/' . ltrim((string)$s['site_logo'], '/')) : '';
$favicon = !empty($s['site_favicon']) ? ($prefix . '/' . ltrim((string)$s['site_favicon'], '/')) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Login · Área do Cliente</title>
    <?php if ($favicon): ?><link rel="icon" href="<?= e($favicon) ?>" type="image/png"><?php endif; ?>
    <link rel="stylesheet" href="<?= e($prefix . '/assets/css/site.css') ?>">
    <link rel="stylesheet" href="<?= e($prefix . '/assets/css/cliente.css') ?>">
</head>
<body class="cliente-body">
<div class="cliente-login-wrap">
    <div class="cliente-login-card">
        <?php if ($logo): ?>
            <img class="cliente-login-logo" src="<?= e($logo) ?>" alt="<?= e($nomeSite) ?>">
        <?php else: ?>
            <div class="brand" style="justify-content:center;margin-bottom:10px;">
                <span class="brand-badge">🎙</span>
                <span><?= e($nomeSite) ?></span>
            </div>
        <?php endif; ?>
        <h1>Área do cliente</h1>
        <p>Acesse conteúdos atualizados e envie textos para gravação.</p>
        <?php if ($erro): ?><div class="alert alert-err"><?= e($erro) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
            <div class="field"><label>E-mail</label><input type="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></div>
            <div class="field"><label>Senha</label><input type="password" name="senha" required autocomplete="current-password"></div>
            <button class="btn btn-primary" type="submit" style="width:100%;">Entrar</button>
        </form>
        <p class="muted" style="margin-top:16px;text-align:center;font-size:.88rem;">
            Acesso liberado pela equipe · <a href="<?= e($prefix . '/') ?>">Voltar ao site</a>
        </p>
    </div>
</div>
</body>
</html>
