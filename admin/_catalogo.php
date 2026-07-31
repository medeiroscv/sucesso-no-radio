<?php
/**
 * Catálogo compartilhado:
 * - area=demonstrativo → site público (form completo)
 * - area=conteudo → produtos do cliente (apenas vinculo com pasta Nextcloud)
 */
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/nextcloud.php';
require_once __DIR__ . '/../includes/billing.php';

$area = defined('CATALOGO_AREA') ? CATALOGO_AREA : 'conteudo';
if (!app_catalogo_area_valida($area)) {
    $area = 'conteudo';
}
$areaMeta = app_catalogo_area_meta($area);
$isDemo = $area === 'demonstrativo';
$script = $areaMeta['file'];
$navActive = $areaMeta['active'];

$pdo = app_pdo();
$tipos = $isDemo ? app_conteudo_tipos() : app_conteudo_tipos_cliente();
$ok = $err = '';
$edit = null;

$tipo = trim((string)($_GET['tipo'] ?? $_POST['tipo'] ?? ''));
if ($tipo !== '' && !isset($tipos[$tipo])) {
    $tipo = '';
}

// ---- Excluir ----
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $tipoDel = trim((string)($_GET['tipo'] ?? ''));
    if (!isset($tipos[$tipoDel])) {
        $tipoDel = array_key_first($tipos) ?: 'diario';
    }
    $st = $pdo->prepare('SELECT capa, area FROM conteudos WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if ($row && ($row['area'] ?? '') === $area) {
        if (!empty($row['capa'])) {
            admin_delete_local_upload((string)$row['capa']);
        }
        $pdo->prepare('DELETE FROM conteudos WHERE id = ?')->execute([$id]);
    }
    header('Location: ' . $script . '?tipo=' . rawurlencode($tipoDel) . '&ok=1');
    exit;
}

// ---- nc_folder_set: criar/atualizar conteudo diretamente ----
if (isset($_GET['nc_folder_set']) && !$isDemo) {
    $ncSet = trim((string)($_GET['nc_folder_set']));
    if ($ncSet !== '') {
        $id = intval($_GET['id'] ?? 0);
        $tipoSet = $tipo ?: 'diario';
        if ($id > 0) {
            $pdo->prepare('UPDATE conteudos SET nc_folder=?, updated_at=NOW() WHERE id=? AND area=?')
                ->execute([$ncSet, $id, $area]);
        } else {
            $titulo = basename($ncSet);
            $slug = app_slug($titulo);
            $slugCheck = $pdo->prepare('SELECT id FROM conteudos WHERE slug = ? LIMIT 1');
            $slugCheck->execute([$slug]);
            if ($slugCheck->fetch()) {
                $slug .= '-' . bin2hex(random_bytes(2));
            }
            $pdo->prepare(
                'INSERT INTO conteudos (area,tipo,titulo,slug,nc_folder,ativo,created_at)
                 VALUES (?,?,?,?,?,1,NOW())'
            )->execute([$area, $tipoSet, $titulo, $slug, $ncSet]);
        }
    }
    header('Location: ' . $script . '?tipo=' . rawurlencode($tipo));
    exit;
}

// ---- Salvar (apenas demonstrativo usa POST form) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Produto: salva entregas e demonstrativos (sem Nextcloud)
    if (!$isDemo && $tipo === 'produto') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            if (function_exists('admin_salvar_produto_entregas')) admin_salvar_produto_entregas($id);
            if (function_exists('admin_salvar_produto_demonstrativos')) admin_salvar_produto_demonstrativos($id);
        }
        header('Location: ' . $script . '?tipo=produto&id=' . $id . '&ok=1');
        exit;
    }
    $id = intval($_POST['id'] ?? 0);
    $tipoPost = trim((string)($_POST['tipo'] ?? 'diario'));
    if (!isset($tipos[$tipoPost])) {
        $tipoPost = array_key_first($tipos) ?: 'diario';
    }
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    if ($slug === '') {
        $slug = app_slug($titulo);
    } else {
        $slug = app_slug($slug);
    }
    $resumo = trim((string)($_POST['resumo'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $duracao = trim((string)($_POST['duracao'] ?? ''));
    $blocos = trim((string)($_POST['blocos'] ?? ''));
    $dias = trim((string)($_POST['dias'] ?? ''));
    $insercoes = trim((string)($_POST['insercoes'] ?? ''));
    $ordem = intval($_POST['ordem'] ?? 0);
    $destaque = !empty($_POST['destaque']) ? 1 : 0;
    $ativo = !empty($_POST['ativo']) ? 1 : 0;
    $whatsapp_msg = trim((string)($_POST['whatsapp_msg'] ?? ''));
    $capaAtual = trim((string)($_POST['capa_atual'] ?? ''));
    $capaNova = admin_upload('capa', 'programas');
    if ($capaNova !== '') {
        if ($capaAtual !== '' && $capaAtual !== $capaNova) {
            admin_delete_local_upload($capaAtual);
        }
        $capa = $capaNova;
    } else {
        $capa = $capaAtual;
    }

    if ($titulo === '') {
        $err = 'Título obrigatório.';
        $tipo = $tipoPost;
        $edit = compact('id', 'titulo', 'slug', 'resumo', 'descricao', 'capa', 'duracao', 'blocos', 'dias', 'insercoes', 'destaque', 'ativo', 'ordem', 'whatsapp_msg');
        $edit['tipo'] = $tipoPost;
        $edit['area'] = $area;
    } else {
        $slugCheck = $pdo->prepare('SELECT id FROM conteudos WHERE slug = ? AND id <> ? LIMIT 1');
        $slugCheck->execute([$slug, $id]);
        if ($slugCheck->fetch()) {
            $slug .= '-' . ($id > 0 ? $id : bin2hex(random_bytes(2)));
        }
        try {
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE conteudos SET area=?, tipo=?, titulo=?, slug=?, resumo=?, descricao=?, capa=?, duracao=?, blocos=?, dias=?, insercoes=?, destaque=?, ativo=?, ordem=?, whatsapp_msg=?, updated_at=NOW()
                     WHERE id=? AND area=?'
                )->execute([
                    $area, $tipoPost, $titulo, $slug, $resumo, $descricao, $capa, $duracao, $blocos, $dias,
                    $insercoes, $destaque, $ativo, $ordem, $whatsapp_msg, $id, $area,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO conteudos (area,tipo,titulo,slug,resumo,descricao,capa,duracao,blocos,dias,insercoes,destaque,ativo,ordem,whatsapp_msg,created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
                )->execute([
                    $area, $tipoPost, $titulo, $slug, $resumo, $descricao, $capa, $duracao, $blocos, $dias,
                    $insercoes, $destaque, $ativo, $ordem, $whatsapp_msg,
                ]);
                $id = intval($pdo->lastInsertId());
            }
            if ($isDemo) {
                admin_salvar_demonstrativos('conteudo', $id);
            }
            header('Location: ' . $script . '?tipo=' . rawurlencode($tipoPost) . '&id=' . $id . '&ok=1');
            exit;
        } catch (Throwable $e) {
            $err = 'Erro ao salvar: ' . $e->getMessage();
            $tipo = $tipoPost;
        }
    }
}

// ---- Form novo/editar ----
if (isset($_GET['id']) || isset($_GET['novo'])) {
    if (!empty($_GET['id'])) {
        if ($tipo === 'produto' && !$isDemo) {
            $edit = billing_produto_by_id(intval($_GET['id']));
            if ($edit) $edit = billing_produto_normalize_row($edit);
        } else {
            $st = $pdo->prepare('SELECT * FROM conteudos WHERE id = ? AND area = ?');
            $st->execute([intval($_GET['id']), $area]);
            $edit = $st->fetch() ?: null;
        }
        if ($edit) {
            $tipoVal = $edit['tipo'] ?? '';
            if ($tipoVal !== '' && isset($tipos[$tipoVal])) $tipo = $tipoVal;
        }
    } else {
        if ($tipo === 'produto' && !$isDemo) {
            header('Location: produtos.php');
            exit;
        }
        $tipoNovo = ($tipo !== '' && isset($tipos[$tipo])) ? $tipo : (array_key_first($tipos) ?: 'diario');
        $meta = $tipos[$tipoNovo];
        $edit = [
            'id' => 0,
            'area' => $area,
            'tipo' => $tipoNovo,
            'titulo' => '',
            'slug' => '',
            'resumo' => '',
            'descricao' => '',
            'capa' => '',
            'duracao' => '',
            'blocos' => '',
            'dias' => $meta['dias_default'] ?? '',
            'insercoes' => $tipoNovo === 'programete' ? '1x/dia' : '',
            'destaque' => 0,
            'ativo' => 1,
            'ordem' => 0,
            'whatsapp_msg' => '',
            'nc_folder' => '',
        ];
        $tipo = $tipoNovo;
    }
}

if (isset($_GET['ok'])) {
    $ok = 'Salvo com sucesso.';
}

$counts = [];
foreach (array_keys($tipos) as $t) {
    try {
        if (!$isDemo && $t === 'produto') {
            $st = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE tipo IN ('avulso','pacote')");
        } else {
            $st = $pdo->prepare('SELECT COUNT(*) FROM conteudos WHERE tipo = ? AND area = ?');
            $st->execute([$t, $area]);
        }
        $counts[$t] = (int)$st->fetchColumn();
    } catch (Throwable $e) {
        $counts[$t] = 0;
    }
}

$lista = [];
if ($tipo !== '' && $edit === null) {
    if ($tipo === 'produto' && !$isDemo) {
        $todos = billing_produtos_lista(false, false);
        $lista = array_values(array_filter($todos, fn($p) => in_array($p['tipo'] ?? '', ['avulso', 'pacote'], true)));
        $lista = array_map('billing_produto_normalize_row', $lista);
    } else {
        $st = $pdo->prepare('SELECT * FROM conteudos WHERE tipo = ? AND area = ? ORDER BY ordem ASC, id DESC');
        $st->execute([$tipo, $area]);
        $lista = $st->fetchAll() ?: [];
    }
}

if ($tipo === 'produto' && !$isDemo && $edit !== null) {
    $pageTitle = 'Entregas · ' . ($edit['nome'] ?? 'Produto');
} elseif ($edit !== null) {
    $tipoLabel = $tipos[$edit['tipo'] ?? $tipo]['label'] ?? $areaMeta['singular'];
    $pageTitle = !empty($edit['id']) ? 'Editar · ' . $tipoLabel : 'Novo · ' . $tipoLabel;
} elseif ($tipo !== '') {
    $pageTitle = ($tipos[$tipo]['label'] ?? '') . ' · ' . $areaMeta['label'];
} else {
    $pageTitle = $areaMeta['label'];
}

admin_header($pageTitle, $navActive);
admin_flash($ok, $err);

// ========== HUB ==========
if ($tipo === '' && $edit === null):
?>
<div class="card">
    <p class="muted" style="margin-bottom:8px;"><strong><?= e($areaMeta['label']) ?></strong></p>
    <p class="muted" style="margin-bottom:16px;"><?= e($areaMeta['desc']) ?></p>
    <div class="conteudo-hub">
        <?php foreach ($tipos as $key => $meta): ?>
            <a class="conteudo-hub-card" href="<?= e($script) ?>?tipo=<?= e($key) ?>">
                <div class="conteudo-hub-icon"><?= $meta['icon'] ?></div>
                <h3><?= e($meta['label']) ?></h3>
                <p><?= e($meta['desc']) ?></p>
                <div class="conteudo-hub-count"><?= (int)($counts[$key] ?? 0) ?> item(ns)</div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php
// ========== PRODUTO: FORMULÁRIO DE ENTREGAS ==========
elseif ($tipo === 'produto' && !$isDemo && $edit !== null):
    $prodId = intval($edit['id'] ?? 0);
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="<?= e($script) ?>?tipo=produto">← Lista de produtos</a>
    <a class="btn btn-secondary btn-small" href="produtos.php?id=<?= $prodId ?>" target="_blank">Editar produto</a>
</div>
<div class="card">
    <h3 style="margin-bottom:8px;"><?= e($edit['nome'] ?? 'Produto') ?></h3>
    <p class="muted" style="margin-bottom:16px;">
        <?= e($tipos['produto']['icon'] ?? '') ?>
        <?= e($edit['tipo'] === 'avulso' ? 'Produto avulso' : 'Pacote') ?> ·
        <?= e(app_money_br(intval($edit['valor_centavos']))) ?>
    </p>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $prodId ?>">
        <?php admin_bloco_produto_entregas($prodId); ?>
        <div class="actions" style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">Salvar entregas</button>
            <a class="btn btn-secondary" href="<?= e($script) ?>?tipo=produto">Cancelar</a>
        </div>
    </form>
</div>
<?php
// ========== FORM (demonstrativo) ==========
elseif ($edit !== null):
    $tipoAtual = (string)($edit['tipo'] ?? $tipo);
    if ($isDemo):
        $isProgramete = $tipoAtual === 'programete';
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="<?= e($script) ?>">← Tipos</a>
    <a class="btn btn-secondary btn-small" href="<?= e($script) ?>?tipo=<?= e($tipoAtual) ?>">← Lista de <?= e($tipos[$tipoAtual]['label'] ?? '') ?></a>
</div>
<div class="card">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= intval($edit['id']) ?>">
        <input type="hidden" name="capa_atual" value="<?= e($edit['capa'] ?? '') ?>">
        <div class="field-row">
            <div class="field">
                <label>Tipo *</label>
                <select name="tipo" required>
                    <?php foreach ($tipos as $key => $meta): ?>
                        <option value="<?= e($key) ?>" <?= $tipoAtual === $key ? 'selected' : '' ?>>
                            <?= e($meta['icon'] . ' ' . $meta['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Ordem</label><input type="number" name="ordem" value="<?= intval($edit['ordem'] ?? 0) ?>"></div>
        </div>
        <div class="field-row">
            <div class="field"><label>Título *</label><input name="titulo" required value="<?= e($edit['titulo'] ?? '') ?>"></div>
            <div class="field"><label>Slug (URL)</label><input name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="gerado automaticamente"></div>
        </div>
        <?php if (!$isProgramete): ?>
        <div class="field-row">
            <div class="field"><label>Duração</label><input name="duracao" value="<?= e($edit['duracao'] ?? '') ?>" placeholder="ex: 3 horas"></div>
            <div class="field"><label>Blocos</label><input name="blocos" value="<?= e($edit['blocos'] ?? '') ?>" placeholder="ex: 9 Blocos"></div>
        </div>
        <div class="field-row">
            <div class="field"><label>Dias</label><input name="dias" value="<?= e($edit['dias'] ?? '') ?>" placeholder="SEG A SAB"></div>
            <div class="field"><label>Inserções (opcional)</label><input name="insercoes" value="<?= e($edit['insercoes'] ?? '') ?>"></div>
        </div>
        <?php else: ?>
        <div class="field-row">
            <div class="field"><label>Inserções</label><input name="insercoes" value="<?= e($edit['insercoes'] ?? '1x/dia') ?>"></div>
            <div class="field"><label>Duração (opcional)</label><input name="duracao" value="<?= e($edit['duracao'] ?? '') ?>"></div>
        </div>
        <input type="hidden" name="blocos" value="<?= e($edit['blocos'] ?? '') ?>">
        <input type="hidden" name="dias" value="<?= e($edit['dias'] ?? '') ?>">
        <?php endif; ?>
        <div class="field"><label>Resumo (card)</label><textarea name="resumo" rows="2"><?= e($edit['resumo'] ?? '') ?></textarea></div>
        <div class="field"><label>Descrição completa</label><textarea name="descricao" rows="5"><?= e($edit['descricao'] ?? '') ?></textarea></div>
        <div class="field"><label>Mensagem WhatsApp (opcional)</label><input name="whatsapp_msg" value="<?= e($edit['whatsapp_msg'] ?? '') ?>"></div>
        <div class="field">
            <label>Capa (imagem)</label>
            <p class="muted" style="margin:4px 0 8px;">Convertida para JPEG (máx. 540×675).</p>
            <?php if (!empty($edit['capa'])): ?>
                <p class="muted">Atual: <img class="thumb" src="../<?= e($edit['capa']) ?>" alt=""></p>
            <?php endif; ?>
            <input type="file" name="capa" accept="image/*">
        </div>
        <div class="field-row">
            <div class="field"><label><input type="checkbox" name="ativo" value="1" <?= !empty($edit['ativo']) ? 'checked' : '' ?>> Ativo</label></div>
            <div class="field"><label><input type="checkbox" name="destaque" value="1" <?= !empty($edit['destaque']) ? 'checked' : '' ?>> Destaque</label></div>
        </div>
        <?php admin_bloco_demonstrativos('conteudo', intval($edit['id'] ?? 0)); ?>
        <p class="muted" style="margin-top:8px;">Áudios de demonstração — aparecem no site público.</p>
        <div class="actions" style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">Salvar</button>
            <a class="btn btn-secondary" href="<?= e($script) ?>?tipo=<?= e($tipoAtual) ?>">Cancelar</a>
        </div>
    </form>
</div>
<?php
    else:
        // ===== CONTEUDO: apenas vinculo com pasta Nextcloud =====
        $ncOk = nc_configurado();
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="<?= e($script) ?>?tipo=<?= e($tipoAtual) ?>">← Lista de <?= e($tipos[$tipoAtual]['label'] ?? '') ?></a>
</div>
<div class="card">
    <h3 style="margin-bottom:8px;">Vincular pasta do Nextcloud</h3>
    <p class="muted" style="margin-bottom:16px;">Selecione a pasta do Nextcloud que será exibida para os clientes nesta categoria.</p>

    <?php if ($ncOk):
        $ncBrowse = trim((string)($_GET['nc_browse'] ?? ''));
        if ($ncBrowse === '' && empty($edit['nc_folder'])) {
            $ncBrowse = '_root_';
        }
        if ($ncBrowse !== ''):
            $ncPath = $ncBrowse === '_root_' ? '' : $ncBrowse; ?>
        <div style="background:var(--card);border:1px solid var(--line);border-radius:8px;padding:10px;max-height:400px;overflow-y:auto;">
            <p class="muted" style="margin-bottom:8px;font-size:.85rem;">
                Navegando: <code><?= e($ncPath ?: 'Raiz') ?></code>
                <?php if (!empty($edit['nc_folder'])): ?>
                    <span class="chip" style="margin-left:8px;">Atual: <?= e($edit['nc_folder']) ?></span>
                <?php endif; ?>
            </p>
            <div style="margin-bottom:8px;display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($ncPath !== ''): ?>
                    <a class="btn btn-ghost btn-small" href="<?= e($script) ?>?tipo=<?= e($tipoAtual) ?>&id=<?= intval($edit['id']) ?>&nc_browse=_root_">← Raiz</a>
                    <?php $parent = dirname($ncPath); if ($parent !== '.' && $parent !== $ncPath): ?>
                        <a class="btn btn-ghost btn-small" href="<?= e($script) ?>?tipo=<?= e($tipoAtual) ?>&id=<?= intval($edit['id']) ?>&nc_browse=<?= rawurlencode($parent) ?>">← Pasta anterior</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php $ncItens = nc_listar($ncPath); ?>
            <?php if (!$ncItens): ?>
                <div class="muted">Pasta vazia.</div>
            <?php else: ?>
                <div style="display:grid;gap:3px;">
                    <?php foreach ($ncItens as $item): ?>
                        <?php if ($item['type'] === 'folder'): ?>
                            <div style="display:flex;align-items:center;gap:8px;padding:4px 6px;border-radius:4px;background:rgba(34,197,94,.06);border:1px solid var(--line);">
                                <span>📁 <?= e($item['name']) ?></span>
                                <span style="flex:1;"></span>
                                <a class="btn btn-ghost btn-small" href="<?= e($script) ?>?tipo=<?= e($tipoAtual) ?>&id=<?= intval($edit['id']) ?>&nc_browse=<?= rawurlencode($item['path']) ?>">Abrir</a>
                                <a class="btn btn-primary btn-small" href="<?= e($script) ?>?tipo=<?= e($tipoAtual) ?>&id=<?= intval($edit['id']) ?>&nc_folder_set=<?= rawurlencode($item['path']) ?>">Selecionar</a>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php elseif (!empty($edit['nc_folder'])): ?>
            <p>Pasta vinculada: <strong><?= e($edit['nc_folder']) ?></strong></p>
            <a class="btn btn-primary btn-small" href="<?= e($script) ?>?tipo=<?= e($tipoAtual) ?>&id=<?= intval($edit['id']) ?>&nc_browse=<?= rawurlencode($edit['nc_folder']) ?>">Alterar pasta</a>
        <?php else: ?>
            <p class="muted">Navegue pelas pastas acima e clique em <strong>Selecionar</strong> para vincular.</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="muted">Configure o Nextcloud em <a href="nextcloud.php">Admin > Nextcloud</a> para vincular pastas.</p>
    <?php endif; ?>
</div>
<?php
    endif;
// ========== PRODUTO: LISTA ==========
elseif ($tipo === 'produto' && !$isDemo):
    $ehAvulso = fn($p) => ($p['tipo'] ?? '') === 'avulso';
    $ehPacote = fn($p) => ($p['tipo'] ?? '') === 'pacote';
?>
<div class="actions" style="margin-bottom:12px;">
    <a class="btn btn-secondary btn-small" href="<?= e($script) ?>">← Todos os tipos</a>
    <a class="btn btn-primary btn-small" href="produtos.php?novo=1" target="_blank">+ Novo produto em Produtos e Preços</a>
</div>
<div class="card">
    <strong style="font-size:1.05rem;">📦 Produtos avulsos e pacotes</strong>
    <div class="muted" style="margin-bottom:14px;">Gerencie os arquivos de entrega e links.</div>
    <?php if (!$lista): ?>
        <p class="muted">Nenhum produto avulso ou pacote cadastrado. Crie um em <a href="produtos.php">Produtos e Preços</a>.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Tipo</th>
                    <th>Valor</th>
                    <th>Entregas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lista as $p):
                $qtd = count(app_produto_entregas(intval($p['id'])));
                $temDemo = count(app_produto_demonstrativos(intval($p['id']))) > 0;
            ?>
                <tr>
                    <td><strong><?= e($p['nome']) ?></strong></td>
                    <td><?= $ehAvulso($p) ? 'Avulso' : 'Pacote' ?></td>
                    <td><?= e(app_money_br(intval($p['valor_centavos']))) ?></td>
                    <td><?= $qtd ?> ite(ns)</td>
                    <td class="actions">
                        <a class="btn btn-secondary btn-small" href="<?= e($script) ?>?tipo=produto&id=<?= intval($p['id']) ?>">Gerenciar entregas</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
// ========== LISTA (demais tipos) ==========
else:
    $meta = $tipos[$tipo];
?>
<div class="actions" style="margin-bottom:12px;align-items:center;">
    <a class="btn btn-secondary btn-small" href="<?= e($script) ?>">← Todos os tipos</a>
    <?php foreach ($tipos as $key => $m): ?>
        <a class="btn btn-small <?= $key === $tipo ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e($script) ?>?tipo=<?= e($key) ?>">
            <?= $m['icon'] ?> <?= e($m['label']) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="actions" style="margin-bottom:14px;justify-content:space-between;width:100%;">
        <div>
            <strong style="font-size:1.05rem;"><?= $meta['icon'] ?> <?= e($meta['label']) ?></strong>
            <div class="muted"><?= e($meta['desc']) ?> · <?= e($areaMeta['label']) ?></div>
        </div>
        <a class="btn btn-primary" href="<?= e($script) ?>?tipo=<?= e($tipo) ?>&novo=1">+ Novo</a>
    </div>
    <table>
        <thead>
            <tr>
                <?php if ($isDemo): ?>
                <th>Capa</th>
                <?php endif; ?>
                <th>Título</th>
                <?php if ($isDemo): ?>
                <th><?= $tipo === 'programete' ? 'Inserções' : 'Duração' ?></th>
                <?php else: ?>
                <th>Pasta NC</th>
                <?php endif; ?>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $p): ?>
            <tr>
                <?php if ($isDemo): ?>
                <td><?php if (!empty($p['capa'])): ?><img class="thumb" src="../<?= e($p['capa']) ?>" alt=""><?php else: ?>—<?php endif; ?></td>
                <?php endif; ?>
                <td><strong><?= e($p['titulo']) ?></strong><?php if (!$isDemo): ?><div class="muted"><?= e($p['slug']) ?></div><?php endif; ?></td>
                <?php if ($isDemo): ?>
                <td><?= e($tipo === 'programete' ? ($p['insercoes'] ?: '—') : ($p['duracao'] ?: '—')) ?></td>
                <?php else: ?>
                <td><code><?= e($p['nc_folder'] ?: '—') ?></code></td>
                <?php endif; ?>
                <td><?= !empty($p['ativo']) ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-off">Inativo</span>' ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="<?= e($script) ?>?tipo=<?= e($tipo) ?>&id=<?= intval($p['id']) ?>">Editar</a>
                    <a class="btn btn-danger btn-small" href="<?= e($script) ?>?tipo=<?= e($tipo) ?>&del=<?= intval($p['id']) ?>" onclick="return confirm('Excluir?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?>
            <tr><td colspan="<?= $isDemo ? 5 : 4 ?>" class="muted">Nenhum item ainda. Clique em <strong>+ Novo</strong>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
endif;
admin_footer();
