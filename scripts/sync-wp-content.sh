#!/bin/bash
# Copia plugins/themes do bind mount (Windows) para o volume Linux do container.
# mu-plugins: sempre ressincroniza (mudam com frequencia e sao leves).
set -euo pipefail

HOST_CONTENT="${HOST_WP_CONTENT:-/host-wp-content}"
DEST="${DEST_WP_CONTENT:-/var/www/html/wp-content}"
FORCE="${SYNC_WP_CONTENT:-0}"

if [ ! -d "$HOST_CONTENT" ]; then
  echo "[local] host wp-content ausente em ${HOST_CONTENT}; pulando sync"
  exit 0
fi

needs_full_sync() {
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

# mu-plugins sempre: evita hero/CSS/PHP antigo apos editar no host sem rebuild.
sync_mu_plugins() {
  if [ ! -d "$HOST_CONTENT/mu-plugins" ]; then
    return 0
  fi
  local stamp_host stamp_dest
  stamp_host="$(find "$HOST_CONTENT/mu-plugins" -type f -printf '%T@ %p\n' 2>/dev/null | sort | md5sum | awk '{print $1}')"
  stamp_dest=""
  if [ -f "$DEST/.mu-plugins-stamp" ]; then
    stamp_dest="$(cat "$DEST/.mu-plugins-stamp" 2>/dev/null || true)"
  fi
  if [ -n "$stamp_host" ] && [ "$stamp_host" = "$stamp_dest" ] && [ -d "$DEST/mu-plugins" ]; then
    echo "[local] mu-plugins ja atualizados"
    return 0
  fi
  sync_tree "mu-plugins"
  if [ -d "$DEST/mu-plugins" ]; then
    chown -R www-data:www-data "$DEST/mu-plugins" 2>/dev/null || true
  fi
  printf '%s\n' "$stamp_host" > "$DEST/.mu-plugins-stamp"
}

mkdir -p "$DEST"

if needs_full_sync; then
  echo "[local] sincronizando wp-content do host -> volume Docker (pode demorar na 1a vez)..."
  for tree in plugins themes languages; do
    sync_tree "$tree"
  done

  for f in index.php .htaccess advanced-cache.php object-cache.php ccd-http-error.php ccd-http-error-404-template.php ccd-env-urls-lib.php; do
    if [ -f "$HOST_CONTENT/$f" ]; then
      cp -a "$HOST_CONTENT/$f" "$DEST/$f"
    fi
  done

  date -u +%Y-%m-%dT%H:%M:%SZ > "$DEST/.synced-from-host"
  chown -R www-data:www-data "$DEST/plugins" "$DEST/themes" 2>/dev/null || true
  echo "[local] sync completo (plugins/themes/languages)"
else
  echo "[local] volume ja sincronizado (SYNC_WP_CONTENT=1 forca plugins/themes)"
fi

sync_mu_plugins

echo "[local] sync OK"
