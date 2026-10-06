<?php
require_once __DIR__ . '/_layout.php';

$pdo = app_pdo();
$ok = $err = '';
$edit = null;

function ps_parse_valor(string $raw): int {
    $raw = trim($raw);
    $raw = preg_replace('/[^0-9,.-]/', '', $raw) ?? '0';
    if (str_contains($raw, ',')) {
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
    }
    return (int)round(max(0, (float)$raw) * 100);
}

function ps_by_id(int $id): ?array {
    if ($id <= 0) return null;
    $st = app_pdo()->prepare('SELECT * FROM produtos_servicos WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function ps_demos_from_post(): array {
    $titulos = $_POST['demo_titulo'] ?? [];
    $links = $_POST['demo_link'] ?? [];
    if (!is_array($titulos)) $titulos = [];
    if (!is_array($links)) $links = [];

    $rows = [];
    $total = max(count($titulos), count($links));
    for ($i = 0; $i < $total; $i++) {
        $titulo = trim((string)($titulos[$i] ?? ''));
        $url = trim((string)($links[$i] ?? ''));
        if ($titulo === '' && $url === '') continue;
        $rows[] = ['titulo' => $titulo, 'url' => $url];
    }
    return $rows;
}

function ps_demos_validate(array $demos): string {
    foreach ($demos as $i => $demo) {
        $url = trim((string)($demo['url'] ?? ''));
        if ($url === '') {
            return 'Informe o link da demonstração ' . ($i + 1) . ' ou remova essa linha.';
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return 'O link da demonstração ' . ($i + 1) . ' precisa ser uma URL válida.';
        }
    }
    return '';
}

function ps_demos_save(int $produtoServicoId, array $demos): void {
    if ($produtoServicoId <= 0) return;
    $pdo = app_pdo();
    $pdo->prepare('DELETE FROM produto_servico_demos WHERE produto_servico_id = ?')->execute([$produtoServicoId]);
    $ins = $pdo->prepare(
        'INSERT INTO produto_servico_demos (produto_servico_id, titulo, url, ordem, created_at)
         VALUES (?,?,?,?,NOW())'
    );
    foreach ($demos as $i => $demo) {
        $titulo = trim((string)($demo['titulo'] ?? ''));
        $url = trim((string)($demo['url'] ?? ''));
        if ($url === '') continue;
        if ($titulo === '') $titulo = 'Modelo ' . ($i + 1);
        $ins->execute([$produtoServicoId, $titulo, $url, $i]);
    }

    // Mantém a antiga coluna preenchida com o primeiro link por compatibilidade.
    $primeiro = trim((string)($demos[0]['url'] ?? ''));
    $pdo->prepare('UPDATE produtos_servicos SET demo_url = ? WHERE id = ?')->execute([$primeiro, $produtoServicoId]);
}

$tipos = [
    'servico' => 'Serviço',
    'produto' => 'Produto',
];
$periodicidades = [
    'sob_consulta' => 'Sob consulta',
    'unico' => 'Pagamento único',
    'mensal' => 'Mensal',
    'trimestral' => 'Trimestral',
    'semestral' => 'Semestral',
    'anual' => 'Anual',
];

if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $row = ps_by_id($id);
    if ($row) {
        $pdo->prepare('DELETE FROM produtos_servicos WHERE id = ?')->execute([$id]);
        if (!empty($row['capa'])) admin_delete_local_upload((string)$row['capa']);
    }
    header('Location: produtos-servicos.php?ok=deleted');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim((string)($_POST['nome'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    $tipo = (string)($_POST['tipo'] ?? 'servico');
    if (!isset($tipos[$tipo])) $tipo = 'servico';
    $categoria = trim((string)($_POST['categoria'] ?? ''));
    $resumo = trim((string)($_POST['resumo'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $recursos = trim((string)($_POST['recursos'] ?? ''));
    $precoCentavos = ps_parse_valor((string)($_POST['preco'] ?? '0'));
    $exibirPreco = !empty($_POST['exibir_preco']) ? 1 : 0;
    $precoTexto = trim((string)($_POST['preco_texto'] ?? 'Sob consulta')) ?: 'Sob consulta';
    $periodicidade = (string)($_POST['periodicidade'] ?? 'sob_consulta');
    if (!isset($periodicidades[$periodicidade])) $periodicidade = 'sob_consulta';
    $whmcs = trim((string)($_POST['whmcs_url'] ?? ''));
    $demosPost = ps_demos_from_post();
    $demoErro = ps_demos_validate($demosPost);
    $demoUrl = trim((string)($demosPost[0]['url'] ?? ''));
    $wa = trim((string)($_POST['whatsapp_msg'] ?? ''));
    $botao = trim((string)($_POST['botao_texto'] ?? 'Saiba mais')) ?: 'Saiba mais';
    $destaque = !empty($_POST['destaque']) ? 1 : 0;
    $ativo = !empty($_POST['ativo']) ? 1 : 0;
    $ordem = (int)($_POST['ordem'] ?? 0);
    $capaAtual = trim((string)($_POST['capa_atual'] ?? ''));
    $capa = $capaAtual;

    if ($nome === '') {
        $err = 'Informe o nome do produto ou serviço.';
    } elseif ($whmcs !== '' && !filter_var($whmcs, FILTER_VALIDATE_URL)) {
        $err = 'O link do WHMCS precisa ser uma URL válida.';
    } elseif ($demoErro !== '') {
        $err = $demoErro;
    } else {
        if (!empty($_POST['remover_capa'])) {
            if ($capaAtual !== '') admin_delete_local_upload($capaAtual);
            $capa = '';
        } else {
            $nova = admin_upload('capa', 'produtos-servicos', [], 900, 900, 84);
            if ($nova !== '') {
                if ($capaAtual !== '' && $capaAtual !== $nova) admin_delete_local_upload($capaAtual);
                $capa = $nova;
            }
        }

        $baseSlug = app_slug($slug !== '' ? $slug : $nome);
        $slugTry = $baseSlug;
        $n = 2;
        while (true) {
            $st = $pdo->prepare('SELECT id FROM produtos_servicos WHERE slug = ? AND id <> ? LIMIT 1');
            $st->execute([$slugTry, $id]);
            if (!$st->fetch()) break;
            $slugTry = $baseSlug . '-' . $n++;
        }
        $slug = $slugTry;

        if ($id > 0) {
            $pdo->prepare(
                'UPDATE produtos_servicos SET
                    nome=?, slug=?, tipo=?, categoria=?, resumo=?, descricao=?, capa=?, recursos=?,
                    preco_centavos=?, exibir_preco=?, preco_texto=?, periodicidade=?, whmcs_url=?,
                    demo_url=?, whatsapp_msg=?, botao_texto=?, destaque=?, ativo=?, ordem=?, updated_at=NOW()
                 WHERE id=?'
            )->execute([
                $nome, $slug, $tipo, $categoria, $resumo, $descricao, $capa, $recursos,
                $precoCentavos, $exibirPreco, $precoTexto, $periodicidade, $whmcs,
                $demoUrl, $wa, $botao, $destaque, $ativo, $ordem, $id
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO produtos_servicos
                    (nome,slug,tipo,categoria,resumo,descricao,capa,recursos,preco_centavos,
                     exibir_preco,preco_texto,periodicidade,whmcs_url,demo_url,whatsapp_msg,botao_texto,
                     destaque,ativo,ordem,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
            )->execute([
                $nome, $slug, $tipo, $categoria, $resumo, $descricao, $capa, $recursos,
                $precoCentavos, $exibirPreco, $precoTexto, $periodicidade, $whmcs,
                $demoUrl, $wa, $botao, $destaque, $ativo, $ordem
            ]);
            $id = (int)$pdo->lastInsertId();
        }
        ps_demos_save($id, $demosPost);
        header('Location: produtos-servicos.php?id=' . $id . '&ok=1');
        exit;
    }

    $edit = compact(
        'id','nome','slug','tipo','categoria','resumo','descricao','capa','recursos',
        'precoCentavos','exibirPreco','precoTexto','periodicidade','whmcs','demoUrl','wa','botao',
        'destaque','ativo','ordem'
    );
    $edit['preco_centavos'] = $precoCentavos;
    $edit['exibir_preco'] = $exibirPreco;
    $edit['preco_texto'] = $precoTexto;
    $edit['whmcs_url'] = $whmcs;
    $edit['demo_url'] = $demoUrl;
    $edit['whatsapp_msg'] = $wa;
    $edit['botao_texto'] = $botao;
    $edit['_demos'] = $demosPost;
}

if ($edit === null && (isset($_GET['id']) || isset($_GET['novo']))) {
    if (!empty($_GET['id'])) {
        $edit = ps_by_id((int)$_GET['id']);
    } else {
        $edit = [
            'id'=>0,'nome'=>'','slug'=>'','tipo'=>'servico','categoria'=>'',
            'resumo'=>'','descricao'=>'','capa'=>'','recursos'=>'','preco_centavos'=>0,
            'exibir_preco'=>0,'preco_texto'=>'Sob consulta','periodicidade'=>'sob_consulta',
            'whmcs_url'=>'','demo_url'=>'','whatsapp_msg'=>'','botao_texto'=>'Saiba mais',
            'destaque'=>0,'ativo'=>1,'ordem'=>0
        ];
    }
}

$lista = [];
try {
    $lista = $pdo->query('SELECT * FROM produtos_servicos ORDER BY destaque DESC, ordem ASC, nome ASC')->fetchAll() ?: [];
} catch (Throwable $e) {}

if (isset($_GET['ok'])) $ok = $_GET['ok'] === 'deleted' ? 'Item excluído.' : 'Salvo com sucesso.';

admin_header($edit ? (!empty($edit['id']) ? 'Editar produto ou serviço' : 'Novo produto ou serviço') : 'Produtos e Serviços', 'produtos-servicos');
admin_flash($ok, $err);

if ($edit):
    $valorBr = number_format(((int)($edit['preco_centavos'] ?? 0)) / 100, 2, ',', '.');
    $demosEdit = $edit['_demos'] ?? app_produto_servico_demos((int)($edit['id'] ?? 0), $edit);
    if (!$demosEdit) $demosEdit = [['titulo' => '', 'url' => '']];
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="produtos-servicos.php">← Lista</a>
    <?php if (!empty($edit['slug'])): ?>
        <a class="btn btn-secondary btn-small" href="../produto-servico.php?slug=<?= rawurlencode((string)$edit['slug']) ?>" target="_blank">Ver no site</a>
    <?php endif; ?>
</div>

<div class="card">
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <input type="hidden" name="capa_atual" value="<?= e($edit['capa'] ?? '') ?>">

    <h3 style="margin-bottom:14px;">Informações principais</h3>
    <div class="field-row">
        <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($edit['nome'] ?? '') ?>"></div>
        <div class="field"><label>Slug</label><input name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="gerado automaticamente"></div>
    </div>

    <div class="field-row">
        <div class="field">
            <label>Tipo</label>
            <select name="tipo"><?php foreach ($tipos as $k=>$label): ?><option value="<?= e($k) ?>" <?= ($edit['tipo']??'')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>Categoria</label><input name="categoria" value="<?= e($edit['categoria'] ?? '') ?>" placeholder="Ex.: Streaming, Sites, Áudio, Marketing"></div>
    </div>

    <div class="field"><label>Resumo para o card</label><textarea name="resumo" rows="3"><?= e($edit['resumo'] ?? '') ?></textarea></div>
    <div class="field"><label>Descrição completa</label><textarea name="descricao" rows="7"><?= e($edit['descricao'] ?? '') ?></textarea></div>
    <div class="field"><label>Benefícios / recursos (um por linha)</label><textarea name="recursos" rows="6"><?= e($edit['recursos'] ?? '') ?></textarea></div>

    <div class="field">
        <label>Capa</label>
        <?php if (!empty($edit['capa'])): ?>
            <p style="margin:8px 0;"><img class="thumb" src="../<?= e($edit['capa']) ?>" alt="" style="max-width:180px;"></p>
            <label class="muted"><input type="checkbox" name="remover_capa" value="1"> Remover capa atual</label>
        <?php endif; ?>
        <input type="file" name="capa" accept="image/*" style="margin-top:8px;">
    </div>

    <h3 style="margin:22px 0 12px;">Preço e contratação</h3>
    <div class="field-row">
        <div class="field"><label>Preço (R$)</label><input name="preco" value="<?= e($valorBr) ?>" placeholder="99,90"></div>
        <div class="field">
            <label>Periodicidade</label>
            <select name="periodicidade"><?php foreach ($periodicidades as $k=>$label): ?><option value="<?= e($k) ?>" <?= ($edit['periodicidade']??'')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        </div>
    </div>

    <div class="field-row">
        <div class="field"><label>Texto quando o preço não for exibido</label><input name="preco_texto" value="<?= e($edit['preco_texto'] ?? 'Sob consulta') ?>" placeholder="Sob consulta"></div>
        <div class="field"><label>Texto do botão</label><input name="botao_texto" value="<?= e($edit['botao_texto'] ?? 'Saiba mais') ?>"></div>
    </div>

    <div class="field"><label>Link do WHMCS (opcional)</label><input type="url" name="whmcs_url" value="<?= e($edit['whmcs_url'] ?? '') ?>" placeholder="https://..."></div>
    <div class="field" style="margin-top:18px;">
        <label>Demonstrações / modelos (opcional)</label>
        <p class="muted" style="margin:5px 0 10px;font-size:.8rem;">Adicione um ou vários links de modelos, sites de demonstração ou apresentações online.</p>
        <div id="demoLinks" style="display:grid;gap:10px;">
            <?php foreach ($demosEdit as $i => $demo): ?>
                <div class="ps-demo-row" style="display:grid;grid-template-columns:minmax(150px,.7fr) minmax(260px,1.7fr) auto;gap:8px;align-items:end;">
                    <div>
                        <label style="font-size:.76rem;">Nome do modelo (opcional)</label>
                        <input name="demo_titulo[]" value="<?= e($demo['titulo'] ?? '') ?>" placeholder="Ex.: Modelo 1">
                    </div>
                    <div>
                        <label style="font-size:.76rem;">Link da demonstração</label>
                        <input type="url" name="demo_link[]" value="<?= e($demo['url'] ?? '') ?>" placeholder="https://exemplo.com/modelo">
                    </div>
                    <button type="button" class="btn btn-danger btn-small" onclick="removeDemoRow(this)">Remover</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-secondary btn-small" style="margin-top:10px;" onclick="addDemoRow()">+ Adicionar demonstração</button>
    </div>
    <script>
    function addDemoRow() {
        var box = document.getElementById('demoLinks');
        if (!box) return;
        var row = document.createElement('div');
        row.className = 'ps-demo-row';
        row.style.cssText = 'display:grid;grid-template-columns:minmax(150px,.7fr) minmax(260px,1.7fr) auto;gap:8px;align-items:end;';
        row.innerHTML =
            '<div><label style="font-size:.76rem;">Nome do modelo (opcional)</label>' +
            '<input name="demo_titulo[]" placeholder="Ex.: Modelo ' + (box.children.length + 1) + '"></div>' +
            '<div><label style="font-size:.76rem;">Link da demonstração</label>' +
            '<input type="url" name="demo_link[]" placeholder="https://exemplo.com/modelo"></div>' +
            '<button type="button" class="btn btn-danger btn-small" onclick="removeDemoRow(this)">Remover</button>';
        box.appendChild(row);
    }
    function removeDemoRow(btn) {
        var box = document.getElementById('demoLinks');
        var row = btn.closest('.ps-demo-row');
        if (row) row.remove();
        if (box && box.children.length === 0) addDemoRow();
    }
    </script>
    <div class="field"><label>Mensagem de WhatsApp (fallback)</label><input name="whatsapp_msg" value="<?= e($edit['whatsapp_msg'] ?? '') ?>" placeholder="Olá! Quero saber mais sobre..."></div>

    <div class="field-row">
        <div class="field"><label>Ordem</label><input type="number" name="ordem" value="<?= (int)($edit['ordem'] ?? 0) ?>"></div>
        <div class="field" style="display:flex;gap:16px;align-items:end;flex-wrap:wrap;">
            <label><input type="checkbox" name="ativo" value="1" <?= !empty($edit['ativo'])?'checked':'' ?>> Ativo</label>
            <label><input type="checkbox" name="destaque" value="1" <?= !empty($edit['destaque'])?'checked':'' ?>> Destaque</label>
            <label><input type="checkbox" name="exibir_preco" value="1" <?= !empty($edit['exibir_preco'])?'checked':'' ?>> Exibir preço</label>
        </div>
    </div>

    <div class="actions" style="margin-top:18px;">
        <button class="btn btn-primary" type="submit">Salvar</button>
        <a class="btn btn-secondary" href="produtos-servicos.php">Cancelar</a>
    </div>
</form>
</div>
<?php else: ?>
<div class="card">
    <div class="actions" style="margin-bottom:14px;justify-content:space-between;width:100%;">
        <p class="muted" style="margin:0;">Cadastre qualquer produto ou serviço comercial sem depender dos Demonstrativos.</p>
        <a class="btn btn-primary" href="produtos-servicos.php?novo=1">+ Novo produto ou serviço</a>
    </div>
    <table>
        <thead><tr><th>Nome</th><th>Tipo</th><th>Categoria</th><th>Preço</th><th>Demo</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $item): ?>
            <tr>
                <td><strong><?= e($item['nome']) ?></strong><?php if (!empty($item['destaque'])): ?> <span class="badge badge-ok">destaque</span><?php endif; ?></td>
                <td><?= e($tipos[$item['tipo']] ?? ucfirst((string)$item['tipo'])) ?></td>
                <td><?= e($item['categoria'] ?: '—') ?></td>
                <td><?= e(app_produto_servico_preco($item)) ?></td>
                <?php $demosLista = app_produto_servico_demos((int)$item['id'], $item); ?>
                <td>
                    <?php if ($demosLista): ?>
                        <a href="<?= e($demosLista[0]['url']) ?>" target="_blank" rel="noopener"><?= count($demosLista) ?> modelo(s)</a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= !empty($item['ativo']) ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-off">Inativo</span>' ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="produtos-servicos.php?id=<?= (int)$item['id'] ?>">Editar</a>
                    <a class="btn btn-danger btn-small" href="produtos-servicos.php?del=<?= (int)$item['id'] ?>" onclick="return confirm('Excluir este item?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?><tr><td colspan="7" class="muted">Nenhum produto ou serviço cadastrado.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; admin_footer(); ?>
