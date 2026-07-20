<?php
require_once __DIR__ . '/_layout.php';
cliente_require_auth();

$cli = cliente_atual();
if (!$cli) {
    cliente_logout(true);
}

$ok = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    $cpf = app_only_digits((string)($_POST['cpf'] ?? ''));
    $radio = trim((string)($_POST['radio'] ?? ''));
    $cidade = trim((string)($_POST['cidade'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    $senha2 = (string)($_POST['senha2'] ?? '');

    try {
        if ($cpf !== '' && strlen($cpf) !== 11 && strlen($cpf) !== 14) {
            $err = 'CPF/CNPJ inválido.';
        } elseif ($senha !== '') {
            if (strlen($senha) < 6) {
                $err = 'A nova senha precisa ter pelo menos 6 caracteres.';
            } elseif ($senha !== $senha2) {
                $err = 'As senhas não conferem.';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                app_pdo()->prepare(
                    'UPDATE clientes SET whatsapp=?, telefone=?, cpf=?, radio=?, cidade=?, senha_hash=?, updated_at=NOW() WHERE id=?'
                )->execute([$whatsapp, $telefone, $cpf, $radio, $cidade, $hash, intval($cli['id'])]);
                $ok = 'Dados e senha atualizados.';
            }
        } else {
            app_pdo()->prepare(
                'UPDATE clientes SET whatsapp=?, telefone=?, cpf=?, radio=?, cidade=?, updated_at=NOW() WHERE id=?'
            )->execute([$whatsapp, $telefone, $cpf, $radio, $cidade, intval($cli['id'])]);
            $ok = 'Dados atualizados.';
        }
        $cli = cliente_atual();
    } catch (Throwable $e) {
        $err = 'Não foi possível salvar.';
    }
}

cliente_header('Meus dados', 'perfil');
cliente_flash($ok, $err);
?>
<div class="form-card" style="max-width:560px;">
    <form method="post">
        <div class="field"><label>Nome</label><input value="<?= e($cli['nome']) ?>" disabled></div>
        <div class="field"><label>E-mail (login)</label><input value="<?= e($cli['email']) ?>" disabled></div>
        <p class="muted" style="margin-bottom:12px;font-size:.85rem;">Nome e e-mail são alterados apenas pela administração.</p>
        <div class="field-row-form">
            <div class="field"><label>CPF (para boleto/Pix)</label><input name="cpf" value="<?= e($cli['cpf'] ?? '') ?>" placeholder="000.000.000-00"></div>
            <div class="field"><label>WhatsApp</label><input name="whatsapp" value="<?= e($cli['whatsapp'] ?? '') ?>"></div>
        </div>
        <div class="field-row-form">
            <div class="field"><label>Telefone</label><input name="telefone" value="<?= e($cli['telefone'] ?? '') ?>"></div>
            <div class="field"></div>
        </div>
        <div class="field-row-form">
            <div class="field"><label>Rádio / empresa</label><input name="radio" value="<?= e($cli['radio'] ?? '') ?>"></div>
            <div class="field"><label>Cidade</label><input name="cidade" value="<?= e($cli['cidade'] ?? '') ?>"></div>
        </div>
        <h3 style="margin:18px 0 10px;font-size:1rem;">Trocar senha</h3>
        <div class="field-row-form">
            <div class="field"><label>Nova senha</label><input type="password" name="senha" autocomplete="new-password" placeholder="deixe em branco para manter"></div>
            <div class="field"><label>Confirmar senha</label><input type="password" name="senha2" autocomplete="new-password"></div>
        </div>
        <button class="btn btn-primary" type="submit">Salvar</button>
    </form>
</div>
<?php cliente_footer(); ?>
