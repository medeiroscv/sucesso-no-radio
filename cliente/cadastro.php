<?php
/**
 * Cadastro de cliente (público) — usado no fluxo de contratação de planos.
 */
require_once __DIR__ . '/../includes/layout_public.php';
require_once __DIR__ . '/../includes/billing.php';

cliente_session_start();

$produtoSlug = trim((string)($_GET['produto'] ?? $_POST['produto'] ?? ''));
$prod = $produtoSlug !== '' ? billing_produto_by_slug($produtoSlug) : null;
if (!$prod && $produtoSlug !== '' && ctype_digit($produtoSlug)) {
    $prod = billing_produto_by_id(intval($produtoSlug));
}

// Já logado com produto → checkout
if (cliente_logado() && cliente_atual() && $prod) {
    header('Location: ' . app_url('cliente/contratar.php?produto=' . rawurlencode((string)$prod['slug'])));
    exit;
}
if (cliente_logado() && cliente_atual() && !$prod) {
    header('Location: ' . cliente_home_url());
    exit;
}

$erro = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');
    $senha2 = (string)($_POST['senha2'] ?? '');
    $cpf = app_only_digits((string)($_POST['cpf'] ?? ''));
    $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
    $radio = trim((string)($_POST['radio'] ?? ''));
    $cidade = trim((string)($_POST['cidade'] ?? ''));
    $produtoSlug = trim((string)($_POST['produto'] ?? $produtoSlug));

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Nome, e-mail e senha são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha precisa ter pelo menos 6 caracteres.';
    } elseif ($senha !== $senha2) {
        $erro = 'As senhas não conferem.';
    } elseif ($cpf !== '' && strlen($cpf) !== 11 && strlen($cpf) !== 14) {
        $erro = 'CPF/CNPJ inválido.';
    } else {
        try {
            $st = app_pdo()->prepare('SELECT id FROM clientes WHERE LOWER(email) = ? LIMIT 1');
            $st->execute([$email]);
            if ($st->fetch()) {
                $erro = 'Já existe uma conta com este e-mail. Faça login.';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                app_pdo()->prepare(
                    'INSERT INTO clientes (nome, email, senha_hash, cpf, whatsapp, radio, cidade, ativo, acesso_total, created_at)
                     VALUES (?,?,?,?,?,?,?,1,0,NOW())'
                )->execute([$nome, $email, $hash, $cpf, $whatsapp, $radio, $cidade]);
                $newId = intval(app_pdo()->lastInsertId());
                $st = app_pdo()->prepare('SELECT * FROM clientes WHERE id = ? LIMIT 1');
                $st->execute([$newId]);
                $cli = $st->fetch();
                if ($cli) {
                    cliente_login_ok($cli);
                    if ($produtoSlug !== '') {
                        header('Location: ' . app_url('cliente/contratar.php?produto=' . rawurlencode($produtoSlug)));
                        exit;
                    }
                    header('Location: ' . cliente_home_url());
                    exit;
                }
                $erro = 'Conta criada, mas o login automático falhou. Entre com seu e-mail.';
            }
        } catch (Throwable $e) {
            $erro = 'Não foi possível cadastrar. Tente novamente.';
        }
    }
}

$titulo = $prod ? ('Cadastro · ' . $prod['nome']) : 'Criar conta';
layout_header($titulo, 'precos');
?>
<main>
    <div class="container">
        <div class="page-title">
            <h1>Criar conta</h1>
            <?php if ($prod): ?>
                <p>Cadastre-se para assinar <strong><?= e($prod['nome']) ?></strong>
                    (<?= e(app_money_br(intval($prod['valor_centavos']))) ?>).
                    Depois você será direcionado ao pagamento.</p>
            <?php else: ?>
                <p>Preencha seus dados para acessar a área do cliente.</p>
            <?php endif; ?>
        </div>

        <div class="forms-grid" style="max-width:920px;">
            <div class="form-card">
                <?php if ($erro): ?><div class="alert alert-err"><?= e($erro) ?></div><?php endif; ?>
                <form method="post" action="">
                    <input type="hidden" name="produto" value="<?= e($produtoSlug) ?>">
                    <div class="field">
                        <label>Nome completo *</label>
                        <input name="nome" required value="<?= e($_POST['nome'] ?? '') ?>" autocomplete="name">
                    </div>
                    <div class="field">
                        <label>E-mail (login) *</label>
                        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">
                    </div>
                    <div class="field-row-form">
                        <div class="field">
                            <label>Senha *</label>
                            <input type="password" name="senha" required minlength="6" autocomplete="new-password">
                        </div>
                        <div class="field">
                            <label>Confirmar senha *</label>
                            <input type="password" name="senha2" required minlength="6" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="field">
                        <label>CPF/CNPJ (necessário para boleto)</label>
                        <input name="cpf" value="<?= e($_POST['cpf'] ?? '') ?>" placeholder="000.000.000-00" autocomplete="off">
                    </div>
                    <div class="field-row-form">
                        <div class="field">
                            <label>WhatsApp</label>
                            <input name="whatsapp" value="<?= e($_POST['whatsapp'] ?? '') ?>" placeholder="(00) 00000-0000">
                        </div>
                        <div class="field">
                            <label>Rádio / empresa</label>
                            <input name="radio" value="<?= e($_POST['radio'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label>Cidade</label>
                        <input name="cidade" value="<?= e($_POST['cidade'] ?? '') ?>">
                    </div>
                    <button class="btn btn-primary" type="submit" style="width:100%;">
                        <?= $prod ? 'Cadastrar e ir para o pagamento' : 'Criar conta' ?>
                    </button>
                </form>
                <p class="muted" style="margin-top:16px;text-align:center;font-size:.88rem;">
                    Já tem conta?
                    <a href="<?= e(app_url('cliente/login.php?redirect=' . rawurlencode(
                        $prod ? ('contratar.php?produto=' . $prod['slug']) : 'index.php'
                    ))) ?>">Entrar</a>
                    · <a href="<?= e(app_url('precos.php')) ?>">Voltar aos preços</a>
                </p>
            </div>
            <?php if ($prod):
                $p = billing_produto_normalize_row($prod);
            ?>
            <div class="hero-card">
                <h3><?= e($p['nome']) ?></h3>
                <p style="font-size:1.4rem;font-weight:800;margin:10px 0;"><?= e(app_money_br(intval($p['valor_centavos']))) ?></p>
                <?php if (!empty($p['descricao'])): ?>
                    <p class="muted"><?= e($p['descricao']) ?></p>
                <?php endif; ?>
                <?php if (!empty($p['recursos_list'])): ?>
                    <ul style="color:var(--muted);margin:12px 0 0 18px;line-height:1.75;">
                        <?php foreach ($p['recursos_list'] as $rec): ?>
                            <li><?= e($rec) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="muted" style="margin-top:16px;font-size:.85rem;">
                    Após o cadastro você verá o Pix e o boleto. Com o pagamento confirmado, o acesso é liberado automaticamente.
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php layout_footer(); ?>
