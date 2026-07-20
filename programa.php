<?php
require_once __DIR__ . '/includes/layout_public.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$base = app_base_path();
$programa = null;
$tipos = app_conteudo_tipos();

try {
    if ($slug !== '') {
        $programa = app_conteudo_by_slug($slug, 'demonstrativo');
        // fallback legado
        if (!$programa) {
            $st = app_pdo()->prepare('SELECT * FROM programas WHERE slug = ? AND ativo = 1 LIMIT 1');
            $st->execute([$slug]);
            $row = $st->fetch();
            if ($row) {
                $programa = $row;
                $programa['tipo'] = app_map_periodo_to_tipo((string)($row['periodo'] ?? 'diario'), intval($row['categoria_id'] ?? 0), app_pdo());
            }
        }
    }
} catch (Throwable $e) {
    $programa = null;
}

if (!$programa) {
    http_response_code(404);
    layout_header('Conteúdo não encontrado');
    echo '<div class="container page-title"><h1>Conteúdo não encontrado</h1><p><a href="' . e($base === '' ? '/' : $base . '/') . '">Voltar ao início</a></p></div>';
    layout_footer();
    exit;
}

$tipo = (string)($programa['tipo'] ?? 'diario');
$tipoLabel = $tipos[$tipo]['label'] ?? 'Conteúdo';

layout_header($programa['titulo']);
$capa = !empty($programa['capa']) ? (($base === '' ? '' : $base) . '/' . ltrim($programa['capa'], '/')) : '';
$msg = $programa['whatsapp_msg'] ?: ('Olá! Quero contratar: ' . $programa['titulo']);

$demos = app_demonstrativos('conteudo', intval($programa['id']));
if (!$demos) {
    $demos = app_demonstrativos('programa', intval($programa['id']));
}
if (!$demos && $tipo === 'programete') {
    $demos = app_demonstrativos('programete', intval($programa['id']));
}
?>
<main class="container">
    <div class="page-title">
        <p style="color:var(--muted);font-size:.9rem;">
            <a href="<?= e($base === '' ? '/' : $base . '/') ?>">Início</a> · <?= e($tipoLabel) ?>
        </p>
        <h1><?= e($programa['titulo']) ?></h1>
    </div>
    <div class="destaque" style="margin-bottom:40px;">
        <div>
            <div class="card-meta" style="margin-bottom:12px;">
                <span class="chip"><?= e($tipoLabel) ?></span>
                <?php if (!empty($programa['duracao'])): ?><span class="chip"><?= e($programa['duracao']) ?></span><?php endif; ?>
                <?php if (!empty($programa['blocos'])): ?><span class="chip"><?= e($programa['blocos']) ?></span><?php endif; ?>
                <?php if (!empty($programa['dias'])): ?><span class="chip"><?= e($programa['dias']) ?></span><?php endif; ?>
                <?php if (!empty($programa['insercoes'])): ?><span class="chip"><?= e($programa['insercoes']) ?></span><?php endif; ?>
            </div>
            <?php if (!empty($programa['resumo'])): ?>
                <p style="font-size:1.05rem;margin-bottom:12px;"><?= e($programa['resumo']) ?></p>
            <?php endif; ?>
            <div style="color:var(--muted);white-space:pre-wrap;"><?= e($programa['descricao'] ?: '') ?></div>
            <?php if ($demos): ?>
            <div style="margin-top:22px;">
                <h3 style="margin-bottom:12px;font-size:1.1rem;">Demonstrativos</h3>
                <div style="display:grid;gap:12px;">
                    <?php foreach ($demos as $d): ?>
                        <div style="background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px 14px;">
                            <div style="font-weight:700;margin-bottom:8px;font-size:.95rem;"><?= e($d['titulo'] ?: 'Áudio') ?></div>
                            <audio controls preload="none" style="width:100%;">
                                <source src="<?= e(($base === '' ? '' : $base) . '/' . ltrim($d['arquivo'], '/')) ?>" type="audio/mpeg">
                            </audio>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="hero-actions">
                <a class="btn btn-wa" href="<?= e(wa_link($msg)) ?>" target="_blank" rel="noopener">Contratar no WhatsApp</a>
                <a class="btn btn-ghost" href="<?= e($base === '' ? '/' : $base . '/') ?>">Ver catálogo</a>
            </div>
        </div>
        <?php if ($capa): ?>
            <img src="<?= e($capa) ?>" alt="<?= e($programa['titulo']) ?>">
        <?php endif; ?>
    </div>
</main>
<?php layout_footer(); ?>
