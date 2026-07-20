<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$tipo = trim((string)($_GET['tipo'] ?? 'contato'));
if (!in_array($tipo, ['contato', 'texto'], true)) {
    $tipo = 'contato';
}

if (isset($_GET['lido'])) {
    $id = intval($_GET['lido']);
    if ($tipo === 'texto') {
        $pdo->prepare('UPDATE textos_gravacao SET lido = 1 WHERE id = ?')->execute([$id]);
    } else {
        $pdo->prepare('UPDATE contatos SET lido = 1 WHERE id = ?')->execute([$id]);
    }
    header('Location: contatos.php?tipo=' . rawurlencode($tipo));
    exit;
}
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    if ($tipo === 'texto') {
        $pdo->prepare('DELETE FROM textos_gravacao WHERE id = ?')->execute([$id]);
    } else {
        $pdo->prepare('DELETE FROM contatos WHERE id = ?')->execute([$id]);
    }
    header('Location: contatos.php?tipo=' . rawurlencode($tipo));
    exit;
}

$ver = null;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($tipo === 'texto') {
        $st = $pdo->prepare('SELECT * FROM textos_gravacao WHERE id = ?');
    } else {
        $st = $pdo->prepare('SELECT * FROM contatos WHERE id = ?');
    }
    $st->execute([$id]);
    $ver = $st->fetch() ?: null;
    if ($ver && empty($ver['lido'])) {
        if ($tipo === 'texto') {
            $pdo->prepare('UPDATE textos_gravacao SET lido = 1 WHERE id = ?')->execute([$id]);
        } else {
            $pdo->prepare('UPDATE contatos SET lido = 1 WHERE id = ?')->execute([$id]);
        }
        $ver['lido'] = 1;
    }
}

$listaContato = [];
$listaTexto = [];
try {
    $listaContato = $pdo->query('SELECT * FROM contatos ORDER BY id DESC LIMIT 200')->fetchAll();
    $listaTexto = $pdo->query('SELECT * FROM textos_gravacao ORDER BY id DESC LIMIT 200')->fetchAll();
} catch (Throwable $e) { /* ok */ }

$qC = count($listaContato);
$qT = count($listaTexto);
$novosC = count(array_filter($listaContato, fn($r) => empty($r['lido'])));
$novosT = count(array_filter($listaTexto, fn($r) => empty($r['lido'])));

admin_header($tipo === 'texto' ? 'Textos para gravação' : 'Contatos', 'contatos');
?>
<div class="actions" style="margin-bottom:14px;">
    <a class="btn btn-small <?= $tipo === 'contato' ? 'btn-primary' : 'btn-secondary' ?>" href="contatos.php?tipo=contato">
        ✉️ Contato (<?= $qC ?><?= $novosC ? " · {$novosC} novos" : '' ?>)
    </a>
    <a class="btn btn-small <?= $tipo === 'texto' ? 'btn-primary' : 'btn-secondary' ?>" href="contatos.php?tipo=texto">
        🎙️ Textos (<?= $qT ?><?= $novosT ? " · {$novosT} novos" : '' ?>)
    </a>
    <a class="btn btn-secondary btn-small" href="configuracoes.php?sec=<?= $tipo === 'texto' ? 'formulario_texto' : 'formulario_contato' ?>">Configurar formulário</a>
</div>

<?php if ($ver): ?>
<div class="card" style="margin-bottom:16px;">
    <div class="actions" style="margin-bottom:12px;">
        <a class="btn btn-secondary btn-small" href="contatos.php?tipo=<?= e($tipo) ?>">← Voltar à lista</a>
        <a class="btn btn-danger btn-small" href="contatos.php?tipo=<?= e($tipo) ?>&del=<?= intval($ver['id']) ?>" onclick="return confirm('Excluir este envio?')">Excluir</a>
    </div>
    <h3 style="margin-bottom:12px;"><?= e($ver['nome'] ?: 'Sem nome') ?></h3>
    <p class="muted" style="margin-bottom:10px;"><?= e(substr((string)$ver['created_at'], 0, 16)) ?></p>
    <div style="display:grid;gap:6px;margin-bottom:14px;font-size:.92rem;">
        <?php if (!empty($ver['email'])): ?><div><strong>E-mail:</strong> <?= e($ver['email']) ?></div><?php endif; ?>
        <?php if (!empty($ver['telefone'])): ?><div><strong>Telefone:</strong> <?= e($ver['telefone']) ?></div><?php endif; ?>
        <?php if (!empty($ver['whatsapp'])): ?><div><strong>WhatsApp:</strong> <?= e($ver['whatsapp']) ?></div><?php endif; ?>
        <?php if ($tipo === 'texto' && !empty($ver['titulo'])): ?><div><strong>Título:</strong> <?= e($ver['titulo']) ?></div><?php endif; ?>
        <?php if ($tipo === 'contato' && (!empty($ver['radio']) || !empty($ver['cidade']))): ?>
            <div><strong>Rádio / Cidade:</strong> <?= e(trim(($ver['radio'] ?? '') . ' · ' . ($ver['cidade'] ?? ''), ' ·')) ?></div>
        <?php endif; ?>
    </div>
    <div style="background:#0f172a;border:1px solid var(--line);border-radius:12px;padding:14px;white-space:pre-wrap;line-height:1.55;">
        <?= e($tipo === 'texto' ? ($ver['texto'] ?? '') : ($ver['mensagem'] ?? '')) ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
<?php if ($tipo === 'texto'): ?>
    <table>
        <thead><tr><th>Data</th><th>Nome</th><th>Contato</th><th>Título</th><th>Texto</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($listaTexto as $c): ?>
            <tr style="<?= empty($c['lido']) ? 'background:rgba(34,197,94,.06)' : '' ?>">
                <td class="muted"><?= e(substr((string)$c['created_at'], 0, 16)) ?></td>
                <td><strong><?= e($c['nome']) ?></strong></td>
                <td><?= e($c['whatsapp'] ?: $c['telefone'] ?: $c['email']) ?></td>
                <td><?= e($c['titulo'] ?: '—') ?></td>
                <td><?= e(mb_strimwidth($c['texto'] ?? '', 0, 70, '…')) ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="contatos.php?tipo=texto&id=<?= intval($c['id']) ?>">Abrir</a>
                    <?php if (empty($c['lido'])): ?>
                        <a class="btn btn-secondary btn-small" href="contatos.php?tipo=texto&lido=<?= intval($c['id']) ?>">Lido</a>
                    <?php endif; ?>
                    <a class="btn btn-danger btn-small" href="contatos.php?tipo=texto&del=<?= intval($c['id']) ?>" onclick="return confirm('Excluir?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$listaTexto): ?><tr><td colspan="6" class="muted">Nenhum texto enviado ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
<?php else: ?>
    <table>
        <thead><tr><th>Data</th><th>Nome</th><th>Contato</th><th>Mensagem</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($listaContato as $c): ?>
            <tr style="<?= empty($c['lido']) ? 'background:rgba(34,197,94,.06)' : '' ?>">
                <td class="muted"><?= e(substr((string)$c['created_at'], 0, 16)) ?></td>
                <td><strong><?= e($c['nome']) ?></strong></td>
                <td>
                    <?= e($c['whatsapp'] ?: $c['telefone'] ?: $c['email']) ?>
                    <?php if (!empty($c['email']) && ($c['whatsapp'] || $c['telefone'])): ?>
                        <div class="muted"><?= e($c['email']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= e(mb_strimwidth($c['mensagem'] ?? '', 0, 80, '…')) ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="contatos.php?tipo=contato&id=<?= intval($c['id']) ?>">Abrir</a>
                    <?php if (empty($c['lido'])): ?>
                        <a class="btn btn-secondary btn-small" href="contatos.php?tipo=contato&lido=<?= intval($c['id']) ?>">Lido</a>
                    <?php endif; ?>
                    <a class="btn btn-danger btn-small" href="contatos.php?tipo=contato&del=<?= intval($c['id']) ?>" onclick="return confirm('Excluir?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$listaContato): ?><tr><td colspan="5" class="muted">Nenhum contato ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
<?php admin_footer(); ?>
