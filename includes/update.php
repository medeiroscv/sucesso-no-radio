<?php
/**
 * Atualização do site via GitHub (ZIP + merge seletivo).
 *
 * Config: site_settings (github_repo, github_branch, github_token)
 *         com override por ENV (GITHUB_REPO, GITHUB_BRANCH, GITHUB_TOKEN).
 *
 * Preserva: uploads/, data/, config/, .env, .git
 * Atualiza: código PHP, assets, admin, includes, scripts, etc.
 */

require_once __DIR__ . '/env.php';

function app_update_root(): string {
    return dirname(__DIR__);
}

function app_update_cfg(string $settingKey, string $envKey, string $default = ''): string {
    $env = app_env($envKey, '');
    if (is_string($env) && $env !== '') {
        return trim($env);
    }
    if (function_exists('app_setting')) {
        return trim(app_setting($settingKey, $default));
    }
    return $default;
}

function app_update_repo(): string {
    $repo = app_update_cfg('github_repo', 'GITHUB_REPO', 'medeiroscv/sucesso-no-radio');
    $repo = preg_replace('#^https?://github\.com/#i', '', $repo) ?? $repo;
    $repo = rtrim($repo, '/');
    if (str_ends_with(strtolower($repo), '.git')) {
        $repo = substr($repo, 0, -4);
    }
    return $repo !== '' ? $repo : 'medeiroscv/sucesso-no-radio';
}

function app_update_branch(): string {
    $b = app_update_cfg('github_branch', 'GITHUB_BRANCH', 'master');
    return $b !== '' ? $b : 'master';
}

function app_update_token(): string {
    return app_update_cfg('github_token', 'GITHUB_TOKEN', '');
}

function app_update_token_saved(): bool {
    return app_update_token() !== '';
}

function app_update_token_from_env(): bool {
    $env = app_env('GITHUB_TOKEN', '');
    return is_string($env) && $env !== '';
}

function app_update_version_path(): string {
    return app_update_root() . '/version.json';
}

function app_update_log_path(): string {
    return app_update_root() . '/data/update_log.txt';
}

function app_update_meta_path(): string {
    return app_update_root() . '/data/update_meta.json';
}

/** Pastas/arquivos que NUNCA são sobrescritos pelo update. */
function app_update_protected_names(): array {
    return [
        'uploads',
        'data',
        'config',
        '.env',
        '.git',
        'node_modules',
        'vendor',
    ];
}

function app_update_log_write(string $text, bool $append = true): void {
    $dir = dirname(app_update_log_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $text . "\n";
    if ($append) {
        @file_put_contents(app_update_log_path(), $line, FILE_APPEND);
    } else {
        @file_put_contents(app_update_log_path(), $line);
    }
}

function app_update_log_read(int $maxBytes = 50000): string {
    $path = app_update_log_path();
    if (!is_file($path)) return '';
    $size = filesize($path);
    if ($size === false || $size <= $maxBytes) {
        return (string)file_get_contents($path);
    }
    $fp = fopen($path, 'rb');
    if (!$fp) return '';
    fseek($fp, -$maxBytes, SEEK_END);
    $data = stream_get_contents($fp);
    fclose($fp);
    return "…\n" . (string)$data;
}

function app_update_meta_read(): array {
    $path = app_update_meta_path();
    if (!is_file($path)) return [];
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : [];
}

function app_update_meta_write(array $meta): void {
    $dir = dirname(app_update_meta_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $cur = app_update_meta_read();
    $merged = array_merge($cur, $meta, ['updated_at' => date('c')]);
    @file_put_contents(
        app_update_meta_path(),
        json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

/** @return array{commit:string,short:string,branch:string,repo:string,updated_at:string,message:string} */
function app_update_local_version(): array {
    $defaults = [
        'commit' => '',
        'short' => '',
        'branch' => app_update_branch(),
        'repo' => app_update_repo(),
        'updated_at' => '',
        'message' => '',
    ];
    $path = app_update_version_path();
    if (is_file($path)) {
        $j = json_decode((string)file_get_contents($path), true);
        if (is_array($j)) {
            foreach (['commit', 'short', 'branch', 'repo', 'updated_at', 'message'] as $k) {
                if (isset($j[$k]) && is_string($j[$k])) {
                    $defaults[$k] = $j[$k];
                }
            }
        }
    }
    if ($defaults['short'] === '' && $defaults['commit'] !== '') {
        $defaults['short'] = substr($defaults['commit'], 0, 7);
    }
    return $defaults;
}

function app_update_write_version(array $info): bool {
    $payload = [
        'commit' => (string)($info['commit'] ?? ''),
        'short' => (string)($info['short'] ?? substr((string)($info['commit'] ?? ''), 0, 7)),
        'branch' => (string)($info['branch'] ?? app_update_branch()),
        'repo' => (string)($info['repo'] ?? app_update_repo()),
        'updated_at' => (string)($info['updated_at'] ?? date('c')),
        'message' => (string)($info['message'] ?? ''),
    ];
    return @file_put_contents(
        app_update_version_path(),
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    ) !== false;
}

/**
 * @return array{code:int,data:mixed,raw:string,headers:array,error?:string}
 */
function app_update_http(string $url, array $extraHeaders = [], bool $binary = false): array {
    $headers = array_merge([
        'Accept: application/vnd.github+json',
        'User-Agent: SucessoNoRadio-Updater/2.0',
        'X-GitHub-Api-Version: 2022-11-28',
    ], $extraHeaders);
    $token = app_update_token();
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['code' => 0, 'data' => null, 'raw' => '', 'headers' => [], 'error' => $cerr ?: 'Falha de rede'];
    }

    $rawHeaders = substr((string)$resp, 0, $headerSize);
    $body = substr((string)$resp, $headerSize);
    $hdrMap = [];
    foreach (explode("\n", $rawHeaders) as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $hdrMap[strtolower(trim($k))] = trim($v);
        }
    }

    if ($binary) {
        return ['code' => $code, 'data' => $body, 'raw' => $body, 'headers' => $hdrMap];
    }

    $data = json_decode($body, true);
    $err = null;
    if ($code >= 400) {
        $err = is_array($data) && !empty($data['message'])
            ? (string)$data['message']
            : mb_substr($body, 0, 300);
    }
    return ['code' => $code, 'data' => $data, 'raw' => $body, 'headers' => $hdrMap, 'error' => $err];
}

function app_update_api_get(string $path): array {
    return app_update_http('https://api.github.com' . $path);
}

/**
 * Testa credenciais / repositório / branch.
 * @return array{ok:bool,message:string,detail?:array}
 */
function app_update_test(): array {
    $repo = app_update_repo();
    $branch = app_update_branch();

    if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
        return ['ok' => false, 'message' => 'Repositório inválido. Use o formato dono/repositorio.'];
    }

    $r = app_update_api_get('/repos/' . $repo);
    if (($r['code'] ?? 0) !== 200) {
        $msg = $r['error'] ?? ('HTTP ' . ($r['code'] ?? 0));
        if (($r['code'] ?? 0) === 404) {
            $msg = 'Repositório não encontrado (404). Verifique o nome ou o token (repo privado).';
        } elseif (($r['code'] ?? 0) === 401) {
            $msg = 'Token inválido ou ausente (401).';
        }
        return ['ok' => false, 'message' => $msg];
    }

    $c = app_update_api_get('/repos/' . $repo . '/commits/' . rawurlencode($branch));
    if (($c['code'] ?? 0) !== 200) {
        return [
            'ok' => false,
            'message' => 'Repositório OK, mas branch "' . $branch . '" não encontrada: ' . ($c['error'] ?? 'erro'),
        ];
    }

    $sha = (string)($c['data']['sha'] ?? '');
    $msg = trim((string)($c['data']['commit']['message'] ?? ''));
    $msg = preg_split('/\r\n|\r|\n/', $msg)[0] ?? $msg;

    app_update_meta_write([
        'last_test_ok' => true,
        'last_test_at' => date('c'),
        'last_test_sha' => $sha,
        'last_test_message' => $msg,
    ]);

    return [
        'ok' => true,
        'message' => 'GitHub OK · ' . $repo . ' @ ' . $branch . ' · ' . substr($sha, 0, 7) . ' — ' . $msg,
        'detail' => [
            'repo' => $repo,
            'branch' => $branch,
            'sha' => $sha,
            'short' => substr($sha, 0, 7),
            'message' => $msg,
            'private' => !empty($r['data']['private']),
            'token' => app_update_token_saved(),
        ],
    ];
}

/**
 * Consulta status remoto vs local.
 * @return array{ok:bool,error?:string,local:array,remote_commit:string,remote_short:string,remote_message:string,remote_date:string,behind:int,up_to_date:bool,html_url:string}
 */
function app_update_check(bool $force = false): array {
    unset($force); // sempre fresco o suficiente; force mantido por assinatura
    $local = app_update_local_version();
    $repo = app_update_repo();
    $branch = app_update_branch();
    $base = [
        'ok' => false,
        'local' => $local,
        'remote_commit' => '',
        'remote_short' => '',
        'remote_message' => '',
        'remote_date' => '',
        'behind' => 0,
        'up_to_date' => false,
        'html_url' => 'https://github.com/' . $repo . '/tree/' . rawurlencode($branch),
    ];

    if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
        $base['error'] = 'Repositório inválido.';
        return $base;
    }

    $c = app_update_api_get('/repos/' . $repo . '/commits/' . rawurlencode($branch));
    if (($c['code'] ?? 0) !== 200 || !is_array($c['data'])) {
        $base['error'] = $c['error'] ?? 'Falha ao consultar commits no GitHub.';
        return $base;
    }

    $sha = (string)($c['data']['sha'] ?? '');
    $msg = trim((string)($c['data']['commit']['message'] ?? ''));
    $msg = preg_split('/\r\n|\r|\n/', $msg)[0] ?? $msg;
    $date = (string)($c['data']['commit']['committer']['date'] ?? $c['data']['commit']['author']['date'] ?? '');

    $base['ok'] = true;
    $base['remote_commit'] = $sha;
    $base['remote_short'] = substr($sha, 0, 7);
    $base['remote_message'] = $msg;
    $base['remote_date'] = $date;
    $base['html_url'] = (string)($c['data']['html_url'] ?? $base['html_url']);

    $localSha = strtolower((string)($local['commit'] ?? ''));
    $remoteSha = strtolower($sha);
    if ($localSha !== '' && ($localSha === $remoteSha || str_starts_with($remoteSha, $localSha) || str_starts_with($localSha, $remoteSha))) {
        $base['up_to_date'] = true;
        $base['behind'] = 0;
    } else {
        $base['up_to_date'] = false;
        // tenta compare para contagem
        if ($localSha !== '' && preg_match('/^[0-9a-f]{7,40}$/', $localSha)) {
            $cmp = app_update_api_get('/repos/' . $repo . '/compare/' . rawurlencode($local['commit']) . '...' . rawurlencode($branch));
            if (($cmp['code'] ?? 0) === 200 && is_array($cmp['data'])) {
                $base['behind'] = (int)($cmp['data']['ahead_by'] ?? 1);
            } else {
                $base['behind'] = 1;
            }
        } else {
            $base['behind'] = 1;
        }
    }
    return $base;
}

/**
 * Baixa ZIP do GitHub e aplica sobre o código, preservando dados.
 * @return array{ok:bool,message:string,log:string,version?:array}
 */
function app_update_apply(): array {
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    $log = [];
    $logFn = static function (string $m) use (&$log): void {
        $log[] = $m;
        app_update_log_write($m);
    };

    app_update_log_write('========== INÍCIO DA ATUALIZAÇÃO ==========', false);
    $logFn('Iniciando atualização via GitHub ZIP…');

    $repo = app_update_repo();
    $branch = app_update_branch();
    $logFn('Repositório: ' . $repo . ' · branch: ' . $branch);

    $fail = static function (string $message, array $logLines) {
        app_update_meta_write([
            'last_apply_ok' => false,
            'last_apply_at' => date('c'),
            'last_apply_message' => $message,
        ]);
        return ['ok' => false, 'message' => $message, 'log' => implode("\n", $logLines)];
    };

    if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
        $logFn('ERRO: repositório inválido.');
        return $fail('Repositório inválido.', $log);
    }

    if (!class_exists('ZipArchive')) {
        $logFn('ERRO: extensão PHP ZipArchive não disponível.');
        return $fail('ZipArchive não disponível no PHP.', $log);
    }

    $check = app_update_check(true);
    if (empty($check['ok'])) {
        $msg = (string)($check['error'] ?? 'Falha ao consultar GitHub.');
        $logFn('ERRO: ' . $msg);
        return $fail($msg, $log);
    }

    $remoteSha = (string)$check['remote_commit'];
    $remoteMsg = (string)$check['remote_message'];
    $logFn('Remoto: ' . substr($remoteSha, 0, 7) . ' — ' . $remoteMsg);

    if (!empty($check['up_to_date'])) {
        $logFn('Sistema já está atualizado. Nada a fazer.');
        app_update_meta_write([
            'last_apply_ok' => true,
            'last_apply_at' => date('c'),
            'last_apply_message' => 'Já atualizado',
            'last_apply_sha' => $remoteSha,
        ]);
        return [
            'ok' => true,
            'message' => 'Sistema já está na versão mais recente (' . substr($remoteSha, 0, 7) . ').',
            'log' => implode("\n", $log),
            'version' => app_update_local_version(),
        ];
    }

    $root = app_update_root();
    $tmpDir = $root . '/data/tmp_update';
    $zipPath = $tmpDir . '/source.zip';
    $extractDir = $tmpDir . '/extract';

    app_update_rrmdir($tmpDir);
    if (!@mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
        $logFn('ERRO: não foi possível criar pasta temporária.');
        return ['ok' => false, 'message' => 'Sem permissão em data/tmp_update.', 'log' => implode("\n", $log)];
    }
    @mkdir($extractDir, 0775, true);

    // Download zipball
    $zipUrl = 'https://api.github.com/repos/' . $repo . '/zipball/' . rawurlencode($branch);
    $logFn('Baixando pacote: ' . $zipUrl);
    $dl = app_update_http($zipUrl, ['Accept: application/vnd.github+json'], true);
    if (($dl['code'] ?? 0) !== 200 || !is_string($dl['data']) || strlen($dl['data']) < 100) {
        $err = $dl['error'] ?? ('HTTP ' . ($dl['code'] ?? 0));
        // body may be JSON error when binary expected
        if (is_string($dl['data']) && str_starts_with(ltrim($dl['data']), '{')) {
            $j = json_decode($dl['data'], true);
            if (!empty($j['message'])) $err = (string)$j['message'];
        }
        $logFn('ERRO no download: ' . $err);
        app_update_rrmdir($tmpDir);
        return ['ok' => false, 'message' => 'Falha ao baixar ZIP: ' . $err, 'log' => implode("\n", $log)];
    }

    if (@file_put_contents($zipPath, $dl['data']) === false) {
        $logFn('ERRO: falha ao gravar ZIP em disco.');
        app_update_rrmdir($tmpDir);
        return ['ok' => false, 'message' => 'Falha ao gravar ZIP.', 'log' => implode("\n", $log)];
    }
    $logFn('ZIP salvo (' . number_format(strlen($dl['data']) / 1024, 1) . ' KB).');

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        $logFn('ERRO: não foi possível abrir o ZIP.');
        app_update_rrmdir($tmpDir);
        return ['ok' => false, 'message' => 'ZIP inválido.', 'log' => implode("\n", $log)];
    }
    if (!$zip->extractTo($extractDir)) {
        $zip->close();
        $logFn('ERRO: falha ao extrair ZIP.');
        app_update_rrmdir($tmpDir);
        return ['ok' => false, 'message' => 'Falha ao extrair ZIP.', 'log' => implode("\n", $log)];
    }
    $zip->close();
    $logFn('ZIP extraído.');

    // GitHub zip tem uma pasta raiz tipo owner-repo-sha/
    $sourceRoot = app_update_find_zip_root($extractDir);
    if ($sourceRoot === '' || !is_dir($sourceRoot)) {
        $logFn('ERRO: estrutura do ZIP não reconhecida.');
        app_update_rrmdir($tmpDir);
        return ['ok' => false, 'message' => 'Estrutura do ZIP inválida.', 'log' => implode("\n", $log)];
    }
    $logFn('Pasta fonte: ' . basename($sourceRoot));

    $stats = app_update_merge_tree($sourceRoot, $root, '', $logFn);
    $logFn(sprintf(
        'Arquivos copiados: %d · pastas: %d · ignorados (protegidos): %d',
        $stats['files'],
        $stats['dirs'],
        $stats['skipped']
    ));

    $version = [
        'commit' => $remoteSha,
        'short' => substr($remoteSha, 0, 7),
        'branch' => $branch,
        'repo' => $repo,
        'updated_at' => date('c'),
        'message' => $remoteMsg,
    ];
    if (app_update_write_version($version)) {
        $logFn('version.json atualizado → ' . $version['short']);
    } else {
        $logFn('AVISO: não foi possível gravar version.json');
    }

    // Bootstrap de schema (não mexe em dados)
    try {
        if (function_exists('app_pdo')) {
            app_pdo();
            $logFn('Schema/bootstrap verificado (db_version ok).');
        }
    } catch (Throwable $e) {
        $logFn('AVISO bootstrap DB: ' . $e->getMessage());
    }

    app_update_rrmdir($tmpDir);
    $logFn('Temporários removidos.');
    $logFn('========== ATUALIZAÇÃO CONCLUÍDA ==========');

    app_update_meta_write([
        'last_apply_ok' => true,
        'last_apply_at' => date('c'),
        'last_apply_message' => 'Atualizado com sucesso',
        'last_apply_sha' => $remoteSha,
        'last_apply_commit_message' => $remoteMsg,
        'last_apply_files' => $stats['files'],
    ]);

    return [
        'ok' => true,
        'message' => 'Atualização aplicada com sucesso (' . $version['short'] . '). ' . $stats['files'] . ' arquivo(s) atualizado(s).',
        'log' => implode("\n", $log),
        'version' => $version,
    ];
}

function app_update_find_zip_root(string $extractDir): string {
    $items = @scandir($extractDir) ?: [];
    $dirs = [];
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $full = $extractDir . DIRECTORY_SEPARATOR . $it;
        if (is_dir($full)) $dirs[] = $full;
    }
    if (count($dirs) === 1) return $dirs[0];
    // se já extraiu flat
    if (is_file($extractDir . '/index.php') || is_dir($extractDir . '/includes')) {
        return $extractDir;
    }
    return $dirs[0] ?? '';
}

/**
 * Copia árvore de $src para $dstRel sob $destRoot, respeitando protegidos.
 * @param callable(string):void $logFn
 * @return array{files:int,dirs:int,skipped:int}
 */
function app_update_merge_tree(string $srcDir, string $destRoot, string $rel, callable $logFn): array {
    $stats = ['files' => 0, 'dirs' => 0, 'skipped' => 0];
    $protected = array_map('strtolower', app_update_protected_names());

    $items = @scandir($srcDir) ?: [];
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;

        $relPath = $rel === '' ? $it : ($rel . '/' . $it);
        $top = strtolower(explode('/', str_replace('\\', '/', $relPath))[0]);

        if (in_array($top, $protected, true)) {
            $stats['skipped']++;
            $logFn('Preservado: ' . $relPath);
            continue;
        }

        // Nunca sobrescrever o próprio log/meta em execução de forma destrutiva no data/ (já protegido)
        $src = $srcDir . DIRECTORY_SEPARATOR . $it;
        $dst = $destRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

        if (is_dir($src)) {
            if (!is_dir($dst)) {
                if (!@mkdir($dst, 0775, true) && !is_dir($dst)) {
                    $logFn('AVISO: não criou pasta ' . $relPath);
                    continue;
                }
                $stats['dirs']++;
            }
            $sub = app_update_merge_tree($src, $destRoot, $relPath, $logFn);
            $stats['files'] += $sub['files'];
            $stats['dirs'] += $sub['dirs'];
            $stats['skipped'] += $sub['skipped'];
        } elseif (is_file($src)) {
            $dir = dirname($dst);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (@copy($src, $dst)) {
                $stats['files']++;
            } else {
                $logFn('AVISO: falha ao copiar ' . $relPath);
            }
        }
    }
    return $stats;
}

function app_update_rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $items = @scandir($dir) ?: [];
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $it;
        if (is_dir($path) && !is_link($path)) {
            app_update_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

/** Status resumido para o hub de configurações. */
function app_update_hub_status(): string {
    $local = app_update_local_version();
    $short = $local['short'] !== '' ? $local['short'] : '—';
    $meta = app_update_meta_read();
    if (!empty($meta['last_apply_ok']) && !empty($meta['last_apply_at'])) {
        $when = date('d/m H:i', strtotime((string)$meta['last_apply_at']));
        return 'OK · ' . $short . ' · ' . $when;
    }
    if (app_update_token_saved()) {
        return 'Token ok · ' . $short;
    }
    return 'Configurar · ' . $short;
}

/** Compat: nomes antigos ainda referenciados em scripts CLI. */
function app_update_allow_apply(): bool {
    return true;
}

function app_update_git_available(): bool {
    return false;
}
