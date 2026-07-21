<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
app_require_auth();

function admin_header(string $title, string $active = ''): void {
    $nome = $_SESSION['admin_nome'] ?? 'Admin';
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> · Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar">
        <div class="logo">🎙 Sucesso no Rádio</div>
        <nav>
            <a href="index.php" class="<?= $active === 'dash' ? 'active' : '' ?>">Dashboard</a>
            <a href="demonstrativos.php" class="<?= $active === 'demonstrativos' ? 'active' : '' ?>">Demonstrativos</a>
            <a href="conteudos.php" class="<?= $active === 'conteudos' ? 'active' : '' ?>">Conteúdos</a>
            <a href="clientes.php" class="<?= $active === 'clientes' ? 'active' : '' ?>">Clientes</a>
            <a href="produtos.php" class="<?= $active === 'produtos' ? 'active' : '' ?>">Produtos / preços</a>
            <a href="assinaturas.php" class="<?= $active === 'assinaturas' ? 'active' : '' ?>">Assinaturas</a>
            <a href="financeiro.php" class="<?= $active === 'financeiro' ? 'active' : '' ?>">Financeiro</a>
            <a href="textos.php" class="<?= $active === 'textos' ? 'active' : '' ?>">Textos a gravar</a>
            <a href="contatos.php" class="<?= $active === 'contatos' ? 'active' : '' ?>">Contatos</a>
            <a href="banners.php" class="<?= $active === 'banners' ? 'active' : '' ?>">Banners</a>
            <a href="configuracoes.php" class="<?= $active === 'config' ? 'active' : '' ?>">Configurações</a>
            <a href="../" target="_blank">Ver site</a>
            <a href="logout.php">Sair</a>
        </nav>
    </aside>
    <div class="main">
        <div class="topbar">
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="muted">Olá, <?= htmlspecialchars($nome) ?></div>
        </div>
<?php
}

function admin_footer(): void {
    echo '</div></div></body></html>';
}

function admin_flash(?string $ok = null, ?string $err = null): void {
    if ($ok) echo '<div class="alert alert-ok">' . htmlspecialchars($ok) . '</div>';
    if ($err) echo '<div class="alert alert-err">' . htmlspecialchars($err) . '</div>';
}

/**
 * Upload de imagem: converte para JPEG, corrige orientação EXIF,
 * redimensiona (máx. 540×675 = metade de 1080×1350, mantendo proporção) e compacta.
 * Retorna caminho relativo (ex: uploads/programas/xxx.jpg) ou '' se falhar.
 */
function admin_upload(
    string $field,
    string $subdir,
    array $extensoes = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'jfif', 'bmp'],
    int $maxW = 540,
    int $maxH = 675,
    int $quality = 82
): string {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return '';
    }
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }

    $tmp = (string)$_FILES[$field]['tmp_name'];
    $origName = (string)($_FILES[$field]['name'] ?? '');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    // Aceita por extensão ou por MIME real (celulares às vezes mandam sem extensão útil)
    $info = @getimagesize($tmp);
    $mimeOk = $info && in_array($info['mime'] ?? '', [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
    ], true);
    if (!$mimeOk && !in_array($ext, $extensoes, true)) {
        return '';
    }

    $dir = dirname(__DIR__) . '/uploads/' . trim($subdir, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $dest = $dir . '/' . $name;

    if (!admin_image_to_jpeg($tmp, $dest, $maxW, $maxH, $quality)) {
        return '';
    }

    return 'uploads/' . trim($subdir, '/') . '/' . $name;
}

/** Remove arquivo local sob uploads/ (não apaga URLs externas). */
function admin_delete_local_upload(string $relPath): void {
    $relPath = str_replace('\\', '/', trim($relPath));
    if ($relPath === '' || str_contains($relPath, '..')) return;
    if (!str_starts_with($relPath, 'uploads/')) return;
    $full = dirname(__DIR__) . '/' . $relPath;
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Processa imagem: EXIF, resize proporcional dentro do box, JPEG progressivo.
 */
function admin_image_to_jpeg(string $srcPath, string $destPath, int $maxW = 540, int $maxH = 675, int $quality = 82): bool {
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $info = @getimagesize($srcPath);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return false;
    }

    $type = (int)$info[2];
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($srcPath),
        IMAGETYPE_PNG  => @imagecreatefrompng($srcPath),
        IMAGETYPE_GIF  => @imagecreatefromgif($srcPath),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false,
        IMAGETYPE_BMP  => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($srcPath) : false,
        default        => false,
    };
    if (!$src) {
        return false;
    }

    // Corrigir rotação de fotos de celular (JPEG)
    if ($type === IMAGETYPE_JPEG) {
        $src = admin_image_apply_exif_orientation($src, $srcPath);
    }

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w < 1 || $h < 1) {
        imagedestroy($src);
        return false;
    }

    $maxW = max(1, $maxW);
    $maxH = max(1, $maxH);
    $scale = 1.0;
    if ($w > $maxW || $h > $maxH) {
        $scale = min($maxW / $w, $maxH / $h);
    }
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }

    // Fundo branco (PNG/WebP com transparência → JPEG)
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    imagealphablending($src, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    // JPEG progressivo = carrega mais suave e costuma ser um pouco menor
    if (function_exists('imageinterlace')) {
        imageinterlace($dst, true);
    }

    $quality = max(60, min(92, $quality));
    $ok = @imagejpeg($dst, $destPath, $quality);
    imagedestroy($dst);

    if (!$ok || !is_file($destPath)) {
        return false;
    }

    // Se ainda ficou pesado (>180KB no tamanho reduzido), recompacta um pouco mais
    $size = filesize($destPath);
    if ($size !== false && $size > 180 * 1024 && $quality > 72) {
        $re = @imagecreatefromjpeg($destPath);
        if ($re) {
            if (function_exists('imageinterlace')) {
                imageinterlace($re, true);
            }
            @imagejpeg($re, $destPath, 72);
            imagedestroy($re);
        }
    }

    return is_file($destPath);
}

/** Aplica orientação EXIF (fotos de iPhone/Android). */
function admin_image_apply_exif_orientation($img, string $path) {
    if (!function_exists('exif_read_data')) {
        return $img;
    }
    $exif = @exif_read_data($path);
    if (!$exif || empty($exif['Orientation'])) {
        return $img;
    }
    $orientation = (int)$exif['Orientation'];
    switch ($orientation) {
        case 2:
            imageflip($img, IMG_FLIP_HORIZONTAL);
            break;
        case 3:
            $img = admin_image_rotate($img, 180);
            break;
        case 4:
            imageflip($img, IMG_FLIP_VERTICAL);
            break;
        case 5:
            imageflip($img, IMG_FLIP_HORIZONTAL);
            $img = admin_image_rotate($img, 270);
            break;
        case 6:
            $img = admin_image_rotate($img, 270); // 90° CW
            break;
        case 7:
            imageflip($img, IMG_FLIP_HORIZONTAL);
            $img = admin_image_rotate($img, 90);
            break;
        case 8:
            $img = admin_image_rotate($img, 90); // 90° CCW
            break;
    }
    return $img;
}

function admin_image_rotate($img, int $angle) {
    // imagerotate usa ângulo anti-horário; fundo branco
    $bg = imagecolorallocate($img, 255, 255, 255);
    $rotated = imagerotate($img, $angle, $bg);
    if ($rotated === false) {
        return $img;
    }
    imagedestroy($img);
    return $rotated;
}

/**
 * Upload de logo/favicon em PNG (leve, com transparência quando possível).
 * $mode: 'logo' (máx. 480×160) | 'favicon' (64×64 quadrado).
 */
function admin_upload_asset(string $field, string $subdir, string $mode = 'logo'): string {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return '';
    }
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }
    $tmp = (string)$_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info) {
        return '';
    }

    $type = (int)$info[2];
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
        IMAGETYPE_GIF  => @imagecreatefromgif($tmp),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        default        => false,
    };
    if (!$src) {
        return '';
    }
    if ($type === IMAGETYPE_JPEG) {
        $src = admin_image_apply_exif_orientation($src, $tmp);
    }

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w < 1 || $h < 1) {
        imagedestroy($src);
        return '';
    }

    if ($mode === 'favicon') {
        $size = 64;
        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
        // cover crop central
        $scale = max($size / $w, $size / $h);
        $nw = (int)round($w * $scale);
        $nh = (int)round($h * $scale);
        $ox = (int)round(($size - $nw) / 2);
        $oy = (int)round(($size - $nh) / 2);
        imagecopyresampled($dst, $src, $ox, $oy, 0, 0, $nw, $nh, $w, $h);
    } else {
        $maxW = 480;
        $maxH = 160;
        $scale = 1.0;
        if ($w > $maxW || $h > $maxH) {
            $scale = min($maxW / $w, $maxH / $h);
        }
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagedestroy($src);

    $dir = dirname(__DIR__) . '/uploads/' . trim($subdir, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $name = $mode . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.png';
    $dest = $dir . '/' . $name;
    $ok = @imagepng($dst, $dest, 6);
    imagedestroy($dst);
    if (!$ok || !is_file($dest)) {
        return '';
    }
    return 'uploads/' . trim($subdir, '/') . '/' . $name;
}

/**
 * Upload de vários áudios (campo file[] + títulos em POST).
 * $fileField: nome do input file (ex: demos, entregas)
 * $tituloField: nome do array de títulos (ex: demo_titulos, entrega_titulos)
 */
function admin_upload_audios_multi(string $fileField, string $tituloField, string $subdir, string $tituloPadrao = 'Áudio'): array {
    $extOk = ['mp3', 'mpeg', 'mpga', 'm4a', 'ogg', 'wav', 'aac'];
    $out = [];
    if (empty($_FILES[$fileField]) || !is_array($_FILES[$fileField]['name'] ?? null)) {
        return $out;
    }
    $names = $_FILES[$fileField]['name'];
    $tmps = $_FILES[$fileField]['tmp_name'];
    $errs = $_FILES[$fileField]['error'];
    $dir = dirname(__DIR__) . '/uploads/' . trim($subdir, '/');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $titulos = $_POST[$tituloField] ?? [];
    if (!is_array($titulos)) $titulos = [];

    foreach ($names as $i => $origName) {
        if (empty($tmps[$i]) || !is_uploaded_file($tmps[$i])) continue;
        if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo((string)$origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $extOk, true)) continue;
        $fname = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $fname;
        if (!@move_uploaded_file($tmps[$i], $dest)) continue;
        $titulo = trim((string)($titulos[$i] ?? ''));
        if ($titulo === '') {
            $titulo = pathinfo((string)$origName, PATHINFO_FILENAME) ?: ($tituloPadrao . ' ' . ($i + 1));
        }
        $out[] = [
            'titulo' => $titulo,
            'arquivo' => 'uploads/' . trim($subdir, '/') . '/' . $fname,
        ];
    }
    return $out;
}

/** Upload de vários áudios do campo demos[] (ou demos com índices). */
function admin_upload_demos_multi(string $subdir = 'demos'): array {
    return admin_upload_audios_multi('demos', 'demo_titulos', $subdir, 'Demonstrativo');
}

/** Upload de um único áudio (MP3 etc.). Retorna caminho relativo ou ''. */
function admin_upload_audio_single(string $field, string $subdir = 'textos_audio'): string {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return '';
    }
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }
    $extOk = ['mp3', 'mpeg', 'mpga', 'm4a', 'ogg', 'wav', 'aac'];
    $orig = (string)($_FILES[$field]['name'] ?? '');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $extOk, true)) {
        return '';
    }
    $dir = dirname(__DIR__) . '/uploads/' . trim($subdir, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $fname = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $fname;
    if (!@move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        return '';
    }
    return 'uploads/' . trim($subdir, '/') . '/' . $fname;
}

/** Grava novos demos e remove os marcados para exclusão. */
function admin_salvar_demonstrativos(string $tipo, int $conteudoId): void {
    if ($conteudoId <= 0) return;
    $pdo = app_pdo();

    // Remover demos marcados
    $del = $_POST['demo_del'] ?? [];
    if (is_array($del)) {
        foreach ($del as $did) {
            app_delete_demonstrativo(intval($did));
        }
    }

    // Atualizar títulos dos existentes
    $titExist = $_POST['demo_titulo_existente'] ?? [];
    if (is_array($titExist)) {
        $st = $pdo->prepare('UPDATE demonstrativos SET titulo = ? WHERE id = ? AND tipo_conteudo = ? AND conteudo_id = ?');
        foreach ($titExist as $did => $tit) {
            $st->execute([trim((string)$tit), intval($did), $tipo, $conteudoId]);
        }
    }

    // Novos uploads
    $novos = admin_upload_demos_multi('demos');
    if (!$novos) return;
    $ordSt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) FROM demonstrativos WHERE tipo_conteudo = ? AND conteudo_id = ?');
    $ordSt->execute([$tipo, $conteudoId]);
    $ordem = intval($ordSt->fetchColumn());
    $ins = $pdo->prepare(
        'INSERT INTO demonstrativos (tipo_conteudo, conteudo_id, titulo, arquivo, ordem, created_at)
         VALUES (?,?,?,?,?,NOW())'
    );
    foreach ($novos as $n) {
        $ordem++;
        $ins->execute([$tipo, $conteudoId, $n['titulo'], $n['arquivo'], $ordem]);
    }
}

/** Bloco HTML + JS dos demonstrativos no formulário. */
function admin_bloco_demonstrativos(string $tipo, int $conteudoId): void {
    $demos = app_demonstrativos($tipo, $conteudoId);
    ?>
    <div class="field" style="margin-top:18px;padding-top:14px;border-top:1px solid var(--line);">
        <label style="font-size:1rem;color:var(--text);">Demonstrativos (áudio MP3)</label>
        <p class="muted" style="margin:6px 0 12px;">Envie um ou mais áudios de demonstração. Use o botão <strong>+</strong> para adicionar outro arquivo.</p>

        <?php if ($demos): ?>
            <div style="display:grid;gap:10px;margin-bottom:14px;">
                <?php foreach ($demos as $d): ?>
                    <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;background:#0f172a;border:1px solid var(--line);border-radius:10px;padding:10px 12px;">
                        <div>
                            <input name="demo_titulo_existente[<?= intval($d['id']) ?>]" value="<?= htmlspecialchars($d['titulo'] ?? '') ?>" placeholder="Título do áudio" style="width:100%;margin-bottom:8px;border:1px solid var(--line);background:#111827;color:var(--text);border-radius:8px;padding:8px 10px;">
                            <audio controls preload="none" style="width:100%;max-width:420px;">
                                <source src="../<?= htmlspecialchars($d['arquivo']) ?>" type="audio/mpeg">
                            </audio>
                        </div>
                        <label class="muted" style="font-size:.82rem;white-space:nowrap;">
                            <input type="checkbox" name="demo_del[]" value="<?= intval($d['id']) ?>"> Excluir
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div id="demoSlots" style="display:grid;gap:10px;"></div>
        <div class="actions" style="margin-top:10px;">
            <button type="button" class="btn btn-secondary btn-small" id="btnAddDemo" onclick="addDemoSlot()">+ Adicionar áudio</button>
        </div>
    </div>
    <script>
    (function () {
        var idx = 0;
        window.addDemoSlot = function () {
            var box = document.getElementById('demoSlots');
            if (!box) return;
            var i = idx++;
            var row = document.createElement('div');
            row.className = 'demo-slot';
            row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:end;background:#0f172a;border:1px solid #334155;border-radius:10px;padding:10px;';
            row.innerHTML =
                '<div class="field" style="margin:0"><label>Título do áudio</label>' +
                '<input name="demo_titulos[' + i + ']" placeholder="Ex.: Bloco 1, Vinheta, Amostra"></div>' +
                '<div class="field" style="margin:0"><label>Arquivo MP3</label>' +
                '<input type="file" name="demos[' + i + ']" accept="audio/mpeg,audio/mp3,audio/*,.mp3,.m4a,.wav,.ogg"></div>' +
                '<button type="button" class="btn btn-danger btn-small" onclick="this.closest(\'.demo-slot\').remove()">Remover</button>';
            box.appendChild(row);
        };
        // Um campo já aberto ao carregar o formulário
        if (document.getElementById('demoSlots') && !document.getElementById('demoSlots').children.length) {
            addDemoSlot();
        }
    })();
    </script>
    <?php
}

/** Grava entregas (área do cliente) e remove marcadas. */
function admin_salvar_entregas(int $conteudoId): void {
    if ($conteudoId <= 0) return;
    $pdo = app_pdo();

    $del = $_POST['entrega_del'] ?? [];
    if (is_array($del)) {
        foreach ($del as $eid) {
            app_delete_entrega(intval($eid));
        }
    }

    $titExist = $_POST['entrega_titulo_existente'] ?? [];
    $dataExist = $_POST['entrega_data_existente'] ?? [];
    if (is_array($titExist)) {
        $st = $pdo->prepare('UPDATE conteudo_entregas SET titulo = ?, data_ref = ? WHERE id = ? AND conteudo_id = ?');
        foreach ($titExist as $eid => $tit) {
            $data = trim((string)($dataExist[$eid] ?? ''));
            $dataRef = $data !== '' ? $data : null;
            $st->execute([trim((string)$tit), $dataRef, intval($eid), $conteudoId]);
        }
    }

    $novos = admin_upload_audios_multi('entregas', 'entrega_titulos', 'entregas', 'Entrega');
    if (!$novos) return;

    $datas = $_POST['entrega_datas'] ?? [];
    if (!is_array($datas)) $datas = [];

    $ordSt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) FROM conteudo_entregas WHERE conteudo_id = ?');
    $ordSt->execute([$conteudoId]);
    $ordem = intval($ordSt->fetchColumn());
    $ins = $pdo->prepare(
        'INSERT INTO conteudo_entregas (conteudo_id, titulo, arquivo, data_ref, ordem, ativo, created_at)
         VALUES (?,?,?,?,?,1,NOW())'
    );
    foreach ($novos as $i => $n) {
        $ordem++;
        $data = trim((string)($datas[$i] ?? ''));
        if ($data === '') $data = date('Y-m-d');
        $ins->execute([$conteudoId, $n['titulo'], $n['arquivo'], $data, $ordem]);
    }
}

/** Bloco HTML + JS das entregas (somente clientes). */
function admin_bloco_entregas(int $conteudoId): void {
    $itens = app_entregas($conteudoId, false);
    ?>
    <div class="field" style="margin-top:18px;padding-top:14px;border-top:1px solid var(--line);">
        <label style="font-size:1rem;color:var(--text);">Arquivos para clientes (entrega diária)</label>
        <p class="muted" style="margin:6px 0 12px;">
            Estes áudios <strong>não aparecem no site público</strong> — só na área logada do cliente.
            Atualize diariamente conforme a programação. Use o botão <strong>+</strong> para mais arquivos.
        </p>

        <?php if ($itens): ?>
            <div style="display:grid;gap:10px;margin-bottom:14px;">
                <?php foreach ($itens as $d): ?>
                    <div style="display:grid;grid-template-columns:1fr 140px auto;gap:10px;align-items:center;background:#0f172a;border:1px solid var(--line);border-radius:10px;padding:10px 12px;">
                        <div>
                            <input name="entrega_titulo_existente[<?= intval($d['id']) ?>]" value="<?= htmlspecialchars($d['titulo'] ?? '') ?>" placeholder="Título" style="width:100%;margin-bottom:8px;border:1px solid var(--line);background:#111827;color:var(--text);border-radius:8px;padding:8px 10px;">
                            <audio controls preload="none" style="width:100%;max-width:420px;">
                                <source src="../<?= htmlspecialchars($d['arquivo']) ?>" type="audio/mpeg">
                            </audio>
                        </div>
                        <div>
                            <label class="muted" style="font-size:.75rem;">Data ref.</label>
                            <input type="date" name="entrega_data_existente[<?= intval($d['id']) ?>]" value="<?= htmlspecialchars($d['data_ref'] ?? '') ?>" style="width:100%;border:1px solid var(--line);background:#111827;color:var(--text);border-radius:8px;padding:8px 10px;">
                        </div>
                        <label class="muted" style="font-size:.82rem;white-space:nowrap;">
                            <input type="checkbox" name="entrega_del[]" value="<?= intval($d['id']) ?>"> Excluir
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div id="entregaSlots" style="display:grid;gap:10px;"></div>
        <div class="actions" style="margin-top:10px;">
            <button type="button" class="btn btn-secondary btn-small" onclick="addEntregaSlot()">+ Adicionar entrega</button>
        </div>
    </div>
    <script>
    (function () {
        var idx = 0;
        window.addEntregaSlot = function () {
            var box = document.getElementById('entregaSlots');
            if (!box) return;
            var i = idx++;
            var row = document.createElement('div');
            row.className = 'entrega-slot';
            row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 140px auto;gap:8px;align-items:end;background:#0f172a;border:1px solid #334155;border-radius:10px;padding:10px;';
            var today = new Date().toISOString().slice(0, 10);
            row.innerHTML =
                '<div class="field" style="margin:0"><label>Título</label>' +
                '<input name="entrega_titulos[' + i + ']" placeholder="Ex.: Programa 20/07 — Bloco 1"></div>' +
                '<div class="field" style="margin:0"><label>Arquivo MP3</label>' +
                '<input type="file" name="entregas[' + i + ']" accept="audio/mpeg,audio/mp3,audio/*,.mp3,.m4a,.wav,.ogg"></div>' +
                '<div class="field" style="margin:0"><label>Data</label>' +
                '<input type="date" name="entrega_datas[' + i + ']" value="' + today + '"></div>' +
                '<button type="button" class="btn btn-danger btn-small" onclick="this.closest(\'.entrega-slot\').remove()">Remover</button>';
            box.appendChild(row);
        };
        if (document.getElementById('entregaSlots') && !document.getElementById('entregaSlots').children.length) {
            addEntregaSlot();
        }
    })();
    </script>
    <?php
}
