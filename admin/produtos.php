<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/billing.php';

$pdo = app_pdo();
$ok = $err = '';
$edit = null;
$tipos = billing_produto_tipos();
$ciclos = billing_ciclos();

if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    try {
        $stc = $pdo->prepare("SELECT COUNT(*) FROM assinaturas WHERE produto_id = ? AND status = 'ativa'");
        $stc->execute([$id]);
        $n = (int)$stc->fetchColumn();
        if ($n > 0) {
            $err = "Não é possível excluir: há {$n} assinatura(s) ativa(s). Cancele-as antes.";
        } else {
            $pdo->prepare('DELETE FROM produtos WHERE id = ?')->execute([$id]);
            header('Location: produtos.php?ok=1');
            exit;
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $nome = trim((string)($_POST['nome'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    if ($slug === '' && $nome !== '') $slug = app_slug($nome);
    $tipo = (string)($_POST['tipo'] ?? 'mensalidade');
    if (!isset($tipos[$tipo])) $tipo = 'mensalidade';
    $ciclo = (string)($_POST['ciclo'] ?? 'mensal');
    if (!isset($ciclos[$ciclo])) $ciclo = 'mensal';
    if ($tipo === 'avulso') $ciclo = 'unico';
    $valor = (float)str_replace(',', '.', preg_replace('/[^\d,.]/', '', (string)($_POST['valor'] ?? '0')));
    $cent = (int)round($valor * 100);
    $desc = trim((string)($_POST['descricao'] ?? ''));
    $recursos = trim((string)($_POST['recursos'] ?? ''));
    $destaque = !empty($_POST['destaque']) ? 1 : 0;
    $ativo = !empty($_POST['ativo']) ? 1 : 0;
    $mostrar = !empty($_POST['mostrar_site']) ? 1 : 0;
    $ordem = intval($_POST['ordem'] ?? 0);
    $diasGerar = max(0, min(60, intval($_POST['dias_gerar_antes'] ?? 7)));
    $antes = billing_encode_dias_list(billing_parse_dias_list($_POST['cobranca_antes'] ?? ''));
    $apos = billing_encode_dias_list(billing_parse_dias_list($_POST['cobranca_apos'] ?? ''));
    $noVenc = !empty($_POST['cobranca_no_vencimento']) ? 1 : 0;
    $emitir = !empty($_POST['emitir_auto']) ? 1 : 0;
    $botao = trim((string)($_POST['botao_texto'] ?? 'Assinar')) ?: 'Assinar';
    $wa = trim((string)($_POST['whatsapp_msg'] ?? ''));
    $libTotal = !empty($_POST['liberar_acesso_total']) ? 1 : 0;
    $libTipos = [];
    if (!$libTotal && !empty($_POST['liberar_tipos']) && is_array($_POST['liberar_tipos'])) {
        foreach ($_POST['liberar_tipos'] as $t) {
            $t = trim((string)$t);
            if (app_conteudo_tipo_valido($t)) $libTipos[] = $t;
        }
    }
    $libTiposJson = json_encode(array_values(array_unique($libTipos)), JSON_UNESCAPED_UNICODE);

    if ($nome === '' || $cent < 0) {
        $err = 'Nome e valor são obrigatórios.';
    } else {
        try {
            // slug único
            $baseSlug = app_slug($slug !== '' ? $slug : $nome);
            $slugTry = $baseSlug;
            $i = 2;
            while (true) {
                $st = $pdo->prepare('SELECT id FROM produtos WHERE slug = ? AND id <> ? LIMIT 1');
                $st->execute([$slugTry, $id]);
                if (!$st->fetch()) break;
                $slugTry = $baseSlug . '-' . $i;
                $i++;
            }
            $slug = $slugTry;

            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE produtos SET nome=?, slug=?, tipo=?, ciclo=?, valor_centavos=?, descricao=?, recursos=?,
                     destaque=?, ativo=?, mostrar_site=?, ordem=?, dias_gerar_antes=?, cobranca_antes=?,
                     cobranca_no_vencimento=?, cobranca_apos=?, emitir_auto=?, botao_texto=?, whatsapp_msg=?,
                     liberar_tipos=?, liberar_acesso_total=?, updated_at=NOW()
                     WHERE id=?'
                )->execute([
                    $nome, $slug, $tipo, $ciclo, $cent, $desc, $recursos, $destaque, $ativo, $mostrar, $ordem,
                    $diasGerar, $antes, $noVenc, $apos, $emitir, $botao, $wa, $libTiposJson, $libTotal, $id,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO produtos
                     (nome, slug, tipo, ciclo, valor_centavos, descricao, recursos, destaque, ativo, mostrar_site, ordem,
                      dias_gerar_antes, cobranca_antes, cobranca_no_vencimento, cobranca_apos, emitir_auto, botao_texto, whatsapp_msg,
                      liberar_tipos, liberar_acesso_total, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
                )->execute([
                    $nome, $slug, $tipo, $ciclo, $cent, $desc, $recursos, $destaque, $ativo, $mostrar, $ordem,
                    $diasGerar, $antes, $noVenc, $apos, $emitir, $botao, $wa, $libTiposJson, $libTotal,
                ]);
                $id = intval($pdo->lastInsertId());
            }
            header('Location: produtos.php?id=' . $id . '&ok=1');
            exit;
        } catch (Throwable $e) {
            $err = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
    $edit = [
        'id' => $id, 'nome' => $nome, 'slug' => $slug, 'tipo' => $tipo, 'ciclo' => $ciclo,
        'valor_centavos' => $cent, 'descricao' => $desc, 'recursos' => $recursos,
        'destaque' => $destaque, 'ativo' => $ativo, 'mostrar_site' => $mostrar, 'ordem' => $ordem,
        'dias_gerar_antes' => $diasGerar, 'cobranca_antes' => $antes, 'cobranca_apos' => $apos,
        'cobranca_no_vencimento' => $noVenc, 'emitir_auto' => $emitir, 'botao_texto' => $botao, 'whatsapp_msg' => $wa,
        'liberar_tipos' => $libTiposJson, 'liberar_acesso_total' => $libTotal,
    ];
}

if ($edit === null && (isset($_GET['id']) || isset($_GET['novo']))) {
    if (!empty($_GET['id'])) {
        $edit = billing_produto_by_id(intval($_GET['id']));
    } else {
        $edit = [
            'id' => 0, 'nome' => '', 'slug' => '', 'tipo' => 'mensalidade', 'ciclo' => 'mensal',
            'valor_centavos' => 0, 'descricao' => '', 'recursos' => "Conteúdos liberados\nSuporte\nAtualizações",
            'destaque' => 0, 'ativo' => 1, 'mostrar_site' => 1, 'ordem' => 0,
            'dias_gerar_antes' => 7, 'cobranca_antes' => '[3]', 'cobranca_apos' => '[1,2,3]',
            'cobranca_no_vencimento' => 1, 'emitir_auto' => 1, 'botao_texto' => 'Assinar', 'whatsapp_msg' => '',
            'liberar_tipos' => '[]', 'liberar_acesso_total' => 0,
        ];
    }
}

$lista = billing_produtos_lista(false, false);
if (isset($_GET['ok'])) $ok = 'Salvo com sucesso.';

admin_header($edit ? (!empty($edit['id']) ? 'Editar produto' : 'Novo produto') : 'Produtos e preços', 'produtos');
admin_flash($ok, $err);

if ($edit):
    $edit = billing_produto_normalize_row($edit);
    $valorBr = number_format(intval($edit['valor_centavos']) / 100, 2, ',', '.');
    $antesStr = implode(', ', $edit['cobranca_antes_list']);
    $aposStr = implode(', ', $edit['cobranca_apos_list']);
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="produtos.php">← Lista</a>
    <a class="btn btn-secondary btn-small" href="../precos.php" target="_blank">Ver página de preços</a>
</div>
<div class="card">
    <form method="post">
        <input type="hidden" name="id" value="<?= intval($edit['id']) ?>">
        <h3 style="margin-bottom:12px;">Produto / plano / pacote</h3>
        <div class="field-row">
            <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($edit['nome']) ?>"></div>
            <div class="field"><label>Slug (URL)</label><input name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="auto"></div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Tipo</label>
                <select name="tipo">
                    <?php foreach ($tipos as $k => $m): ?>
                        <option value="<?= e($k) ?>" <?= ($edit['tipo'] ?? '') === $k ? 'selected' : '' ?>><?= e($m['icon'] . ' ' . $m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Ciclo de cobrança</label>
                <select name="ciclo">
                    <?php foreach ($ciclos as $k => $m): ?>
                        <option value="<?= e($k) ?>" <?= ($edit['ciclo'] ?? '') === $k ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="muted" style="margin-top:4px;font-size:.78rem;">Produto único usa ciclo “Único” (sem recorrência).</p>
            </div>
        </div>
        <div class="field-row">
            <div class="field"><label>Valor (R$) *</label><input name="valor" required value="<?= e($valorBr) ?>" placeholder="299,90"></div>
            <div class="field"><label>Ordem na vitrine</label><input type="number" name="ordem" value="<?= intval($edit['ordem'] ?? 0) ?>"></div>
        </div>
        <div class="field"><label>Descrição curta</label><textarea name="descricao" rows="2"><?= e($edit['descricao'] ?? '') ?></textarea></div>
        <div class="field">
            <label>Recursos (um por linha — aparece na tabela de preços)</label>
            <textarea name="recursos" rows="5"><?= e($edit['recursos'] ?? '') ?></textarea>
        </div>
        <div class="field-row">
            <div class="field"><label>Texto do botão</label><input name="botao_texto" value="<?= e($edit['botao_texto'] ?? 'Contratar') ?>"></div>
            <div class="field"><label>Mensagem WhatsApp (contratar)</label><input name="whatsapp_msg" value="<?= e($edit['whatsapp_msg'] ?? '') ?>" placeholder="Quero o plano X"></div>
        </div>
        <div class="field">
            <label><input type="checkbox" name="ativo" value="1" <?= !empty($edit['ativo']) ? 'checked' : '' ?>> Ativo</label>
            &nbsp;&nbsp;
            <label><input type="checkbox" name="mostrar_site" value="1" <?= !empty($edit['mostrar_site']) ? 'checked' : '' ?>> Mostrar na página de preços</label>
            &nbsp;&nbsp;
            <label><input type="checkbox" name="destaque" value="1" <?= !empty($edit['destaque']) ? 'checked' : '' ?>> Destaque (recomendado)</label>
        </div>

        <h3 style="margin:22px 0 12px;font-size:1.05rem;">Cobrança recorrente (automática)</h3>
        <p class="muted" style="margin-bottom:12px;font-size:.85rem;">
            Configure como o sistema gera faturas e quando emite Pix/boleto no Asaas (estilo WHMCS).
        </p>
        <div class="field">
            <label>Gerar fatura com quantos dias de antecedência?</label>
            <input type="number" name="dias_gerar_antes" min="0" max="60" value="<?= intval($edit['dias_gerar_antes'] ?? 7) ?>">
            <p class="muted" style="margin-top:4px;font-size:.78rem;">Ex.: 7 = a fatura do vencimento 20/08 é criada em 13/08.</p>
        </div>
        <div class="field">
            <label>Cobrar (emitir Pix/boleto) quantos dias <strong>antes</strong> do vencimento?</label>
            <input name="cobranca_antes" value="<?= e($antesStr) ?>" placeholder="Ex.: 7, 3, 1  (vazio = não cobra antes)">
            <p class="muted" style="margin-top:4px;font-size:.78rem;">Lista de dias separados por vírgula. Opcional.</p>
        </div>
        <div class="field">
            <label><input type="checkbox" name="cobranca_no_vencimento" value="1" <?= !empty($edit['cobranca_no_vencimento']) ? 'checked' : '' ?>> Cobrar no dia do vencimento</label>
        </div>
        <div class="field">
            <label>Cobrar quantos dias <strong>depois</strong> do vencimento?</label>
            <input name="cobranca_apos" value="<?= e($aposStr) ?>" placeholder="Ex.: 1, 2, 3">
            <p class="muted" style="margin-top:4px;font-size:.78rem;">Ex.: 1,2,3 = tenta de novo no 1º, 2º e 3º dia de atraso (renova QR/boleto se preciso).</p>
        </div>
        <div class="field">
            <label><input type="checkbox" name="emitir_auto" value="1" <?= !empty($edit['emitir_auto']) ? 'checked' : '' ?>> Emitir Pix/boleto automaticamente no Asaas</label>
        </div>

        <h3 style="margin:22px 0 12px;font-size:1.05rem;">Liberação após o pagamento</h3>
        <p class="muted" style="margin-bottom:12px;font-size:.85rem;">
            Quando o cliente pagar a fatura deste produto, o sistema libera automaticamente as categorias abaixo na área do cliente.
        </p>
        <div class="field">
            <label>
                <input type="checkbox" name="liberar_acesso_total" value="1" id="libTotal"
                    <?= !empty($edit['liberar_acesso_total']) ? 'checked' : '' ?>>
                <strong>Acesso total</strong> — todas as categorias de conteúdo
            </label>
        </div>
        <div id="libTiposBox" style="<?= !empty($edit['liberar_acesso_total']) ? 'opacity:.45;pointer-events:none;' : '' ?>">
            <div style="display:grid;gap:8px;">
                <?php
                $libList = $edit['liberar_tipos_list'] ?? [];
                foreach (app_conteudo_tipos_cliente() as $tKey => $tMeta):
                ?>
                    <label style="display:flex;align-items:center;gap:10px;background:#0f172a;border:1px solid var(--line);border-radius:10px;padding:10px 12px;font-weight:600;">
                        <input type="checkbox" name="liberar_tipos[]" value="<?= e($tKey) ?>"
                            <?= in_array($tKey, $libList, true) ? 'checked' : '' ?>>
                        <span><?= $tMeta['icon'] ?? '' ?> <?= e($tMeta['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="actions" style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">Salvar</button>
            <a class="btn btn-secondary" href="produtos.php">Cancelar</a>
        </div>
    </form>
</div>
<script>
(function () {
    var t = document.getElementById('libTotal');
    var b = document.getElementById('libTiposBox');
    if (t && b) {
        t.addEventListener('change', function () {
            b.style.opacity = t.checked ? '.45' : '1';
            b.style.pointerEvents = t.checked ? 'none' : '';
        });
    }
})();
</script>
<?php else: ?>
<div class="card">
    <div class="actions" style="margin-bottom:14px;justify-content:space-between;width:100%;">
        <p class="muted" style="margin:0;">Cadastre planos, pacotes, mensalidades e produtos únicos. A página pública é <code>/precos.php</code>.</p>
        <div class="actions">
            <a class="btn btn-secondary btn-small" href="assinaturas.php">Assinaturas</a>
            <a class="btn btn-primary" href="produtos.php?novo=1">+ Novo produto</a>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Nome</th><th>Tipo</th><th>Ciclo</th><th>Valor</th><th>Gerar</th><th>Cobranças</th><th>Site</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $p):
            $p = billing_produto_normalize_row($p);
            $t = $tipos[$p['tipo']] ?? ['label' => $p['tipo']];
            $c = $ciclos[$p['ciclo']] ?? ['label' => $p['ciclo']];
            $antes = $p['cobranca_antes_list'];
            $apos = $p['cobranca_apos_list'];
            $cob = [];
            if ($antes) $cob[] = 'antes: ' . implode(',', $antes);
            if (!empty($p['cobranca_no_vencimento'])) $cob[] = 'no venc.';
            if ($apos) $cob[] = 'após: ' . implode(',', $apos);
        ?>
            <tr>
                <td>
                    <strong><?= e($p['nome']) ?></strong>
                    <?php if (!empty($p['destaque'])): ?><span class="badge badge-ok">destaque</span><?php endif; ?>
                    <?php if (empty($p['ativo'])): ?><span class="badge badge-off">inativo</span><?php endif; ?>
                </td>
                <td><?= e($t['label']) ?></td>
                <td><?= e($c['label']) ?></td>
                <td><?= e(app_money_br(intval($p['valor_centavos']))) ?></td>
                <td class="muted"><?= intval($p['dias_gerar_antes']) ?>d antes</td>
                <td class="muted" style="font-size:.8rem;"><?= e($cob ? implode(' · ', $cob) : '—') ?></td>
                <td><?= !empty($p['mostrar_site']) ? 'Sim' : 'Não' ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="produtos.php?id=<?= intval($p['id']) ?>">Editar</a>
                    <a class="btn btn-danger btn-small" href="produtos.php?del=<?= intval($p['id']) ?>" onclick="return confirm('Excluir este produto?');">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?>
            <tr><td colspan="8" class="muted">Nenhum produto ainda. Clique em <strong>+ Novo produto</strong>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
endif;
admin_footer();
