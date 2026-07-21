<?php
require_once __DIR__ . '/_layout.php';
cliente_require_auth();

$cli = cliente_atual();
$cliId = intval($cli['id'] ?? 0);
$liberado = cliente_tem_liberacao($cli);
$tipos = app_conteudo_tipos_cliente();
$counts = [];
$recentes = [];
$totalLiberados = 0;
$bloqueado = isset($_GET['bloqueado']) || !$liberado;

if ($liberado) {
    try {
        foreach (array_keys($tipos) as $t) {
            $n = count(cliente_conteudos_por_tipo($cliId, $t, $cli));
            $counts[$t] = $n;
            $totalLiberados += $n;
        }

        if (cliente_tem_acesso_total($cli)) {
            $recentes = app_pdo()->query(
                "SELECT e.id AS entrega_id, e.titulo AS entrega_titulo, e.data_ref, e.created_at,
                        c.id AS conteudo_id, c.titulo AS conteudo_titulo, c.tipo, c.slug
                 FROM conteudo_entregas e
                 INNER JOIN conteudos c ON c.id = e.conteudo_id AND c.ativo = 1 AND c.area = 'conteudo'
                 WHERE e.ativo = 1
                 ORDER BY e.created_at DESC, e.id DESC
                 LIMIT 12"
            )->fetchAll() ?: [];
        } else {
            $tiposOk = cliente_tipos_liberados($cliId);
            if ($tiposOk) {
                $ph = implode(',', array_fill(0, count($tiposOk), '?'));
                $st = app_pdo()->prepare(
                    "SELECT e.id AS entrega_id, e.titulo AS entrega_titulo, e.data_ref, e.created_at,
                            c.id AS conteudo_id, c.titulo AS conteudo_titulo, c.tipo, c.slug
                     FROM conteudo_entregas e
                     INNER JOIN conteudos c ON c.id = e.conteudo_id AND c.ativo = 1 AND c.area = 'conteudo'
                     WHERE e.ativo = 1 AND c.tipo IN ($ph)
                     ORDER BY e.created_at DESC, e.id DESC
                     LIMIT 12"
                );
                $st->execute($tiposOk);
                $recentes = $st->fetchAll() ?: [];
            }
        }
    } catch (Throwable $e) {
        $recentes = [];
    }
}

cliente_header('Olá, ' . ($cli['nome'] ?? 'cliente'), 'home');

if ($bloqueado):
?>
<div class="empty" style="margin-bottom:28px;">
    <strong style="display:block;font-size:1.1rem;margin-bottom:10px;color:var(--text);">Acesso aos conteúdos bloqueado</strong>
    Sua conta está ativa, mas ainda <strong>não há categorias liberadas</strong> para o seu cadastro.<br>
    Você não consegue visualizar programas nem enviar textos até a equipe liberar o produto.<br>
    <span class="muted" style="display:block;margin-top:12px;">Fale com a Sucesso no Rádio para ativar sua liberação.</span>
</div>
<?php
cliente_footer();
exit;
endif;

if (!cliente_financeiro_em_dia($cli)):
    // Prepara meios válidos em background leve (cliente já encontra QR/boleto prontos no Financeiro)
    try {
        if (function_exists('asaas_configured') || is_file(__DIR__ . '/../includes/asaas.php')) {
            require_once __DIR__ . '/../includes/asaas.php';
            if (asaas_configured()) {
                finance_preparar_faturas_cliente(intval($cli['id'] ?? 0), 10);
            }
        }
    } catch (Throwable $e) { /* não bloqueia a home */ }
?>
<div class="empty" style="margin-bottom:28px;">
    <strong style="display:block;font-size:1.1rem;margin-bottom:10px;color:var(--text);">Pagamento em atraso</strong>
    Há fatura em aberto ou vencida. Em <strong>Financeiro</strong> o Pix e o boleto são gerados/atualizados automaticamente para você pagar sem erro de QR expirado.<br>
    <a class="btn btn-primary" style="margin-top:16px;" href="<?= e(app_url('cliente/financeiro.php')) ?>">Ir para Financeiro e pagar</a>
</div>
<?php
cliente_footer();
exit;
endif;
?>
<p class="cliente-intro">
    Aqui ficam os <strong>conteúdos do seu produto</strong> (liberados pela equipe).
    Os demonstrativos da página inicial são apenas amostras públicas.
    <?php if (cliente_tem_acesso_total($cli)): ?>
        <br><span class="chip" style="margin-top:8px;display:inline-block;">Acesso total liberado</span>
    <?php else: ?>
        <br><span class="muted" style="font-size:.9rem;"><?= (int)$totalLiberados ?> conteúdo(s) liberado(s)</span>
    <?php endif; ?>
</p>

<div class="cliente-hub">
    <?php foreach ($tipos as $key => $meta):
        $okTipo = cliente_pode_acessar_tipo($key, $cli);
    ?>
        <a class="cliente-hub-card" href="<?= e(app_url('cliente/conteudos.php?tipo=' . rawurlencode($key))) ?>">
            <div class="conteudo-hub-icon"><?= $meta['icon'] ?></div>
            <h3><?= e($meta['label']) ?><?= $okTipo ? '' : ' 🔒' ?></h3>
            <p><?= e($meta['desc']) ?></p>
            <div class="conteudo-hub-count">
                <?= $okTipo ? ((int)($counts[$key] ?? 0) . ' item(ns)') : 'Só nomes · sem arquivos' ?>
            </div>
        </a>
    <?php endforeach; ?>
    <a class="cliente-hub-card cliente-hub-accent" href="<?= e(app_url('cliente/texto.php')) ?>">
        <div class="conteudo-hub-icon">🎙️</div>
        <h3>Meus textos</h3>
        <p>Envie textos, acompanhe correções e baixe o áudio gravado.</p>
        <div class="conteudo-hub-count">Gravação &amp; status</div>
    </a>
</div>

<section class="section" style="padding-top:12px;">
    <div class="section-head">
        <h2>Atualizações recentes</h2>
        <p>Últimos arquivos de entrega dos conteúdos liberados para você.</p>
    </div>
    <?php if (!$recentes): ?>
        <div class="empty">Ainda não há arquivos de entrega. Assim que a equipe publicar, eles aparecem aqui.</div>
    <?php else: ?>
        <div class="cliente-list">
            <?php foreach ($recentes as $r):
                $tipoLabel = $tipos[$r['tipo']]['label'] ?? $r['tipo'];
                $data = $r['data_ref'] ?: substr((string)$r['created_at'], 0, 10);
            ?>
                <a class="cliente-list-item" href="<?= e(app_url('cliente/conteudo.php?id=' . intval($r['conteudo_id']))) ?>">
                    <div>
                        <strong><?= e($r['entrega_titulo'] ?: $r['conteudo_titulo']) ?></strong>
                        <div class="muted" style="font-size:.88rem;margin-top:4px;">
                            <?= e($r['conteudo_titulo']) ?> · <?= e($tipoLabel) ?>
                        </div>
                    </div>
                    <div class="cliente-list-meta">
                        <span class="chip"><?= e($data) ?></span>
                        <span class="btn btn-ghost btn-small">Abrir</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php cliente_footer(); ?>
