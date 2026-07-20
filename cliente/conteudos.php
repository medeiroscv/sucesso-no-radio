<?php
require_once __DIR__ . '/_layout.php';
cliente_require_auth();

$cli = cliente_atual();
$cliId = intval($cli['id'] ?? 0);
$tipos = app_conteudo_tipos();
$tipo = trim((string)($_GET['tipo'] ?? 'diario'));
if (!app_conteudo_tipo_valido($tipo)) {
    $tipo = 'diario';
}
$meta = $tipos[$tipo];
$lista = cliente_conteudos_por_tipo($cliId, $tipo, $cli);

cliente_header($meta['label'], $tipo);
?>
<p class="cliente-intro"><?= e($meta['desc']) ?></p>

<div class="actions">
    <?php foreach ($tipos as $key => $m): ?>
        <a class="btn btn-small <?= $key === $tipo ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($key))) ?>">
            <?= $m['icon'] ?> <?= e($m['label']) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!$lista): ?>
    <div class="empty">
        Nenhum conteúdo deste tipo liberado para o seu cadastro.
        <?php if (!cliente_tem_acesso_total($cli)): ?>
            <br><span style="font-size:.9rem;">Peça à equipe para liberar os conteúdos em Admin → Clientes.</span>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="grid-cards">
        <?php foreach ($lista as $p):
            $capa = $p['capa'] ? app_url(ltrim($p['capa'], '/')) : '';
            $nEnt = count(app_entregas(intval($p['id']), true));
        ?>
            <article class="card">
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
                        <span class="chip chip-soft"><?= $nEnt ?> arquivo(s)</span>
                    </div>
                    <p class="card-desc"><?= e($p['resumo'] ?: mb_strimwidth(strip_tags($p['descricao'] ?? ''), 0, 140, '…')) ?></p>
                    <div class="card-actions">
                        <a class="btn btn-primary btn-small" href="<?= e(app_url('cliente/conteudo.php?id=' . intval($p['id']))) ?>">Acessar</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php cliente_footer(); ?>
