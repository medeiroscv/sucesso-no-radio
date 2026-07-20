<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!empty($_SESSION['admin_logado'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    try {
        $st = app_pdo()->prepare('SELECT * FROM usuarios WHERE usuario = ? AND ativo = 1 LIMIT 1');
        $st->execute([$usuario]);
        $u = $st->fetch();
        if ($u && password_verify($senha, $u['senha_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logado'] = true;
            $_SESSION['admin_usuario'] = $u['usuario'];
            $_SESSION['admin_nome'] = $u['nome'] ?? $u['usuario'];
            $_SESSION['admin_id'] = intval($u['id']);
            header('Location: index.php');
            exit;
        }
        $erro = 'Usuário ou senha inválidos.';
    } catch (Throwable $e) {
        $erro = 'Erro de conexão com o banco. Confira o Postgres no EasyPanel.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin · Sucesso no Rádio</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>🎙 Sucesso no Rádio</h1>
        <p>Área administrativa — gerencie o conteúdo do site.</p>
        <?php if ($erro): ?><div class="alert alert-err"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
        <form method="post">
            <div class="field"><label>Usuário</label><input name="usuario" required autocomplete="username"></div>
            <div class="field"><label>Senha</label><input type="password" name="senha" required autocomplete="current-password"></div>
            <button class="btn btn-primary" type="submit" style="width:100%;">Entrar</button>
        </form>
        <p class="muted" style="margin-top:16px;">No EasyPanel use BOOTSTRAP_ADMIN_USER e BOOTSTRAP_ADMIN_PASSWORD no primeiro deploy.</p>
    </div>
</div>
</body>
</html>
