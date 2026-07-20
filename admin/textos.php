<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$ok = $err = '';
$statusMeta = app_texto_status_meta();

if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $row = app_texto_by_id($id);
    if ($row && !empty($row['audio_arquivo'])) {
        admin_delete_local_upload((string)$row['audio_arquivo']);
    }
    $pdo->prepare('DELETE FROM textos_gravacao WHERE id = ?')->execute([$id]);
    header('Location: textos.php?ok=1');
    exit;
}

// ---- Salvar ação do admin (status, obs, áudio) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['texto_id'])) {
    $id = intval($_POST['texto_id'] ?? 0);
    $acao = trim((string)($_POST['acao'] ?? ''));
    $obs = trim((string)($_POST['observacao_admin'] ?? ''));
    $row = app_texto_by_id($id);

    if (!$row) {
        $err = 'Texto não encontrado.';
    } elseif ($acao === 'pedir_correcao') {
        if ($obs === '') {
            $err = 'Informe a observação para o cliente (o que precisa corrigir).';
            $ver = $row;
        } else {
            $pdo->prepare(
                "UPDATE textos_gravacao
                 SET status = 'precisa_correcao', observacao_admin = ?, lido = 1, lido_cliente = 0, updated_at = NOW()
                 WHERE id = ?"
            )->execute([$obs, $id]);
            header('Location: textos.php?id=' . $id . '&ok=1');
            exit;
        }
    } elseif ($acao === 'entregar_audio') {
        $audioAtual = (string)($row['audio_arquivo'] ?? '');
        $audioNovo = admin_upload_audio_single('audio', 'textos_audio');
        if ($audioNovo === '' && $audioAtual === '') {
            $err = 'Anexe o áudio MP3 da gravação.';
            $ver = $row;
        } else {
            if ($audioNovo !== '') {
                if ($audioAtual !== '' && $audioAtual !== $audioNovo) {
                    admin_delete_local_upload($audioAtual);
                }
                $audioFinal = $audioNovo;
            } else {
                $audioFinal = $audioAtual;
            }
            $pdo->prepare(
                "UPDATE textos_gravacao
                 SET status = 'entregue', observacao_admin = ?, audio_arquivo = ?, lido = 1, lido_cliente = 0, updated_at = NOW()
                 WHERE id = ?"
            )->execute([$obs, $audioFinal, $id]);
            header('Location: textos.php?id=' . $id . '&ok=1');
            exit;
        }
    } elseif ($acao === 'salvar_obs') {
        $pdo->prepare(
            'UPDATE textos_gravacao SET observacao_admin = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$obs, $id]);
        header('Location: textos.php?id=' . $id . '&ok=1');
        exit;
    } elseif ($acao === 'voltar_pendente') {
        $pdo->prepare(
            "UPDATE textos_gravacao SET status = 'pendente', lido_cliente = 0, updated_at = NOW() WHERE id = ?"
        )->execute([$id]);
        header('Location: textos.php?id=' . $id . '&ok=1');
        exit;
    }
}

$ver = $ver ?? null;
if ($ver === null && isset($_GET['id'])) {
    $st = $pdo->prepare(
        'SELECT t.*,
                c.nome AS cliente_nome, c.email AS cliente_email, c.whatsapp AS cliente_whatsapp,
                c.telefone AS cliente_telefone, c.radio AS cliente_radio, c.cidade AS cliente_cidade,
                c.id AS cliente_cadastro_id
         FROM textos_gravacao t
         LEFT JOIN clientes c ON c.id = t.cliente_id
         WHERE t.id = ?'
    );
    $st->execute([intval($_GET['id'])]);
    $ver = $st->fetch() ?: null;
    if ($ver && empty($ver['lido'])) {
        $pdo->prepare('UPDATE textos_gravacao SET lido = 1 WHERE id = ?')->execute([intval($ver['id'])]);
        $ver['lido'] = 1;
    }
} elseif ($ver && empty($ver['cliente_nome'])) {
    // recarrega com join se veio do POST com erro
    $st = $pdo->prepare(
        'SELECT t.*,
                c.nome AS cliente_nome, c.email AS cliente_email, c.whatsapp AS cliente_whatsapp,
                c.telefone AS cliente_telefone, c.radio AS cliente_radio, c.cidade AS cliente_cidade,
                c.id AS cliente_cadastro_id
         FROM textos_gravacao t
         LEFT JOIN clientes c ON c.id = t.cliente_id
         WHERE t.id = ?'
    );
    $st->execute([intval($ver['id'])]);
    $ver = $st->fetch() ?: $ver;
}

$lista = [];
try {
    $lista = $pdo->query(
        'SELECT t.*,
                c.nome AS cliente_nome, c.email AS cliente_email, c.whatsapp AS cliente_whatsapp,
                c.telefone AS cliente_telefone, c.radio AS cliente_radio
         FROM textos_gravacao t
         LEFT JOIN clientes c ON c.id = t.cliente_id
         ORDER BY
           CASE t.status
             WHEN \'corrigido\' THEN 0
             WHEN \'pendente\' THEN 1
             WHEN \'precisa_correcao\' THEN 2
             WHEN \'entregue\' THEN 3
             ELSE 4
           END,
           t.id DESC
         LIMIT 200'
    )->fetchAll() ?: [];
} catch (Throwable $e) { /* ok */ }

$novos = count(array_filter($lista, fn($r) => empty($r['lido']) || in_array(($r['status'] ?? ''), ['pendente', 'corrigido'], true)));
if (isset($_GET['ok'])) $ok = 'Salvo com sucesso.';

admin_header('Textos a gravar', 'textos');
admin_flash($ok, $err);
?>
<div class="actions" style="margin-bottom:14px;">
    <span class="muted"><?= count($lista) ?> envio(s)</span>
    <a class="btn btn-secondary btn-small" href="clientes.php">Ver clientes</a>
</div>

<?php if ($ver):
    $stKey = (string)($ver['status'] ?? 'pendente');
    if ($stKey === '') $stKey = 'pendente';
    $meta = app_texto_status_meta($stKey);
    $nomeExibir = $ver['cliente_nome'] ?: $ver['nome'];
    $emailExibir = $ver['cliente_email'] ?: $ver['email'];
    $waExibir = $ver['cliente_whatsapp'] ?: $ver['whatsapp'];
    $telExibir = $ver['cliente_telefone'] ?: $ver['telefone'];
    $radioExibir = $ver['cliente_radio'] ?? '';
    $cliId = intval($ver['cliente_cadastro_id'] ?? $ver['cliente_id'] ?? 0);
?>
<div class="card" style="margin-bottom:16px;">
    <div class="actions" style="margin-bottom:12px;">
        <a class="btn btn-secondary btn-small" href="textos.php">← Voltar à lista</a>
        <a class="btn btn-danger btn-small" href="textos.php?del=<?= intval($ver['id']) ?>" onclick="return confirm('Excluir este envio e o áudio anexado?')">Excluir</a>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;">Texto #<?= intval($ver['id']) ?></h3>
        <span style="font-size:.78rem;font-weight:800;padding:4px 10px;border-radius:999px;color:<?= e($meta['color']) ?>;background:<?= e($meta['bg']) ?>;">
            <?= e($meta['label']) ?>
        </span>
        <span class="muted"><?= e(substr((string)$ver['created_at'], 0, 16)) ?>
            <?php if (!empty($ver['updated_at'])): ?> · atualizado <?= e(substr((string)$ver['updated_at'], 0, 16)) ?><?php endif; ?>
        </span>
    </div>

    <div style="background:#0f172a;border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:14px;">
        <strong style="display:block;margin-bottom:10px;">Cliente</strong>
        <div style="display:grid;gap:6px;font-size:.92rem;">
            <div><strong>Nome:</strong> <?= e($nomeExibir ?: '—') ?></div>
            <div><strong>E-mail:</strong>
                <?php if ($emailExibir): ?><a href="mailto:<?= e($emailExibir) ?>"><?= e($emailExibir) ?></a><?php else: ?>—<?php endif; ?>
            </div>
            <div><strong>WhatsApp:</strong>
                <?php if ($waExibir):
                    $waNum = preg_replace('/\D+/', '', $waExibir);
                ?>
                    <?= e($waExibir) ?>
                    <?php if ($waNum): ?> · <a href="https://wa.me/<?= e($waNum) ?>" target="_blank" rel="noopener">Abrir chat</a><?php endif; ?>
                <?php else: ?>—<?php endif; ?>
            </div>
            <?php if ($telExibir): ?><div><strong>Telefone:</strong> <?= e($telExibir) ?></div><?php endif; ?>
            <?php if ($radioExibir): ?><div><strong>Rádio:</strong> <?= e($radioExibir) ?></div><?php endif; ?>
            <?php if ($cliId > 0): ?>
                <div style="margin-top:6px;"><a class="btn btn-secondary btn-small" href="clientes.php?id=<?= $cliId ?>">Cadastro do cliente</a></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($ver['titulo'])): ?>
        <p style="margin-bottom:10px;"><strong>Título / referência:</strong> <?= e($ver['titulo']) ?></p>
    <?php endif; ?>
    <div style="background:#0f172a;border:1px solid var(--line);border-radius:12px;padding:14px;white-space:pre-wrap;line-height:1.55;margin-bottom:18px;">
        <?= e($ver['texto'] ?? '') ?>
    </div>

    <?php if (!empty($ver['observacao_admin'])): ?>
        <div style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.3);border-radius:12px;padding:12px 14px;margin-bottom:16px;">
            <strong style="color:#fbbf24;">Observação atual para o cliente</strong>
            <div style="margin-top:8px;white-space:pre-wrap;"><?= e($ver['observacao_admin']) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($ver['audio_arquivo'])): ?>
        <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:12px;padding:12px 14px;margin-bottom:16px;">
            <strong style="color:#86efac;">Áudio anexado</strong>
            <audio controls preload="none" style="width:100%;max-width:480px;margin-top:10px;display:block;">
                <source src="../<?= e($ver['audio_arquivo']) ?>" type="audio/mpeg">
            </audio>
        </div>
    <?php endif; ?>

    <h3 style="margin:8px 0 12px;font-size:1.05rem;">Ações</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <!-- Pedir correção -->
        <div style="background:#0f172a;border:1px solid var(--line);border-radius:12px;padding:14px;">
            <strong style="display:block;margin-bottom:8px;">① Pedir correção ao cliente</strong>
            <p class="muted" style="margin-bottom:10px;font-size:.85rem;">O cliente verá a observação e poderá editar o texto e reenviar.</p>
            <form method="post">
                <input type="hidden" name="texto_id" value="<?= intval($ver['id']) ?>">
                <input type="hidden" name="acao" value="pedir_correcao">
                <div class="field">
                    <label>Observação (obrigatória)</label>
                    <textarea name="observacao_admin" rows="4" required placeholder="Ex.: Corrija o nome da cidade no 2º parágrafo..."><?= e($ver['observacao_admin'] ?? '') ?></textarea>
                </div>
                <button class="btn btn-secondary" type="submit">Enviar pedido de correção</button>
            </form>
        </div>

        <!-- Entregar áudio -->
        <div style="background:#0f172a;border:1px solid var(--line);border-radius:12px;padding:14px;">
            <strong style="display:block;margin-bottom:8px;">② Anexar áudio gravado (MP3)</strong>
            <p class="muted" style="margin-bottom:10px;font-size:.85rem;">Quando o texto estiver ok, anexe o MP3. O cliente ouve e baixa na área dele.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="texto_id" value="<?= intval($ver['id']) ?>">
                <input type="hidden" name="acao" value="entregar_audio">
                <div class="field">
                    <label>Arquivo MP3 <?= empty($ver['audio_arquivo']) ? '*' : '(substituir)' ?></label>
                    <input type="file" name="audio" accept="audio/mpeg,audio/mp3,audio/*,.mp3,.m4a,.wav,.ogg" <?= empty($ver['audio_arquivo']) ? 'required' : '' ?>>
                </div>
                <div class="field">
                    <label>Observação (opcional)</label>
                    <textarea name="observacao_admin" rows="3" placeholder="Ex.: Gravação final · 30s · tom institucional"><?= e($stKey === 'entregue' ? ($ver['observacao_admin'] ?? '') : '') ?></textarea>
                </div>
                <button class="btn btn-primary" type="submit">Salvar áudio e liberar ao cliente</button>
            </form>
        </div>
    </div>
    <?php if ($stKey === 'precisa_correcao'): ?>
        <form method="post" style="margin-top:12px;">
            <input type="hidden" name="texto_id" value="<?= intval($ver['id']) ?>">
            <input type="hidden" name="acao" value="voltar_pendente">
            <button class="btn btn-secondary btn-small" type="submit">Cancelar pedido de correção (voltar para pendente)</button>
        </form>
    <?php endif; ?>
</div>
<style>@media(max-width:900px){div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr!important}}</style>
<?php endif; ?>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Status</th>
                <th>Cliente</th>
                <th>WhatsApp</th>
                <th>Título</th>
                <th>Texto</th>
                <th>Áudio</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $c):
            $nome = $c['cliente_nome'] ?: $c['nome'];
            $wa = $c['cliente_whatsapp'] ?: $c['whatsapp'];
            $st = (string)($c['status'] ?? 'pendente');
            if ($st === '') $st = 'pendente';
            $m = app_texto_status_meta($st);
            $destaque = in_array($st, ['pendente', 'corrigido'], true);
        ?>
            <tr style="<?= $destaque ? 'background:rgba(34,197,94,.06)' : '' ?>">
                <td class="muted"><?= e(substr((string)$c['created_at'], 0, 16)) ?></td>
                <td>
                    <span style="font-size:.72rem;font-weight:800;padding:3px 8px;border-radius:999px;color:<?= e($m['color']) ?>;background:<?= e($m['bg']) ?>;white-space:nowrap;">
                        <?= e($m['label']) ?>
                    </span>
                </td>
                <td>
                    <strong><?= e($nome ?: '—') ?></strong>
                    <?php if (!empty($c['cliente_radio'])): ?><div class="muted"><?= e($c['cliente_radio']) ?></div><?php endif; ?>
                </td>
                <td><?= e($wa ?: '—') ?></td>
                <td><?= e($c['titulo'] ?: '—') ?></td>
                <td><?= e(mb_strimwidth($c['texto'] ?? '', 0, 50, '…')) ?></td>
                <td><?= !empty($c['audio_arquivo']) ? '✓' : '—' ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="textos.php?id=<?= intval($c['id']) ?>">Abrir</a>
                    <a class="btn btn-danger btn-small" href="textos.php?del=<?= intval($c['id']) ?>" onclick="return confirm('Excluir?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?>
            <tr><td colspan="8" class="muted">Nenhum texto enviado ainda.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
