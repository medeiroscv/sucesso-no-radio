<?php
require_once __DIR__ . '/includes/layout_public.php';
require_once __DIR__ . '/includes/billing.php';

// Garante tabelas
try { app_pdo(); } catch (Throwable $e) { /* ok */ }

$produtos = array_map('billing_produto_normalize_row', billing_produtos_lista(true, true));
$s = site_settings_all();
$titulo = $s['precos_titulo'] ?? 'Planos e preços';
$intro = $s['precos_intro'] ?? '';
$tipos = billing_produto_tipos();
$ciclos = billing_ciclos();
$wa = preg_replace('/\D+/', '', $s['whatsapp'] ?? '5561974002349');

layout_header($titulo, 'precos');
$base = app_base_path();
?>
<main>
<section class="section" style="padding-top:28px;">
    <div class="container">
        <div class="page-title" style="text-align:center;max-width:640px;margin:0 auto 28px;">
            <p class="cliente-kicker" style="justify-content:center;">Tabela de preços</p>
            <h1 style="font-size:clamp(1.6rem,3vw,2.1rem);"><?= e($titulo) ?></h1>
            <?php if ($intro !== ''): ?>
                <p class="muted" style="margin-top:10px;"><?= e($intro) ?></p>
            <?php endif; ?>
        </div>

        <?php if (!$produtos): ?>
            <div class="empty" style="text-align:center;">
                Em breve publicaremos nossos planos.<br>
                <?php if ($wa): ?>
                    <a class="btn btn-primary" style="margin-top:14px;" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Olá! Quero conhecer os planos da Sucesso no Rádio.') ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="pricing-grid">
                <?php foreach ($produtos as $p):
                    $cicloLabel = $ciclos[$p['ciclo']]['label'] ?? $p['ciclo'];
                    $tipoLabel = $tipos[$p['tipo']]['label'] ?? $p['tipo'];
                    $msg = trim((string)($p['whatsapp_msg'] ?? ''));
                    if ($msg === '') {
                        $msg = 'Olá! Tenho interesse no plano: ' . $p['nome'];
                    }
                    $href = $wa ? ('https://wa.me/' . $wa . '?text=' . rawurlencode($msg)) : app_url('contato.php');
                    $destaque = !empty($p['destaque']);
                ?>
                    <article class="pricing-card<?= $destaque ? ' is-featured' : '' ?>">
                        <?php if ($destaque): ?>
                            <div class="pricing-badge">Recomendado</div>
                        <?php endif; ?>
                        <p class="pricing-type"><?= e($tipoLabel) ?> · <?= e($cicloLabel) ?></p>
                        <h2 class="pricing-name"><?= e($p['nome']) ?></h2>
                        <div class="pricing-price">
                            <?= e(app_money_br(intval($p['valor_centavos']))) ?>
                            <?php if (($p['ciclo'] ?? '') !== 'unico'): ?>
                                <span class="pricing-period">/ <?= e(str_replace(['Mensal', 'Trimestral', 'Semestral', 'Anual'], ['mês', 'trimestre', 'semestre', 'ano'], $cicloLabel)) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($p['descricao'])): ?>
                            <p class="pricing-desc"><?= e($p['descricao']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($p['recursos_list'])): ?>
                            <ul class="pricing-features">
                                <?php foreach ($p['recursos_list'] as $rec): ?>
                                    <li><?= e($rec) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <a class="btn <?= $destaque ? 'btn-primary' : 'btn-ghost' ?>" style="width:100%;margin-top:auto;" href="<?= e($href) ?>" target="_blank" rel="noopener">
                            <?= e($p['botao_texto'] ?: 'Contratar') ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="muted" style="text-align:center;margin-top:28px;font-size:.9rem;">
                Já é cliente? <a href="<?= e(app_url('cliente/login.php')) ?>" style="color:var(--accent);font-weight:700;">Acesse sua área</a>
                para faturas e pagamentos.
            </p>
        <?php endif; ?>
    </div>
</section>
</main>
<?php
layout_footer();
