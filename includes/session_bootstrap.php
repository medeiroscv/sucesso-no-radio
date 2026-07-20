<?php
if (PHP_SAPI === 'cli') {
    return;
}
$sessionDir = dirname(__DIR__) . '/data/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0775, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($secure) ini_set('session.cookie_secure', '1');
    ini_set('session.gc_maxlifetime', (string)(14 * 24 * 3600));
    session_set_cookie_params([
        'lifetime' => 14 * 24 * 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
