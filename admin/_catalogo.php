<?php
/**
 * Catálogo compartilhado:
 * - area=demonstrativo → site público
 * - area=conteudo → produtos do cliente (liberação manual)
 */
require_once __DIR__ . '/_layout.php';

$area = defined('CATALOGO_AREA') ? CATALOGO_AREA : 'conteudo';
if (!app_catalogo_area_valida($area)) {
    $area = 'conteudo';
}
$areaMeta = app_catalogo_area_meta($area);
$isDemo = $area === 'demonstrativo';
$script = $areaMeta['file'];
$navActive = $areaMeta['active'];

$pdo = app_pdo();
// Tipos: demonstrativos tem os 4; conteúdos do cliente = diários/semanais/informativos
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
        foreach (app_demonstrativos('conteudo', $id) as $d) {
            app_delete_demonstrativo(intval($d['id']));
        }
        foreach (app_entregas($id, false) as $ent) {
            app_delete_entrega(intval($ent['id']));
        }
        if (!empty($row['capa'])) {
            admin_delete_local_upload((string)$row['capa']);
        }
        $pdo->prepare('DELETE FROM conteudos WHERE id = ?')->execute([$id]);
    }
    header('Location: ' . $script . '?tipo=' . rawurlencode($tipoDel) . '&ok=1');
    exit;
}

// ---- Salvar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $capaNova = admin_upload('capa', $isDemo ? 'programas' : 'conteudos');
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
                // garante que não troca de área
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
            } else {
                admin_salvar_entregas($id);
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
        $st = $pdo->prepare('SELECT * FROM conteudos WHERE id = ? AND area = ?');
        $st->execute([intval($_GET['id']), $area]);
        $edit = $st->fetch() ?: null;
        if ($edit) {
            $tipo = (string)$edit['tipo'];
        }
    } else {
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
        $st = $pdo->prepare('SELECT COUNT(*) FROM conteudos WHERE tipo = ? AND area = ?');
        $st->execute([$t, $area]);
        $counts[$t] = (int)$st->fetchColumn();
    } catch (Throwable $e) {
        $counts[$t] = 0;
    }
}

$lista = [];
if ($tipo !== '' && $edit === null) {
    $st = $pdo->prepare('SELECT * FROM conteudos WHERE tipo = ? AND area = ? ORDER BY ordem ASC, id DESC');
    $st->execute([$tipo, $area]);
    $lista = $st->fetchAll() ?: [];
}

if ($edit !== null) {
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
// ========== FORM ==========
elseif ($edit !== null):
    $tipoAtual = (string)($edit['tipo'] ?? $tipo);
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
            <div class="field">
                <label>Ordem</label>
                <input type="number" name="ordem" value="<?= intval($edit['ordem'] ?? 0) ?>">
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label>Título *</label>
                <input name="titulo" required value="<?= e($edit['titulo'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Slug (URL)</label>
                <input name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="gerado automaticamente">
            </div>
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
        <?php if ($isDemo): ?>
        <div class="field"><label>Mensagem WhatsApp (opcional)</label><input name="whatsapp_msg" value="<?= e($edit['whatsapp_msg'] ?? '') ?>"></div>
        <?php else: ?>
        <input type="hidden" name="whatsapp_msg" value="">
        <?php endif; ?>
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

        <?php if ($isDemo): ?>
            <?php admin_bloco_demonstrativos('conteudo', intval($edit['id'] ?? 0)); ?>
            <p class="muted" style="margin-top:8px;">Áudios de demonstração — aparecem no site público.</p>
        <?php else: ?>
            <?php if (!empty($edit['id'])): ?>
                <?php admin_bloco_entregas(intval($edit['id'])); ?>
            <?php else: ?>
                <div class="field" style="margin-top:18px;padding-top:14px;border-top:1px solid var(--line);">
                    <p class="muted">Salve o conteúdo primeiro para enviar <strong>arquivos de entrega</strong> (só clientes com liberação).</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="actions" style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">Salvar</button>
            <a class="btn btn-secondary" href="<?= e($script) ?>?tipo=<?= e($tipoAtual) ?>">Cancelar</a>
        </div>
    </form>
</div>
<?php
// ========== LISTA ==========
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
                <th>Capa</th>
                <th>Título</th>
                <th><?= $tipo === 'programete' ? 'Inserções' : 'Duração' ?></th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lista as $p): ?>
            <tr>
                <td><?php if (!empty($p['capa'])): ?><img class="thumb" src="../<?= e($p['capa']) ?>" alt=""><?php else: ?>—<?php endif; ?></td>
                <td><strong><?= e($p['titulo']) ?></strong><div class="muted"><?= e($p['slug']) ?></div></td>
                <td><?= e($tipo === 'programete' ? ($p['insercoes'] ?: '—') : ($p['duracao'] ?: '—')) ?></td>
                <td><?= !empty($p['ativo']) ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-off">Inativo</span>' ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="<?= e($script) ?>?tipo=<?= e($tipo) ?>&id=<?= intval($p['id']) ?>">Editar</a>
                    <a class="btn btn-danger btn-small" href="<?= e($script) ?>?tipo=<?= e($tipo) ?>&del=<?= intval($p['id']) ?>" onclick="return confirm('Excluir?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?>
            <tr><td colspan="5" class="muted">Nenhum item ainda. Clique em <strong>+ Novo</strong>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
endif;
admin_footer();
