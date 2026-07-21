<?php
/**
 * Módulo de preços, assinaturas e cobrança recorrente (estilo WHMCS).
 * Integra com faturas + Asaas (includes/asaas.php).
 */

require_once __DIR__ . '/env.php';

/** Tipos de produto. */
function billing_produto_tipos(): array {
    return [
        'mensalidade' => ['label' => 'Mensalidade', 'icon' => '📅'],
        'plano' => ['label' => 'Plano', 'icon' => '📦'],
        'pacote' => ['label' => 'Pacote', 'icon' => '🎁'],
        'avulso' => ['label' => 'Produto único', 'icon' => '⭐'],
    ];
}

/** Ciclos de cobrança. */
function billing_ciclos(): array {
    return [
        'unico' => ['label' => 'Único (sem recorrência)', 'months' => 0],
        'mensal' => ['label' => 'Mensal', 'months' => 1],
        'trimestral' => ['label' => 'Trimestral', 'months' => 3],
        'semestral' => ['label' => 'Semestral', 'months' => 6],
        'anual' => ['label' => 'Anual', 'months' => 12],
    ];
}

function billing_status_assinatura_meta(?string $status = null): array {
    $all = [
        'ativa' => ['label' => 'Ativa', 'color' => '#86efac', 'bg' => 'rgba(34,197,94,.15)'],
        'suspensa' => ['label' => 'Suspensa', 'color' => '#fcd34d', 'bg' => 'rgba(245,158,11,.15)'],
        'cancelada' => ['label' => 'Cancelada', 'color' => '#fca5a5', 'bg' => 'rgba(239,68,68,.15)'],
        'encerrada' => ['label' => 'Encerrada', 'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,.15)'],
    ];
    if ($status === null) return $all;
    return $all[$status] ?? $all['ativa'];
}

function billing_parse_dias_list($raw): array {
    if (is_array($raw)) {
        $list = $raw;
    } else {
        $s = trim((string)$raw);
        if ($s === '') return [];
        $j = json_decode($s, true);
        if (is_array($j)) {
            $list = $j;
        } else {
            // "1,2,3" ou "7 3 1"
            $list = preg_split('/[\s,;]+/', $s) ?: [];
        }
    }
    $out = [];
    foreach ($list as $v) {
        $n = intval($v);
        if ($n > 0 && $n <= 90) $out[] = $n;
    }
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

function billing_encode_dias_list(array $dias): string {
    $dias = billing_parse_dias_list($dias);
    return json_encode($dias, JSON_UNESCAPED_UNICODE);
}

function billing_log(string $tipo, string $ref, string $msg): void {
    try {
        app_pdo()->prepare(
            'INSERT INTO billing_log (tipo, referencia, mensagem, created_at) VALUES (?,?,?,NOW())'
        )->execute([$tipo, mb_substr($ref, 0, 120), mb_substr($msg, 0, 2000)]);
    } catch (Throwable $e) { /* ok */ }
}

function billing_produto_by_id(int $id): ?array {
    if ($id <= 0) return null;
    try {
        $st = app_pdo()->prepare('SELECT * FROM produtos WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function billing_produto_by_slug(string $slug): ?array {
    $slug = trim($slug);
    if ($slug === '') return null;
    try {
        $st = app_pdo()->prepare('SELECT * FROM produtos WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        $r = $st->fetch();
        return $r ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** @return list<array> */
function billing_produtos_lista(bool $somenteAtivos = false, bool $somenteSite = false): array {
    try {
        $sql = 'SELECT * FROM produtos WHERE 1=1';
        if ($somenteAtivos) $sql .= ' AND ativo = 1';
        if ($somenteSite) $sql .= ' AND mostrar_site = 1';
        $sql .= ' ORDER BY ordem ASC, destaque DESC, nome ASC';
        return app_pdo()->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function billing_produto_normalize_row(array $p): array {
    $p['cobranca_antes_list'] = billing_parse_dias_list($p['cobranca_antes'] ?? '[]');
    $p['cobranca_apos_list'] = billing_parse_dias_list($p['cobranca_apos'] ?? '[]');
    $p['recursos_list'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($p['recursos'] ?? '')) ?: [])));
    return $p;
}

function billing_assinatura_by_id(int $id): ?array {
    if ($id <= 0) return null;
    try {
        $st = app_pdo()->prepare(
            'SELECT a.*, p.nome AS produto_nome, p.ciclo AS produto_ciclo, p.tipo AS produto_tipo,
                    c.nome AS cliente_nome, c.email AS cliente_email
             FROM assinaturas a
             INNER JOIN produtos p ON p.id = a.produto_id
             INNER JOIN clientes c ON c.id = a.cliente_id
             WHERE a.id = ? LIMIT 1'
        );
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** @return list<array> */
function billing_assinaturas_lista(?string $status = null, int $limit = 200): array {
    try {
        $sql = 'SELECT a.*, p.nome AS produto_nome, p.ciclo AS produto_ciclo, p.tipo AS produto_tipo,
                       c.nome AS cliente_nome, c.email AS cliente_email
                FROM assinaturas a
                INNER JOIN produtos p ON p.id = a.produto_id
                INNER JOIN clientes c ON c.id = a.cliente_id';
        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' WHERE a.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY a.status ASC, a.proximo_vencimento ASC, a.id DESC LIMIT ' . max(1, min(500, $limit));
        $st = app_pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** @return list<array> */
function billing_assinaturas_cliente(int $clienteId): array {
    if ($clienteId <= 0) return [];
    try {
        $st = app_pdo()->prepare(
            'SELECT a.*, p.nome AS produto_nome, p.ciclo AS produto_ciclo, p.tipo AS produto_tipo
             FROM assinaturas a
             INNER JOIN produtos p ON p.id = a.produto_id
             WHERE a.cliente_id = ?
             ORDER BY a.status ASC, a.proximo_vencimento ASC'
        );
        $st->execute([$clienteId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function billing_add_months(string $ymd, int $months): string {
    $dt = DateTime::createFromFormat('Y-m-d', $ymd) ?: new DateTime($ymd);
    $day = (int)$dt->format('d');
    $dt->modify('first day of this month');
    $dt->modify('+' . max(0, $months) . ' months');
    $last = (int)$dt->format('t');
    $dt->setDate((int)$dt->format('Y'), (int)$dt->format('m'), min($day, $last));
    return $dt->format('Y-m-d');
}

function billing_next_due_from_day(int $diaVenc, ?string $fromYmd = null): string {
    $diaVenc = max(1, min(28, $diaVenc));
    $from = $fromYmd ? new DateTime($fromYmd) : new DateTime('today');
    $y = (int)$from->format('Y');
    $m = (int)$from->format('m');
    $d = (int)$from->format('d');
    $candidate = sprintf('%04d-%02d-%02d', $y, $m, min($diaVenc, (int)date('t', mktime(0, 0, 0, $m, 1, $y))));
    if ($candidate <= $from->format('Y-m-d')) {
        $from->modify('first day of next month');
        $y = (int)$from->format('Y');
        $m = (int)$from->format('m');
        $candidate = sprintf('%04d-%02d-%02d', $y, $m, min($diaVenc, (int)$from->format('t')));
    }
    return $candidate;
}

/**
 * Cria assinatura para cliente.
 * @return array{ok:bool,id?:int,message:string}
 */
function billing_criar_assinatura(int $clienteId, int $produtoId, array $opts = []): array {
    $prod = billing_produto_by_id($produtoId);
    if (!$prod || empty($prod['ativo'])) {
        return ['ok' => false, 'message' => 'Produto inválido ou inativo.'];
    }
    $st = app_pdo()->prepare('SELECT id FROM clientes WHERE id = ? LIMIT 1');
    $st->execute([$clienteId]);
    if (!$st->fetch()) {
        return ['ok' => false, 'message' => 'Cliente não encontrado.'];
    }

    $valor = isset($opts['valor_centavos']) ? intval($opts['valor_centavos']) : intval($prod['valor_centavos']);
    if ($valor <= 0) $valor = intval($prod['valor_centavos']);
    $dia = isset($opts['dia_vencimento']) ? max(1, min(28, intval($opts['dia_vencimento']))) : (int)date('d');
    if ($dia > 28) $dia = 28;
    $inicio = !empty($opts['data_inicio']) ? (string)$opts['data_inicio'] : date('Y-m-d');
    $proximo = !empty($opts['proximo_vencimento'])
        ? (string)$opts['proximo_vencimento']
        : billing_next_due_from_day($dia, $inicio);
    $obs = trim((string)($opts['observacao'] ?? ''));

    try {
        app_pdo()->prepare(
            'INSERT INTO assinaturas
             (cliente_id, produto_id, status, valor_centavos, dia_vencimento, data_inicio, proximo_vencimento, observacao, created_at)
             VALUES (?,?,?,?,?,?,?,?,NOW())'
        )->execute([
            $clienteId, $produtoId, 'ativa', $valor, $dia, $inicio, $proximo, $obs,
        ]);
        $id = intval(app_pdo()->lastInsertId());
        billing_log('assinatura_criada', 'ass_' . $id, "Cliente #{$clienteId} · produto #{$produtoId} · venc {$proximo}");
        // Gera primeira fatura se já estiver na janela (ou for avulso)
        billing_run(false, $id);
        return ['ok' => true, 'id' => $id, 'message' => 'Assinatura criada.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function billing_fatura_cobrancas_log(array $fat): array {
    $j = json_decode((string)($fat['cobrancas_log'] ?? '[]'), true);
    return is_array($j) ? $j : [];
}

function billing_fatura_log_add(int $faturaId, string $evento, string $detalhe = ''): void {
    $fat = app_fatura_by_id($faturaId);
    if (!$fat) return;
    $log = billing_fatura_cobrancas_log($fat);
    $log[] = [
        'em' => date('c'),
        'evento' => $evento,
        'detalhe' => $detalhe,
    ];
    if (count($log) > 40) {
        $log = array_slice($log, -40);
    }
    try {
        app_pdo()->prepare('UPDATE faturas SET cobrancas_log = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($log, JSON_UNESCAPED_UNICODE), $faturaId]);
    } catch (Throwable $e) { /* ok */ }
}

function billing_fatura_ja_teve_evento(array $fat, string $evento): bool {
    foreach (billing_fatura_cobrancas_log($fat) as $row) {
        if (($row['evento'] ?? '') === $evento) return true;
    }
    return false;
}

/**
 * Gera fatura para um período da assinatura (se ainda não existir).
 * @return array{ok:bool,fatura_id?:int,created?:bool,message:string}
 */
function billing_gerar_fatura_assinatura(array $ass, array $prod, string $vencimento): array {
    $assId = intval($ass['id']);
    $cliId = intval($ass['cliente_id']);
    $periodo = $vencimento; // chave do ciclo
    $pdo = app_pdo();

    // Já existe fatura para este período?
    try {
        $st = $pdo->prepare(
            "SELECT id FROM faturas WHERE assinatura_id = ? AND periodo_ref = ? LIMIT 1"
        );
        $st->execute([$assId, $periodo]);
        $exist = intval($st->fetchColumn());
        if ($exist > 0) {
            return ['ok' => true, 'fatura_id' => $exist, 'created' => false, 'message' => 'Fatura já existia.'];
        }
    } catch (Throwable $e) { /* segue */ }

    $valor = intval($ass['valor_centavos'] ?: $prod['valor_centavos']);
    $nomeProd = (string)($prod['nome'] ?? 'Mensalidade');
    $desc = $nomeProd . ' · venc. ' . date('d/m/Y', strtotime($vencimento));

    try {
        $pdo->prepare(
            "INSERT INTO faturas
             (cliente_id, descricao, valor_centavos, vencimento, status, assinatura_id, produto_id, periodo_ref, cobrancas_log, created_at)
             VALUES (?,?,?,?,'aberta',?,?,?,'[]',NOW())"
        )->execute([$cliId, $desc, $valor, $vencimento, $assId, intval($prod['id']), $periodo]);
        $fid = intval($pdo->lastInsertId());
        $pdo->prepare('UPDATE assinaturas SET ultima_fatura_id = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$fid, $assId]);
        billing_fatura_log_add($fid, 'gerada', 'Fatura criada pelo billing');
        billing_log('fatura_gerada', 'fat_' . $fid, "Assinatura #{$assId} · {$desc} · R$ " . number_format($valor / 100, 2, ',', '.'));

        $emitir = !empty($prod['emitir_auto']);
        if ($emitir && function_exists('finance_emitir_pagamento')) {
            try {
                require_once __DIR__ . '/asaas.php';
                if (asaas_configured()) {
                    finance_emitir_pagamento($fid, false);
                    billing_fatura_log_add($fid, 'emitida_criacao', 'Pix/boleto na geração');
                }
            } catch (Throwable $e) {
                billing_fatura_log_add($fid, 'emit_erro', $e->getMessage());
            }
        }

        return ['ok' => true, 'fatura_id' => $fid, 'created' => true, 'message' => 'Fatura gerada.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Avança próximo vencimento da assinatura após gerar a fatura do período atual.
 */
function billing_avancar_proximo_vencimento(array $ass, array $prod): void {
    $ciclo = (string)($prod['ciclo'] ?? 'mensal');
    $months = billing_ciclos()[$ciclo]['months'] ?? 1;
    if ($months <= 0) {
        // avulso: encerra após gerar
        app_pdo()->prepare(
            "UPDATE assinaturas SET status = 'encerrada', updated_at = NOW() WHERE id = ?"
        )->execute([intval($ass['id'])]);
        return;
    }
    $prox = billing_add_months((string)$ass['proximo_vencimento'], $months);
    // alinha dia de vencimento preferido
    $dia = max(1, min(28, intval($ass['dia_vencimento'])));
    $dt = new DateTime($prox);
    $last = (int)$dt->format('t');
    $dt->setDate((int)$dt->format('Y'), (int)$dt->format('m'), min($dia, $last));
    $prox = $dt->format('Y-m-d');
    app_pdo()->prepare(
        'UPDATE assinaturas SET proximo_vencimento = ?, updated_at = NOW() WHERE id = ?'
    )->execute([$prox, intval($ass['id'])]);
}

/**
 * Emite cobrança (Pix/boleto) se o dia bater com a agenda do produto.
 */
function billing_processar_cobrancas_fatura(array $fat, array $prod): void {
    if (!in_array($fat['status'] ?? '', ['aberta', 'vencida'], true)) return;
    if (empty($prod['emitir_auto'])) return;
    if (!function_exists('finance_emitir_pagamento')) {
        require_once __DIR__ . '/asaas.php';
    }
    if (!asaas_configured()) return;

    $fid = intval($fat['id']);
    $venc = (string)$fat['vencimento'];
    $today = date('Y-m-d');
    $antes = billing_parse_dias_list($prod['cobranca_antes'] ?? '[]');
    $apos = billing_parse_dias_list($prod['cobranca_apos'] ?? '[]');
    $noVenc = !empty($prod['cobranca_no_vencimento']);

    $diff = (int)round((strtotime($venc) - strtotime($today)) / 86400); // positivo = antes do venc

    $evento = '';
    $force = false;

    if ($diff > 0 && in_array($diff, $antes, true)) {
        $evento = 'cobranca_antes_' . $diff;
    } elseif ($diff === 0 && $noVenc) {
        $evento = 'cobranca_no_vencimento';
    } elseif ($diff < 0) {
        $diasAtraso = abs($diff);
        if (in_array($diasAtraso, $apos, true)) {
            $evento = 'cobranca_apos_' . $diasAtraso;
            $force = true; // renova meios se vencido
        }
    }

    if ($evento === '') return;
    if (billing_fatura_ja_teve_evento($fat, $evento)) return;

    // Sem meios OU forçar renovação em atraso
    $semMeios = empty($fat['pix_copia_cola']) && empty($fat['boleto_url']) && empty($fat['boleto_barcode']);
    try {
        if ($semMeios || $force) {
            finance_emitir_pagamento($fid, $force);
        } else {
            // garante meios válidos
            if (function_exists('finance_garantir_meios_pagamento')) {
                finance_garantir_meios_pagamento($fat, false);
            }
        }
        billing_fatura_log_add($fid, $evento, 'Cobrança automática');
        billing_log('cobranca', 'fat_' . $fid, $evento);
    } catch (Throwable $e) {
        billing_fatura_log_add($fid, $evento . '_erro', $e->getMessage());
    }
}

/**
 * Motor principal: gera faturas futuras e dispara cobranças agendadas.
 *
 * @param bool $full se true processa tudo; se false e $onlyAssinaturaId set, só uma
 * @return array{assinaturas:int,faturas_novas:int,cobrancas:int,erros:list<string>}
 */
function billing_run(bool $full = true, int $onlyAssinaturaId = 0): array {
    $stats = ['assinaturas' => 0, 'faturas_novas' => 0, 'cobrancas' => 0, 'erros' => []];
    $today = date('Y-m-d');

    try {
        if ($onlyAssinaturaId > 0) {
            $st = app_pdo()->prepare(
                "SELECT a.*, p.nome AS p_nome, p.ciclo, p.tipo, p.valor_centavos AS p_valor,
                        p.dias_gerar_antes, p.cobranca_antes, p.cobranca_no_vencimento, p.cobranca_apos, p.emitir_auto
                 FROM assinaturas a
                 INNER JOIN produtos p ON p.id = a.produto_id
                 WHERE a.id = ? AND a.status = 'ativa'"
            );
            $st->execute([$onlyAssinaturaId]);
            $rows = $st->fetchAll() ?: [];
        } else {
            $rows = app_pdo()->query(
                "SELECT a.*, p.nome AS p_nome, p.ciclo, p.tipo, p.valor_centavos AS p_valor,
                        p.dias_gerar_antes, p.cobranca_antes, p.cobranca_no_vencimento, p.cobranca_apos, p.emitir_auto
                 FROM assinaturas a
                 INNER JOIN produtos p ON p.id = a.produto_id
                 WHERE a.status = 'ativa'
                 ORDER BY a.proximo_vencimento ASC
                 LIMIT 500"
            )->fetchAll() ?: [];
        }
    } catch (Throwable $e) {
        $stats['erros'][] = $e->getMessage();
        return $stats;
    }

    foreach ($rows as $ass) {
        $stats['assinaturas']++;
        $prod = [
            'id' => intval($ass['produto_id']),
            'nome' => $ass['p_nome'] ?? 'Produto',
            'ciclo' => $ass['ciclo'] ?? 'mensal',
            'tipo' => $ass['tipo'] ?? 'mensalidade',
            'valor_centavos' => intval($ass['p_valor'] ?? 0),
            'dias_gerar_antes' => intval($ass['dias_gerar_antes'] ?? 7),
            'cobranca_antes' => $ass['cobranca_antes'] ?? '[]',
            'cobranca_no_vencimento' => $ass['cobranca_no_vencimento'] ?? 1,
            'cobranca_apos' => $ass['cobranca_apos'] ?? '[]',
            'emitir_auto' => $ass['emitir_auto'] ?? 1,
        ];

        $prox = (string)$ass['proximo_vencimento'];
        $diasAntes = max(0, intval($prod['dias_gerar_antes']));
        $gerarAPartir = date('Y-m-d', strtotime($prox . ' -' . $diasAntes . ' days'));

        // Avulso: gera na criação / se ainda não gerou
        $isUnico = ($prod['ciclo'] ?? '') === 'unico';

        if ($isUnico || $today >= $gerarAPartir) {
            $r = billing_gerar_fatura_assinatura($ass, $prod, $prox);
            if (!empty($r['created'])) {
                $stats['faturas_novas']++;
                // avança próximo vencimento só após gerar com sucesso
                try {
                    billing_avancar_proximo_vencimento($ass, $prod);
                } catch (Throwable $e) {
                    $stats['erros'][] = 'Avançar venc ass #' . $ass['id'] . ': ' . $e->getMessage();
                }
            } elseif (empty($r['ok'])) {
                $stats['erros'][] = 'Ass #' . $ass['id'] . ': ' . ($r['message'] ?? 'erro');
            }
        }
    }

    // Processa cobranças de faturas abertas vinculadas a assinatura
    try {
        $fats = app_pdo()->query(
            "SELECT f.*, p.cobranca_antes, p.cobranca_no_vencimento, p.cobranca_apos, p.emitir_auto, p.nome AS p_nome
             FROM faturas f
             LEFT JOIN produtos p ON p.id = f.produto_id
             WHERE f.status IN ('aberta','vencida')
               AND (f.assinatura_id IS NOT NULL OR f.produto_id IS NOT NULL)
             ORDER BY f.vencimento ASC
             LIMIT 300"
        )->fetchAll() ?: [];
    } catch (Throwable $e) {
        $fats = [];
    }

    foreach ($fats as $fat) {
        // monta prod a partir da fatura ou defaults
        $prod = [
            'emitir_auto' => $fat['emitir_auto'] ?? 1,
            'cobranca_antes' => $fat['cobranca_antes'] ?? '[]',
            'cobranca_no_vencimento' => $fat['cobranca_no_vencimento'] ?? 1,
            'cobranca_apos' => $fat['cobranca_apos'] ?? '[1,2,3]',
        ];
        // se produto sumiu, ainda cobra com default
        if ($fat['produto_id'] === null) {
            $prod['emitir_auto'] = 1;
            $prod['cobranca_no_vencimento'] = 1;
            $prod['cobranca_apos'] = '[1,2,3]';
        }
        $before = count(billing_fatura_cobrancas_log($fat));
        billing_processar_cobrancas_fatura($fat, $prod);
        $fat2 = app_fatura_by_id(intval($fat['id']));
        if ($fat2 && count(billing_fatura_cobrancas_log($fat2)) > $before) {
            $stats['cobrancas']++;
        }
    }

    if (function_exists('finance_marcar_vencidas')) {
        finance_marcar_vencidas(null);
    }

    billing_log('billing_run', 'cron', json_encode($stats, JSON_UNESCAPED_UNICODE));
    return $stats;
}

/** Executa billing no máximo 1x a cada N segundos (web). */
function billing_run_throttled(int $seconds = 300): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $key = 'billing_last_run';
    $last = intval($_SESSION[$key] ?? 0);
    if ($last > 0 && (time() - $last) < $seconds) {
        return null;
    }
    // também throttle global em arquivo
    $lock = dirname(__DIR__) . '/data/billing_last_run.txt';
    if (is_file($lock)) {
        $t = intval(trim((string)@file_get_contents($lock)));
        if ($t > 0 && (time() - $t) < $seconds) {
            return null;
        }
    }
    $stats = billing_run(true);
    $_SESSION[$key] = time();
    @file_put_contents($lock, (string)time());
    return $stats;
}
