<?php
require_once __DIR__ . '/_layout.php';
$pdo = app_pdo();
$ok = $err = '';
$edit = null;

if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    foreach (app_demonstrativos('programa', $id) as $d) {
        app_delete_demonstrativo(intval($d['id']));
    }
    $pdo->prepare('DELETE FROM programas WHERE id = ?')->execute([$id]);
    header('Location: programas.php?ok=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    if ($slug === '') $slug = app_slug($titulo);
    $categoria_id = intval($_POST['categoria_id'] ?? 0) ?: null;
    $resumo = trim((string)($_POST['resumo'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $duracao = trim((string)($_POST['duracao'] ?? ''));
    $blocos = trim((string)($_POST['blocos'] ?? ''));
    $dias = trim((string)($_POST['dias'] ?? 'SEG A SAB'));
    $periodo = trim((string)($_POST['periodo'] ?? 'diario'));
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
    } else {
        try {
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE programas SET categoria_id=?, titulo=?, slug=?, resumo=?, descricao=?, capa=?, duracao=?, blocos=?, dias=?, periodo=?, destaque=?, ativo=?, ordem=?, whatsapp_msg=?, updated_at=NOW() WHERE id=?'
                )->execute([$categoria_id, $titulo, $slug, $resumo, $descricao, $capa, $duracao, $blocos, $dias, $periodo, $destaque, $ativo, $ordem, $whatsapp_msg, $id]);
            } else {
                $pdo->prepare(
                    'INSERT INTO programas (categoria_id,titulo,slug,resumo,descricao,capa,duracao,blocos,dias,periodo,destaque,ativo,ordem,whatsapp_msg,created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
                )->execute([$categoria_id, $titulo, $slug, $resumo, $descricao, $capa, $duracao, $blocos, $dias, $periodo, $destaque, $ativo, $ordem, $whatsapp_msg]);
                $id = intval($pdo->lastInsertId());
            }
            admin_salvar_demonstrativos('programa', $id);
            header('Location: programas.php?id=' . $id . '&ok=1');
            exit;
        } catch (Throwable $e) {
            $err = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['id']) || isset($_GET['novo'])) {
    if (!empty($_GET['id'])) {
        $st = $pdo->prepare('SELECT * FROM programas WHERE id = ?');
        $st->execute([intval($_GET['id'])]);
        $edit = $st->fetch() ?: null;
    } else {
        $edit = [
            'id' => 0, 'titulo' => '', 'slug' => '', 'resumo' => '', 'descricao' => '',
            'capa' => '', 'duracao' => '', 'blocos' => '', 'dias' => 'SEG A SAB',
            'periodo' => 'diario', 'destaque' => 0, 'ativo' => 1, 'ordem' => 0,
            'whatsapp_msg' => '', 'categoria_id' => null,
        ];
    }
}

$lista = $pdo->query(
    'SELECT p.*, c.nome AS categoria_nome FROM programas p
     LEFT JOIN categorias c ON c.id = p.categoria_id
     ORDER BY p.ordem ASC, p.id DESC'
)->fetchAll();
$categorias = $pdo->query('SELECT * FROM categorias WHERE ativo = 1 ORDER BY ordem, nome')->fetchAll();
if (isset($_GET['ok'])) $ok = 'Salvo com sucesso.';

admin_header($edit !== null ? ($edit['id'] ? 'Editar programa' : 'Novo programa') : 'Programas', 'programas');
admin_flash($ok, $err);

if ($edit !== null):
?>
<div class="card">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= intval($edit['id']) ?>">
        <input type="hidden" name="capa_atual" value="<?= htmlspecialchars($edit['capa'] ?? '') ?>">
        <div class="field-row">
            <div class="field"><label>Título *</label><input name="titulo" required value="<?= htmlspecialchars($edit['titulo']) ?>"></div>
            <div class="field"><label>Slug (URL)</label><input name="slug" value="<?= htmlspecialchars($edit['slug']) ?>" placeholder="gerado automaticamente"></div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Categoria</label>
                <select name="categoria_id">
                    <option value="">—</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= intval($edit['categoria_id'] ?? 0) === intval($c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Período</label>
                <select name="periodo">
                    <?php foreach (['diario' => 'Diário', 'fim_semana' => 'Fim de semana', 'jornalismo' => 'Jornalismo', 'campanha' => 'Campanha'] as $k => $lab): ?>
                        <option value="<?= $k ?>" <?= ($edit['periodo'] ?? '') === $k ? 'selected' : '' ?>><?= $lab ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field-row">
            <div class="field"><label>Duração</label><input name="duracao" value="<?= htmlspecialchars($edit['duracao']) ?>" placeholder="ex: 3 horas"></div>
            <div class="field"><label>Blocos</label><input name="blocos" value="<?= htmlspecialchars($edit['blocos']) ?>" placeholder="ex: 9 Blocos"></div>
        </div>
        <div class="field-row">
            <div class="field"><label>Dias</label><input name="dias" value="<?= htmlspecialchars($edit['dias']) ?>" placeholder="SEG A SAB"></div>
            <div class="field"><label>Ordem</label><input type="number" name="ordem" value="<?= intval($edit['ordem']) ?>"></div>
        </div>
        <div class="field"><label>Resumo (card)</label><textarea name="resumo" rows="2"><?= htmlspecialchars($edit['resumo']) ?></textarea></div>
        <div class="field"><label>Descrição completa</label><textarea name="descricao" rows="5"><?= htmlspecialchars($edit['descricao']) ?></textarea></div>
        <div class="field"><label>Mensagem WhatsApp (opcional)</label><input name="whatsapp_msg" value="<?= htmlspecialchars($edit['whatsapp_msg']) ?>" placeholder="Olá! Quero o programa..."></div>
        <div class="field">
            <label>Capa (imagem)</label>
            <p class="muted" style="margin:4px 0 8px;">Convertida para JPEG e redimensionada (máx. 540×675) para ficar leve.</p>
            <?php if (!empty($edit['capa'])): ?><p class="muted">Atual: <img class="thumb" src="../<?= htmlspecialchars($edit['capa']) ?>" alt=""></p><?php endif; ?>
            <input type="file" name="capa" accept="image/*">
        </div>
        <div class="field-row">
            <div class="field"><label><input type="checkbox" name="ativo" value="1" <?= !empty($edit['ativo']) ? 'checked' : '' ?>> Ativo no site</label></div>
            <div class="field"><label><input type="checkbox" name="destaque" value="1" <?= !empty($edit['destaque']) ? 'checked' : '' ?>> Destaque</label></div>
        </div>
        <?php admin_bloco_demonstrativos('programa', intval($edit['id'] ?? 0)); ?>
        <div class="actions" style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">Salvar</button>
            <a class="btn btn-secondary" href="programas.php">Cancelar</a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="card">
    <div class="actions" style="margin-bottom:14px;">
        <a class="btn btn-primary" href="programas.php?novo=1">+ Novo programa</a>
    </div>
    <table>
        <thead><tr><th>Capa</th><th>Título</th><th>Categoria</th><th>Duração</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $p): ?>
            <tr>
                <td><?php if ($p['capa']): ?><img class="thumb" src="../<?= htmlspecialchars($p['capa']) ?>" alt=""><?php else: ?>—<?php endif; ?></td>
                <td><strong><?= htmlspecialchars($p['titulo']) ?></strong><div class="muted"><?= htmlspecialchars($p['slug']) ?></div></td>
                <td><?= htmlspecialchars($p['categoria_nome'] ?: $p['periodo']) ?></td>
                <td><?= htmlspecialchars($p['duracao']) ?></td>
                <td><?= $p['ativo'] ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge badge-off">Inativo</span>' ?></td>
                <td class="actions">
                    <a class="btn btn-secondary btn-small" href="programas.php?id=<?= $p['id'] ?>">Editar</a>
                    <a class="btn btn-danger btn-small" href="programas.php?del=<?= $p['id'] ?>" onclick="return confirm('Excluir este programa?')">Excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?><tr><td colspan="6" class="muted">Nenhum programa ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif;
admin_footer();
