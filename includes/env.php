<?php
/**
 * Lê variáveis de ambiente (EasyPanel / Docker).
 */
function app_env(string $key, $default = null) {
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }
    return $v;
}

function app_env_bool(string $key, bool $default = false): bool {
    $v = app_env($key, null);
    if ($v === null) return $default;
    $v = strtolower(trim((string)$v));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

function app_db_config_from_env(): ?array {
    $url = app_env('DATABASE_URL', '');
    if (is_string($url) && $url !== '') {
        $p = parse_url($url);
        if (!empty($p['host'])) {
            return [
                'driver' => 'pgsql',
                'host' => $p['host'],
                'port' => (string)($p['port'] ?? 5432),
                'database' => ltrim((string)($p['path'] ?? '/'), '/'),
                'username' => $p['user'] ?? '',
                'password' => $p['pass'] ?? '',
            ];
        }
    }
    $host = app_env('DB_HOST', '');
    if ($host === '') return null;
    return [
        'driver' => 'pgsql',
        'host' => $host,
        'port' => (string)app_env('DB_PORT', '5432'),
        'database' => (string)app_env('DB_DATABASE', app_env('DB_NAME', 'sucesso_radio')),
        'username' => (string)app_env('DB_USERNAME', app_env('DB_USER', 'postgres')),
        'password' => (string)app_env('DB_PASSWORD', app_env('DB_PASS', '')),
    ];
}
