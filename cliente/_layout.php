<?php
/**
 * Área do cliente — usa o MESMO layout do site público (header, menus, footer).
 */
require_once __DIR__ . '/../includes/layout_public.php';
cliente_session_start();

function cliente_header(string $title, string $active = ''): void {
    layout_header($title, $active !== '' ? $active : 'cliente');
    ?>
<main>
    <div class="container">
        <div class="page-title">
            <p class="cliente-kicker">Área do cliente</p>
            <h1><?= e($title) ?></h1>
        </div>
        <?php cliente_subnav($active); ?>
<?php
}

function cliente_subnav(string $active = ''): void {
    if (!cliente_logado()) return;
    $cli = cliente_atual();
    $liberado = cliente_tem_liberacao($cli);
    $tipos = app_conteudo_tipos_cliente();
    ?>
    <nav class="cliente-subnav" aria-label="Menu da área do cliente">
        <a href="<?= e(cliente_home_url()) ?>" class="<?= $active === 'home' || $active === 'cliente' ? 'active' : '' ?>">Início</a>
        <?php if ($liberado && function_exists('cliente_financeiro_em_dia') && cliente_financeiro_em_dia($cli)): ?>
            <?php foreach ($tipos as $key => $meta): ?>
                <a href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($key))) ?>" class="<?= $active === $key ? 'active' : '' ?>">
                    <?= e($meta['label']) ?>
                </a>
            <?php endforeach; ?>
            <a href="<?= e(app_url('cliente/texto.php')) ?>" class="<?= $active === 'texto' ? 'active' : '' ?>">Meus textos</a>
        <?php endif; ?>
        <?php if (function_exists('app_finance_ativo') && app_finance_ativo()): ?>
            <a href="<?= e(app_url('cliente/financeiro.php')) ?>" class="<?= $active === 'financeiro' ? 'active' : '' ?>">Financeiro</a>
        <?php endif; ?>
        <a href="<?= e(app_url('cliente/perfil.php')) ?>" class="<?= $active === 'perfil' ? 'active' : '' ?>">Meus dados</a>
        <a href="<?= e(app_url('cliente/logout.php')) ?>">Sair</a>
    </nav>
    <?php
}

function cliente_footer(): void {
    ?>
    </div>
</main>
<?php
    layout_footer();
}

function cliente_flash(?string $ok = null, ?string $err = null): void {
    if ($ok) echo '<div class="alert alert-ok">' . e($ok) . '</div>';
    if ($err) echo '<div class="alert alert-err">' . e($err) . '</div>';
}
