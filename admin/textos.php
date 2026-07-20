<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();

if (isset($_GET['lido'])) {
    $pdo->prepare('UPDATE textos_gravacao SET lido = 1 WHERE id = ?')->execute([intval($_GET['lido'])]);
    header('Location: textos.php');
    exit;
}
if (isset($_GET['del'])) {
    $pdo->prepare('DELETE FROM textos_gravacao WHERE id = ?')->execute([intval($_GET['del'])]);
    header('Location: textos.php');
    exit;
}

$ver = null;
if (isset($_GET['id'])) {
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
}

$lista = [];
try {
    $lista = $pdo->query(
        'SELECT t.*,
                c.nome AS cliente_nome, c.email AS cliente_email, c.whatsapp AS cliente_whatsapp,
                c.telefone AS cliente_telefone, c.radio AS cliente_radio
         FROM textos_gravacao t
         LEFT JOIN clientes c ON c.id = t.cliente_id
         ORDER BY t.id DESC
         LIMIT 200'
    )->fetchAll() ?: [];
} catch (Throwable $e) { /* ok */ }

$novos = count(array_filter($lista, fn($r) => empty($r['lido'])));

admin_header('Textos a gravar', 'textos');
?>
<div class="actions" style="margin-bottom:14px;">
    <span class="muted"><?= count($lista) ?> envio(s)<?= $novos ? " · {$novos} novo(s)" : '' ?></span>
    <a class="btn btn-secondary btn-small" href="clientes.php">Ver clientes</a>
    <a class="btn btn-secondary btn-small" href="configuracoes.php?sec=formulario_texto">Configurar formulário</a>
</div>

<?php if ($ver): ?>
<div class="card" style="margin-bottom:16px;">
    <div class="actions" style="margin-bottom:12px;">
        <a class="btn btn-secondary btn-small" href="textos.php">← Voltar à lista</a>
        <a class="btn btn-danger btn-small" href="textos.php?del=<?= intval($ver['id']) ?>" onclick="return confirm('Excluir este envio?')">Excluir</a>
    </div>

    <h3 style="margin-bottom:6px;">Texto para gravação</h3>
    <p class="muted" style="margin-bottom:14px;"><?= e(substr((string)$ver['created_at'], 0, 16)) ?></p>

    <div style="background:#0f172a;border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:14px;">
        <strong style="display:block;margin-bottom:10px;">Cliente que enviou</strong>
        <?php
        $nomeExibir = $ver['cliente_nome'] ?: $ver['nome'];
        $emailExibir = $ver['cliente_email'] ?: $ver['email'];
        $waExibir = $ver['cliente_whatsapp'] ?: $ver['whatsapp'];
        $telExibir = $ver['cliente_telefone'] ?: $ver['telefone'];
        $radioExibir = $ver['cliente_radio'] ?? '';
        $cliId = intval($ver['cliente_cadastro_id'] ?? $ver['cliente_id'] ?? 0);
        ?>
        <div style="display:grid;gap:6px;font-size:.92rem;">
            <div><strong>Nome:</strong> <?= e($nomeExibir ?: '—') ?></div>
            <div><strong>E-mail:</strong>
                <?php if ($emailExibir): ?>
                    <a href="mailto:<?= e($emailExibir) ?>"><?= e($emailExibir) ?></a>
                <?php else: ?>—<?php endif; ?>
            </div>
            <div><strong>WhatsApp:</strong>
                <?php if ($waExibir):
                    $waNum = preg_replace('/\D+/', '', $waExibir);
                ?>
                    <?= e($waExibir) ?>
                    <?php if ($waNum): ?>
                        · <a href="https://wa.me/<?= e($waNum) ?>" target="_blank" rel="noopener">Abrir chat</a>
                    <?php endif; ?>
                <?php else: ?>—<?php endif; ?>
            </div>
            <?php if ($telExibir): ?><div><strong>Telefone:</strong> <?= e($telExibir) ?></div><?php endif; ?>
            <?php if ($radioExibir): ?><div><strong>Rádio:</strong> <?= e($radioExibir) ?></div><?php endif; ?>
            <?php if (!empty($ver['cliente_cidade'])): ?><div><strong>Cidade:</strong> <?= e($ver['cliente_cidade']) ?></div><?php endif; ?>
            <?php if ($cliId > 0): ?>
                <div style="margin-top:6px;">
                    <a class="btn btn-secondary btn-small" href="clientes.php?id=<?= $cliId ?>">Abrir cadastro do cliente</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($ver['titulo'])): ?>
        <p style="margin-bottom:10px;"><strong>Título / referência:</strong> <?= e($ver['titulo']) ?></p>
    <?php endif; ?>
    <div style="background:#0f172a;border:1px solid var(--line);border-radius:12px;padding:14px;white-space:pre-wrap;line-height:1.55;">
        <?= e($ver['texto'] ?? '') ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Cliente</th>
                <th>E-mail</th>
                <th>WhatsApp</th>
                <th>Título</th>
                <th>Texto</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $c):
            $nome = $c['cliente_nome'] ?: $c['nome'];
            $email = $c['cliente_email'] ?: $c['email'];
            $wa = $c['cliente_whatsapp'] ?: $c['whatsapp'];
        ?>
            <tr style="<?= empty($c['lido']) ? 'background:rgba(34,197,94,.06)' : '' ?>">
                <td class="muted"><?= e(substr((string)$c['created_at'], 0, 16)) ?></td>
                <td>
                    <strong><?= e($nome ?: '—') ?></strong>
                    <?php if (!empty($c['cliente_radio'])): ?>
                        <div class="muted"><?= e($c['cliente_radio']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= e($email ?: '—') ?></td>
                <td><?= e($wa ?: '—') ?></td>
                <td><?= e($c['titulo'] ?: '—') ?></td>
                <td><?= e(mb_strimwidth($c['texto'] ?? '', 0, 60, '…')) ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="textos.php?id=<?= intval($c['id']) ?>">Abrir</a>
                    <?php if (empty($c['lido'])): ?>
                        <a class="btn btn-secondary btn-small" href="textos.php?lido=<?= intval($c['id']) ?>">Lido</a>
                    <?php endif; ?>
                    <a class="btn btn-danger btn-small" href="textos.php?del=<?= intval($c['id']) ?>" onclick="return confirm('Excluir?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?>
            <tr><td colspan="7" class="muted">Nenhum texto enviado ainda. Os clientes enviam pela área logada.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
