<?php
/**
 * Entrega de arquivos somente para cliente autenticado.
 * Impede acesso público direto aos MP3 de entrega.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

cliente_require_auth('download.php?id=' . intval($_GET['id'] ?? 0));

$id = intval($_GET['id'] ?? 0);
$forceDl = !empty($_GET['dl']);
$ent = app_entrega_by_id($id);

if (!$ent || empty($ent['ativo'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Arquivo não encontrado.';
    exit;
}

// Conteúdo precisa estar ativo
$conteudo = app_conteudo_by_id(intval($ent['conteudo_id']), true);
if (!$conteudo) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Conteúdo indisponível.';
    exit;
}

$rel = str_replace('\\', '/', ltrim((string)$ent['arquivo'], '/'));
if ($rel === '' || str_contains($rel, '..') || !str_starts_with($rel, 'uploads/entregas/')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acesso negado.';
    exit;
}

$full = dirname(__DIR__) . '/' . $rel;
if (!is_file($full)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Arquivo ausente no servidor.';
    exit;
}

$fname = basename($full);
$titulo = preg_replace('/[^a-zA-Z0-9_\-\. ]+/', '', (string)($ent['titulo'] ?? '')) ?: $fname;
$ext = pathinfo($full, PATHINFO_EXTENSION) ?: 'mp3';
$downloadName = trim($titulo) . '.' . $ext;

$mime = 'application/octet-stream';
$extL = strtolower($ext);
if (in_array($extL, ['mp3', 'mpeg', 'mpga'], true)) $mime = 'audio/mpeg';
elseif ($extL === 'm4a') $mime = 'audio/mp4';
elseif ($extL === 'ogg') $mime = 'audio/ogg';
elseif ($extL === 'wav') $mime = 'audio/wav';
elseif ($extL === 'aac') $mime = 'audio/aac';

$size = filesize($full);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$size);
header('Accept-Ranges: bytes');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if ($forceDl) {
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
} else {
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
}

// Stream eficiente
$fp = fopen($full, 'rb');
if ($fp) {
    fpassthru($fp);
    fclose($fp);
}
exit;
