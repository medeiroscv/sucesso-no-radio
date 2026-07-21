<?php
/**
 * Verificação e aplicação de atualizações via GitHub.
 *
 * - Consulta a API pública do repositório (commits / compare)
 * - Versão local em version.json (e fallback git rev-parse)
 * - Aplicação in-place só se houver .git e APP_UPDATE_ALLOW=true
 */

require_once __DIR__ . '/env.php';

function app_update_repo(): string {
    $repo = trim((string)app_env('GITHUB_REPO', 'medeiroscv/sucesso-no-radio'));
    return $repo !== '' ? $repo : 'medeiroscv/sucesso-no-radio';
}

function app_update_branch(): string {
    $b = trim((string)app_env('GITHUB_BRANCH', 'master'));
    return $b !== '' ? $b : 'master';
}

function app_update_token(): string {
    return trim((string)app_env('GITHUB_TOKEN', ''));
}

function app_update_allow_apply(): bool {
    return app_env_bool('APP_UPDATE_ALLOW', false);
}

function app_update_version_path(): string {
    return dirname(__DIR__) . '/version.json';
}

function app_update_cache_path(): string {
    return dirname(__DIR__) . '/data/update_check.json';
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
            $defaults['commit'] = (string)($j['commit'] ?? '');
            $defaults['short'] = (string)($j['short'] ?? '');
            $defaults['branch'] = (string)($j['branch'] ?? $defaults['branch']);
            $defaults['repo'] = (string)($j['repo'] ?? $defaults['repo']);
            $defaults['updated_at'] = (string)($j['updated_at'] ?? '');
            $defaults['message'] = (string)($j['message'] ?? '');
        }
    }

    // Fallback: git no disco
    if ($defaults['commit'] === '' && app_update_git_available()) {
        $sha = trim((string)@shell_exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' rev-parse HEAD 2>/dev/null'));
        if (preg_match('/^[0-9a-f]{7,40}$/i', $sha)) {
            $defaults['commit'] = $sha;
            $defaults['short'] = substr($sha, 0, 7);
            $msg = trim((string)@shell_exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' log -1 --pretty=%s 2>/dev/null'));
            $defaults['message'] = $msg;
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
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return @file_put_contents(app_update_version_path(), $json . "\n") !== false;
}

function app_update_git_available(): bool {
    $root = dirname(__DIR__);
    if (!is_dir($root . '/.git')) return false;
    $out = @shell_exec('git --version 2>/dev/null');
    return is_string($out) && stripos($out, 'git version') !== false;
}

/**
 * HTTP GET JSON na API GitHub.
 * @return array{code:int,data:mixed,raw:string,error?:string}
 */
function app_update_github_get(string $path): array {
    $url = 'https://api.github.com' . $path;
    $headers = [
        'Accept: application/vnd.github+json',
        'User-Agent: SucessoNoRadio-Updater/1.0',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    $token = app_update_token();
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['code' => 0, 'data' => null, 'raw' => '', 'error' => $cerr ?: 'Falha de rede'];
    }
    $data = json_decode((string)$raw, true);
    return [
        'code' => $code,
        'data' => $data,
        'raw' => (string)$raw,
        'error' => $code >= 400 ? app_update_github_error($data, (string)$raw) : null,
    ];
}

function app_update_github_error($data, string $raw): string {
    if (is_array($data) && !empty($data['message'])) {
        return (string)$data['message'];
    }
    return mb_substr($raw !== '' ? $raw : 'erro GitHub', 0, 300);
}

/**
 * Verifica se há commits novos no GitHub.
 * @return array{
 *   ok:bool,
 *   error?:string,
 *   local:array,
 *   remote_commit:string,
 *   remote_short:string,
 *   remote_message:string,
 *   remote_date:string,
 *   behind:int,
 *   ahead:int,
 *   commits:list<array{sha:string,short:string,message:string,author:string,date:string,url:string}>,
 *   html_url:string,
 *   checked_at:string,
 *   can_apply:bool,
 *   apply_reason:string
 * }
 */
function app_update_check(bool $force = false): array {
    $local = app_update_local_version();
    $repo = app_update_repo();
    $branch = app_update_branch();
    $checkedAt = date('c');
    $canApply = app_update_allow_apply() && app_update_git_available();
    $applyReason = '';
    if (!app_update_allow_apply()) {
        $applyReason = 'Aplicação pelo painel desligada (defina APP_UPDATE_ALLOW=true para habilitar git pull).';
    } elseif (!app_update_git_available()) {
        $applyReason = 'Este ambiente não tem repositório Git (.git) — use redeploy EasyPanel ou o script CLI no host.';
    } else {
        $applyReason = 'git pull disponível neste ambiente.';
    }

    // Cache 15 min (salvo se force)
    $cachePath = app_update_cache_path();
    if (!$force && is_file($cachePath)) {
        $cached = json_decode((string)file_get_contents($cachePath), true);
        if (is_array($cached) && !empty($cached['checked_at'])) {
            $ts = strtotime((string)$cached['checked_at']);
            if ($ts && (time() - $ts) < 900 && !empty($cached['ok'])) {
                $cached['local'] = $local;
                $cached['can_apply'] = $canApply;
                $cached['apply_reason'] = $applyReason;
                $cached['from_cache'] = true;
                return $cached;
            }
        }
    }

    $base = [
        'ok' => false,
        'local' => $local,
        'remote_commit' => '',
        'remote_short' => '',
        'remote_message' => '',
        'remote_date' => '',
        'behind' => 0,
        'ahead' => 0,
        'commits' => [],
        'html_url' => 'https://github.com/' . $repo . '/tree/' . rawurlencode($branch),
        'checked_at' => $checkedAt,
        'can_apply' => $canApply,
        'apply_reason' => $applyReason,
        'from_cache' => false,
    ];

    // Último commit remoto (repo no formato owner/name — não codificar a barra)
    $latest = app_update_github_get('/repos/' . $repo . '/commits/' . rawurlencode($branch));
    if (($latest['code'] ?? 0) !== 200 || !is_array($latest['data'])) {
        $base['error'] = $latest['error'] ?? ('GitHub HTTP ' . ($latest['code'] ?? 0));
        return $base;
    }

    $remoteSha = (string)($latest['data']['sha'] ?? '');
    $base['remote_commit'] = $remoteSha;
    $base['remote_short'] = substr($remoteSha, 0, 7);
    $base['remote_message'] = trim((string)($latest['data']['commit']['message'] ?? ''));
    $base['remote_date'] = (string)($latest['data']['commit']['committer']['date'] ?? $latest['data']['commit']['author']['date'] ?? '');
    $base['html_url'] = (string)($latest['data']['html_url'] ?? $base['html_url']);

    $localSha = (string)($local['commit'] ?? '');

    if ($localSha === '') {
        // Sem versão local: lista últimos commits como referência
        $list = app_update_github_get('/repos/' . $repo . '/commits?sha=' . rawurlencode($branch) . '&per_page=10');
        $base['ok'] = true;
        $base['behind'] = -1; // desconhecido
        $base['error'] = 'Versão local desconhecida (version.json ausente). Compare manualmente com o GitHub.';
        if (($list['code'] ?? 0) === 200 && is_array($list['data'])) {
            $base['commits'] = app_update_normalize_commits($list['data']);
        }
        app_update_save_cache($base);
        return $base;
    }

    if (strcasecmp($localSha, $remoteSha) === 0 || str_starts_with(strtolower($remoteSha), strtolower($localSha))) {
        $base['ok'] = true;
        $base['behind'] = 0;
        $base['ahead'] = 0;
        app_update_save_cache($base);
        return $base;
    }

    // Compare local...remote
    $cmp = app_update_github_get(
        '/repos/' . $repo . '/compare/' . rawurlencode($localSha) . '...' . rawurlencode($branch)
    );
    if (($cmp['code'] ?? 0) !== 200 || !is_array($cmp['data'])) {
        // Fallback: listar commits recentes
        $list = app_update_github_get('/repos/' . $repo . '/commits?sha=' . rawurlencode($branch) . '&per_page=15');
        $base['ok'] = true;
        $base['behind'] = 1;
        $base['error'] = 'Não foi possível comparar SHAs (versão local pode não existir no GitHub).';
        if (($list['code'] ?? 0) === 200 && is_array($list['data'])) {
            $base['commits'] = app_update_normalize_commits($list['data']);
        }
        app_update_save_cache($base);
        return $base;
    }

    $status = (string)($cmp['data']['status'] ?? '');
    $base['ok'] = true;
    $base['behind'] = (int)($cmp['data']['ahead_by'] ?? 0); // commits no remote à frente do local
    $base['ahead'] = (int)($cmp['data']['behind_by'] ?? 0);
    if ($status === 'identical') {
        $base['behind'] = 0;
        $base['ahead'] = 0;
    }
    $commits = $cmp['data']['commits'] ?? [];
    if (is_array($commits)) {
        // API devolve do mais antigo ao mais novo — inverte para UI
        $base['commits'] = app_update_normalize_commits(array_reverse($commits));
    }
    app_update_save_cache($base);
    return $base;
}

/** @param list<array> $list */
function app_update_normalize_commits(array $list): array {
    $out = [];
    foreach ($list as $c) {
        if (!is_array($c)) continue;
        $sha = (string)($c['sha'] ?? '');
        if ($sha === '') continue;
        $msg = trim((string)($c['commit']['message'] ?? ''));
        $msg = preg_split('/\r\n|\r|\n/', $msg)[0] ?? $msg;
        $out[] = [
            'sha' => $sha,
            'short' => substr($sha, 0, 7),
            'message' => $msg,
            'author' => (string)($c['commit']['author']['name'] ?? $c['author']['login'] ?? ''),
            'date' => (string)($c['commit']['author']['date'] ?? $c['commit']['committer']['date'] ?? ''),
            'url' => (string)($c['html_url'] ?? ''),
        ];
        if (count($out) >= 20) break;
    }
    return $out;
}

function app_update_save_cache(array $data): void {
    $dir = dirname(app_update_cache_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents(app_update_cache_path(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Aplica atualização via git pull (apenas se permitido e com .git).
 * @return array{ok:bool,message:string,log:string,version?:array}
 */
function app_update_apply(): array {
    if (!app_update_allow_apply()) {
        return [
            'ok' => false,
            'message' => 'Aplicação desabilitada. Defina APP_UPDATE_ALLOW=true no ambiente.',
            'log' => '',
        ];
    }
    if (!app_update_git_available()) {
        return [
            'ok' => false,
            'message' => 'Git não disponível neste ambiente (sem pasta .git ou binário git).',
            'log' => '',
        ];
    }

    $root = dirname(__DIR__);
    $branch = app_update_branch();
    $cmds = [
        'git -C ' . escapeshellarg($root) . ' fetch origin ' . escapeshellarg($branch) . ' 2>&1',
        'git -C ' . escapeshellarg($root) . ' pull --ff-only origin ' . escapeshellarg($branch) . ' 2>&1',
        'git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>&1',
        'git -C ' . escapeshellarg($root) . ' log -1 --pretty=%s 2>&1',
    ];
    $log = [];
    $sha = '';
    $msg = '';
    foreach ($cmds as $i => $cmd) {
        $out = [];
        $code = 0;
        @exec($cmd, $out, $code);
        $text = implode("\n", $out);
        $log[] = '$ ' . $cmd . "\n" . $text . ($code !== 0 ? "\n[exit $code]" : '');
        if ($code !== 0 && $i < 2) {
            return [
                'ok' => false,
                'message' => 'Falha ao atualizar via git. Veja o log.',
                'log' => implode("\n\n", $log),
            ];
        }
        if ($i === 2) $sha = trim($text);
        if ($i === 3) $msg = trim($text);
    }

    $version = [
        'commit' => $sha,
        'short' => substr($sha, 0, 7),
        'branch' => $branch,
        'repo' => app_update_repo(),
        'updated_at' => date('c'),
        'message' => $msg,
    ];
    app_update_write_version($version);
    @unlink(app_update_cache_path());

    // Re-bootstrap schema se possível
    try {
        if (function_exists('app_pdo')) {
            app_pdo();
        }
    } catch (Throwable $e) { /* ok */ }

    return [
        'ok' => true,
        'message' => 'Atualização aplicada com sucesso (' . substr($sha, 0, 7) . ').',
        'log' => implode("\n\n", $log),
        'version' => $version,
    ];
}

/** Status resumido para o hub (usa cache se houver). */
function app_update_hub_status(): string {
    $local = app_update_local_version();
    $short = $local['short'] !== '' ? $local['short'] : '—';
    $cachePath = app_update_cache_path();
    if (is_file($cachePath)) {
        $c = json_decode((string)file_get_contents($cachePath), true);
        if (is_array($c) && !empty($c['ok'])) {
            $behind = (int)($c['behind'] ?? 0);
            if ($behind > 0) {
                return $behind . ' atualização(ões) · ' . $short;
            }
            if ($behind === 0) {
                return 'Em dia · ' . $short;
            }
        }
    }
    return 'Verificar · ' . $short;
}
