<?php
require_once __DIR__ . '/_layout.php';
cliente_require_auth('texto');

$cli = cliente_atual();
if (!$cli) {
    cliente_logout(true);
}

$ok = $err = '';
$formAtivo = app_setting('form_texto_ativo', '1') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formAtivo) {
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $texto = trim((string)($_POST['texto'] ?? ''));
    if ($texto === '') {
        $err = 'Informe o texto para gravação.';
    } else {
        try {
            // Sempre grava dados oficiais do cadastro do cliente
            $st = app_pdo()->prepare(
                'INSERT INTO textos_gravacao
                 (cliente_id, nome, email, telefone, whatsapp, titulo, texto, created_at)
                 VALUES (?,?,?,?,?,?,?,NOW())'
            );
            $st->execute([
                intval($cli['id']),
                (string)$cli['nome'],
                (string)$cli['email'],
                (string)($cli['telefone'] ?? ''),
                (string)($cli['whatsapp'] ?? ''),
                $titulo,
                $texto,
            ]);
            $ok = 'Texto enviado com sucesso! A equipe já recebe com os seus dados de cliente.';
            $_POST = [];
        } catch (Throwable $e) {
            $err = 'Não foi possível enviar agora. Tente novamente em instantes.';
        }
    }
}

$tituloPag = app_setting('form_texto_titulo', 'Envio de texto para gravação');
$intro = app_setting('form_texto_intro', 'Envie o texto que deseja gravar.');
$instrucoes = app_setting('form_texto_instrucoes', '');
$btn = app_setting('form_texto_btn', 'Enviar texto');

cliente_header($tituloPag, 'texto');
cliente_flash($ok, $err);
?>
<p class="cliente-intro"><?= e($intro) ?></p>

<div class="forms-grid">
    <div class="form-card" style="max-width:720px;">
        <?php if (!$formAtivo): ?>
            <div class="alert alert-err">O envio de textos está temporariamente desativado.</div>
        <?php else: ?>
            <div class="cliente-dados-box">
                <strong>Seus dados (cadastro)</strong>
                <div class="muted" style="margin-top:8px;line-height:1.7;font-size:.92rem;">
                    <div><strong>Nome:</strong> <?= e($cli['nome']) ?></div>
                    <div><strong>E-mail:</strong> <?= e($cli['email']) ?></div>
                    <?php if (!empty($cli['whatsapp'])): ?><div><strong>WhatsApp:</strong> <?= e($cli['whatsapp']) ?></div><?php endif; ?>
                    <?php if (!empty($cli['telefone'])): ?><div><strong>Telefone:</strong> <?= e($cli['telefone']) ?></div><?php endif; ?>
                    <?php if (!empty($cli['radio'])): ?><div><strong>Rádio:</strong> <?= e($cli['radio']) ?></div><?php endif; ?>
                </div>
                <p class="muted" style="margin-top:10px;font-size:.82rem;">Estes dados vão automaticamente com o texto. Para alterar, fale com a equipe ou use <a href="perfil.php">Meus dados</a>.</p>
            </div>

            <form method="post" style="margin-top:16px;">
                <div class="field">
                    <label>Título / referência</label>
                    <input name="titulo" value="<?= e($_POST['titulo'] ?? '') ?>" placeholder="Ex.: Programa X, campanha, vinheta...">
                </div>
                <?php if ($instrucoes !== ''): ?>
                    <p class="muted" style="margin-bottom:8px;font-size:.9rem;"><?= e($instrucoes) ?></p>
                <?php endif; ?>
                <div class="field">
                    <label>Texto para gravação *</label>
                    <textarea name="texto" rows="14" required placeholder="Cole ou escreva aqui o texto completo que será gravado..."><?= e($_POST['texto'] ?? '') ?></textarea>
                </div>
                <button class="btn btn-primary" type="submit"><?= e($btn) ?></button>
            </form>
        <?php endif; ?>
    </div>
    <div class="hero-card">
        <h3>Como funciona</h3>
        <ul style="color:var(--muted);font-size:.95rem;margin:12px 0 0 18px;line-height:1.7;">
            <li>Você já está identificado pelo login</li>
            <li>Enviamos o texto + seus contatos</li>
            <li>A equipe grava e retorna</li>
        </ul>
    </div>
</div>
<?php cliente_footer(); ?>
