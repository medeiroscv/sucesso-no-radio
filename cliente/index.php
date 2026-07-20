<?php
require_once __DIR__ . '/_layout.php';
cliente_require_auth();

$cli = cliente_atual();
$cliId = intval($cli['id'] ?? 0);
$tipos = app_conteudo_tipos();
$counts = [];
$recentes = [];
$totalLiberados = 0;

try {
    foreach (array_keys($tipos) as $t) {
        $n = count(cliente_conteudos_por_tipo($cliId, $t, $cli));
        $counts[$t] = $n;
        $totalLiberados += $n;
    }

    if (cliente_tem_acesso_total($cli)) {
        $recentes = app_pdo()->query(
            "SELECT e.id AS entrega_id, e.titulo AS entrega_titulo, e.data_ref, e.created_at,
                    c.id AS conteudo_id, c.titulo AS conteudo_titulo, c.tipo, c.slug
             FROM conteudo_entregas e
             INNER JOIN conteudos c ON c.id = e.conteudo_id AND c.ativo = 1
             WHERE e.ativo = 1
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT 12"
        )->fetchAll() ?: [];
    } else {
        $st = app_pdo()->prepare(
            "SELECT e.id AS entrega_id, e.titulo AS entrega_titulo, e.data_ref, e.created_at,
                    c.id AS conteudo_id, c.titulo AS conteudo_titulo, c.tipo, c.slug
             FROM conteudo_entregas e
             INNER JOIN conteudos c ON c.id = e.conteudo_id AND c.ativo = 1
             INNER JOIN cliente_conteudos cc ON cc.conteudo_id = c.id AND cc.cliente_id = ?
             WHERE e.ativo = 1
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT 12"
        );
        $st->execute([$cliId]);
        $recentes = $st->fetchAll() ?: [];
    }
} catch (Throwable $e) {
    $recentes = [];
}

cliente_header('Olá, ' . ($cli['nome'] ?? 'cliente'), 'home');
?>
<p class="cliente-intro">
    Aqui ficam os conteúdos <strong>liberados para o seu cadastro</strong>.
    Os demonstrativos da página inicial são só amostras — os arquivos de entrega ficam nesta área.
    <?php if (cliente_tem_acesso_total($cli)): ?>
        <br><span class="chip" style="margin-top:8px;display:inline-block;">Acesso total liberado</span>
    <?php else: ?>
        <br><span class="muted" style="font-size:.9rem;"><?= (int)$totalLiberados ?> conteúdo(s) liberado(s)</span>
    <?php endif; ?>
</p>

<div class="cliente-hub">
    <?php foreach ($tipos as $key => $meta): ?>
        <a class="cliente-hub-card" href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($key))) ?>">
            <div class="conteudo-hub-icon"><?= $meta['icon'] ?></div>
            <h3><?= e($meta['label']) ?></h3>
            <p><?= e($meta['desc']) ?></p>
            <div class="conteudo-hub-count"><?= (int)($counts[$key] ?? 0) ?> liberado(s)</div>
        </a>
    <?php endforeach; ?>
    <a class="cliente-hub-card cliente-hub-accent" href="<?= e(app_url('cliente/texto.php')) ?>">
        <div class="conteudo-hub-icon">🎙️</div>
        <h3>Meus textos</h3>
        <p>Envie textos, acompanhe correções e baixe o áudio gravado.</p>
        <div class="conteudo-hub-count">Gravação &amp; status</div>
    </a>
</div>

<section class="section">
    <div class="section-head">
        <h2>Atualizações recentes</h2>
        <p>Últimos arquivos de entrega dos conteúdos liberados para você.</p>
    </div>
    <?php if ($totalLiberados === 0 && !cliente_tem_acesso_total($cli)): ?>
        <div class="empty">Nenhum conteúdo foi liberado ainda para o seu cadastro. Fale com a equipe para liberar o acesso.</div>
    <?php elseif (!$recentes): ?>
        <div class="empty">Ainda não há arquivos de entrega. Assim que a equipe publicar, eles aparecem aqui.</div>
    <?php else: ?>
        <div class="cliente-list">
            <?php foreach ($recentes as $r):
                $tipoLabel = $tipos[$r['tipo']]['label'] ?? $r['tipo'];
                $data = $r['data_ref'] ?: substr((string)$r['created_at'], 0, 10);
            ?>
                <a class="cliente-list-item" href="<?= e(app_url('cliente/conteudo.php?id=' . intval($r['conteudo_id']))) ?>">
                    <div>
                        <strong><?= e($r['entrega_titulo'] ?: $r['conteudo_titulo']) ?></strong>
                        <div class="muted" style="font-size:.88rem;margin-top:4px;">
                            <?= e($r['conteudo_titulo']) ?> · <?= e($tipoLabel) ?>
                        </div>
                    </div>
                    <div class="cliente-list-meta">
                        <span class="chip"><?= e($data) ?></span>
                        <span class="btn btn-ghost btn-small">Abrir</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php cliente_footer(); ?>
