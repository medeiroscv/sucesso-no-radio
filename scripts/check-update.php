<?php
/**
 * CLI: verifica atualizações no GitHub.
 * Uso: php scripts/check-update.php [--force]
 */
require_once __DIR__ . '/../includes/update.php';

$force = in_array('--force', $argv ?? [], true) || in_array('-f', $argv ?? [], true);
$r = app_update_check($force);

echo "Repo:   " . app_update_repo() . " @ " . app_update_branch() . PHP_EOL;
echo "Local:  " . ($r['local']['short'] ?: '(desconhecido)') . ' ' . ($r['local']['message'] ?? '') . PHP_EOL;
echo "Remoto: " . ($r['remote_short'] ?: '—') . ' ' . ($r['remote_message'] ?? '') . PHP_EOL;

if (!empty($r['error']) && empty($r['ok'])) {
    fwrite(STDERR, "ERRO: " . $r['error'] . PHP_EOL);
    exit(1);
}
if (!empty($r['error'])) {
    echo "Aviso: " . $r['error'] . PHP_EOL;
}

$behind = (int)($r['behind'] ?? 0);
if ($behind === 0) {
    echo "Status: em dia" . PHP_EOL;
    exit(0);
}
if ($behind < 0) {
    echo "Status: versão local desconhecida — veja commits recentes" . PHP_EOL;
} else {
    echo "Status: $behind commit(s) atrás" . PHP_EOL;
}

foreach (($r['commits'] ?? []) as $c) {
    $date = !empty($c['date']) ? date('Y-m-d', strtotime($c['date'])) : '';
    echo "  - {$c['short']} {$date} {$c['message']}" . PHP_EOL;
}

echo PHP_EOL . "Para aplicar: bash scripts/update.sh" . PHP_EOL;
exit($behind > 0 ? 2 : 0);
