<?php
/**
 * Download do áudio de um texto — só o cliente dono (ou admin via path público no admin).
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

cliente_require_auth('texto.php');

$id = intval($_GET['id'] ?? 0);
$forceDl = !empty($_GET['dl']);
$cli = cliente_atual();
$row = app_texto_by_id($id);

if (
    !$row
    || !$cli
    || intval($row['cliente_id'] ?? 0) !== intval($cli['id'])
    || ($row['status'] ?? '') !== 'entregue'
    || empty($row['audio_arquivo'])
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Áudio indisponível.';
    exit;
}

$rel = str_replace('\\', '/', ltrim((string)$row['audio_arquivo'], '/'));
if ($rel === '' || str_contains($rel, '..') || !str_starts_with($rel, 'uploads/textos_audio/')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acesso negado.';
    exit;
}

$full = dirname(__DIR__) . '/' . $rel;
if (!is_file($full)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Arquivo ausente.';
    exit;
}

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION) ?: 'mp3');
$mime = 'audio/mpeg';
if ($ext === 'm4a') $mime = 'audio/mp4';
elseif ($ext === 'ogg') $mime = 'audio/ogg';
elseif ($ext === 'wav') $mime = 'audio/wav';
elseif ($ext === 'aac') $mime = 'audio/aac';

$nome = 'gravacao_' . $id . '.' . $ext;
$size = filesize($full);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$size);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if ($forceDl) {
    header('Content-Disposition: attachment; filename="' . $nome . '"');
} else {
    header('Content-Disposition: inline; filename="' . $nome . '"');
}
$fp = fopen($full, 'rb');
if ($fp) {
    fpassthru($fp);
    fclose($fp);
}
exit;
