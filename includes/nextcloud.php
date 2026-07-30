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
    $parts = explode('/', $path);
    $parts = array_map('rawurlencode', $parts);
    $pathEnc = implode('/', $parts);
    $url = $pathEnc !== '' ? $base . '/' . $pathEnc . '/' : $base . '/';
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

    $base = nc_base_webdav();
    $baseUrl = rtrim($base, '/') . '/';

    $itens = [];
    $blocos = preg_split('/<(?:d:)?response\s*>/i', $xml);
    array_shift($blocos);

    foreach ($blocos as $bloco) {
        $end = strrpos($bloco, '</d:response>');
        if ($end === false) $end = strrpos($bloco, '</response>');
        if ($end === false) continue;
        $bloco = substr($bloco, 0, $end);
        if ($bloco === '') continue;

        preg_match('/<(?:d:)?href[^>]*>(.*?)<\/(?:d:)?href>/is', $bloco, $m);
        $href = trim($m[1] ?? '');
        if ($href === '') continue;

        $href = rawurldecode($href);
        $relPath = '';

        $p = strpos($href, $baseUrl);
        if ($p !== false) {
            $relPath = substr($href, $p + strlen($baseUrl));
        } else {
            preg_match('#/remote\.php/dav/files/[^/]+/(.*)$#', $href, $m2);
            if (!empty($m2[1])) $relPath = $m2[1];
        }

        $relPath = rtrim($relPath, '/');
        if ($relPath === '') continue;

        $name = basename($relPath);
        if ($name === '') continue;

        $isCollection = preg_match('/<(?:d:)?collection\s*\/>/i', $bloco) === 1;

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
            preg_match('/<(?:d:)?getcontentlength[^>]*>(.*?)<\/(?:d:)?getcontentlength>/is', $bloco, $ms);
            $size = (int)($ms[1] ?? 0);
            $item['size'] = $size;
            $item['size_fmt'] = $size > 1048576
                ? round($size / 1048576, 1) . ' MB'
                : ($size > 1024 ? round($size / 1024, 1) . ' KB' : $size . ' B');

            preg_match('/<(?:d:)?getcontenttype[^>]*>(.*?)<\/(?:d:)?getcontenttype>/is', $bloco, $mm);
            $item['mimetype'] = (string)($mm[1] ?? '');
        }

        preg_match('/<(?:d:)?getlastmodified[^>]*>(.*?)<\/(?:d:)?getlastmodified>/is', $bloco, $mt);
        $mtime = (string)($mt[1] ?? '');
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
