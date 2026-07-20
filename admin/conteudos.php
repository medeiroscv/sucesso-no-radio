<?php
require_once __DIR__ . '/_layout.php';

$pdo = app_pdo();
$tipos = app_conteudo_tipos();
$ok = $err = '';
$edit = null;

$tipo = trim((string)($_GET['tipo'] ?? $_POST['tipo'] ?? ''));
if ($tipo !== '' && !app_conteudo_tipo_valido($tipo)) {
    $tipo = '';
}

// ---- Excluir ----
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $tipoDel = trim((string)($_GET['tipo'] ?? ''));
    if (!app_conteudo_tipo_valido($tipoDel)) {
        $tipoDel = 'diario';
    }
    $st = $pdo->prepare('SELECT capa FROM conteudos WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if ($row) {
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
    header('Location: conteudos.php?tipo=' . rawurlencode($tipoDel) . '&ok=1');
    exit;
}

// ---- Salvar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $tipoPost = trim((string)($_POST['tipo'] ?? 'diario'));
    if (!app_conteudo_tipo_valido($tipoPost)) {
        $tipoPost = 'diario';
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
    $capaNova = admin_upload('capa', 'conteudos');
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
        $edit = [
            'id' => $id,
            'tipo' => $tipoPost,
            'titulo' => $titulo,
            'slug' => $slug,
            'resumo' => $resumo,
            'descricao' => $descricao,
            'capa' => $capa,
            'duracao' => $duracao,
            'blocos' => $blocos,
            'dias' => $dias,
            'insercoes' => $insercoes,
            'destaque' => $destaque,
            'ativo' => $ativo,
            'ordem' => $ordem,
            'whatsapp_msg' => $whatsapp_msg,
        ];
    } else {
        // slug único
        $slugCheck = $pdo->prepare('SELECT id FROM conteudos WHERE slug = ? AND id <> ? LIMIT 1');
        $slugCheck->execute([$slug, $id]);
        if ($slugCheck->fetch()) {
            $slug .= '-' . ($id > 0 ? $id : bin2hex(random_bytes(2)));
        }
        try {
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE conteudos SET tipo=?, titulo=?, slug=?, resumo=?, descricao=?, capa=?, duracao=?, blocos=?, dias=?, insercoes=?, destaque=?, ativo=?, ordem=?, whatsapp_msg=?, updated_at=NOW() WHERE id=?'
                )->execute([
                    $tipoPost, $titulo, $slug, $resumo, $descricao, $capa, $duracao, $blocos, $dias,
                    $insercoes, $destaque, $ativo, $ordem, $whatsapp_msg, $id,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO conteudos (tipo,titulo,slug,resumo,descricao,capa,duracao,blocos,dias,insercoes,destaque,ativo,ordem,whatsapp_msg,created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
                )->execute([
                    $tipoPost, $titulo, $slug, $resumo, $descricao, $capa, $duracao, $blocos, $dias,
                    $insercoes, $destaque, $ativo, $ordem, $whatsapp_msg,
                ]);
                $id = intval($pdo->lastInsertId());
            }
            admin_salvar_demonstrativos('conteudo', $id);
            admin_salvar_entregas($id);
            header('Location: conteudos.php?tipo=' . rawurlencode($tipoPost) . '&id=' . $id . '&ok=1');
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
        $st = $pdo->prepare('SELECT * FROM conteudos WHERE id = ?');
        $st->execute([intval($_GET['id'])]);
        $edit = $st->fetch() ?: null;
        if ($edit) {
            $tipo = (string)$edit['tipo'];
        }
    } else {
        $tipoNovo = $tipo !== '' ? $tipo : 'diario';
        if (!app_conteudo_tipo_valido($tipoNovo)) {
            $tipoNovo = 'diario';
        }
        $meta = $tipos[$tipoNovo];
        $edit = [
            'id' => 0,
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

// Contagens para o hub
$counts = [];
foreach (array_keys($tipos) as $t) {
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM conteudos WHERE tipo = ?');
        $st->execute([$t]);
        $counts[$t] = (int)$st->fetchColumn();
    } catch (Throwable $e) {
        $counts[$t] = 0;
    }
}

$lista = [];
if ($tipo !== '' && $edit === null) {
    $st = $pdo->prepare('SELECT * FROM conteudos WHERE tipo = ? ORDER BY ordem ASC, id DESC');
    $st->execute([$tipo]);
    $lista = $st->fetchAll() ?: [];
}

// Títulos
if ($edit !== null) {
    $tipoLabel = $tipos[$edit['tipo'] ?? $tipo]['label'] ?? 'Conteúdo';
    $pageTitle = !empty($edit['id']) ? 'Editar · ' . $tipoLabel : 'Novo · ' . $tipoLabel;
} elseif ($tipo !== '') {
    $pageTitle = $tipos[$tipo]['label'] ?? 'Conteúdos';
} else {
    $pageTitle = 'Conteúdos';
}

admin_header($pageTitle, 'conteudos');
admin_flash($ok, $err);

// ========== HUB: quatro opções lado a lado ==========
if ($tipo === '' && $edit === null):
?>
<div class="card">
    <p class="muted" style="margin-bottom:16px;">Escolha o tipo de conteúdo para listar, editar ou cadastrar.</p>
    <div class="conteudo-hub">
        <?php foreach ($tipos as $key => $meta): ?>
            <a class="conteudo-hub-card" href="conteudos.php?tipo=<?= e($key) ?>">
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
    <a class="btn btn-secondary btn-small" href="conteudos.php">← Tipos</a>
    <a class="btn btn-secondary btn-small" href="conteudos.php?tipo=<?= e($tipoAtual) ?>">← Lista de <?= e($tipos[$tipoAtual]['label'] ?? '') ?></a>
</div>
<div class="card">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= intval($edit['id']) ?>">
        <input type="hidden" name="capa_atual" value="<?= e($edit['capa'] ?? '') ?>">

        <div class="field-row">
            <div class="field">
                <label>Tipo de conteúdo *</label>
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
            <div class="field">
                <label>Duração</label>
                <input name="duracao" value="<?= e($edit['duracao'] ?? '') ?>" placeholder="ex: 3 horas">
            </div>
            <div class="field">
                <label>Blocos</label>
                <input name="blocos" value="<?= e($edit['blocos'] ?? '') ?>" placeholder="ex: 9 Blocos">
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Dias</label>
                <input name="dias" value="<?= e($edit['dias'] ?? '') ?>" placeholder="SEG A SAB">
            </div>
            <div class="field">
                <label>Inserções (opcional)</label>
                <input name="insercoes" value="<?= e($edit['insercoes'] ?? '') ?>" placeholder="ex: 1x/hora">
            </div>
        </div>
        <?php else: ?>
        <div class="field-row">
            <div class="field">
                <label>Inserções</label>
                <input name="insercoes" value="<?= e($edit['insercoes'] ?? '1x/dia') ?>" placeholder="1x/dia">
            </div>
            <div class="field">
                <label>Duração (opcional)</label>
                <input name="duracao" value="<?= e($edit['duracao'] ?? '') ?>" placeholder="ex: 60 segundos">
            </div>
        </div>
        <input type="hidden" name="blocos" value="<?= e($edit['blocos'] ?? '') ?>">
        <input type="hidden" name="dias" value="<?= e($edit['dias'] ?? '') ?>">
        <?php endif; ?>

        <div class="field">
            <label>Resumo (card do site)</label>
            <textarea name="resumo" rows="2"><?= e($edit['resumo'] ?? '') ?></textarea>
        </div>
        <div class="field">
            <label>Descrição completa</label>
            <textarea name="descricao" rows="5"><?= e($edit['descricao'] ?? '') ?></textarea>
        </div>
        <div class="field">
            <label>Mensagem WhatsApp (opcional)</label>
            <input name="whatsapp_msg" value="<?= e($edit['whatsapp_msg'] ?? '') ?>" placeholder="Olá! Quero este conteúdo...">
        </div>
        <div class="field">
            <label>Capa (imagem)</label>
            <p class="muted" style="margin:4px 0 8px;">Convertida para JPEG e redimensionada (máx. 540×675) para ficar leve.</p>
            <?php if (!empty($edit['capa'])): ?>
                <p class="muted">Atual: <img class="thumb" src="../<?= e($edit['capa']) ?>" alt=""></p>
            <?php endif; ?>
            <input type="file" name="capa" accept="image/*">
        </div>
        <div class="field-row">
            <div class="field">
                <label><input type="checkbox" name="ativo" value="1" <?= !empty($edit['ativo']) ? 'checked' : '' ?>> Ativo no site</label>
            </div>
            <div class="field">
                <label><input type="checkbox" name="destaque" value="1" <?= !empty($edit['destaque']) ? 'checked' : '' ?>> Destaque</label>
            </div>
        </div>

        <?php admin_bloco_demonstrativos('conteudo', intval($edit['id'] ?? 0)); ?>
        <?php if (!empty($edit['id'])): ?>
            <?php admin_bloco_entregas(intval($edit['id'])); ?>
        <?php else: ?>
            <div class="field" style="margin-top:18px;padding-top:14px;border-top:1px solid var(--line);">
                <p class="muted">Salve o conteúdo primeiro para poder enviar <strong>arquivos de entrega</strong> (área do cliente).</p>
            </div>
        <?php endif; ?>

        <div class="actions" style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">Salvar</button>
            <a class="btn btn-secondary" href="conteudos.php?tipo=<?= e($tipoAtual) ?>">Cancelar</a>
        </div>
    </form>
</div>
<?php
// ========== LISTA POR TIPO ==========
else:
    $meta = $tipos[$tipo];
?>
<div class="actions" style="margin-bottom:12px;align-items:center;">
    <a class="btn btn-secondary btn-small" href="conteudos.php">← Todos os tipos</a>
    <?php foreach ($tipos as $key => $m): ?>
        <a class="btn btn-small <?= $key === $tipo ? 'btn-primary' : 'btn-secondary' ?>" href="conteudos.php?tipo=<?= e($key) ?>">
            <?= $m['icon'] ?> <?= e($m['label']) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="actions" style="margin-bottom:14px;justify-content:space-between;width:100%;">
        <div>
            <strong style="font-size:1.05rem;"><?= $meta['icon'] ?> <?= e($meta['label']) ?></strong>
            <div class="muted"><?= e($meta['desc']) ?></div>
        </div>
        <a class="btn btn-primary" href="conteudos.php?tipo=<?= e($tipo) ?>&novo=1">+ Novo</a>
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
                <td>
                    <?php if (!empty($p['capa'])): ?>
                        <img class="thumb" src="../<?= e($p['capa']) ?>" alt="">
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <strong><?= e($p['titulo']) ?></strong>
                    <div class="muted"><?= e($p['slug']) ?></div>
                </td>
                <td><?= e($tipo === 'programete' ? ($p['insercoes'] ?: '—') : ($p['duracao'] ?: '—')) ?></td>
                <td>
                    <?= !empty($p['ativo'])
                        ? '<span class="badge badge-ok">Ativo</span>'
                        : '<span class="badge badge-off">Inativo</span>' ?>
                </td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="conteudos.php?tipo=<?= e($tipo) ?>&id=<?= intval($p['id']) ?>">Editar</a>
                    <a class="btn btn-danger btn-small" href="conteudos.php?tipo=<?= e($tipo) ?>&del=<?= intval($p['id']) ?>" onclick="return confirm('Excluir este conteúdo?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?>
            <tr><td colspan="5" class="muted">Nenhum item ainda. Clique em <strong>+ Novo</strong> para cadastrar.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
endif;

admin_footer();
