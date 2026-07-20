<?php
require_once __DIR__ . '/includes/layout_public.php';

$pdo = null;
$erro = '';
$programasDiarios = [];
$programasFs = [];
$jornalismo = [];
$programetes = [];
$banners = [];
$campanhas = [];

try {
    $pdo = app_pdo();
    $programasDiarios = $pdo->query(
        "SELECT p.*, c.slug AS cat_slug FROM programas p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.ativo = 1 AND (c.slug = 'programas-diarios' OR (c.slug IS NULL AND p.periodo = 'diario'))
         ORDER BY p.destaque DESC, p.ordem ASC, p.titulo ASC"
    )->fetchAll();
    $programasFs = $pdo->query(
        "SELECT p.*, c.slug AS cat_slug FROM programas p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.ativo = 1 AND (c.slug = 'fim-de-semana' OR p.periodo = 'fim_semana')
         ORDER BY p.ordem ASC, p.titulo ASC"
    )->fetchAll();
    $jornalismo = $pdo->query(
        "SELECT p.* FROM programas p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.ativo = 1 AND (c.slug = 'jornalismo' OR p.periodo = 'jornalismo')
         ORDER BY p.ordem ASC, p.titulo ASC"
    )->fetchAll();
    $campanhas = $pdo->query(
        "SELECT p.* FROM programas p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.ativo = 1 AND (c.slug = 'campanhas' OR p.destaque = 1)
         ORDER BY p.ordem ASC LIMIT 6"
    )->fetchAll();
    $programetes = $pdo->query(
        "SELECT * FROM programetes WHERE ativo = 1 ORDER BY ordem ASC, titulo ASC"
    )->fetchAll();
    $banners = $pdo->query(
        "SELECT * FROM banners WHERE ativo = 1 ORDER BY ordem ASC, id DESC LIMIT 5"
    )->fetchAll();
} catch (Throwable $e) {
    $erro = 'Conteúdo ainda não disponível. Configure o banco no EasyPanel (AUTO_INSTALL + Postgres).';
}

$s = site_settings_all();
layout_header('', 'home');
$base = app_base_path();
?>
<main>
    <section class="hero container">
        <div class="hero-grid">
            <div>
                <h1><?= e($s['site_slogan'] ?? 'Tudo que sua rádio precisa em um só lugar') ?></h1>
                <p><?= e($s['sobre'] ?? 'Programas, programetes e jornalismo profissionais para a sua grade — gerenciados com facilidade.') ?></p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="#programas">Ver programas</a>
                    <a class="btn btn-wa" href="<?= e(wa_link('Olá! Quero conhecer os planos da ' . ($s['site_nome'] ?? 'Sucesso no Rádio'))) ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
                </div>
            </div>
            <div class="hero-card">
                <h3>Para rádios e web rádios</h3>
                <p style="color:var(--muted);font-size:.95rem;">Conteúdo pronto para ir ao ar, com blocos e duração definidos.</p>
                <ul>
                    <li>Programas diários e de fim de semana</li>
                    <li>Jornalismo e boletins</li>
                    <li>Programetes e dicas</li>
                    <li>Campanhas sazonais</li>
                </ul>
            </div>
        </div>
    </section>

    <?php if ($erro): ?>
        <div class="container"><div class="alert alert-err"><?= e($erro) ?></div></div>
    <?php endif; ?>

    <?php if ($banners): ?>
    <section class="section container">
        <?php foreach ($banners as $b): ?>
            <div class="destaque" style="margin-bottom:18px;">
                <div>
                    <h2><?= e($b['titulo'] ?: 'Destaque') ?></h2>
                    <?php if ($b['subtitulo']): ?><p style="color:var(--muted);margin-top:8px;"><?= e($b['subtitulo']) ?></p><?php endif; ?>
                    <div class="hero-actions">
                        <?php if ($b['link']): ?>
                            <a class="btn btn-primary" href="<?= e($b['link']) ?>" target="_blank" rel="noopener"><?= e($b['botao_texto'] ?: 'Saiba mais') ?></a>
                        <?php else: ?>
                            <a class="btn btn-wa" href="<?= e(wa_link('Olá! Vi o destaque: ' . ($b['titulo'] ?: ''))) ?>" target="_blank"><?= e($b['botao_texto'] ?: 'Contratar') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($b['imagem']): ?>
                    <img src="<?= e(($base === '' ? '' : $base) . '/' . ltrim($b['imagem'], '/')) ?>" alt="<?= e($b['titulo']) ?>" loading="lazy">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="section container" id="programas">
        <div class="section-head">
            <h2>Programas diários</h2>
            <p>Produções para a grade de segunda a sábado — gerenciadas no painel admin.</p>
        </div>
        <?php if (!$programasDiarios): ?>
            <div class="empty">Nenhum programa cadastrado ainda. Acesse o <a href="<?= e(($base === '' ? '' : $base) . '/admin/') ?>">admin</a> e adicione conteúdos.</div>
        <?php else: ?>
            <div class="grid-cards">
                <?php foreach ($programasDiarios as $p): ?>
                    <?php
                    $capa = $p['capa'] ? (($base === '' ? '' : $base) . '/' . ltrim($p['capa'], '/')) : '';
                    $msg = $p['whatsapp_msg'] ?: ('Olá! Quero contratar o programa: ' . $p['titulo']);
                    $detalhe = ($base === '' ? '' : $base) . '/programa.php?slug=' . rawurlencode($p['slug']);
                    ?>
                    <article class="card">
                        <?php if ($capa): ?><img class="card-cover" src="<?= e($capa) ?>" alt="<?= e($p['titulo']) ?>" loading="lazy"><?php else: ?>
                        <div class="card-cover" style="display:grid;place-items:center;color:var(--muted);font-weight:700;">🎙</div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h3><?= e($p['titulo']) ?></h3>
                            <div class="card-meta">
                                <?php if ($p['duracao']): ?><span class="chip"><?= e($p['duracao']) ?></span><?php endif; ?>
                                <?php if ($p['blocos']): ?><span class="chip"><?= e($p['blocos']) ?></span><?php endif; ?>
                                <?php if ($p['dias']): ?><span class="chip"><?= e($p['dias']) ?></span><?php endif; ?>
                            </div>
                            <p class="card-desc"><?= e($p['resumo'] ?: mb_strimwidth(strip_tags($p['descricao'] ?? ''), 0, 120, '…')) ?></p>
                            <div class="card-actions">
                                <a class="btn btn-ghost btn-small" href="<?= e($detalhe) ?>">Detalhes</a>
                                <a class="btn btn-wa btn-small" href="<?= e(wa_link($msg)) ?>" target="_blank">Contratar</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="section container" id="fim-de-semana">
        <div class="section-head">
            <h2>Programas de fim de semana</h2>
            <p>Opções para sábado e domingo.</p>
        </div>
        <?php if (!$programasFs): ?>
            <div class="empty">Cadastre programas na categoria “Fim de Semana” no admin.</div>
        <?php else: ?>
            <div class="grid-cards">
                <?php foreach ($programasFs as $p): ?>
                    <?php
                    $capa = $p['capa'] ? (($base === '' ? '' : $base) . '/' . ltrim($p['capa'], '/')) : '';
                    $msg = $p['whatsapp_msg'] ?: ('Olá! Quero o programa de fim de semana: ' . $p['titulo']);
                    $detalhe = ($base === '' ? '' : $base) . '/programa.php?slug=' . rawurlencode($p['slug']);
                    ?>
                    <article class="card">
                        <?php if ($capa): ?><img class="card-cover" src="<?= e($capa) ?>" alt="<?= e($p['titulo']) ?>" loading="lazy"><?php else: ?>
                        <div class="card-cover" style="display:grid;place-items:center;color:var(--muted);font-weight:700;">🎵</div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h3><?= e($p['titulo']) ?></h3>
                            <div class="card-meta">
                                <?php if ($p['duracao']): ?><span class="chip"><?= e($p['duracao']) ?></span><?php endif; ?>
                                <?php if ($p['blocos']): ?><span class="chip"><?= e($p['blocos']) ?></span><?php endif; ?>
                            </div>
                            <p class="card-desc"><?= e($p['resumo'] ?: '') ?></p>
                            <div class="card-actions">
                                <a class="btn btn-ghost btn-small" href="<?= e($detalhe) ?>">Detalhes</a>
                                <a class="btn btn-wa btn-small" href="<?= e(wa_link($msg)) ?>" target="_blank">Contratar</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="section container" id="jornalismo">
        <div class="section-head">
            <h2>Jornalismo</h2>
            <p>Notícias e boletins para a sua emissora.</p>
        </div>
        <?php if (!$jornalismo): ?>
            <div class="empty">Cadastre itens na categoria Jornalismo.</div>
        <?php else: ?>
            <div class="grid-cards">
                <?php foreach ($jornalismo as $p): ?>
                    <?php $msg = $p['whatsapp_msg'] ?: ('Olá! Quero o jornalismo: ' . $p['titulo']); ?>
                    <article class="card">
                        <div class="card-body">
                            <h3><?= e($p['titulo']) ?></h3>
                            <div class="card-meta">
                                <?php if ($p['duracao']): ?><span class="chip"><?= e($p['duracao']) ?></span><?php endif; ?>
                                <?php if ($p['blocos']): ?><span class="chip"><?= e($p['blocos']) ?></span><?php endif; ?>
                                <?php if ($p['dias']): ?><span class="chip"><?= e($p['dias']) ?></span><?php endif; ?>
                            </div>
                            <p class="card-desc"><?= e($p['resumo'] ?: '') ?></p>
                            <div class="card-actions">
                                <a class="btn btn-wa btn-small" href="<?= e(wa_link($msg)) ?>" target="_blank">Contratar</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="section container" id="programetes">
        <div class="section-head">
            <h2>Programetes</h2>
            <p>Pacotes de dicas e inserções rápidas.</p>
        </div>
        <?php if (!$programetes): ?>
            <div class="empty">Cadastre programetes no admin.</div>
        <?php else: ?>
            <div class="grid-cards">
                <?php foreach ($programetes as $pr): ?>
                    <?php $demosPr = app_demonstrativos('programete', intval($pr['id'])); ?>
                    <article class="card">
                        <div class="card-body">
                            <h3><?= e($pr['titulo']) ?></h3>
                            <?php if ($pr['insercoes']): ?><div class="card-meta"><span class="chip"><?= e($pr['insercoes']) ?></span></div><?php endif; ?>
                            <p class="card-desc"><?= e($pr['descricao'] ?: '') ?></p>
                            <?php if ($demosPr): ?>
                                <div style="display:grid;gap:8px;margin:4px 0 8px;">
                                    <?php foreach ($demosPr as $d): ?>
                                        <div>
                                            <div style="font-size:.8rem;font-weight:700;margin-bottom:4px;color:var(--muted);"><?= e($d['titulo'] ?: 'Demo') ?></div>
                                            <audio controls preload="none" style="width:100%;height:36px;">
                                                <source src="<?= e(($base === '' ? '' : $base) . '/' . ltrim($d['arquivo'], '/')) ?>" type="audio/mpeg">
                                            </audio>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="card-actions">
                                <a class="btn btn-wa btn-small" href="<?= e(wa_link('Olá! Quero o programete: ' . $pr['titulo'])) ?>" target="_blank">Contratar</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="section container">
        <div class="destaque">
            <div>
                <h2>Quer montar a grade da sua rádio?</h2>
                <p style="color:var(--muted);margin-top:8px;">Fale com a gente no WhatsApp e receba indicação de programas sob medida para o seu público.</p>
                <div class="hero-actions">
                    <a class="btn btn-wa" href="<?= e(wa_link('Olá! Quero montar a grade da minha rádio com a ' . ($s['site_nome'] ?? 'Sucesso no Rádio'))) ?>" target="_blank">Chamar no WhatsApp</a>
                    <a class="btn btn-ghost" href="<?= e(($base === '' ? '' : $base) . '/contato.php') ?>">Formulário de contato</a>
                </div>
            </div>
        </div>
    </section>
</main>
<?php layout_footer(); ?>
