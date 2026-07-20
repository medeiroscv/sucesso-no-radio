<?php
require_once __DIR__ . '/env.php';

function app_db_config_path(): string {
    return __DIR__ . '/../config/database.php';
}

function app_is_installed(): bool {
    if (app_db_config_from_env() !== null) return true;
    return file_exists(app_db_config_path());
}

function app_db_config(): array {
    $fromEnv = app_db_config_from_env();
    if ($fromEnv !== null) return $fromEnv;
    if (!file_exists(app_db_config_path())) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Sistema nao configurado. Defina DATABASE_URL ou DB_* no EasyPanel.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $cfg = require app_db_config_path();
    return is_array($cfg) ? $cfg : [];
}

function app_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $c = app_db_config();
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $c['host'], $c['port'], $c['database']);
    $pdo = new PDO($dsn, $c['username'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $tz = app_env('APP_TIMEZONE', 'America/Sao_Paulo');
    try {
        $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
    } catch (Throwable $e) { /* ok */ }
    date_default_timezone_set($tz);
    // auto-migrate em requests web (leve)
    if (PHP_SAPI !== 'cli') {
        try { app_bootstrap_database($pdo); } catch (Throwable $e) { /* bootstrap no entrypoint */ }
    }
    return $pdo;
}

function app_now(): string {
    return date('Y-m-d H:i:s');
}

function app_json_encode($data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function app_json_decode($json, $default = []) {
    if (!is_string($json) || $json === '') return $default;
    $d = json_decode($json, true);
    return is_array($d) ? $d : $default;
}

function app_input_json(): array {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw ?: '', true);
    return is_array($d) ? $d : [];
}

function app_db_version(): int {
    try {
        $v = app_pdo()->query("SELECT valor FROM configuracoes WHERE chave = 'db_version'")->fetchColumn();
        return intval($v ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function app_table_exists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name = ?");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}

function app_bootstrap_database(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
        chave VARCHAR(120) PRIMARY KEY,
        valor TEXT,
        updated_at TIMESTAMP NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id SERIAL PRIMARY KEY,
        usuario VARCHAR(80) NOT NULL UNIQUE,
        senha_hash TEXT NOT NULL,
        nome VARCHAR(160) DEFAULT '',
        ativo SMALLINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categorias (
        id SERIAL PRIMARY KEY,
        nome VARCHAR(160) NOT NULL,
        slug VARCHAR(180) NOT NULL UNIQUE,
        tipo VARCHAR(40) DEFAULT 'programa',
        ordem INT DEFAULT 0,
        ativo SMALLINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT NOW()
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS programas (
        id SERIAL PRIMARY KEY,
        categoria_id INT NULL REFERENCES categorias(id) ON DELETE SET NULL,
        titulo VARCHAR(200) NOT NULL,
        slug VARCHAR(220) NOT NULL UNIQUE,
        resumo TEXT DEFAULT '',
        descricao TEXT DEFAULT '',
        capa VARCHAR(500) DEFAULT '',
        duracao VARCHAR(80) DEFAULT '',
        blocos VARCHAR(80) DEFAULT '',
        dias VARCHAR(80) DEFAULT 'SEG A SAB',
        periodo VARCHAR(40) DEFAULT 'diario',
        destaque SMALLINT DEFAULT 0,
        ativo SMALLINT DEFAULT 1,
        ordem INT DEFAULT 0,
        whatsapp_msg TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS banners (
        id SERIAL PRIMARY KEY,
        titulo VARCHAR(200) DEFAULT '',
        subtitulo TEXT DEFAULT '',
        imagem VARCHAR(500) DEFAULT '',
        link VARCHAR(500) DEFAULT '',
        botao_texto VARCHAR(80) DEFAULT 'Saiba mais',
        ativo SMALLINT DEFAULT 1,
        ordem INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW()
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS programetes (
        id SERIAL PRIMARY KEY,
        titulo VARCHAR(200) NOT NULL,
        descricao TEXT DEFAULT '',
        insercoes VARCHAR(80) DEFAULT '1x/dia',
        ativo SMALLINT DEFAULT 1,
        ordem INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW()
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS contatos (
        id SERIAL PRIMARY KEY,
        nome VARCHAR(160) DEFAULT '',
        email VARCHAR(200) DEFAULT '',
        telefone VARCHAR(40) DEFAULT '',
        cidade VARCHAR(120) DEFAULT '',
        radio VARCHAR(160) DEFAULT '',
        mensagem TEXT DEFAULT '',
        lido SMALLINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW()
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        chave VARCHAR(120) PRIMARY KEY,
        valor TEXT,
        updated_at TIMESTAMP NULL
    )");

    // seed settings
    $defaults = [
        'site_nome' => app_env('APP_NAME', 'Sucesso no Rádio'),
        'site_slogan' => 'Tudo que sua rádio precisa em um só lugar',
        'whatsapp' => '5561974002349',
        'telefone' => '',
        'email' => '',
        'sobre' => 'Conteúdo profissional para rádios e web rádios: programas, programetes e jornalismo.',
        'db_version' => '1',
    ];
    $st = $pdo->prepare(
        "INSERT INTO site_settings (chave, valor, updated_at) VALUES (?, ?, NOW())
         ON CONFLICT (chave) DO NOTHING"
    );
    foreach ($defaults as $k => $v) {
        if ($k === 'db_version') {
            $pdo->prepare(
                "INSERT INTO configuracoes (chave, valor, updated_at) VALUES ('db_version', ?, NOW())
                 ON CONFLICT (chave) DO NOTHING"
            )->execute(['1']);
            continue;
        }
        $st->execute([$k, $v]);
    }

    // categorias padrão
    $cats = [
        ['Programas Diários', 'programas-diarios', 'programa', 1],
        ['Fim de Semana', 'fim-de-semana', 'programa', 2],
        ['Jornalismo', 'jornalismo', 'jornalismo', 3],
        ['Programetes', 'programetes', 'programete', 4],
        ['Campanhas', 'campanhas', 'campanha', 5],
    ];
    $stc = $pdo->prepare(
        "INSERT INTO categorias (nome, slug, tipo, ordem, ativo, created_at)
         VALUES (?, ?, ?, ?, 1, NOW())
         ON CONFLICT (slug) DO NOTHING"
    );
    foreach ($cats as $c) {
        $stc->execute($c);
    }
}

function app_require_auth(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['admin_logado'])) {
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Nao autenticado'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: login.php');
        exit;
    }
}

function app_setting(string $chave, string $default = ''): string {
    try {
        $st = app_pdo()->prepare('SELECT valor FROM site_settings WHERE chave = ?');
        $st->execute([$chave]);
        $v = $st->fetchColumn();
        return $v !== false && $v !== null ? (string)$v : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function app_slug(string $text): string {
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $t = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string)$t) ?? '');
    return trim($t, '-') ?: 'item';
}

function app_base_path(): string {
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // se estiver em /admin ou /api, sobe um nível
    if (str_ends_with($script, '/admin') || str_ends_with($script, '/api')) {
        $script = dirname($script);
    }
    if ($script === '/' || $script === '\\' || $script === '.') return '';
    return rtrim($script, '/');
}

function app_url(string $path = ''): string {
    $base = app_base_path();
    $path = ltrim($path, '/');
    return ($base === '' ? '' : $base) . ($path !== '' ? '/' . $path : '/');
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
