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
        whatsapp VARCHAR(40) DEFAULT '',
        cidade VARCHAR(120) DEFAULT '',
        radio VARCHAR(160) DEFAULT '',
        mensagem TEXT DEFAULT '',
        lido SMALLINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW()
    )");
    // colunas extras se a tabela já existia
    try { $pdo->exec("ALTER TABLE contatos ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(40) DEFAULT ''"); } catch (Throwable $e) { /* ok */ }

    // Clientes (área restrita)
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
        id SERIAL PRIMARY KEY,
        nome VARCHAR(160) NOT NULL,
        email VARCHAR(200) NOT NULL UNIQUE,
        senha_hash TEXT NOT NULL,
        whatsapp VARCHAR(40) DEFAULT '',
        telefone VARCHAR(40) DEFAULT '',
        radio VARCHAR(160) DEFAULT '',
        cidade VARCHAR(120) DEFAULT '',
        observacoes TEXT DEFAULT '',
        acesso_total SMALLINT DEFAULT 0,
        ativo SMALLINT DEFAULT 1,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP NULL
    )");
    try { $pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS acesso_total SMALLINT DEFAULT 0"); } catch (Throwable $e) { /* ok */ }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_clientes_ativo ON clientes (ativo, nome)');

    // Textos enviados para gravação (sempre vinculados ao cliente logado)
    $pdo->exec("CREATE TABLE IF NOT EXISTS textos_gravacao (
        id SERIAL PRIMARY KEY,
        cliente_id INT NULL REFERENCES clientes(id) ON DELETE SET NULL,
        nome VARCHAR(160) DEFAULT '',
        email VARCHAR(200) DEFAULT '',
        telefone VARCHAR(40) DEFAULT '',
        whatsapp VARCHAR(40) DEFAULT '',
        titulo VARCHAR(200) DEFAULT '',
        texto TEXT DEFAULT '',
        lido SMALLINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW()
    )");
    try { $pdo->exec('ALTER TABLE textos_gravacao ADD COLUMN IF NOT EXISTS cliente_id INT NULL REFERENCES clientes(id) ON DELETE SET NULL'); } catch (Throwable $e) { /* ok */ }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_textos_cliente ON textos_gravacao (cliente_id, id DESC)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        chave VARCHAR(120) PRIMARY KEY,
        valor TEXT,
        updated_at TIMESTAMP NULL
    )");

    // Demonstrativos em áudio (MP3 etc.) — vários por conteúdo
    $pdo->exec("CREATE TABLE IF NOT EXISTS demonstrativos (
        id SERIAL PRIMARY KEY,
        tipo_conteudo VARCHAR(40) NOT NULL,
        conteudo_id INT NOT NULL,
        titulo VARCHAR(200) DEFAULT '',
        arquivo VARCHAR(500) NOT NULL,
        ordem INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW()
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_demonstrativos_conteudo ON demonstrativos (tipo_conteudo, conteudo_id, ordem, id)');

    // Conteúdos unificados (diários, semanais, informativos, programetes)
    $pdo->exec("CREATE TABLE IF NOT EXISTS conteudos (
        id SERIAL PRIMARY KEY,
        tipo VARCHAR(40) NOT NULL DEFAULT 'diario',
        titulo VARCHAR(200) NOT NULL,
        slug VARCHAR(220) NOT NULL UNIQUE,
        resumo TEXT DEFAULT '',
        descricao TEXT DEFAULT '',
        capa VARCHAR(500) DEFAULT '',
        duracao VARCHAR(80) DEFAULT '',
        blocos VARCHAR(80) DEFAULT '',
        dias VARCHAR(80) DEFAULT '',
        insercoes VARCHAR(80) DEFAULT '',
        destaque SMALLINT DEFAULT 0,
        ativo SMALLINT DEFAULT 1,
        ordem INT DEFAULT 0,
        whatsapp_msg TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP NULL
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_conteudos_tipo ON conteudos (tipo, ativo, ordem, id)');

    // Arquivos de entrega (somente área do cliente — atualizados diariamente)
    $pdo->exec("CREATE TABLE IF NOT EXISTS conteudo_entregas (
        id SERIAL PRIMARY KEY,
        conteudo_id INT NOT NULL REFERENCES conteudos(id) ON DELETE CASCADE,
        titulo VARCHAR(200) DEFAULT '',
        arquivo VARCHAR(500) NOT NULL,
        data_ref DATE NULL,
        ordem INT DEFAULT 0,
        ativo SMALLINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT NOW()
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_entregas_conteudo ON conteudo_entregas (conteudo_id, ativo, data_ref DESC, ordem, id)');

    // Conteúdos liberados por cliente
    $pdo->exec("CREATE TABLE IF NOT EXISTS cliente_conteudos (
        cliente_id INT NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
        conteudo_id INT NOT NULL REFERENCES conteudos(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT NOW(),
        PRIMARY KEY (cliente_id, conteudo_id)
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cliente_conteudos_conteudo ON cliente_conteudos (conteudo_id)');

    // seed settings
    $defaults = [
        'site_nome' => app_env('APP_NAME', 'Sucesso no Rádio'),
        'site_slogan' => 'Tudo que sua rádio precisa em um só lugar',
        'whatsapp' => '5561974002349',
        'telefone' => '',
        'email' => '',
        'sobre' => 'Conteúdo profissional para rádios e web rádios: diários, semanais, informativos e programetes.',
        'site_logo' => '',
        'site_favicon' => '',
        // Formulário de contato
        'form_contato_ativo' => '1',
        'form_contato_titulo' => 'Contato',
        'form_contato_intro' => 'Fale com a nossa equipe. Responderemos o mais breve possível.',
        'form_contato_btn' => 'Enviar mensagem',
        // Formulário de texto para gravação
        'form_texto_ativo' => '1',
        'form_texto_titulo' => 'Envio de texto para gravação',
        'form_texto_intro' => 'Envie o texto que deseja gravar. Nossa equipe receberá e entrará em contato.',
        'form_texto_btn' => 'Enviar texto',
        'form_texto_instrucoes' => 'Cole o texto completo abaixo. Se preferir, indique o título ou o programa ao qual se refere.',
        'db_version' => '6',
    ];
    $st = $pdo->prepare(
        "INSERT INTO site_settings (chave, valor, updated_at) VALUES (?, ?, NOW())
         ON CONFLICT (chave) DO NOTHING"
    );
    foreach ($defaults as $k => $v) {
        if ($k === 'db_version') {
            $pdo->prepare(
                "INSERT INTO configuracoes (chave, valor, updated_at) VALUES ('db_version', ?, NOW())
                 ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = NOW()"
            )->execute(['6']);
            continue;
        }
        $st->execute([$k, $v]);
    }

    // categorias padrão (legado / opcional)
    $cats = [
        ['Diários', 'diarios', 'diario', 1],
        ['Semanais', 'semanais', 'semanal', 2],
        ['Informativos', 'informativos', 'informativo', 3],
        ['Programetes', 'programetes', 'programete', 4],
    ];
    $stc = $pdo->prepare(
        "INSERT INTO categorias (nome, slug, tipo, ordem, ativo, created_at)
         VALUES (?, ?, ?, ?, 1, NOW())
         ON CONFLICT (slug) DO NOTHING"
    );
    foreach ($cats as $c) {
        $stc->execute($c);
    }

    app_migrate_to_conteudos($pdo);
}

/** Tipos de conteúdo do sistema (chave => meta). */
function app_conteudo_tipos(): array {
    return [
        'diario' => [
            'label' => 'Diários',
            'icon' => '📅',
            'desc' => 'Programas e conteúdos da grade diária',
            'dias_default' => 'SEG A SAB',
        ],
        'semanal' => [
            'label' => 'Semanais',
            'icon' => '🗓',
            'desc' => 'Conteúdos semanais e de fim de semana',
            'dias_default' => 'SÁB E DOM',
        ],
        'informativo' => [
            'label' => 'Informativos',
            'icon' => '📰',
            'desc' => 'Jornalismo, boletins e notícias',
            'dias_default' => 'SEG A SEX',
        ],
        'programete' => [
            'label' => 'Programetes',
            'icon' => '⚡',
            'desc' => 'Inserções rápidas, dicas e vinhetas',
            'dias_default' => '',
        ],
    ];
}

function app_conteudo_tipo_valido(string $tipo): bool {
    return array_key_exists($tipo, app_conteudo_tipos());
}

/** Migra programas + programetes → conteudos (uma vez). */
function app_migrate_to_conteudos(PDO $pdo): void {
    try {
        $done = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'conteudos_migrated'")->fetchColumn();
        if ($done === '1') {
            return;
        }
    } catch (Throwable $e) {
        // segue
    }

    if (!app_table_exists($pdo, 'conteudos')) {
        return;
    }

    $count = (int)$pdo->query('SELECT COUNT(*) FROM conteudos')->fetchColumn();
    if ($count > 0) {
        $pdo->prepare(
            "INSERT INTO configuracoes (chave, valor, updated_at) VALUES ('conteudos_migrated', '1', NOW())
             ON CONFLICT (chave) DO UPDATE SET valor = '1', updated_at = NOW()"
        )->execute();
        return;
    }

    // --- Programas → conteudos (preserva IDs) ---
    if (app_table_exists($pdo, 'programas')) {
        $rows = $pdo->query('SELECT * FROM programas ORDER BY id')->fetchAll();
        $ins = $pdo->prepare(
            'INSERT INTO conteudos
             (id, tipo, titulo, slug, resumo, descricao, capa, duracao, blocos, dias, insercoes, destaque, ativo, ordem, whatsapp_msg, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON CONFLICT (id) DO NOTHING'
        );
        foreach ($rows as $r) {
            $tipo = app_map_periodo_to_tipo((string)($r['periodo'] ?? 'diario'), intval($r['categoria_id'] ?? 0), $pdo);
            $ins->execute([
                intval($r['id']),
                $tipo,
                $r['titulo'],
                $r['slug'] ?: app_slug((string)$r['titulo']),
                $r['resumo'] ?? '',
                $r['descricao'] ?? '',
                $r['capa'] ?? '',
                $r['duracao'] ?? '',
                $r['blocos'] ?? '',
                $r['dias'] ?? '',
                '',
                intval($r['destaque'] ?? 0),
                intval($r['ativo'] ?? 1),
                intval($r['ordem'] ?? 0),
                $r['whatsapp_msg'] ?? '',
                $r['created_at'] ?? date('Y-m-d H:i:s'),
                $r['updated_at'] ?? null,
            ]);
        }
        // demos de programa → conteudo
        try {
            $pdo->exec("UPDATE demonstrativos SET tipo_conteudo = 'conteudo' WHERE tipo_conteudo = 'programa'");
        } catch (Throwable $e) { /* ok */ }
    }

    // Ajusta sequence
    try {
        $pdo->exec("SELECT setval(pg_get_serial_sequence('conteudos','id'), COALESCE((SELECT MAX(id) FROM conteudos), 1))");
    } catch (Throwable $e) { /* ok */ }

    // --- Programetes → conteudos (novos IDs) ---
    if (app_table_exists($pdo, 'programetes')) {
        $rows = $pdo->query('SELECT * FROM programetes ORDER BY id')->fetchAll();
        $ins = $pdo->prepare(
            'INSERT INTO conteudos
             (tipo, titulo, slug, resumo, descricao, capa, duracao, blocos, dias, insercoes, destaque, ativo, ordem, whatsapp_msg, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             RETURNING id'
        );
        $upDemo = $pdo->prepare(
            "UPDATE demonstrativos SET tipo_conteudo = 'conteudo', conteudo_id = ? WHERE tipo_conteudo = 'programete' AND conteudo_id = ?"
        );
        $usedSlugs = [];
        foreach ($pdo->query('SELECT slug FROM conteudos')->fetchAll() as $s) {
            $usedSlugs[$s['slug']] = true;
        }
        foreach ($rows as $r) {
            $baseSlug = app_slug((string)$r['titulo']);
            $slug = $baseSlug;
            $n = 2;
            while (isset($usedSlugs[$slug])) {
                $slug = $baseSlug . '-' . $n;
                $n++;
            }
            $usedSlugs[$slug] = true;
            $ins->execute([
                'programete',
                $r['titulo'],
                $slug,
                '',
                $r['descricao'] ?? '',
                '',
                '',
                '',
                '',
                $r['insercoes'] ?? '1x/dia',
                0,
                intval($r['ativo'] ?? 1),
                intval($r['ordem'] ?? 0),
                '',
                $r['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
            $newId = intval($ins->fetchColumn());
            if ($newId > 0) {
                $upDemo->execute([$newId, intval($r['id'])]);
            }
        }
    }

    $pdo->prepare(
        "INSERT INTO configuracoes (chave, valor, updated_at) VALUES ('conteudos_migrated', '1', NOW())
         ON CONFLICT (chave) DO UPDATE SET valor = '1', updated_at = NOW()"
    )->execute();
}

function app_map_periodo_to_tipo(string $periodo, int $categoriaId, PDO $pdo): string {
    $periodo = strtolower(trim($periodo));
    $map = [
        'diario' => 'diario',
        'fim_semana' => 'semanal',
        'semanal' => 'semanal',
        'jornalismo' => 'informativo',
        'informativo' => 'informativo',
        'programete' => 'programete',
        'campanha' => 'diario',
    ];
    if (isset($map[$periodo])) {
        return $map[$periodo];
    }
    if ($categoriaId > 0) {
        try {
            $st = $pdo->prepare('SELECT slug, tipo FROM categorias WHERE id = ?');
            $st->execute([$categoriaId]);
            $cat = $st->fetch();
            if ($cat) {
                $slug = (string)($cat['slug'] ?? '');
                if (in_array($slug, ['programas-diarios', 'diarios'], true)) return 'diario';
                if (in_array($slug, ['fim-de-semana', 'semanais'], true)) return 'semanal';
                if (in_array($slug, ['jornalismo', 'informativos'], true)) return 'informativo';
                if ($slug === 'programetes') return 'programete';
                $t = (string)($cat['tipo'] ?? '');
                if (app_conteudo_tipo_valido($t)) return $t;
            }
        } catch (Throwable $e) { /* ok */ }
    }
    return 'diario';
}

function app_conteudos_por_tipo(string $tipo, bool $somenteAtivos = true): array {
    if (!app_conteudo_tipo_valido($tipo)) return [];
    try {
        $sql = 'SELECT * FROM conteudos WHERE tipo = ?';
        if ($somenteAtivos) $sql .= ' AND ativo = 1';
        $sql .= ' ORDER BY destaque DESC, ordem ASC, titulo ASC';
        $st = app_pdo()->prepare($sql);
        $st->execute([$tipo]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** IDs de conteúdos liberados para o cliente (vazio se acesso_total). */
function cliente_conteudo_ids(int $clienteId): array {
    if ($clienteId <= 0) return [];
    try {
        $st = app_pdo()->prepare('SELECT conteudo_id FROM cliente_conteudos WHERE cliente_id = ?');
        $st->execute([$clienteId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

function cliente_tem_acesso_total(?array $cli = null): bool {
    if ($cli === null) $cli = cliente_atual();
    if (!$cli) return false;
    return !empty($cli['acesso_total']);
}

function cliente_pode_acessar_conteudo(int $conteudoId, ?array $cli = null): bool {
    if ($conteudoId <= 0) return false;
    if ($cli === null) $cli = cliente_atual();
    if (!$cli) return false;
    if (!empty($cli['acesso_total'])) return true;
    $ids = cliente_conteudo_ids(intval($cli['id']));
    return in_array($conteudoId, $ids, true);
}

/** Conteúdos liberados para o cliente, por tipo. */
function cliente_conteudos_por_tipo(int $clienteId, string $tipo, ?array $cli = null): array {
    if (!app_conteudo_tipo_valido($tipo) || $clienteId <= 0) return [];
    if ($cli === null) {
        try {
            $st = app_pdo()->prepare('SELECT * FROM clientes WHERE id = ? LIMIT 1');
            $st->execute([$clienteId]);
            $cli = $st->fetch() ?: null;
        } catch (Throwable $e) {
            return [];
        }
    }
    if (!$cli) return [];
    if (!empty($cli['acesso_total'])) {
        return app_conteudos_por_tipo($tipo, true);
    }
    try {
        $st = app_pdo()->prepare(
            'SELECT c.* FROM conteudos c
             INNER JOIN cliente_conteudos cc ON cc.conteudo_id = c.id AND cc.cliente_id = ?
             WHERE c.tipo = ? AND c.ativo = 1
             ORDER BY c.destaque DESC, c.ordem ASC, c.titulo ASC'
        );
        $st->execute([$clienteId, $tipo]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function cliente_salvar_liberacoes(int $clienteId, array $conteudoIds, bool $acessoTotal): void {
    $pdo = app_pdo();
    $pdo->prepare('UPDATE clientes SET acesso_total = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$acessoTotal ? 1 : 0, $clienteId]);
    $pdo->prepare('DELETE FROM cliente_conteudos WHERE cliente_id = ?')->execute([$clienteId]);
    if ($acessoTotal) return;
    $ins = $pdo->prepare('INSERT INTO cliente_conteudos (cliente_id, conteudo_id, created_at) VALUES (?,?,NOW()) ON CONFLICT DO NOTHING');
    foreach ($conteudoIds as $cid) {
        $cid = intval($cid);
        if ($cid > 0) $ins->execute([$clienteId, $cid]);
    }
}

function app_conteudo_by_slug(string $slug): ?array {
    if ($slug === '') return null;
    try {
        $st = app_pdo()->prepare('SELECT * FROM conteudos WHERE slug = ? AND ativo = 1 LIMIT 1');
        $st->execute([$slug]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function app_conteudo_by_id(int $id, bool $somenteAtivos = true): ?array {
    if ($id <= 0) return null;
    try {
        $sql = 'SELECT * FROM conteudos WHERE id = ?';
        if ($somenteAtivos) $sql .= ' AND ativo = 1';
        $sql .= ' LIMIT 1';
        $st = app_pdo()->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Arquivos de entrega (área do cliente). */
function app_entregas(int $conteudoId, bool $somenteAtivos = true): array {
    if ($conteudoId <= 0) return [];
    try {
        $sql = 'SELECT * FROM conteudo_entregas WHERE conteudo_id = ?';
        if ($somenteAtivos) $sql .= ' AND ativo = 1';
        $sql .= ' ORDER BY data_ref DESC NULLS LAST, ordem ASC, id DESC';
        $st = app_pdo()->prepare($sql);
        $st->execute([$conteudoId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function app_entrega_by_id(int $id): ?array {
    if ($id <= 0) return null;
    try {
        $st = app_pdo()->prepare('SELECT * FROM conteudo_entregas WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function app_delete_entrega(int $id): bool {
    if ($id <= 0) return false;
    try {
        $pdo = app_pdo();
        $st = $pdo->prepare('SELECT arquivo FROM conteudo_entregas WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) return false;
        $pdo->prepare('DELETE FROM conteudo_entregas WHERE id = ?')->execute([$id]);
        $path = dirname(__DIR__) . '/' . ltrim((string)$row['arquivo'], '/');
        if (is_file($path)) @unlink($path);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// ---------- Auth cliente ----------

function cliente_session_start(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function cliente_logado(): bool {
    cliente_session_start();
    return !empty($_SESSION['cliente_logado']) && !empty($_SESSION['cliente_id']);
}

function cliente_id(): int {
    return cliente_logado() ? intval($_SESSION['cliente_id']) : 0;
}

function cliente_atual(): ?array {
    $id = cliente_id();
    if ($id <= 0) return null;
    try {
        $st = app_pdo()->prepare('SELECT * FROM clientes WHERE id = ? AND ativo = 1 LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            cliente_logout(false);
            return null;
        }
        return $row;
    } catch (Throwable $e) {
        return null;
    }
}

function cliente_require_auth(string $redirectAfter = ''): void {
    if (cliente_logado() && cliente_atual()) {
        return;
    }
    $login = app_url('cliente/login.php');
    if ($redirectAfter !== '') {
        $login .= '?redirect=' . rawurlencode($redirectAfter);
    }
    header('Location: ' . $login);
    exit;
}

function cliente_login_ok(array $cli): void {
    cliente_session_start();
    session_regenerate_id(true);
    $_SESSION['cliente_logado'] = true;
    $_SESSION['cliente_id'] = intval($cli['id']);
    $_SESSION['cliente_nome'] = (string)($cli['nome'] ?? '');
    $_SESSION['cliente_email'] = (string)($cli['email'] ?? '');
    try {
        app_pdo()->prepare('UPDATE clientes SET last_login = NOW() WHERE id = ?')->execute([intval($cli['id'])]);
    } catch (Throwable $e) { /* ok */ }
}

function cliente_logout(bool $redirect = true): void {
    cliente_session_start();
    unset($_SESSION['cliente_logado'], $_SESSION['cliente_id'], $_SESSION['cliente_nome'], $_SESSION['cliente_email']);
    if ($redirect) {
        header('Location: ' . app_url('cliente/login.php'));
        exit;
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

function app_setting_set(string $chave, string $valor): void {
    $st = app_pdo()->prepare(
        "INSERT INTO site_settings (chave, valor, updated_at) VALUES (?, ?, NOW())
         ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = NOW()"
    );
    $st->execute([$chave, $valor]);
}

/** Seções do hub de Configurações. */
function app_config_secoes(): array {
    return [
        'site' => [
            'label' => 'Configurações do site',
            'icon' => '🌐',
            'desc' => 'Nome, slogan, logo, favicon, WhatsApp e textos gerais',
        ],
        'formulario_contato' => [
            'label' => 'Formulário de contato',
            'icon' => '✉️',
            'desc' => 'Formulário padrão: nome, e-mail, telefone, WhatsApp e mensagem',
        ],
        'formulario_texto' => [
            'label' => 'Envio de texto',
            'icon' => '🎙️',
            'desc' => 'Formulário para envio de texto que será gravado',
        ],
    ];
}

function app_slug(string $text): string {
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $t = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string)$t) ?? '');
    return trim($t, '-') ?: 'item';
}

/**
 * Caminho base da aplicação (sem barra final).
 * Ex.: '' na raiz, '/app' se o site estiver em subpasta.
 * Páginas em /admin, /api ou /cliente sobem um nível para a raiz do app.
 */
function app_base_path(): string {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($script);
    // Normaliza: /foo/cliente/login.php → /foo/cliente → sobe para /foo
    $dir = rtrim($dir, '/');
    if ($dir === '' || $dir === '.' || $dir === '\\') {
        return '';
    }
    // Pastas internas do app: não fazem parte do base path público
    while (preg_match('#/(admin|api|cliente)$#', $dir)) {
        $dir = dirname($dir);
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        if ($dir === '' || $dir === '.' || $dir === '/' || $dir === '\\') {
            return '';
        }
    }
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }
    return $dir;
}

/** URL relativa à raiz do app. Sempre aponta para o arquivo correto. */
function app_url(string $path = ''): string {
    $base = app_base_path();
    $path = ltrim($path, '/');
    return ($base === '' ? '' : $base) . ($path !== '' ? '/' . $path : '/');
}

/** URL da home da área do cliente (index.php explícito). */
function cliente_home_url(): string {
    return app_url('cliente/index.php');
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Lista demonstrativos de um conteúdo (programa | programete). */
function app_demonstrativos(string $tipo, int $conteudoId): array {
    if ($conteudoId <= 0) return [];
    try {
        $st = app_pdo()->prepare(
            'SELECT * FROM demonstrativos
             WHERE tipo_conteudo = ? AND conteudo_id = ?
             ORDER BY ordem ASC, id ASC'
        );
        $st->execute([$tipo, $conteudoId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function app_delete_demonstrativo(int $id): bool {
    if ($id <= 0) return false;
    try {
        $pdo = app_pdo();
        $st = $pdo->prepare('SELECT arquivo FROM demonstrativos WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) return false;
        $pdo->prepare('DELETE FROM demonstrativos WHERE id = ?')->execute([$id]);
        $path = dirname(__DIR__) . '/' . ltrim((string)$row['arquivo'], '/');
        if (is_file($path)) @unlink($path);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
