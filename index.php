<?php
require_once __DIR__ . '/includes/layout_public.php';

$pdo = null;
$erro = '';
$porTipo = [
    'diario' => [],
    'semanal' => [],
    'informativo' => [],
    'programete' => [],
];
$banners = [];
$tiposMeta = app_conteudo_tipos();

try {
    $pdo = app_pdo();
    foreach (array_keys($porTipo) as $t) {
        // Só demonstrativos (catálogo público)
        $porTipo[$t] = app_conteudos_por_tipo($t, true, 'demonstrativo');
    }
    $banners = $pdo->query(
        "SELECT * FROM banners WHERE ativo = 1 ORDER BY ordem ASC, id DESC LIMIT 5"
    )->fetchAll();
} catch (Throwable $e) {
    $erro = 'Conteúdo ainda não disponível. Configure o banco no EasyPanel (AUTO_INSTALL + Postgres).';
}

$s = site_settings_all();
layout_header('', 'home');
$base = app_base_path();
$prefix = $base === '' ? '' : $base;

function render_conteudo_card(array $p, string $base, string $tipo): void {
    $capa = $p['capa'] ? (($base === '' ? '' : $base) . '/' . ltrim($p['capa'], '/')) : '';
    $msg = $p['whatsapp_msg'] ?: ('Olá! Quero contratar: ' . $p['titulo']);
    $detalhe = ($base === '' ? '' : $base) . '/programa.php?slug=' . rawurlencode($p['slug']);
    $demos = app_demonstrativos('conteudo', intval($p['id']));
    // demos legados (antes da unificação)
    if (!$demos && $tipo === 'programete') {
        $demos = app_demonstrativos('programete', intval($p['id']));
    }
    if (!$demos) {
        $demos = app_demonstrativos('programa', intval($p['id']));
    }
    ?>
    <article class="card">
        <?php if ($capa): ?>
            <img class="card-cover" src="<?= e($capa) ?>" alt="<?= e($p['titulo']) ?>" loading="lazy">
        <?php else: ?>
            <div class="card-cover" style="display:grid;place-items:center;color:var(--muted);font-weight:700;">🎙</div>
        <?php endif; ?>
        <div class="card-body">
            <h3><?= e($p['titulo']) ?></h3>
            <div class="card-meta">
                <?php if (!empty($p['duracao'])): ?><span class="chip"><?= e($p['duracao']) ?></span><?php endif; ?>
                <?php if (!empty($p['blocos'])): ?><span class="chip"><?= e($p['blocos']) ?></span><?php endif; ?>
                <?php if (!empty($p['dias'])): ?><span class="chip"><?= e($p['dias']) ?></span><?php endif; ?>
                <?php if (!empty($p['insercoes'])): ?><span class="chip"><?= e($p['insercoes']) ?></span><?php endif; ?>
            </div>
            <p class="card-desc"><?= e($p['resumo'] ?: mb_strimwidth(strip_tags($p['descricao'] ?? ''), 0, 120, '…')) ?></p>
            <?php if ($demos && $tipo === 'programete'): ?>
                <div style="display:grid;gap:8px;margin:4px 0 8px;">
                    <?php foreach ($demos as $d): ?>
                        <div>
                            <div style="font-size:.8rem;font-weight:700;margin-bottom:4px;color:var(--muted);"><?= e($d['titulo'] ?: 'Demo') ?></div>
                            <audio controls preload="none" style="width:100%;height:36px;">
                                <source src="<?= e(($base === '' ? '' : $base) . '/' . ltrim($d['arquivo'], '/')) ?>" type="audio/mpeg">
                            </audio>
                        </div>
    <?php endforeach; ?>

    <?php
    // Produtos avulsos/pacotes com demonstrativos — mesmo padrao dos cards
    if (function_exists('billing_produtos_lista')) {
        require_once __DIR__ . '/includes/billing.php';
        $todos = array_map('billing_produto_normalize_row', billing_produtos_lista(true, true));
        $produtosHome = array_filter($todos, function ($p) {
            return in_array($p['tipo'] ?? '', ['avulso', 'pacote'], true)
                && ($p['ciclo'] ?? '') === 'unico'
                && count(app_produto_demonstrativos(intval($p['id']))) > 0;
        });
    } else {
        $produtosHome = [];
    }
    if ($produtosHome):
    ?>
    <section class="section container">
        <div class="section-head">
            <h2>Produtos</h2>
            <p>Pacotes e coleções com pagamento único. Ouça as amostras e adquira.</p>
        </div>
        <div class="grid-cards">
            <?php foreach ($produtosHome as $p):
                $demos = app_produto_demonstrativos(intval($p['id']));
            ?>
                <article class="card">
                    <div class="card-body">
                        <h3><?= e($p['nome']) ?></h3>
                        <div class="card-meta">
                            <span class="chip"><?= e(app_money_br(intval($p['valor_centavos']))) ?></span>
                            <span class="chip">pagamento único</span>
                        </div>
                        <p class="card-desc"><?= e(mb_strimwidth(strip_tags($p['descricao'] ?? ''), 0, 120, '…')) ?></p>
                        <?php if ($demos): ?>
                            <div style="display:grid;gap:8px;margin:4px 0 8px;">
                                <?php foreach ($demos as $d): ?>
                                    <div>
                                        <div style="font-size:.8rem;font-weight:700;margin-bottom:4px;color:var(--muted);"><?= e($d['titulo'] ?: 'Amostra') ?></div>
                                        <audio controls preload="none" style="width:100%;height:36px;">
                                            <source src="<?= e(($base === '' ? '' : $base) . '/' . ltrim($d['arquivo'], '/')) ?>" type="audio/mpeg">
                                        </audio>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="card-actions">
                            <a class="btn btn-primary btn-small" href="<?= e($prefix . '/cliente/contratar.php?produto=' . rawurlencode((string)$p['slug'])) ?>">Comprar</a>
                            <a class="btn btn-ghost btn-small" href="<?= e($prefix . '/produtos.php') ?>">Ver todos</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="card-actions">
                <a class="btn btn-ghost btn-small" href="<?= e($detalhe) ?>">Detalhes</a>
                <a class="btn btn-wa btn-small" href="<?= e(wa_link($msg)) ?>" target="_blank">Contratar</a>
            </div>
        </div>
    </article>
    <?php
}
?>
<main>
    <section class="hero container">
        <div class="hero-grid">
            <div>
                <h1><?= e($s['site_slogan'] ?? 'Tudo que sua rádio precisa em um só lugar') ?></h1>
                <p><?= e($s['sobre'] ?? 'Diários, semanais, informativos e programetes profissionais para a sua grade.') ?></p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="#diarios">Demonstrativos</a>
                    <a class="btn btn-ghost" href="<?= e($prefix . '/cliente/login.php') ?>">Área do cliente</a>
                    <a class="btn btn-wa" href="<?= e(wa_link('Olá! Quero conhecer os planos da ' . ($s['site_nome'] ?? 'Sucesso no Rádio'))) ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
                </div>
            </div>
            <div class="hero-card">
                <h3>Para rádios e web rádios</h3>
                <p style="color:var(--muted);font-size:.95rem;">Conteúdo pronto para ir ao ar, com blocos e duração definidos.</p>
                <ul>
                    <li>Diários</li>
                    <li>Semanais</li>
                    <li>Informativos</li>
                    <li>Programetes</li>
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

    <?php
    $secoes = [
        'diario' => ['id' => 'diarios', 'sub' => 'Produções para a grade de segunda a sábado.'],
        'semanal' => ['id' => 'semanais', 'sub' => 'Conteúdos semanais e de fim de semana.'],
        'informativo' => ['id' => 'informativos', 'sub' => 'Jornalismo, boletins e notícias para a emissora.'],
        'programete' => ['id' => 'programetes', 'sub' => 'Pacotes de dicas e inserções rápidas.'],
    ];
    foreach ($secoes as $tipoKey => $sec):
        $itens = $porTipo[$tipoKey] ?? [];
        $label = $tiposMeta[$tipoKey]['label'] ?? $tipoKey;
    ?>
    <section class="section container" id="<?= e($sec['id']) ?>">
        <div class="section-head">
            <h2><?= e($label) ?></h2>
            <p><?= e($sec['sub']) ?></p>
        </div>
        <?php if (!$itens): ?>
            <div class="empty">Nenhum item cadastrado ainda. Acesse o <a href="<?= e($prefix . '/admin/conteudos.php?tipo=' . rawurlencode($tipoKey)) ?>">admin</a> e adicione conteúdos.</div>
        <?php else: ?>
            <div class="grid-cards">
                <?php foreach ($itens as $p) {
                    render_conteudo_card($p, $base, $tipoKey);
                } ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>

    <?php
    if (function_exists('billing_produtos_lista')) {
        require_once __DIR__ . '/includes/billing.php';
        $todos = array_map('billing_produto_normalize_row', billing_produtos_lista(true, true));
        $produtosHome = array_filter($todos, function ($p) {
            return in_array($p['tipo'] ?? '', ['avulso', 'pacote'], true)
                && ($p['ciclo'] ?? '') === 'unico'
                && count(app_produto_demonstrativos(intval($p['id']))) > 0;
        });
    } else {
        $produtosHome = [];
    }
    if ($produtosHome):
    ?>
    <section class="section container">
        <div class="section-head">
            <h2>Produtos</h2>
            <p>Pacotes e coleções com pagamento único. Ouça as amostras e adquira.</p>
        </div>
        <div class="grid-cards">
            <?php foreach ($produtosHome as $p):
                $demos = app_produto_demonstrativos(intval($p['id']));
            ?>
                <article class="card">
                    <div class="card-body">
                        <h3><?= e($p['nome']) ?></h3>
                        <div class="card-meta">
                            <span class="chip"><?= e(app_money_br(intval($p['valor_centavos']))) ?></span>
                            <span class="chip">pagamento único</span>
                        </div>
                        <p class="card-desc"><?= e(mb_strimwidth(strip_tags($p['descricao'] ?? ''), 0, 120, '…')) ?></p>
                        <?php if ($demos): ?>
                            <div style="display:grid;gap:8px;margin:4px 0 8px;">
                                <?php foreach ($demos as $d): ?>
                                    <div>
                                        <div style="font-size:.8rem;font-weight:700;margin-bottom:4px;color:var(--muted);"><?= e($d['titulo'] ?: 'Amostra') ?></div>
                                        <audio controls preload="none" style="width:100%;height:36px;">
                                            <source src="<?= e(($base === '' ? '' : $base) . '/' . ltrim($d['arquivo'], '/')) ?>" type="audio/mpeg">
                                        </audio>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="card-actions">
                            <a class="btn btn-primary btn-small" href="<?= e($prefix . '/cliente/contratar.php?produto=' . rawurlencode((string)$p['slug'])) ?>">Comprar</a>
                            <a class="btn btn-ghost btn-small" href="<?= e($prefix . '/produtos.php') ?>">Ver todos</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="section container">
        <div class="destaque">
            <div>
                <h2>Quer montar a grade da sua rádio?</h2>
                <p style="color:var(--muted);margin-top:8px;">Fale com a gente no WhatsApp e receba indicação de conteúdos sob medida para o seu público.</p>
                <div class="hero-actions">
                    <a class="btn btn-wa" href="<?= e(wa_link('Olá! Quero montar a grade da minha rádio com a ' . ($s['site_nome'] ?? 'Sucesso no Rádio'))) ?>" target="_blank">Chamar no WhatsApp</a>
                    <a class="btn btn-ghost" href="<?= e($prefix . '/contato.php') ?>">Formulário de contato</a>
                </div>
            </div>
        </div>
    </section>
</main>
<?php layout_footer(); ?>
