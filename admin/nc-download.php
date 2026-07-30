<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/nextcloud.php';

$path = trim((string)($_GET['path'] ?? ''));
$modoZip = isset($_GET['zip']);

if ($path === '' && !$modoZip) {
    http_response_code(400);
    exit('path missing');
}

$cfg = nc_config();
if (empty($cfg['server']) || empty($cfg['user']) || empty($cfg['pass'])) {
    http_response_code(502);
    exit('Nextcloud not configured');
}

$baseUrl = rtrim($cfg['server'], '/') . '/remote.php/dav/files/' . rawurlencode($cfg['user']);

if ($modoZip) {
    $folder = trim((string)($_GET['zip'] ?? ''));
    $arquivos = nc_listar($folder);
    $zip = new ZipArchive();
    $tmp = tempnam(sys_get_temp_dir(), 'nc_') . '.zip';
    if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
        http_response_code(500);
        exit('Erro ao criar ZIP');
    }
    $adicionados = 0;
    foreach ($arquivos as $item) {
        if ($item['type'] !== 'file') continue;
        $url = $baseUrl . '/' . implode('/', array_map('rawurlencode', explode('/', $item['path'])));
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $cfg['user'] . ':' . $cfg['pass'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 60,
        ]);
        $conteudo = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 400 && $conteudo !== false) {
            $zip->addFromString($item['name'], $conteudo);
            $adicionados++;
        }
    }
    $zip->close();
    if ($adicionados === 0) {
        @unlink($tmp);
        http_response_code(404);
        exit('Nenhum arquivo encontrado para baixar.');
    }
    $nomeZip = $folder !== '' ? basename($folder) : 'Nextcloud';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nomeZip . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

$url = $baseUrl . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_USERPWD => $cfg['user'] . ':' . $cfg['pass'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_HEADERFUNCTION => function ($ch, $header) {
        $trimmed = trim($header);
        if ($trimmed === '') return strlen($header);
        $lower = strtolower($trimmed);
        if (str_starts_with($lower, 'content-type:') ||
            str_starts_with($lower, 'content-length:') ||
            str_starts_with($lower, 'content-disposition:') ||
            str_starts_with($lower, 'accept-ranges:')) {
            header($trimmed);
        }
        return strlen($header);
    },
]);

$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range) {
    curl_setopt($ch, CURLOPT_RANGE, $range);
}

curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 404) { http_response_code(404); exit('File not found'); }
if ($code >= 400) { http_response_code($code); exit('Error fetching file'); }
