<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
cliente_session_start();

$redirect = trim((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
if ($redirect !== '' && (str_contains($redirect, '://') || str_starts_with($redirect, '//') || str_contains($redirect, '..'))) {
    $redirect = '';
}

/** Destino pós-login (sempre URL válida da área do cliente). */
function cliente_redirect_after_login(string $redirect): string {
    if ($redirect === 'texto') {
        return app_url('cliente/texto.php');
    }
    if ($redirect === 'perfil') {
        return app_url('cliente/perfil.php');
    }
    if ($redirect !== '' && !str_contains($redirect, '://') && !str_starts_with($redirect, '//')) {
        // só permite caminhos relativos seguros dentro de cliente/
        $redirect = ltrim($redirect, '/');
        if (str_starts_with($redirect, 'cliente/')) {
            return app_url($redirect);
        }
        if (preg_match('#^[a-z0-9_\-\./]+\.php(\?.*)?$#i', $redirect)) {
            return app_url('cliente/' . $redirect);
        }
    }
    return cliente_home_url(); // cliente/index.php
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

$s = [];
try {
    foreach (app_pdo()->query('SELECT chave, valor FROM site_settings')->fetchAll() as $r) {
        $s[$r['chave']] = $r['valor'];
    }
} catch (Throwable $e) { /* ok */ }
$nomeSite = $s['site_nome'] ?? APP_NAME;
$logo = !empty($s['site_logo']) ? app_url(ltrim((string)$s['site_logo'], '/')) : '';
$favicon = !empty($s['site_favicon']) ? app_url(ltrim((string)$s['site_favicon'], '/')) : '';
$emailVal = e($_POST['email'] ?? '');
$homePublico = app_url('');
if ($homePublico === '') $homePublico = '/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Login · Área do Cliente · <?= e($nomeSite) ?></title>
    <?php if ($favicon): ?><link rel="icon" href="<?= e($favicon) ?>" type="image/png"><?php endif; ?>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            min-height: 100%;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            background: #0b1220;
            color: #e8eef9;
            line-height: 1.5;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background:
                radial-gradient(ellipse 80% 50% at 20% 10%, rgba(34, 197, 94, 0.12), transparent 55%),
                radial-gradient(ellipse 60% 40% at 80% 90%, rgba(14, 165, 233, 0.1), transparent 50%),
                #0b1220;
        }
        a { color: #86efac; text-decoration: none; }
        a:hover { text-decoration: underline; }

        .wrap {
            width: 100%;
            max-width: 420px;
        }
        .card {
            background: #151e30;
            border: 1px solid #243047;
            border-radius: 18px;
            padding: 32px 28px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45);
        }
        .logo-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 14px;
        }
        .logo-wrap img {
            max-height: 56px;
            max-width: 220px;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
        }
        .brand-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 14px;
            font-weight: 800;
            font-size: 1.1rem;
            color: #e8eef9;
        }
        .brand-badge {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #22c55e, #0ea5e9);
            display: grid;
            place-items: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        h1 {
            font-size: 1.4rem;
            font-weight: 800;
            text-align: center;
            color: #f1f5f9;
            margin: 0 0 8px;
        }
        .subtitle {
            text-align: center;
            color: #94a3b8;
            font-size: 0.92rem;
            margin-bottom: 22px;
        }
        .alert {
            padding: 11px 13px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            background: rgba(239, 68, 68, 0.14);
            color: #fecaca;
            border: 1px solid rgba(239, 68, 68, 0.35);
        }
        .field { margin-bottom: 14px; }
        .field label {
            display: block;
            font-weight: 700;
            font-size: 0.82rem;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .field input {
            width: 100%;
            border: 1px solid #334155;
            background: #0b1220;
            color: #f1f5f9;
            border-radius: 10px;
            padding: 12px 14px;
            font: inherit;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .field input::placeholder { color: #64748b; }
        .field input:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            border: 0;
            border-radius: 999px;
            padding: 13px 18px;
            font-weight: 800;
            font-size: 0.98rem;
            font-family: inherit;
            cursor: pointer;
            background: #22c55e;
            color: #052e16;
            margin-top: 4px;
            transition: background 0.15s ease;
        }
        .btn:hover { background: #16a34a; }
        .foot {
            margin-top: 18px;
            text-align: center;
            color: #64748b;
            font-size: 0.86rem;
        }
        .foot a { color: #86efac; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <?php if ($logo): ?>
            <div class="logo-wrap">
                <img src="<?= e($logo) ?>" alt="<?= e($nomeSite) ?>">
            </div>
        <?php else: ?>
            <div class="brand-fallback">
                <span class="brand-badge">🎙</span>
                <span><?= e($nomeSite) ?></span>
            </div>
        <?php endif; ?>

        <h1>Área do cliente</h1>
        <p class="subtitle">Entre com seu e-mail e senha para acessar os conteúdos liberados.</p>

        <?php if ($erro): ?>
            <div class="alert"><?= e($erro) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
            <div class="field">
                <label for="email">E-mail</label>
                <input id="email" type="email" name="email" required autocomplete="username"
                       placeholder="seu@email.com" value="<?= $emailVal ?>">
            </div>
            <div class="field">
                <label for="senha">Senha</label>
                <input id="senha" type="password" name="senha" required autocomplete="current-password"
                       placeholder="••••••••">
            </div>
            <button class="btn" type="submit">Entrar</button>
        </form>

        <p class="foot">
            Acesso liberado pela equipe<br>
            <a href="<?= e($homePublico) ?>">← Voltar ao site</a>
        </p>
    </div>
</div>
</body>
</html>
