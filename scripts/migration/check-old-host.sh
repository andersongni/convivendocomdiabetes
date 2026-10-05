#!/bin/bash
set -euo pipefail
TARGET='https://convivendocomdiabetes-production.up.railway.app'
for u in \
  'https://www.convivendocomdiabetes.com' \
  'http://www.convivendocomdiabetes.com' \
  'https://convivendocomdiabetes.com' \
  'http://convivendocomdiabetes.com'
do
  echo "=== $u ==="
  wp search-replace "$u" "$TARGET" --all-tables --skip-columns=guid --dry-run --allow-root --path=/var/www/html 2>/dev/null | tail -2
done
