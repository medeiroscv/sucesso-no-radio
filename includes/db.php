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
    try { $pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS cpf VARCHAR(14) DEFAULT ''"); } catch (Throwable $e) { /* ok */ }
    try { $pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS asaas_customer_id VARCHAR(60) DEFAULT ''"); } catch (Throwable $e) { /* ok */ }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_clientes_ativo ON clientes (ativo, nome)');

    // Faturas / área financeira (Asaas Pix + boleto)
    // pix_txid guarda o payment id Asaas (pay_xxx); boleto_charge_id idem
    $pdo->exec("CREATE TABLE IF NOT EXISTS faturas (
        id SERIAL PRIMARY KEY,
        cliente_id INT NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
        descricao VARCHAR(255) DEFAULT 'Mensalidade',
        valor_centavos INT NOT NULL DEFAULT 0,
        vencimento DATE NOT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'aberta',
        pix_txid VARCHAR(80) DEFAULT '',
        pix_loc_id VARCHAR(80) DEFAULT '',
        pix_qrcode TEXT DEFAULT '',
        pix_copia_cola TEXT DEFAULT '',
        pix_expira_em TIMESTAMP NULL,
        boleto_charge_id VARCHAR(80) DEFAULT '',
        boleto_url TEXT DEFAULT '',
        boleto_barcode VARCHAR(120) DEFAULT '',
        boleto_pdf TEXT DEFAULT '',
        pago_em TIMESTAMP NULL,
        observacao TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP NULL
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_faturas_cliente ON faturas (cliente_id, status, vencimento DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_faturas_pix_txid ON faturas (pix_txid)');
    try { $pdo->exec('CREATE INDEX IF NOT EXISTS idx_faturas_boleto_charge ON faturas (boleto_charge_id)'); } catch (Throwable $e) { /* ok */ }

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
        status VARCHAR(40) DEFAULT 'pendente',
        observacao_admin TEXT DEFAULT '',
        audio_arquivo VARCHAR(500) DEFAULT '',
        lido SMALLINT DEFAULT 0,
        lido_cliente SMALLINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP NULL
    )");
    try { $pdo->exec('ALTER TABLE textos_gravacao ADD COLUMN IF NOT EXISTS cliente_id INT NULL REFERENCES clientes(id) ON DELETE SET NULL'); } catch (Throwable $e) { /* ok */ }
    try { $pdo->exec("ALTER TABLE textos_gravacao ADD COLUMN IF NOT EXISTS status VARCHAR(40) DEFAULT 'pendente'"); } catch (Throwable $e) { /* ok */ }
    try { $pdo->exec("ALTER TABLE textos_gravacao ADD COLUMN IF NOT EXISTS observacao_admin TEXT DEFAULT ''"); } catch (Throwable $e) { /* ok */ }
    try { $pdo->exec("ALTER TABLE textos_gravacao ADD COLUMN IF NOT EXISTS audio_arquivo VARCHAR(500) DEFAULT ''"); } catch (Throwable $e) { /* ok */ }
    try { $pdo->exec("ALTER TABLE textos_gravacao ADD COLUMN IF NOT EXISTS lido_cliente SMALLINT DEFAULT 0"); } catch (Throwable $e) { /* ok */ }
    try { $pdo->exec("ALTER TABLE textos_gravacao ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL"); } catch (Throwable $e) { /* ok */ }
    $pdo->exec("UPDATE textos_gravacao SET status = 'pendente' WHERE status IS NULL OR status = ''");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_textos_cliente ON textos_gravacao (cliente_id, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_textos_status ON textos_gravacao (status, id DESC)');

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

    // Catálogo: demonstrativos (site público) e conteúdos (área do cliente)
    $pdo->exec("CREATE TABLE IF NOT EXISTS conteudos (
        id SERIAL PRIMARY KEY,
        area VARCHAR(40) NOT NULL DEFAULT 'demonstrativo',
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
    try { $pdo->exec("ALTER TABLE conteudos ADD COLUMN IF NOT EXISTS area VARCHAR(40) NOT NULL DEFAULT 'demonstrativo'"); } catch (Throwable $e) { /* ok */ }
    $pdo->exec("UPDATE conteudos SET area = 'demonstrativo' WHERE area IS NULL OR area = ''");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_conteudos_tipo ON conteudos (area, tipo, ativo, ordem, id)');

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

    // Liberação por CATEGORIA (tipo) para o cliente — não por item
    $pdo->exec("CREATE TABLE IF NOT EXISTS cliente_tipos (
        cliente_id INT NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
        tipo VARCHAR(40) NOT NULL,
        created_at TIMESTAMP DEFAULT NOW(),
        PRIMARY KEY (cliente_id, tipo)
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cliente_tipos_tipo ON cliente_tipos (tipo)');
    // legado: tabela antiga por item (mantida se existir; não é mais usada)

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
        // Financeiro / Asaas
        'finance_ativo' => '0',
        'finance_bloquear_atraso' => '1',
        'asaas_sandbox' => '1',
        'db_version' => '10',
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
            )->execute(['10']);
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

/** Tipos na área do cliente (produtos comprados). */
function app_conteudo_tipos_cliente(): array {
    // Mesmos tipos do catálogo: diários, semanais, informativos e programetes
    return app_conteudo_tipos();
}

function app_conteudo_tipo_valido(string $tipo): bool {
    return array_key_exists($tipo, app_conteudo_tipos());
}

/** Área do catálogo: demonstrativo (site público) | conteudo (cliente logado). */
function app_catalogo_area_valida(string $area): bool {
    return in_array($area, ['demonstrativo', 'conteudo'], true);
}

function app_catalogo_area_meta(string $area): array {
    return match ($area) {
        'demonstrativo' => [
            'label' => 'Demonstrativos',
            'singular' => 'Demonstrativo',
            'desc' => 'Amostras exibidas na página inicial do site (público).',
            'file' => 'demonstrativos.php',
            'active' => 'demonstrativos',
        ],
        default => [
            'label' => 'Conteúdos',
            'singular' => 'Conteúdo',
            'desc' => 'Programas liberados para clientes que compraram o produto (login + liberação manual).',
            'file' => 'conteudos.php',
            'active' => 'conteudos',
        ],
    };
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

function app_conteudos_por_tipo(string $tipo, bool $somenteAtivos = true, string $area = 'demonstrativo'): array {
    if (!app_conteudo_tipo_valido($tipo)) return [];
    if (!app_catalogo_area_valida($area)) $area = 'demonstrativo';
    try {
        $sql = 'SELECT * FROM conteudos WHERE tipo = ? AND area = ?';
        if ($somenteAtivos) $sql .= ' AND ativo = 1';
        $sql .= ' ORDER BY destaque DESC, ordem ASC, titulo ASC';
        $st = app_pdo()->prepare($sql);
        $st->execute([$tipo, $area]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** Categorias (tipos) liberadas para o cliente. */
function cliente_tipos_liberados(int $clienteId): array {
    if ($clienteId <= 0) return [];
    try {
        $st = app_pdo()->prepare('SELECT tipo FROM cliente_tipos WHERE cliente_id = ?');
        $st->execute([$clienteId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $t) {
            $t = (string)$t;
            if (app_conteudo_tipo_valido($t)) $out[] = $t;
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function cliente_tem_acesso_total(?array $cli = null): bool {
    if ($cli === null) $cli = cliente_atual();
    if (!$cli) return false;
    return !empty($cli['acesso_total']);
}

/**
 * Cliente ativo + liberação de ao menos uma CATEGORIA (ou acesso total).
 * Só "ativo" no cadastro NÃO libera nada.
 */
function cliente_tem_liberacao(?array $cli = null): bool {
    if ($cli === null) $cli = cliente_atual();
    if (!$cli || empty($cli['ativo'])) return false;
    if (cliente_tem_acesso_total($cli)) return true;
    return count(cliente_tipos_liberados(intval($cli['id']))) > 0;
}

/** Cliente pode acessar arquivos/detalhes de uma categoria (tipo). */
function cliente_pode_acessar_tipo(string $tipo, ?array $cli = null): bool {
    if (!app_conteudo_tipo_valido($tipo)) return false;
    if ($cli === null) $cli = cliente_atual();
    if (!$cli || !cliente_tem_liberacao($cli)) return false;
    if (cliente_tem_acesso_total($cli)) return true;
    return in_array($tipo, cliente_tipos_liberados(intval($cli['id'])), true);
}

function cliente_pode_acessar_conteudo(int $conteudoId, ?array $cli = null): bool {
    if ($conteudoId <= 0) return false;
    if ($cli === null) $cli = cliente_atual();
    if (!$cli || !cliente_tem_liberacao($cli)) return false;
    try {
        $st = app_pdo()->prepare('SELECT id, area, tipo FROM conteudos WHERE id = ? AND ativo = 1 LIMIT 1');
        $st->execute([$conteudoId]);
        $c = $st->fetch();
        if (!$c || ($c['area'] ?? '') !== 'conteudo') return false;
        return cliente_pode_acessar_tipo((string)$c['tipo'], $cli);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Lista itens de um tipo (área conteudo).
 * Sempre lista os nomes se o cliente tem alguma liberação.
 * Arquivos/detalhe só se a CATEGORIA estiver liberada.
 */
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
    if (!$cli || !cliente_tem_liberacao($cli)) return [];
    // Catálogo da categoria (nomes) — acesso a arquivos é checado à parte
    return app_conteudos_por_tipo($tipo, true, 'conteudo');
}

/** Salva liberação por CATEGORIA (tipos). */
function cliente_salvar_liberacoes(int $clienteId, array $tipos, bool $acessoTotal): void {
    $pdo = app_pdo();
    $pdo->prepare('UPDATE clientes SET acesso_total = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$acessoTotal ? 1 : 0, $clienteId]);
    $pdo->prepare('DELETE FROM cliente_tipos WHERE cliente_id = ?')->execute([$clienteId]);
    if ($acessoTotal) return;
    $ins = $pdo->prepare(
        'INSERT INTO cliente_tipos (cliente_id, tipo, created_at) VALUES (?,?,NOW()) ON CONFLICT DO NOTHING'
    );
    foreach ($tipos as $tipo) {
        $tipo = trim((string)$tipo);
        if (app_conteudo_tipo_valido($tipo)) {
            $ins->execute([$clienteId, $tipo]);
        }
    }
}

/** Exige cliente logado E com liberação de conteúdos. */
function cliente_require_liberacao(string $redirectLogin = ''): void {
    cliente_require_auth($redirectLogin);
    $cli = cliente_atual();
    if (!cliente_tem_liberacao($cli)) {
        header('Location: ' . cliente_home_url() . '?bloqueado=1');
        exit;
    }
    if (!cliente_financeiro_em_dia($cli)) {
        header('Location: ' . app_url('cliente/financeiro.php') . '?atraso=1');
        exit;
    }
}

function app_finance_ativo(): bool {
    return app_setting('finance_ativo', '0') === '1';
}

function app_finance_bloquear_atraso(): bool {
    return app_setting('finance_bloquear_atraso', '1') === '1';
}

/** Sem faturas vencidas em aberto (quando financeiro ativo e bloqueio ligado). */
function cliente_financeiro_em_dia(?array $cli = null): bool {
    if (!app_finance_ativo() || !app_finance_bloquear_atraso()) {
        return true;
    }
    if ($cli === null) $cli = cliente_atual();
    if (!$cli) return false;
    try {
        // marca vencidas
        app_pdo()->prepare(
            "UPDATE faturas SET status = 'vencida', updated_at = NOW()
             WHERE cliente_id = ? AND status = 'aberta' AND vencimento < CURRENT_DATE"
        )->execute([intval($cli['id'])]);

        $st = app_pdo()->prepare(
            "SELECT COUNT(*) FROM faturas
             WHERE cliente_id = ? AND status IN ('aberta','vencida') AND vencimento < CURRENT_DATE"
        );
        $st->execute([intval($cli['id'])]);
        return intval($st->fetchColumn()) === 0;
    } catch (Throwable $e) {
        return true;
    }
}

function app_fatura_by_id(int $id): ?array {
    if ($id <= 0) return null;
    try {
        $st = app_pdo()->prepare('SELECT * FROM faturas WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function app_faturas_cliente(int $clienteId, int $limit = 50): array {
    if ($clienteId <= 0) return [];
    try {
        $st = app_pdo()->prepare(
            'SELECT * FROM faturas WHERE cliente_id = ? ORDER BY vencimento DESC, id DESC LIMIT ' . max(1, min(200, $limit))
        );
        $st->execute([$clienteId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function app_fatura_status_meta(?string $status = null): array {
    $all = [
        'aberta' => ['label' => 'Em aberto', 'color' => '#38bdf8', 'bg' => 'rgba(56,189,248,.15)'],
        'paga' => ['label' => 'Paga', 'color' => '#86efac', 'bg' => 'rgba(34,197,94,.15)'],
        'vencida' => ['label' => 'Vencida', 'color' => '#fca5a5', 'bg' => 'rgba(239,68,68,.15)'],
        'cancelada' => ['label' => 'Cancelada', 'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,.15)'],
    ];
    if ($status === null) return $all;
    return $all[$status] ?? $all['aberta'];
}

function app_money_br(int $centavos): string {
    return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
}

function app_only_digits(string $s): string {
    return preg_replace('/\D+/', '', $s) ?? '';
}

function app_conteudo_by_slug(string $slug, string $area = 'demonstrativo'): ?array {
    if ($slug === '') return null;
    if (!app_catalogo_area_valida($area)) $area = 'demonstrativo';
    try {
        $st = app_pdo()->prepare('SELECT * FROM conteudos WHERE slug = ? AND area = ? AND ativo = 1 LIMIT 1');
        $st->execute([$slug, $area]);
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
        'financeiro' => [
            'label' => 'Financeiro',
            'icon' => '💳',
            'desc' => 'Asaas: Pix, boleto, API Key e bloqueio por atraso',
        ],
        'atualizacao' => [
            'label' => 'Atualização do site',
            'icon' => '🔄',
            'desc' => 'Atualizar código via GitHub sem rebuild do EasyPanel',
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

/**
 * Status do fluxo de texto → gravação.
 * pendente → (admin) precisa_correcao → (cliente) corrigido → (admin) entregue
 */
function app_texto_status_meta(?string $status = null): array {
    $all = [
        'pendente' => [
            'label' => 'Aguardando análise',
            'desc' => 'Texto enviado, aguardando a equipe',
            'color' => '#94a3b8',
            'bg' => 'rgba(148,163,184,.15)',
        ],
        'precisa_correcao' => [
            'label' => 'Precisa correção',
            'desc' => 'A equipe pediu ajustes no texto',
            'color' => '#fbbf24',
            'bg' => 'rgba(251,191,36,.15)',
        ],
        'corrigido' => [
            'label' => 'Corrigido pelo cliente',
            'desc' => 'Cliente reenviou o texto corrigido',
            'color' => '#38bdf8',
            'bg' => 'rgba(56,189,248,.15)',
        ],
        'entregue' => [
            'label' => 'Áudio entregue',
            'desc' => 'Gravação disponível para o cliente',
            'color' => '#86efac',
            'bg' => 'rgba(34,197,94,.15)',
        ],
    ];
    if ($status === null) return $all;
    $status = $status !== '' ? $status : 'pendente';
    return $all[$status] ?? $all['pendente'];
}

function app_texto_by_id(int $id): ?array {
    if ($id <= 0) return null;
    try {
        $st = app_pdo()->prepare('SELECT * FROM textos_gravacao WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function app_textos_do_cliente(int $clienteId): array {
    if ($clienteId <= 0) return [];
    try {
        $st = app_pdo()->prepare(
            'SELECT * FROM textos_gravacao WHERE cliente_id = ? ORDER BY id DESC LIMIT 100'
        );
        $st->execute([$clienteId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
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
