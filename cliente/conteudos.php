<?php
require_once __DIR__ . '/_layout.php';
cliente_require_liberacao();

$cli = cliente_atual();
$cliId = intval($cli['id'] ?? 0);
$tipos = app_conteudo_tipos_cliente();
$tipo = trim((string)($_GET['tipo'] ?? 'diario'));
if (!isset($tipos[$tipo])) {
    $tipo = 'diario';
}
$meta = $tipos[$tipo];
$lista = cliente_conteudos_por_tipo($cliId, $tipo, $cli);
$temAcesso = cliente_pode_acessar_tipo($tipo, $cli);

cliente_header($meta['label'], $tipo);
?>
<p class="cliente-intro">
    <?= e($meta['desc']) ?>
    <?php if (!$temAcesso): ?>
        <br><span class="chip" style="margin-top:8px;display:inline-block;background:rgba(251,191,36,.15);color:#fbbf24;border-color:rgba(251,191,36,.35);">
            Categoria sem liberação — você vê os nomes, mas não os arquivos
        </span>
    <?php endif; ?>
</p>

<div class="actions">
    <?php foreach ($tipos as $key => $m):
        $okTipo = cliente_pode_acessar_tipo($key, $cli);
    ?>
        <a class="btn btn-small <?= $key === $tipo ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($key))) ?>">
            <?= $m['icon'] ?> <?= e($m['label']) ?><?= $okTipo ? '' : ' 🔒' ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!$lista): ?>
    <div class="empty">Nenhum item cadastrado nesta categoria ainda.</div>
<?php else: ?>
    <div class="grid-cards">
        <?php foreach ($lista as $p):
            $capa = $p['capa'] ? app_url(ltrim($p['capa'], '/')) : '';
            $nEnt = $temAcesso ? count(app_entregas(intval($p['id']), true)) : 0;
        ?>
            <article class="card" style="<?= $temAcesso ? '' : 'opacity:.92;' ?>">
                <?php if ($capa): ?>
                    <img class="card-cover" src="<?= e($capa) ?>" alt="<?= e($p['titulo']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="card-cover" style="display:grid;place-items:center;color:var(--muted);font-weight:700;font-size:2rem;"><?= $meta['icon'] ?></div>
                <?php endif; ?>
                <div class="card-body">
                    <h3><?= e($p['titulo']) ?></h3>
                    <div class="card-meta">
                        <?php if (!empty($p['duracao'])): ?><span class="chip"><?= e($p['duracao']) ?></span><?php endif; ?>
                        <?php if (!empty($p['blocos'])): ?><span class="chip"><?= e($p['blocos']) ?></span><?php endif; ?>
                        <?php if (!empty($p['dias'])): ?><span class="chip"><?= e($p['dias']) ?></span><?php endif; ?>
                        <?php if (!empty($p['insercoes'])): ?><span class="chip"><?= e($p['insercoes']) ?></span><?php endif; ?>
                        <?php if ($temAcesso): ?>
                            <span class="chip chip-soft"><?= $nEnt ?> arquivo(s)</span>
                        <?php else: ?>
                            <span class="chip chip-soft">Somente nome</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($temAcesso): ?>
                        <p class="card-desc"><?= e($p['resumo'] ?: mb_strimwidth(strip_tags($p['descricao'] ?? ''), 0, 140, '…')) ?></p>
                        <div class="card-actions">
                            <a class="btn btn-primary btn-small" href="<?= e(app_url('cliente/conteudo.php?id=' . intval($p['id']))) ?>">Acessar</a>
                        </div>
                    <?php else: ?>
                        <p class="card-desc muted">Conteúdo bloqueado. Solicite a liberação desta categoria à equipe.</p>
                        <div class="card-actions">
                            <span class="btn btn-ghost btn-small" style="opacity:.6;cursor:not-allowed;">Bloqueado</span>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php cliente_footer(); ?>
