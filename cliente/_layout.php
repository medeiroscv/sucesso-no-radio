<?php
/**
 * Área do cliente — usa o MESMO layout do site público (header, menus, footer).
 */
require_once __DIR__ . '/../includes/layout_public.php';
cliente_session_start();

function cliente_header(string $title, string $active = ''): void {
    layout_header($title, $active !== '' ? $active : 'cliente');
    $impersonating = function_exists('cliente_impersonando') && cliente_impersonando();
    $cliNome = '';
    if ($impersonating) {
        $c = cliente_atual();
        $cliNome = (string)($c['nome'] ?? $_SESSION['cliente_nome'] ?? 'cliente');
    }
    ?>
<main>
    <div class="container">
        <?php if ($impersonating): ?>
            <div class="alert" style="margin-bottom:16px;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.45);color:#fde68a;padding:12px 14px;border-radius:12px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                <div style="font-size:.92rem;line-height:1.45;">
                    <strong>Modo suporte</strong> — você está acessando como
                    <strong><?= e($cliNome) ?></strong>
                    (admin: <?= e(cliente_impersonator_nome()) ?>).
                    <?php
                    $cAtivo = cliente_atual();
                    if ($cAtivo && empty($cAtivo['ativo'])):
                    ?>
                        <span style="display:inline-block;margin-left:6px;font-size:.78rem;font-weight:800;padding:2px 8px;border-radius:999px;background:rgba(239,68,68,.2);color:#fecaca;">cliente inativo</span>
                    <?php endif; ?>
                </div>
                <div class="actions" style="gap:8px;">
                    <a class="btn btn-ghost btn-small" href="<?= e(app_url('admin/clientes.php')) ?>">Voltar ao admin</a>
                    <a class="btn btn-primary btn-small" href="<?= e(app_url('cliente/logout.php')) ?>">Encerrar acesso como cliente</a>
                </div>
            </div>
        <?php endif; ?>
        <div class="page-title">
            <p class="cliente-kicker">Área do cliente<?= $impersonating ? ' · suporte' : '' ?></p>
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
        <?php if (function_exists('cliente_impersonando') && cliente_impersonando()): ?>
            <a href="<?= e(app_url('admin/clientes.php')) ?>">Admin</a>
            <a href="<?= e(app_url('cliente/logout.php')) ?>">Encerrar suporte</a>
        <?php else: ?>
            <a href="<?= e(app_url('cliente/logout.php')) ?>">Sair</a>
        <?php endif; ?>
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
