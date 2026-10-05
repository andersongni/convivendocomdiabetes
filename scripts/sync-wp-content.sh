#!/bin/bash
# Copia plugins/themes do bind mount (Windows) para o volume Linux do container.
set -euo pipefail

HOST_CONTENT="${HOST_WP_CONTENT:-/host-wp-content}"
DEST="${DEST_WP_CONTENT:-/var/www/html/wp-content}"
FORCE="${SYNC_WP_CONTENT:-0}"

if [ ! -d "$HOST_CONTENT" ]; then
  echo "[local] host wp-content ausente em ${HOST_CONTENT}; pulando sync"
  exit 0
fi

needs_sync() {
  case "$FORCE" in
    1|true|TRUE|yes|YES|always|ALWAYS) return 0 ;;
  esac
  # Volume novo / incompleto
  if [ ! -f "$DEST/.synced-from-host" ]; then
    return 0
  fi
  if [ ! -d "$DEST/plugins" ] || [ -z "$(ls -A "$DEST/plugins" 2>/dev/null || true)" ]; then
    return 0
  fi
  if [ ! -d "$DEST/themes" ] || [ -z "$(ls -A "$DEST/themes" 2>/dev/null || true)" ]; then
    return 0
  fi
  return 1
}

sync_tree() {
  local name="$1"
  if [ ! -d "$HOST_CONTENT/$name" ]; then
    return 0
  fi
  echo "[local] sync ${name}..."
  mkdir -p "$DEST/$name"
  find "$DEST/$name" -mindepth 1 -maxdepth 1 -exec rm -rf {} + 2>/dev/null || true
  tar -C "$HOST_CONTENT/$name" -cf - . | tar -C "$DEST/$name" -xf -
}

if ! needs_sync; then
  echo "[local] volume ja sincronizado (defina SYNC_WP_CONTENT=1 para forcar)"
  exit 0
fi

echo "[local] sincronizando wp-content do host -> volume Docker (pode demorar na 1a vez)..."
mkdir -p "$DEST"

for tree in plugins themes mu-plugins languages; do
  sync_tree "$tree"
done

for f in index.php .htaccess advanced-cache.php; do
  if [ -f "$HOST_CONTENT/$f" ]; then
    cp -a "$HOST_CONTENT/$f" "$DEST/$f"
  fi
done

# uploads fica no bind mount (overlay); nao copiar

date -u +%Y-%m-%dT%H:%M:%SZ > "$DEST/.synced-from-host"
chown -R www-data:www-data "$DEST/plugins" "$DEST/themes" 2>/dev/null || true
if [ -d "$DEST/mu-plugins" ]; then
  chown -R www-data:www-data "$DEST/mu-plugins" 2>/dev/null || true
fi

echo "[local] sync completo"
