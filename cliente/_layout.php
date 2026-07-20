<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
cliente_session_start();

/**
 * CSS embutido da área do cliente (não depende de path externo no deploy).
 */
function cliente_styles_inline(): string {
    $file = dirname(__DIR__) . '/assets/css/cliente.css';
    $extra = is_file($file) ? (string)@file_get_contents($file) : '';

    // Base completa — garante fundo, botões, formulários e tipografia mesmo sem site.css
    $base = <<<'CSS'
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body.cliente-body {
  margin: 0;
  min-height: 100vh;
  font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
  background: #0b1220 !important;
  color: #e8eef9 !important;
  line-height: 1.55;
  -webkit-font-smoothing: antialiased;
}
body.cliente-body a { color: inherit; text-decoration: none; }
body.cliente-body img { max-width: 100%; display: block; }
.muted { color: #94a3b8 !important; font-size: .9rem; }
strong { font-weight: 700; }

/* Botões */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border: 0;
  border-radius: 999px;
  padding: 10px 16px;
  font-weight: 700;
  font-size: .9rem;
  font-family: inherit;
  cursor: pointer;
  text-decoration: none;
  transition: background .15s ease, border-color .15s ease;
  line-height: 1.2;
}
.btn-primary { background: #22c55e; color: #052e16 !important; }
.btn-primary:hover { background: #16a34a; }
.btn-ghost {
  background: transparent;
  color: #e8eef9 !important;
  border: 1px solid #334155;
}
.btn-ghost:hover { border-color: #94a3b8; }
.btn-secondary { background: #334155; color: #fff !important; border: 0; }
.btn-secondary:hover { background: #475569; }
.btn-wa { background: #25d366; color: #052e16 !important; }
.btn-small { padding: 7px 12px; font-size: .82rem; }

/* Chips / badges */
.chip {
  display: inline-block;
  font-size: .75rem;
  font-weight: 700;
  color: #bbf7d0;
  background: rgba(34, 197, 94, .12);
  border: 1px solid rgba(34, 197, 94, .25);
  padding: 4px 8px;
  border-radius: 999px;
}
.card-meta { display: flex; flex-wrap: wrap; gap: 6px; }
.card-desc { color: #94a3b8; font-size: .9rem; flex: 1; margin: 0; }
.card-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
.card-body h3 { font-size: 1.05rem; margin: 0; color: #f1f5f9; }

/* Forms */
.field { margin-bottom: 14px; }
.field label {
  display: block;
  font-weight: 700;
  font-size: .82rem;
  margin-bottom: 6px;
  color: #94a3b8;
}
.field input,
.field textarea,
.field select {
  width: 100%;
  border: 1px solid #334155;
  background: #0b1220;
  color: #f1f5f9;
  border-radius: 10px;
  padding: 11px 12px;
  font: inherit;
  outline: none;
}
.field input:focus,
.field textarea:focus {
  border-color: #22c55e;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, .18);
}
.field input:disabled {
  opacity: .7;
  cursor: not-allowed;
}
.field-row-form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
@media (max-width: 700px) {
  .field-row-form { grid-template-columns: 1fr; }
}

/* Alerts */
.alert {
  padding: 12px 14px;
  border-radius: 10px;
  margin-bottom: 14px;
  font-size: .92rem;
}
.alert-ok {
  background: rgba(34, 197, 94, .12);
  color: #bbf7d0;
  border: 1px solid rgba(34, 197, 94, .3);
}
.alert-err {
  background: rgba(239, 68, 68, .12);
  color: #fecaca;
  border: 1px solid rgba(239, 68, 68, .3);
}

/* Brand badge fallback */
.brand-badge {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: linear-gradient(135deg, #22c55e, #0ea5e9);
  display: grid;
  place-items: center;
  font-size: 18px;
  flex-shrink: 0;
}

/* Audio players */
audio { max-width: 100%; vertical-align: middle; }
CSS;

    return $base . "\n" . $extra;
}

function cliente_header(string $title, string $active = ''): void {
    $s = [];
    try {
        $rows = app_pdo()->query('SELECT chave, valor FROM site_settings')->fetchAll();
        foreach ($rows as $r) $s[$r['chave']] = $r['valor'];
    } catch (Throwable $e) { /* ok */ }
    $nomeSite = $s['site_nome'] ?? APP_NAME;
    $nomeCli = $_SESSION['cliente_nome'] ?? 'Cliente';
    $base = app_base_path();
    $prefix = $base === '' ? '' : $base;
    $logo = !empty($s['site_logo']) ? ($prefix . '/' . ltrim((string)$s['site_logo'], '/')) : '';
    $favicon = !empty($s['site_favicon']) ? ($prefix . '/' . ltrim((string)$s['site_favicon'], '/')) : '';
    $tipos = app_conteudo_tipos();
    $styles = cliente_styles_inline();
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> · Área do Cliente · <?= e($nomeSite) ?></title>
    <?php if ($favicon): ?><link rel="icon" href="<?= e($favicon) ?>" type="image/png"><?php endif; ?>
    <style><?= $styles ?></style>
</head>
<body class="cliente-body">
<div class="cliente-shell">
    <aside class="cliente-sidebar" id="clienteSidebar">
        <div class="cliente-sidebar-brand">
            <a href="<?= e($prefix . '/cliente/') ?>">
                <?php if ($logo): ?>
                    <img src="<?= e($logo) ?>" alt="<?= e($nomeSite) ?>">
                <?php else: ?>
                    <span class="brand-badge">🎙</span>
                    <strong><?= e($nomeSite) ?></strong>
                <?php endif; ?>
            </a>
            <div class="cliente-sidebar-label">Área do cliente</div>
        </div>
        <nav class="cliente-sidebar-nav">
            <a href="<?= e($prefix . '/cliente/') ?>" class="<?= $active === 'home' ? 'active' : '' ?>">🏠 Início</a>
            <div class="cliente-nav-group">Conteúdos</div>
            <?php foreach ($tipos as $key => $meta): ?>
                <a href="<?= e($prefix . '/cliente/conteudos.php?tipo=' . rawurlencode($key)) ?>" class="<?= $active === $key ? 'active' : '' ?>">
                    <?= $meta['icon'] ?> <?= e($meta['label']) ?>
                </a>
            <?php endforeach; ?>
            <div class="cliente-nav-group">Serviços</div>
            <a href="<?= e($prefix . '/cliente/texto.php') ?>" class="<?= $active === 'texto' ? 'active' : '' ?>">🎙️ Enviar texto</a>
            <a href="<?= e($prefix . '/cliente/perfil.php') ?>" class="<?= $active === 'perfil' ? 'active' : '' ?>">👤 Meus dados</a>
        </nav>
        <div class="cliente-sidebar-foot">
            <div class="cliente-user-chip">Olá, <strong><?= e($nomeCli) ?></strong></div>
            <a class="btn btn-ghost btn-small" href="<?= e($prefix === '' ? '/' : $prefix . '/') ?>">Site público</a>
            <a class="btn btn-secondary btn-small" href="<?= e($prefix . '/cliente/logout.php') ?>">Sair</a>
        </div>
    </aside>

    <div class="cliente-content">
        <header class="cliente-topbar">
            <button type="button" class="cliente-menu-btn" id="clienteMenuBtn" aria-label="Abrir menu">☰</button>
            <div class="cliente-topbar-title">
                <span class="cliente-badge">Área do cliente</span>
                <h1><?= e($title) ?></h1>
            </div>
            <div class="cliente-topbar-user muted">Olá, <strong><?= e($nomeCli) ?></strong></div>
        </header>
        <main class="cliente-main">
<?php
}

function cliente_footer(): void {
    ?>
        </main>
        <footer class="cliente-footer">
            Área restrita · conteúdos liberados para o seu cadastro
        </footer>
    </div>
</div>
<div class="cliente-overlay" id="clienteOverlay" hidden></div>
<script>
(function () {
    var btn = document.getElementById('clienteMenuBtn');
    var side = document.getElementById('clienteSidebar');
    var overlay = document.getElementById('clienteOverlay');
    if (!btn || !side) return;
    function open() {
        side.classList.add('open');
        if (overlay) overlay.hidden = false;
        document.body.classList.add('cliente-menu-open');
    }
    function close() {
        side.classList.remove('open');
        if (overlay) overlay.hidden = true;
        document.body.classList.remove('cliente-menu-open');
    }
    btn.addEventListener('click', function () {
        if (side.classList.contains('open')) close(); else open();
    });
    if (overlay) overlay.addEventListener('click', close);
    side.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
            if (window.innerWidth <= 960) close();
        });
    });
})();
</script>
</body>
</html>
<?php
}

function cliente_flash(?string $ok = null, ?string $err = null): void {
    if ($ok) echo '<div class="alert alert-ok">' . e($ok) . '</div>';
    if ($err) echo '<div class="alert alert-err">' . e($err) . '</div>';
}
