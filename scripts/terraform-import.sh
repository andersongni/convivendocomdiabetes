#!/usr/bin/env bash
# Importa o projeto Railway existente para o state do Terraform (uso no GitHub Actions).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/terraform"

if [[ -z "${RAILWAY_TOKEN:-}" && -n "${RAILWAY_API_TOKEN:-}" ]]; then
  export RAILWAY_TOKEN="$RAILWAY_API_TOKEN"
fi
if [[ -z "${RAILWAY_TOKEN:-}" ]]; then
  echo "Defina RAILWAY_TOKEN ou RAILWAY_API_TOKEN."
  exit 1
fi

PROJECT_ID="${TF_IMPORT_PROJECT_ID:-95fcab4e-8a01-4af2-af47-23407e2f66c9}"
WORDPRESS_ID="${TF_IMPORT_WORDPRESS_ID:-deda0a95-a736-451c-bfa0-ce04e143b98c}"
MYSQL_ID="${TF_IMPORT_MYSQL_ID:-f9d67b4c-25c6-4452-b896-130ba077997e}"
ENV_NAME="${TF_IMPORT_ENV_NAME:-production}"
DOMAIN="${TF_IMPORT_DOMAIN:-convivendocomdiabetes-production.up.railway.app}"

terraform init -input=false

import_one() {
  local addr="$1"
  local id="$2"
  if terraform state show "$addr" >/dev/null 2>&1; then
    echo "Ja no state: $addr"
    return 0
  fi
  echo "Importando $addr <- $id"
  terraform import -input=false "$addr" "$id"
}

import_one "railway_project.this" "$PROJECT_ID"
import_one "railway_service.wordpress" "$WORDPRESS_ID"
import_one "railway_service.mysql" "$MYSQL_ID"
import_one "railway_service_domain.wordpress" "${WORDPRESS_ID}:${ENV_NAME}:${DOMAIN}"

while IFS=: read -r tf_name env_name; do
  [[ -z "$tf_name" ]] && continue
  import_one "railway_variable.${tf_name}" "${WORDPRESS_ID}:${ENV_NAME}:${env_name}"
done <<'EOF'
wordpress_db_host:WORDPRESS_DB_HOST
wordpress_db_user:WORDPRESS_DB_USER
wordpress_db_password:WORDPRESS_DB_PASSWORD
wordpress_db_name:WORDPRESS_DB_NAME
wp_admin_user:WP_ADMIN_USER
wp_admin_password:WP_ADMIN_PASSWORD
wp_admin_email:WP_ADMIN_EMAIL
wp_title:WP_TITLE
EOF

echo "Import concluido."
