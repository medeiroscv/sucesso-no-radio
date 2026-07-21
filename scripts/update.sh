#!/usr/bin/env bash
# Atualiza o Sucesso no Rádio a partir do GitHub (linha de comando).
# Uso:
#   bash scripts/update.sh              # git pull + atualiza version.json
#   bash scripts/update.sh --check      # só verifica (sem alterar)
#   bash scripts/update.sh --force      # pull mesmo com mudanças locais (stash)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

REPO="${GITHUB_REPO:-medeiroscv/sucesso-no-radio}"
BRANCH="${GITHUB_BRANCH:-master}"
CHECK_ONLY=0
FORCE=0

for arg in "$@"; do
  case "$arg" in
    --check|-c) CHECK_ONLY=1 ;;
    --force|-f) FORCE=1 ;;
    -h|--help)
      echo "Uso: bash scripts/update.sh [--check] [--force]"
      exit 0
      ;;
  esac
done

log() { echo "[update $(date +%H:%M:%S)] $*"; }

if ! command -v git >/dev/null 2>&1; then
  echo "ERRO: git não encontrado. Instale o Git ou faça redeploy no EasyPanel."
  exit 1
fi

if [ ! -d "$ROOT/.git" ]; then
  echo "ERRO: pasta .git não encontrada em $ROOT"
  echo "Neste ambiente (Docker/EasyPanel sem repositório), use:"
  echo "  1) EasyPanel → Deploy / Redeploy do app a partir do GitHub"
  echo "  2) Ou clone o repo e aponte o volume/código corretamente"
  exit 1
fi

log "Repositório: $REPO · branch: $BRANCH"
log "Pasta: $ROOT"

LOCAL="$(git rev-parse HEAD)"
log "Local:  ${LOCAL:0:7}"

git fetch origin "$BRANCH" 2>&1 | sed 's/^/  /' || true
REMOTE="$(git rev-parse "origin/$BRANCH" 2>/dev/null || true)"
if [ -z "$REMOTE" ]; then
  echo "ERRO: não foi possível obter origin/$BRANCH"
  exit 1
fi
log "Remoto: ${REMOTE:0:7}"

if [ "$LOCAL" = "$REMOTE" ]; then
  log "Já está atualizado."
  # sincroniza version.json
  MSG="$(git log -1 --pretty=%s)"
  cat > "$ROOT/version.json" <<EOF
{
    "commit": "$LOCAL",
    "short": "${LOCAL:0:7}",
    "branch": "$BRANCH",
    "repo": "$REPO",
    "updated_at": "$(date -u +%Y-%m-%dT%H:%M:%S+00:00)",
    "message": $(python3 -c 'import json,sys; print(json.dumps(sys.argv[1]))' "$MSG" 2>/dev/null || php -r 'echo json_encode($argv[1]);' "$MSG" 2>/dev/null || echo "\"$MSG\"")
}
EOF
  exit 0
fi

BEHIND="$(git rev-list --count HEAD.."origin/$BRANCH" 2>/dev/null || echo "?")"
log "Commits a aplicar: $BEHIND"
git log --oneline HEAD.."origin/$BRANCH" | head -20 | sed 's/^/  /'

if [ "$CHECK_ONLY" = "1" ]; then
  log "Modo --check: nenhuma alteração feita."
  exit 0
fi

if [ -n "$(git status --porcelain)" ]; then
  if [ "$FORCE" = "1" ]; then
    log "Alterações locais detectadas — stash (--force)"
    git stash push -u -m "update.sh auto-stash $(date -u +%Y%m%d%H%M%S)" || true
  else
    echo "ERRO: há alterações locais. Commit, descarte ou use --force (stash)."
    git status --short
    exit 1
  fi
fi

log "Aplicando git pull --ff-only origin $BRANCH ..."
git pull --ff-only origin "$BRANCH"

NEW="$(git rev-parse HEAD)"
MSG="$(git log -1 --pretty=%s)"
# Escreve version.json sem depender de python
if command -v php >/dev/null 2>&1; then
  php -r '
    $p = $argv[1];
    $data = [
      "commit" => $argv[2],
      "short" => substr($argv[2], 0, 7),
      "branch" => $argv[3],
      "repo" => $argv[4],
      "updated_at" => gmdate("c"),
      "message" => $argv[5],
    ];
    file_put_contents($p, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n");
  ' "$ROOT/version.json" "$NEW" "$BRANCH" "$REPO" "$MSG"
else
  cat > "$ROOT/version.json" <<EOF
{
    "commit": "$NEW",
    "short": "${NEW:0:7}",
    "branch": "$BRANCH",
    "repo": "$REPO",
    "updated_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "message": "$MSG"
}
EOF
fi

rm -f "$ROOT/data/update_check.json" 2>/dev/null || true

log "OK — agora em ${NEW:0:7}: $MSG"
log "Reinicie o container/Apache se necessário (EasyPanel: Restart)."
