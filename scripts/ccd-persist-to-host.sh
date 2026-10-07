#!/bin/bash
# Copia plugins/themes/mu-plugins/languages (e opcionalmente o core) do volume
# para o bind do host, para o git refletir updates feitos no WP Admin local.
set -euo pipefail

HOST_CONTENT="${HOST_WP_CONTENT:-/host-wordpress/wp-content}"
HOST_WP="${HOST_WORDPRESS:-/host-wordpress}"
SRC_CONTENT="${SRC_WP_CONTENT:-/var/www/html/wp-content}"
SRC_WP="${SRC_WORDPRESS:-/var/www/html}"
MODE="${1:-content}" # content | core | all | plugin | theme | languages

if [ ! -d "$HOST_CONTENT" ]; then
  echo "[persist] host ausente: ${HOST_CONTENT}" >&2
  exit 1
fi
if [ ! -w "$HOST_CONTENT" ]; then
  echo "[persist] host nao gravavel: ${HOST_CONTENT} (monte sem :ro)" >&2
  exit 1
fi

# Copia uma pasta (src -> dst), substituindo o destino.
persist_dir() {
  local src="$1"
  local dst="$2"
  if [ ! -d "$src" ]; then
    echo "[persist] fonte ausente: ${src}" >&2
    return 0
  fi
  echo "[persist] ${src} -> ${dst}"
  mkdir -p "$(dirname "$dst")"
  rm -rf "$dst"
  mkdir -p "$dst"
  tar -C "$src" -cf - . | tar -C "$dst" -xf -
}

persist_tree() {
  local name="$1"
  persist_dir "$SRC_CONTENT/$name" "$HOST_CONTENT/$name"
}

persist_plugin() {
  local file_or_slug="$1"
  local slug
  # aceita "akismet/akismet.php" ou "akismet"
  slug="${file_or_slug%%/*}"
  if [ -z "$slug" ]; then
    return 0
  fi
  if [ -d "$SRC_CONTENT/plugins/$slug" ]; then
    persist_dir "$SRC_CONTENT/plugins/$slug" "$HOST_CONTENT/plugins/$slug"
  elif [ -f "$SRC_CONTENT/plugins/$file_or_slug" ]; then
    # plugin single-file
    mkdir -p "$HOST_CONTENT/plugins"
    cp -a "$SRC_CONTENT/plugins/$file_or_slug" "$HOST_CONTENT/plugins/$file_or_slug"
    echo "[persist] plugin file ${file_or_slug}"
  else
    echo "[persist] plugin nao encontrado: ${file_or_slug}" >&2
  fi
}

persist_theme() {
  local slug="$1"
  if [ -z "$slug" ]; then
    return 0
  fi
  if [ -d "$SRC_CONTENT/themes/$slug" ]; then
    persist_dir "$SRC_CONTENT/themes/$slug" "$HOST_CONTENT/themes/$slug"
  else
    echo "[persist] tema nao encontrado: ${slug}" >&2
  fi
}

persist_core() {
  if [ ! -d "$HOST_WP" ] || [ ! -w "$HOST_WP" ]; then
    echo "[persist] HOST_WORDPRESS nao gravavel; pulando core" >&2
    return 0
  fi
  echo "[persist] core (wp-admin, wp-includes, root php) -> host"
  for name in wp-admin wp-includes; do
    if [ -d "$SRC_WP/$name" ]; then
      persist_dir "$SRC_WP/$name" "$HOST_WP/$name"
    fi
  done
  for f in index.php xmlrpc.php wp-activate.php wp-blog-header.php wp-comments-post.php \
    wp-cron.php wp-links-opml.php wp-load.php wp-login.php wp-mail.php wp-settings.php \
    wp-signup.php wp-trackback.php; do
    if [ -f "$SRC_WP/$f" ]; then
      cp -a "$SRC_WP/$f" "$HOST_WP/$f"
    fi
  done
  if [ -f "$SRC_WP/wp-content/index.php" ]; then
    cp -a "$SRC_WP/wp-content/index.php" "$HOST_CONTENT/index.php" 2>/dev/null || true
  fi
}

mark_synced() {
  date -u +%Y-%m-%dT%H:%M:%SZ > "$HOST_CONTENT/.persisted-from-container" 2>/dev/null || true
  # Evita que o proximo boot ache o volume "desatualizado" e sobrescreva com host velho
  date -u +%Y-%m-%dT%H:%M:%SZ > "$SRC_CONTENT/.synced-from-host" 2>/dev/null || true
  echo "[persist] OK (${MODE}${2:+ $*})"
}

case "$MODE" in
  content)
    for t in plugins themes mu-plugins languages; do
      persist_tree "$t"
    done
    mark_synced
    ;;
  languages)
    persist_tree languages
    mark_synced
    ;;
  plugin)
    shift
    if [ "$#" -eq 0 ]; then
      echo "[persist] usage: plugin <slug|file>" >&2
      exit 1
    fi
    for p in "$@"; do
      persist_plugin "$p"
    done
    mark_synced
    ;;
  theme)
    shift
    if [ "$#" -eq 0 ]; then
      echo "[persist] usage: theme <slug>" >&2
      exit 1
    fi
    for t in "$@"; do
      persist_theme "$t"
    done
    mark_synced
    ;;
  core)
    persist_core
    mark_synced
    ;;
  all)
    for t in plugins themes mu-plugins languages; do
      persist_tree "$t"
    done
    persist_core
    mark_synced
    ;;
  *)
    echo "[persist] modo invalido: $MODE (content|core|all|plugin|theme|languages)" >&2
    exit 1
    ;;
esac
