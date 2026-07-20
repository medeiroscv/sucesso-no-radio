#!/bin/bash
set -e

log() { echo "[sucesso-radio $(date -u +%H:%M:%S)] $*"; }

cd /var/www/html

mkdir -p config data/sessions uploads/programas uploads/conteudos uploads/banners uploads/demos uploads/site
chown -R www-data:www-data config data uploads 2>/dev/null || true
chmod -R 775 config data uploads 2>/dev/null || true

if [ "${AUTO_INSTALL:-true}" = "true" ]; then
  log "bootstrap PostgreSQL (AUTO_INSTALL=true)..."
  if php -r '
    require_once "/var/www/html/includes/db.php";
    $cfg = app_db_config_from_env();
    if ($cfg === null) {
      fwrite(STDERR, "Sem DATABASE_URL/DB_* — pulando bootstrap.\n");
      exit(0);
    }
    $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", $cfg["host"], $cfg["port"], $cfg["database"]);
    $pdo = null;
    $last = "";
    for ($i = 1; $i <= 40; $i++) {
      try {
        $pdo = new PDO($dsn, $cfg["username"], $cfg["password"], [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        break;
      } catch (Throwable $e) {
        $last = $e->getMessage();
        fwrite(STDERR, "aguardando Postgres ({$i}/40): {$last}\n");
        sleep(3);
      }
    }
    if (!$pdo) {
      fwrite(STDERR, "ERRO Postgres: {$last}\n");
      exit(1);
    }
    app_bootstrap_database($pdo);
    echo "schema OK v" . app_db_version() . "\n";

    $user = getenv("BOOTSTRAP_ADMIN_USER") ?: "admin";
    $pass = getenv("BOOTSTRAP_ADMIN_PASSWORD") ?: "";
    $name = getenv("BOOTSTRAP_ADMIN_NAME") ?: "Administrador";
    if ($pass !== "") {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $st = $pdo->prepare(
        "INSERT INTO usuarios (usuario, senha_hash, nome, ativo, created_at)
         VALUES (?, ?, ?, 1, NOW())
         ON CONFLICT (usuario) DO UPDATE SET
           senha_hash = EXCLUDED.senha_hash,
           nome = EXCLUDED.nome,
           ativo = 1,
           updated_at = NOW()"
      );
      $st->execute([$user, $hash, $name]);
      echo "admin: {$user}\n";
    }
  '; then
    log "bootstrap concluido"
  else
    log "AVISO: bootstrap falhou — confira DB_* / DATABASE_URL"
  fi
else
  log "AUTO_INSTALL=false"
fi

log "iniciando Apache"
exec "$@"
