<?php
require_once __DIR__ . '/_layout.php';
cliente_require_auth('texto');

$cli = cliente_atual();
if (!$cli) {
    cliente_logout(true);
}
$cliId = intval($cli['id']);
$ok = $err = '';
$formAtivo = app_setting('form_texto_ativo', '1') === '1';
$verId = intval($_GET['id'] ?? 0);
$modoNovo = isset($_GET['novo']);
$editando = null;

// Carrega texto do cliente para ver/corrigir
if ($verId > 0) {
    $modoNovo = false;
    $editando = app_texto_by_id($verId);
    if (!$editando || intval($editando['cliente_id'] ?? 0) !== $cliId) {
        $editando = null;
        $err = 'Texto não encontrado.';
        $verId = 0;
    } else {
        if (empty($editando['lido_cliente'])) {
            try {
                app_pdo()->prepare('UPDATE textos_gravacao SET lido_cliente = 1 WHERE id = ? AND cliente_id = ?')
                    ->execute([$verId, $cliId]);
                $editando['lido_cliente'] = 1;
            } catch (Throwable $e) { /* ok */ }
        }
    }
}

// ---- POST: novo texto ou correção ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formAtivo) {
    $acao = trim((string)($_POST['acao'] ?? 'novo'));
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $texto = trim((string)($_POST['texto'] ?? ''));
    $idPost = intval($_POST['id'] ?? 0);

    if ($texto === '') {
        $err = 'Informe o texto para gravação.';
        if ($acao === 'novo') $modoNovo = true;
    } elseif ($acao === 'corrigir' && $idPost > 0) {
        $row = app_texto_by_id($idPost);
        if (!$row || intval($row['cliente_id'] ?? 0) !== $cliId) {
            $err = 'Texto não encontrado.';
        } elseif (($row['status'] ?? '') !== 'precisa_correcao') {
            $err = 'Este texto não está aguardando correção.';
        } else {
            try {
                app_pdo()->prepare(
                    "UPDATE textos_gravacao
                     SET titulo = ?, texto = ?, status = 'corrigido', lido = 0, lido_cliente = 1, updated_at = NOW()
                     WHERE id = ? AND cliente_id = ?"
                )->execute([$titulo, $texto, $idPost, $cliId]);
                header('Location: ' . app_url('cliente/texto.php?id=' . $idPost . '&ok=1'));
                exit;
            } catch (Throwable $e) {
                $err = 'Não foi possível salvar a correção.';
            }
        }
    } elseif ($acao === 'novo') {
        try {
            $st = app_pdo()->prepare(
                "INSERT INTO textos_gravacao
                 (cliente_id, nome, email, telefone, whatsapp, titulo, texto, status, lido, lido_cliente, created_at)
                 VALUES (?,?,?,?,?,?,?,'pendente',0,1,NOW())
                 RETURNING id"
            );
            $st->execute([
                $cliId,
                (string)$cli['nome'],
                (string)$cli['email'],
                (string)($cli['telefone'] ?? ''),
                (string)($cli['whatsapp'] ?? ''),
                $titulo,
                $texto,
            ]);
            $newId = intval($st->fetchColumn());
            if ($newId > 0) {
                header('Location: ' . app_url('cliente/texto.php?id=' . $newId . '&ok=1'));
                exit;
            }
            header('Location: ' . app_url('cliente/texto.php?ok=1'));
            exit;
        } catch (Throwable $e) {
            try {
                app_pdo()->prepare(
                    "INSERT INTO textos_gravacao
                     (cliente_id, nome, email, telefone, whatsapp, titulo, texto, status, lido, lido_cliente, created_at)
                     VALUES (?,?,?,?,?,?,?,'pendente',0,1,NOW())"
                )->execute([
                    $cliId,
                    (string)$cli['nome'],
                    (string)$cli['email'],
                    (string)($cli['telefone'] ?? ''),
                    (string)($cli['whatsapp'] ?? ''),
                    $titulo,
                    $texto,
                ]);
                header('Location: ' . app_url('cliente/texto.php?ok=1'));
                exit;
            } catch (Throwable $e2) {
                $err = 'Não foi possível enviar agora. Tente novamente em instantes.';
                $modoNovo = true;
            }
        }
    }
}

if (isset($_GET['ok'])) $ok = 'Salvo com sucesso.';

$meusTextos = app_textos_do_cliente($cliId);
$tituloPag = 'Meus textos';
$intro = app_setting('form_texto_intro', 'Acompanhe os textos enviados para gravação e os áudios entregues.');
$instrucoes = app_setting('form_texto_instrucoes', '');
$btn = app_setting('form_texto_btn', 'Enviar texto');
$precisaCorrecao = $editando && ($editando['status'] ?? '') === 'precisa_correcao';

// Títulos de página por modo
if ($modoNovo) {
    $tituloPag = 'Enviar novo texto';
} elseif ($editando) {
    $tituloPag = $editando['titulo'] ?: ('Texto #' . intval($editando['id']));
}

cliente_header($tituloPag, 'texto');
cliente_flash($ok, $err);

// ========== FORMULÁRIO NOVO TEXTO (tela separada) ==========
if ($modoNovo):
?>
<p class="cliente-intro"><?= e($intro) ?></p>
<div class="actions" style="margin-bottom:16px;">
    <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/texto.php')) ?>">← Voltar para Meus textos</a>
</div>

<?php if (!$formAtivo): ?>
    <div class="alert alert-err">O envio de textos está temporariamente desativado.</div>
<?php else: ?>
<div class="forms-grid">
    <div class="form-card" style="max-width:720px;">
        <div class="cliente-dados-box" style="margin-bottom:14px;">
            <strong>Seus dados</strong>
            <div class="muted" style="margin-top:8px;line-height:1.7;font-size:.92rem;">
                <?= e($cli['nome']) ?> · <?= e($cli['email']) ?>
                <?php if (!empty($cli['whatsapp'])): ?> · WhatsApp <?= e($cli['whatsapp']) ?><?php endif; ?>
            </div>
        </div>
        <form method="post">
            <input type="hidden" name="acao" value="novo">
            <div class="field">
                <label>Título / referência</label>
                <input name="titulo" value="<?= e($_POST['titulo'] ?? '') ?>" placeholder="Ex.: Programa X, campanha...">
            </div>
            <?php if ($instrucoes !== ''): ?>
                <p class="muted" style="margin-bottom:8px;font-size:.9rem;"><?= e($instrucoes) ?></p>
            <?php endif; ?>
            <div class="field">
                <label>Texto para gravação *</label>
                <textarea name="texto" rows="14" required placeholder="Cole ou escreva o texto completo..."><?= e($_POST['texto'] ?? '') ?></textarea>
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit"><?= e($btn) ?></button>
                <a class="btn btn-ghost" href="<?= e(app_url('cliente/texto.php')) ?>">Cancelar</a>
            </div>
        </form>
    </div>
    <div class="hero-card">
        <h3>Como funciona</h3>
        <ul style="color:var(--muted);font-size:.95rem;margin:12px 0 0 18px;line-height:1.8;">
            <li>Você envia o texto</li>
            <li>Se precisar, a equipe pede correção</li>
            <li>Você ajusta e reenvia</li>
            <li>Recebe o áudio MP3 em Meus textos</li>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php
// ========== DETALHE / CORREÇÃO ==========
elseif ($editando):
    $st = (string)($editando['status'] ?? 'pendente');
    $meta = app_texto_status_meta($st);
?>
<div class="actions" style="margin-bottom:16px;">
    <a class="btn btn-ghost btn-small" href="<?= e(app_url('cliente/texto.php')) ?>">← Voltar para Meus textos</a>
    <span style="font-size:.78rem;font-weight:800;padding:4px 10px;border-radius:999px;color:<?= e($meta['color']) ?>;background:<?= e($meta['bg']) ?>;">
        <?= e($meta['label']) ?>
    </span>
</div>

<div class="form-card" style="max-width:800px;margin-bottom:28px;">
    <h3 style="margin:0 0 8px;"><?= e($editando['titulo'] ?: ('Texto #' . intval($editando['id']))) ?></h3>
    <p class="muted" style="margin-bottom:12px;font-size:.88rem;">
        Enviado em <?= e(substr((string)$editando['created_at'], 0, 16)) ?>
        <?php if (!empty($editando['updated_at'])): ?> · atualizado <?= e(substr((string)$editando['updated_at'], 0, 16)) ?><?php endif; ?>
    </p>

    <?php if (!empty($editando['observacao_admin'])): ?>
        <div style="background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.35);border-radius:12px;padding:12px 14px;margin-bottom:14px;">
            <strong style="color:#fbbf24;">Mensagem da equipe</strong>
            <div style="margin-top:8px;white-space:pre-wrap;line-height:1.5;"><?= e($editando['observacao_admin']) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($precisaCorrecao): ?>
        <p class="muted" style="margin-bottom:12px;">Corrija o texto conforme a orientação e reenvie.</p>
        <form method="post">
            <input type="hidden" name="acao" value="corrigir">
            <input type="hidden" name="id" value="<?= intval($editando['id']) ?>">
            <div class="field">
                <label>Título / referência</label>
                <input name="titulo" value="<?= e($_POST['titulo'] ?? $editando['titulo'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Texto corrigido *</label>
                <textarea name="texto" rows="14" required><?= e($_POST['texto'] ?? $editando['texto'] ?? '') ?></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Reenviar texto corrigido</button>
        </form>
    <?php else: ?>
        <div style="background:#0b1220;border:1px solid #243047;border-radius:12px;padding:14px;white-space:pre-wrap;line-height:1.55;margin-bottom:14px;">
            <?= e($editando['texto'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php if ($st === 'entregue' && !empty($editando['audio_arquivo'])):
        $audioUrl = app_url('cliente/download-texto-audio.php?id=' . intval($editando['id']));
    ?>
        <div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.35);border-radius:12px;padding:14px;margin-top:8px;">
            <strong style="color:#86efac;">Áudio da gravação</strong>
            <audio controls preload="none" style="width:100%;margin-top:12px;display:block;">
                <source src="<?= e($audioUrl) ?>" type="audio/mpeg">
            </audio>
            <div class="actions" style="margin-top:12px;">
                <a class="btn btn-primary btn-small" href="<?= e($audioUrl . '&dl=1') ?>">Baixar MP3</a>
            </div>
        </div>
    <?php elseif ($st === 'pendente' || $st === 'corrigido'): ?>
        <p class="muted" style="margin-top:12px;"><?= e($meta['desc']) ?>.</p>
    <?php endif; ?>
</div>

<?php
// ========== LISTA (padrão ao abrir Meus textos) ==========
else:
?>
<p class="cliente-intro"><?= e($intro) ?></p>

<div class="actions" style="margin-bottom:20px;">
    <?php if ($formAtivo): ?>
        <a class="btn btn-primary" href="<?= e(app_url('cliente/texto.php?novo=1')) ?>">Enviar novo texto</a>
    <?php else: ?>
        <span class="muted">Envio de textos temporariamente desativado.</span>
    <?php endif; ?>
</div>

<?php if (!$meusTextos): ?>
    <div class="empty">Nenhum texto solicitado</div>
<?php else: ?>
    <div class="cliente-list">
        <?php foreach ($meusTextos as $t):
            $st = (string)($t['status'] ?? 'pendente');
            $m = app_texto_status_meta($st);
            $url = app_url('cliente/texto.php?id=' . intval($t['id']));
        ?>
            <a class="cliente-list-item" href="<?= e($url) ?>">
                <div>
                    <strong><?= e($t['titulo'] ?: ('Texto #' . intval($t['id']))) ?></strong>
                    <div class="muted" style="font-size:.85rem;margin-top:4px;">
                        <?= e(substr((string)$t['created_at'], 0, 16)) ?>
                        · <?= e(mb_strimwidth($t['texto'] ?? '', 0, 60, '…')) ?>
                    </div>
                </div>
                <div class="cliente-list-meta">
                    <span style="font-size:.72rem;font-weight:800;padding:4px 10px;border-radius:999px;color:<?= e($m['color']) ?>;background:<?= e($m['bg']) ?>;white-space:nowrap;">
                        <?= e($m['label']) ?>
                    </span>
                    <?php if (!empty($t['audio_arquivo'])): ?>
                        <span class="chip">🎧 Áudio</span>
                    <?php endif; ?>
                    <?php if (empty($t['lido_cliente']) && in_array($st, ['precisa_correcao', 'entregue'], true)): ?>
                        <span class="chip" style="background:rgba(239,68,68,.15);color:#fecaca;border-color:rgba(239,68,68,.3);">Novo</span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
endif;
cliente_footer();
