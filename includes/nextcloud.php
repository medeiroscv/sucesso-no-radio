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

function nc_curl_init(string $path, string $method = 'PROPFIND'): ?CurlHandle {
    $cfg = nc_config();
    if (empty($cfg['server']) || empty($cfg['user']) || empty($cfg['pass'])) return null;
    $base = nc_base_webdav();
    $url = $base . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $cfg['user'] . ':' . $cfg['pass'],
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Depth: 1'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    return $ch ?: null;
}

function nc_test(): array {
    $ch = nc_curl_init('');
    if (!$ch) return ['ok' => false, 'msg' => 'Configuracao incompleta. Preencha servidor, usuario e senha.'];
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($code === 401) return ['ok' => false, 'msg' => 'Falha de autenticacao (HTTP 401). Verifique usuario/senha.'];
    if ($code >= 200 && $code < 400) return ['ok' => true, 'msg' => 'Conexao OK (HTTP ' . $code . ')'];
    return ['ok' => false, 'msg' => 'Erro HTTP ' . $code . ($err ? ' — ' . $err : '')];
}

function nc_listar(string $path = ''): array {
    $ch = nc_curl_init($path);
    if (!$ch) return [];
    $xml = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 400 || !$xml) return [];

    $doc = new DOMDocument();
    $ok = @$doc->loadXML($xml);
    if (!$ok) return [];

    $itens = [];
    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('d', 'DAV:');
    $xpath->registerNamespace('s', 'http://owncloud.org/ns');

    $base = nc_base_webdav();
    $prefixLen = strlen(rtrim($base, '/')) + 1;

    foreach ($xpath->query('//d:response') ?: [] as $resp) {
        $hrefNode = $xpath->query('.//d:href', $resp)->item(0);
        if (!$hrefNode) continue;
        $href = rawurldecode($hrefNode->textContent);
        $relPath = substr($href, $prefixLen);
        $relPath = rtrim($relPath, '/');

        if ($relPath === '' || $relPath === basename(rtrim($path, '/'))) continue;

        $isCollection = $xpath->query('.//d:collection', $resp)->length > 0;
        $getetag = $xpath->query('.//d:getetag', $resp)->item(0);
        $etag = $getetag ? trim($getetag->textContent, '"') : '';

        $name = basename($relPath);
        if ($name === '' || $name === $path) continue;

        $item = [
            'name' => $name,
            'path' => $relPath,
            'type' => $isCollection ? 'folder' : 'file',
            'etag' => $etag,
        ];

        if (!$isCollection) {
            $sizeNode = $xpath->query('.//d:getcontentlength', $resp)->item(0);
            $item['size'] = $sizeNode ? (int)$sizeNode->textContent : 0;
            $mimeNode = $xpath->query('.//d:getcontenttype', $resp)->item(0);
            $item['mimetype'] = $mimeNode ? $mimeNode->textContent : '';
            $item['size_fmt'] = $item['size'] > 1048576
                ? round($item['size'] / 1048576, 1) . ' MB'
                : ($item['size'] > 1024 ? round($item['size'] / 1024, 1) . ' KB' : $item['size'] . ' B');
        } else {
            $item['size'] = 0;
            $item['mimetype'] = '';
            $item['size_fmt'] = '';
        }

        $mtimeNode = $xpath->query('.//d:getlastmodified', $resp)->item(0);
        if ($mtimeNode) {
            $item['mtime'] = date('d/m/Y H:i', strtotime($mtimeNode->textContent));
        } else {
            $item['mtime'] = '';
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

function nc_pode_visualizar(string $mime): bool {
    return nc_is_audio($mime) || str_starts_with($mime, 'image/') || $mime === 'application/pdf';
}

function nc_download_url(string $path): string {
    $cfg = nc_config();
    if (empty($cfg['server']) || empty($cfg['user']) || empty($cfg['pass'])) return '';
    return app_url('cliente/nc-file.php?path=' . rawurlencode($path));
}

function nc_categoria_com_pasta(string $tipo): string {
    try {
        $v = app_pdo()->query(
            "SELECT nc_pasta FROM categorias WHERE tipo = " . app_pdo()->quote($tipo) . " AND ativo = 1 LIMIT 1"
        )->fetchColumn();
        return (string)($v ?: '');
    } catch (Throwable $e) {
        return '';
    }
}
