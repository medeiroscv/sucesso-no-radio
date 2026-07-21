<?php
/**
 * CLI: verifica / aplica atualização via GitHub ZIP.
 * Uso:
 *   php scripts/check-update.php
 *   php scripts/check-update.php --apply
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/update.php';

$apply = in_array('--apply', $argv ?? [], true);

echo "Repo:   " . app_update_repo() . " @ " . app_update_branch() . PHP_EOL;
$local = app_update_local_version();
echo "Local:  " . ($local['short'] ?: '(desconhecido)') . ' ' . ($local['message'] ?? '') . PHP_EOL;

if ($apply) {
    $r = app_update_apply();
    echo ($r['ok'] ? "OK: " : "ERRO: ") . $r['message'] . PHP_EOL;
    echo $r['log'] . PHP_EOL;
    exit($r['ok'] ? 0 : 1);
}

$r = app_update_check(true);
if (empty($r['ok'])) {
    fwrite(STDERR, "ERRO: " . ($r['error'] ?? 'falha') . PHP_EOL);
    exit(1);
}
echo "Remoto: " . ($r['remote_short'] ?: '—') . ' ' . ($r['remote_message'] ?? '') . PHP_EOL;
if (!empty($r['up_to_date'])) {
    echo "Status: em dia" . PHP_EOL;
    exit(0);
}
echo "Status: atualização disponível (" . (int)($r['behind'] ?? 1) . " commit(s))" . PHP_EOL;
echo "Para aplicar: php scripts/check-update.php --apply" . PHP_EOL;
exit(2);
