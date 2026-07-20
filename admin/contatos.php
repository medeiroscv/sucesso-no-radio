<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();

// Redireciona links antigos ?tipo=texto
if (isset($_GET['tipo']) && $_GET['tipo'] === 'texto') {
    $q = [];
    if (!empty($_GET['id'])) $q[] = 'id=' . intval($_GET['id']);
    if (!empty($_GET['lido'])) $q[] = 'lido=' . intval($_GET['lido']);
    if (!empty($_GET['del'])) $q[] = 'del=' . intval($_GET['del']);
    header('Location: textos.php' . ($q ? '?' . implode('&', $q) : ''));
    exit;
}

if (isset($_GET['lido'])) {
    $pdo->prepare('UPDATE contatos SET lido = 1 WHERE id = ?')->execute([intval($_GET['lido'])]);
    header('Location: contatos.php');
    exit;
}
if (isset($_GET['del'])) {
    $pdo->prepare('DELETE FROM contatos WHERE id = ?')->execute([intval($_GET['del'])]);
    header('Location: contatos.php');
    exit;
}

$ver = null;
if (isset($_GET['id'])) {
    $st = $pdo->prepare('SELECT * FROM contatos WHERE id = ?');
    $st->execute([intval($_GET['id'])]);
    $ver = $st->fetch() ?: null;
    if ($ver && empty($ver['lido'])) {
        $pdo->prepare('UPDATE contatos SET lido = 1 WHERE id = ?')->execute([intval($ver['id'])]);
        $ver['lido'] = 1;
    }
}

$lista = [];
try {
    $lista = $pdo->query('SELECT * FROM contatos ORDER BY id DESC LIMIT 200')->fetchAll() ?: [];
} catch (Throwable $e) { /* ok */ }

$novos = count(array_filter($lista, fn($r) => empty($r['lido'])));

admin_header('Contatos', 'contatos');
?>
<div class="actions" style="margin-bottom:14px;">
    <span class="muted"><?= count($lista) ?> mensagem(ns)<?= $novos ? " · {$novos} nova(s)" : '' ?></span>
    <a class="btn btn-secondary btn-small" href="configuracoes.php?sec=formulario_contato">Configurar formulário</a>
</div>

<?php if ($ver): ?>
<div class="card" style="margin-bottom:16px;">
    <div class="actions" style="margin-bottom:12px;">
        <a class="btn btn-secondary btn-small" href="contatos.php">← Voltar à lista</a>
        <a class="btn btn-danger btn-small" href="contatos.php?del=<?= intval($ver['id']) ?>" onclick="return confirm('Excluir este envio?')">Excluir</a>
    </div>
    <h3 style="margin-bottom:12px;"><?= e($ver['nome'] ?: 'Sem nome') ?></h3>
    <p class="muted" style="margin-bottom:10px;"><?= e(substr((string)$ver['created_at'], 0, 16)) ?></p>
    <div style="display:grid;gap:6px;margin-bottom:14px;font-size:.92rem;">
        <?php if (!empty($ver['email'])): ?><div><strong>E-mail:</strong> <a href="mailto:<?= e($ver['email']) ?>"><?= e($ver['email']) ?></a></div><?php endif; ?>
        <?php if (!empty($ver['telefone'])): ?><div><strong>Telefone:</strong> <?= e($ver['telefone']) ?></div><?php endif; ?>
        <?php if (!empty($ver['whatsapp'])):
            $waNum = preg_replace('/\D+/', '', $ver['whatsapp']);
        ?>
            <div><strong>WhatsApp:</strong> <?= e($ver['whatsapp']) ?>
                <?php if ($waNum): ?> · <a href="https://wa.me/<?= e($waNum) ?>" target="_blank" rel="noopener">Abrir chat</a><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($ver['radio']) || !empty($ver['cidade'])): ?>
            <div><strong>Rádio / Cidade:</strong> <?= e(trim(($ver['radio'] ?? '') . ' · ' . ($ver['cidade'] ?? ''), ' ·')) ?></div>
        <?php endif; ?>
    </div>
    <div style="background:#0f172a;border:1px solid var(--line);border-radius:12px;padding:14px;white-space:pre-wrap;line-height:1.55;">
        <?= e($ver['mensagem'] ?? '') ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <table>
        <thead><tr><th>Data</th><th>Nome</th><th>Contato</th><th>Mensagem</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $c): ?>
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
                    <a class="btn btn-secondary btn-small" href="contatos.php?id=<?= intval($c['id']) ?>">Abrir</a>
                    <?php if (empty($c['lido'])): ?>
                        <a class="btn btn-secondary btn-small" href="contatos.php?lido=<?= intval($c['id']) ?>">Lido</a>
                    <?php endif; ?>
                    <a class="btn btn-danger btn-small" href="contatos.php?del=<?= intval($c['id']) ?>" onclick="return confirm('Excluir?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?><tr><td colspan="5" class="muted">Nenhum contato ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
