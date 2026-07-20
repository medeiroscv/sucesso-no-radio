<?php
require_once __DIR__ . '/_layout.php';
cliente_require_auth();

$cli = cliente_atual();
$tipos = app_conteudo_tipos();
$counts = [];
$recentes = [];
try {
    foreach (array_keys($tipos) as $t) {
        $counts[$t] = count(app_conteudos_por_tipo($t, true));
    }
    // últimas entregas (atualizações recentes)
    $recentes = app_pdo()->query(
        "SELECT e.id AS entrega_id, e.titulo AS entrega_titulo, e.data_ref, e.created_at,
                c.id AS conteudo_id, c.titulo AS conteudo_titulo, c.tipo, c.slug
         FROM conteudo_entregas e
         INNER JOIN conteudos c ON c.id = e.conteudo_id AND c.ativo = 1
         WHERE e.ativo = 1
         ORDER BY e.created_at DESC, e.id DESC
         LIMIT 12"
    )->fetchAll() ?: [];
} catch (Throwable $e) {
    $recentes = [];
}

cliente_header('Bem-vindo, ' . ($cli['nome'] ?? 'cliente'), 'home');
$base = app_base_path();
$prefix = $base === '' ? '' : $base;
?>
<p class="muted" style="margin-top:-8px;margin-bottom:20px;">
    Aqui estão os conteúdos liberados para a sua rádio. Os demonstrativos do site público são só amostras —
    os arquivos de entrega (atualizados diariamente) ficam nesta área.
</p>

<div class="conteudo-hub cliente-hub">
    <?php foreach ($tipos as $key => $meta): ?>
        <a class="conteudo-hub-card cliente-hub-card" href="<?= e($prefix . '/cliente/conteudos.php?tipo=' . rawurlencode($key)) ?>">
            <div class="conteudo-hub-icon"><?= $meta['icon'] ?></div>
            <h3><?= e($meta['label']) ?></h3>
            <p><?= e($meta['desc']) ?></p>
            <div class="conteudo-hub-count"><?= (int)($counts[$key] ?? 0) ?> conteúdo(s)</div>
        </a>
    <?php endforeach; ?>
    <a class="conteudo-hub-card cliente-hub-card cliente-hub-accent" href="<?= e($prefix . '/cliente/texto.php') ?>">
        <div class="conteudo-hub-icon">🎙️</div>
        <h3>Enviar texto</h3>
        <p>Envie o texto para gravação — ele já vai vinculado aos seus dados cadastrados.</p>
        <div class="conteudo-hub-count">Formulário restrito</div>
    </a>
</div>

<section class="section" style="padding-top:8px;">
    <div class="section-head">
        <h2>Atualizações recentes</h2>
        <p>Últimos arquivos de entrega enviados pela equipe.</p>
    </div>
    <?php if (!$recentes): ?>
        <div class="empty">Ainda não há arquivos de entrega. Assim que a equipe publicar, eles aparecem aqui.</div>
    <?php else: ?>
        <div class="cliente-list">
            <?php foreach ($recentes as $r):
                $tipoLabel = $tipos[$r['tipo']]['label'] ?? $r['tipo'];
                $data = $r['data_ref'] ?: substr((string)$r['created_at'], 0, 10);
            ?>
                <a class="cliente-list-item" href="<?= e($prefix . '/cliente/conteudo.php?id=' . intval($r['conteudo_id'])) ?>">
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
