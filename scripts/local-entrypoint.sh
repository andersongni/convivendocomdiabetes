#!/bin/bash
set -euo pipefail

# Garante LF mesmo se o arquivo veio do Windows
if [ -f /usr/local/bin/sync-wp-content.sh ]; then
  sed -i 's/\r$//' /usr/local/bin/sync-wp-content.sh 2>/dev/null || true
  bash /usr/local/bin/sync-wp-content.sh
fi

exec docker-entrypoint.sh "$@"
