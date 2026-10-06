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
                    whatsapp_msg=?, botao_texto=?, destaque=?, ativo=?, ordem=?, updated_at=NOW()
                 WHERE id=?'
            )->execute([
                $nome, $slug, $tipo, $categoria, $resumo, $descricao, $capa, $recursos,
                $precoCentavos, $exibirPreco, $precoTexto, $periodicidade, $whmcs,
                $wa, $botao, $destaque, $ativo, $ordem, $id
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO produtos_servicos
                    (nome,slug,tipo,categoria,resumo,descricao,capa,recursos,preco_centavos,
                     exibir_preco,preco_texto,periodicidade,whmcs_url,whatsapp_msg,botao_texto,
                     destaque,ativo,ordem,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
            )->execute([
                $nome, $slug, $tipo, $categoria, $resumo, $descricao, $capa, $recursos,
                $precoCentavos, $exibirPreco, $precoTexto, $periodicidade, $whmcs,
                $wa, $botao, $destaque, $ativo, $ordem
            ]);
            $id = (int)$pdo->lastInsertId();
        }
        header('Location: produtos-servicos.php?id=' . $id . '&ok=1');
        exit;
    }

    $edit = compact(
        'id','nome','slug','tipo','categoria','resumo','descricao','capa','recursos',
        'precoCentavos','exibirPreco','precoTexto','periodicidade','whmcs','wa','botao',
        'destaque','ativo','ordem'
    );
    $edit['preco_centavos'] = $precoCentavos;
    $edit['exibir_preco'] = $exibirPreco;
    $edit['preco_texto'] = $precoTexto;
    $edit['whmcs_url'] = $whmcs;
    $edit['whatsapp_msg'] = $wa;
    $edit['botao_texto'] = $botao;
}

if ($edit === null && (isset($_GET['id']) || isset($_GET['novo']))) {
    if (!empty($_GET['id'])) {
        $edit = ps_by_id((int)$_GET['id']);
    } else {
        $edit = [
            'id'=>0,'nome'=>'','slug'=>'','tipo'=>'servico','categoria'=>'',
            'resumo'=>'','descricao'=>'','capa'=>'','recursos'=>'','preco_centavos'=>0,
            'exibir_preco'=>0,'preco_texto'=>'Sob consulta','periodicidade'=>'sob_consulta',
            'whmcs_url'=>'','whatsapp_msg'=>'','botao_texto'=>'Saiba mais',
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
        <thead><tr><th>Nome</th><th>Tipo</th><th>Categoria</th><th>Preço</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $item): ?>
            <tr>
                <td><strong><?= e($item['nome']) ?></strong><?php if (!empty($item['destaque'])): ?> <span class="badge badge-ok">destaque</span><?php endif; ?></td>
                <td><?= e($tipos[$item['tipo']] ?? ucfirst((string)$item['tipo'])) ?></td>
                <td><?= e($item['categoria'] ?: '—') ?></td>
                <td><?= e(app_produto_servico_preco($item)) ?></td>
                <td><?= !empty($item['ativo']) ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-off">Inativo</span>' ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="produtos-servicos.php?id=<?= (int)$item['id'] ?>">Editar</a>
                    <a class="btn btn-danger btn-small" href="produtos-servicos.php?del=<?= (int)$item['id'] ?>" onclick="return confirm('Excluir este item?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?><tr><td colspan="6" class="muted">Nenhum produto ou serviço cadastrado.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; admin_footer(); ?>
