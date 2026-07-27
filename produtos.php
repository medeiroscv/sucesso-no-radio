<?php
require_once __DIR__ . '/includes/layout_public.php';
require_once __DIR__ . '/includes/billing.php';

try { app_pdo(); } catch (Throwable $e) { /* ok */ }

$todos = array_map('billing_produto_normalize_row', billing_produtos_lista(true, true));
$produtos = array_filter($todos, function ($p) {
    return in_array($p['tipo'] ?? '', ['avulso', 'pacote'], true) && ($p['ciclo'] ?? '') === 'unico';
});
$s = site_settings_all();
$wa = preg_replace('/\D+/', '', $s['whatsapp'] ?? '5561974002349');

layout_header('Produtos avulsos', 'produtos');
$base = app_base_path();
?>
<main>
<section class="section" style="padding-top:28px;">
    <div class="container">
        <div class="page-title" style="text-align:center;max-width:640px;margin:0 auto 28px;">
            <p class="cliente-kicker" style="justify-content:center;">Produtos avulsos</p>
            <h1 style="font-size:clamp(1.6rem,3vw,2.1rem);">Compre produtos avulsos e pacotes</h1>
            <p class="muted" style="margin-top:10px;">Escolha o produto, ouça os demonstrativos e adquira com Pix ou boleto. Liberação imediata após a confirmação.</p>
        </div>

        <?php if (!$produtos): ?>
            <div class="empty" style="text-align:center;">
                Nenhum produto avulso disponível no momento.<br>
                <?php if ($wa): ?>
                    <a class="btn btn-primary" style="margin-top:14px;" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Olá! Quero saber sobre os produtos avulsos.') ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="display:grid;gap:28px;">
                <?php foreach ($produtos as $p):
                    $demos = app_produto_demonstrativos(intval($p['id']));
                    $href = app_url('cliente/contratar.php?produto=' . rawurlencode((string)$p['slug']));
                ?>
                    <div style="background:#0b1220;border:1px solid var(--line);border-radius:16px;padding:24px 26px;">
                        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:14px;">
                            <div>
                                <h2 style="font-size:1.3rem;margin:0 0 6px;"><?= e($p['nome']) ?></h2>
                                <?php if (!empty($p['descricao'])): ?>
                                    <p class="muted" style="margin:0;font-size:.92rem;max-width:540px;"><?= e($p['descricao']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div style="text-align:right;flex-shrink:0;">
                                <div style="font-size:1.6rem;font-weight:800;"><?= e(app_money_br(intval($p['valor_centavos']))) ?></div>
                                <span class="muted" style="font-size:.82rem;">pagamento único</span>
                            </div>
                        </div>

                        <?php if (!empty($p['recursos_list'])): ?>
                            <ul style="margin:10px 0 14px 18px;color:var(--muted);font-size:.9rem;line-height:1.7;">
                                <?php foreach (array_slice($p['recursos_list'], 0, 8) as $rec): ?>
                                    <li><?= e($rec) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if ($demos): ?>
                            <div style="margin:14px 0;">
                                <p class="muted" style="font-size:.82rem;font-weight:600;margin-bottom:10px;">OUÇA AMOSTRAS</p>
                                <div style="display:grid;gap:10px;">
                                    <?php foreach ($demos as $d): ?>
                                        <div style="background:#0f172a;border:1px solid var(--line);border-radius:10px;padding:10px 12px;">
                                            <p style="font-size:.88rem;font-weight:600;margin:0 0 6px;"><?= e($d['titulo'] ?: 'Demonstrativo') ?></p>
                                            <audio controls preload="none" style="width:100%;max-width:480px;">
                                                <source src="<?= e($base . '/' . ltrim((string)$d['arquivo'], '/')) ?>" type="audio/mpeg">
                                            </audio>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="actions" style="margin-top:16px;">
                            <a class="btn btn-primary" href="<?= e($href) ?>"><?= e($p['botao_texto'] ?: 'Comprar') ?></a>
                            <?php if (!empty($p['whatsapp_msg']) && $wa): ?>
                                <a class="btn btn-ghost" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode((string)$p['whatsapp_msg']) ?>" target="_blank" rel="noopener">Tirar dúvidas</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
</main>
<?php layout_footer(); ?>
