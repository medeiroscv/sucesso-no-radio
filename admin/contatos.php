<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
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
$lista = $pdo->query('SELECT * FROM contatos ORDER BY id DESC LIMIT 200')->fetchAll();
admin_header('Contatos / Leads', 'contatos');
?>
<div class="card">
    <table>
        <thead><tr><th>Data</th><th>Nome</th><th>Contato</th><th>Rádio / Cidade</th><th>Mensagem</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $c): ?>
            <tr style="<?= empty($c['lido']) ? 'background:rgba(34,197,94,.06)' : '' ?>">
                <td class="muted"><?= htmlspecialchars(substr((string)$c['created_at'], 0, 16)) ?></td>
                <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                <td><?= htmlspecialchars($c['telefone'] ?: $c['email']) ?></td>
                <td><?= htmlspecialchars(trim(($c['radio'] ?? '') . ' · ' . ($c['cidade'] ?? ''), ' ·')) ?></td>
                <td><?= htmlspecialchars(mb_strimwidth($c['mensagem'] ?? '', 0, 80, '…')) ?></td>
                <td class="actions">
                    <?php if (empty($c['lido'])): ?>
                        <a class="btn btn-secondary btn-small" href="contatos.php?lido=<?= $c['id'] ?>">Marcar lido</a>
                    <?php endif; ?>
                    <a class="btn btn-danger btn-small" href="contatos.php?del=<?= $c['id'] ?>" onclick="return confirm('Excluir?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?><tr><td colspan="6" class="muted">Nenhum contato ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
