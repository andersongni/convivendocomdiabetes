#!/bin/bash
set -euo pipefail

# Garante LF mesmo se o arquivo veio do Windows
if [ -f /usr/local/bin/sync-wp-content.sh ]; then
  sed -i 's/\r$//' /usr/local/bin/sync-wp-content.sh 2>/dev/null || true
  bash /usr/local/bin/sync-wp-content.sh
fi

# Erros HTTP amigaveis (ErrorDocument no conf de performance)
a2enmod rewrite >/dev/null 2>&1 || true
a2enconf performance >/dev/null 2>&1 || true

exec docker-entrypoint.sh "$@"
