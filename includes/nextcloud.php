<?php

function nc_config(): array {
    return [
        'server' => app_setting('nc_server', ''),
        'user'   => app_setting('nc_user', ''),
        'pass'   => app_setting('nc_pass', ''),
    ];
}

function nc_base_webdav(): string {
    $cfg = nc_config();
    if (empty($cfg['server']) || empty($cfg['user'])) return '';
    return rtrim($cfg['server'], '/') . '/remote.php/dav/files/' . rawurlencode($cfg['user']);
}

function nc_curl_init(string $path, string $method = 'PROPFIND') {
    $cfg = nc_config();
    if (empty($cfg['server']) || empty($cfg['user']) || empty($cfg['pass'])) return null;
    $base = nc_base_webdav();
    $path = ltrim($path, '/');
    $url = $base . ($path !== '' ? '/' . $path : '/');
    $ch = curl_init($url);
    if (!$ch) return null;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $cfg['user'] . ':' . $cfg['pass'],
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Depth: 1'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    return $ch;
}

function nc_test(): array {
    $ch = nc_curl_init('');
    if (!$ch) return ['ok' => false, 'msg' => 'Configuracao incompleta.'];
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($code === 401) return ['ok' => false, 'msg' => 'Falha de autenticacao (HTTP 401).'];
    if ($code >= 200 && $code < 400) return ['ok' => true, 'msg' => 'Conexao OK (HTTP ' . $code . ')'];
    return ['ok' => false, 'msg' => 'Erro HTTP ' . $code . ($err ? ' — ' . $err : '')];
}

function nc_listar(string $path = ''): array {
    $ch = nc_curl_init($path);
    if (!$ch) return [];
    $xml = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return [];
    if ($code < 200 || $code >= 400) return [];
    if (!$xml) return [];

    $xml = preg_replace('/(xmlns\s*=\s*"[^"]+")/', '', $xml);
    $xml = preg_replace('/<(\\/?)[a-z]+:/i', '<$1', $xml);

    $sxml = @simplexml_load_string($xml);
    if (!$sxml) return [];

    $base = nc_base_webdav();
    $baseUrlLen = strlen(rtrim($base, '/')) + 1;

    $itens = [];
    foreach ($sxml->response ?? [] as $resp) {
        $href = (string)$resp->href;
        if ($href === '') continue;

        $href = rawurldecode($href);
        $pos = strpos($href, '/remote.php/');
        if ($pos !== false) {
            $relPath = substr($href, $pos + strlen('/remote.php/'));
        } else {
            $pos2 = strrpos($href, '/files/');
            if ($pos2 !== false) {
                $relPath = substr($href, $pos2 + strlen('/files/'));
                $relPath = preg_replace('#^[^/]+/#', '', $relPath);
            } else {
                $relPath = ltrim($href, '/');
            }
        }
        $relPath = rtrim($relPath, '/');

        if ($relPath === '') continue;

        $name = basename($relPath);
        if ($name === '') continue;

        $isCollection = !empty($resp->propstat->prop->resourcetype->collection);

        if (!$isCollection && empty((string)$resp->propstat->prop->getcontenttype)) continue;

        $item = [
            'name' => $name,
            'path' => $relPath,
            'type' => $isCollection ? 'folder' : 'file',
            'size' => 0,
            'mimetype' => '',
            'size_fmt' => '',
            'mtime' => '',
        ];

        if (!$isCollection) {
            $size = (int)$resp->propstat->prop->getcontentlength;
            $item['size'] = $size;
            $item['mimetype'] = (string)$resp->propstat->prop->getcontenttype;
            $item['size_fmt'] = $size > 1048576
                ? round($size / 1048576, 1) . ' MB'
                : ($size > 1024 ? round($size / 1024, 1) . ' KB' : $size . ' B');
        }

        $mtime = (string)$resp->propstat->prop->getlastmodified;
        if ($mtime !== '') {
            $item['mtime'] = date('d/m/Y H:i', strtotime($mtime));
        }

        $itens[] = $item;
    }

    usort($itens, function ($a, $b) {
        if ($a['type'] !== $b['type']) return $a['type'] === 'folder' ? -1 : 1;
        return strcasecmp($a['name'], $b['name']);
    });

    return $itens;
}

function nc_is_audio(string $mime): bool {
    return str_starts_with($mime, 'audio/');
}

function nc_download_url(string $path): string {
    $cfg = nc_config();
    if (empty($cfg['server']) || empty($cfg['user']) || empty($cfg['pass'])) return '';
    return app_url('cliente/nc-file.php?path=' . rawurlencode($path));
}

function nc_configurado(): bool {
    $cfg = nc_config();
    return $cfg['server'] !== '' && $cfg['user'] !== '' && $cfg['pass'] !== '';
}
