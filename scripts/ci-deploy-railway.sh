#!/usr/bin/env bash
# Usado pelo GitHub Actions: sobe db + wordpress no Railway sem passos manuais.
set -euo pipefail

: "${RAILWAY_TOKEN:?}"
: "${RAILWAY_PROJECT_ID:?}"
: "${MYSQL_PASSWORD:?}"
: "${MYSQL_ROOT_PASSWORD:?}"
RAILWAY_ENVIRONMENT="${RAILWAY_ENVIRONMENT:-production}"

railway link \
  --project "$RAILWAY_PROJECT_ID" \
  --environment "$RAILWAY_ENVIRONMENT" \
  --json >/dev/null

ensure_service() {
  local name="$1"
  if ! railway service list --json 2>/dev/null | grep -qiE "\"name\"[[:space:]]*:[[:space:]]*\"${name}\""; then
    echo "Criando servico ${name}..."
    railway add --service "$name" --json || railway add --service "$name"
  else
    echo "Servico ${name} ja existe"
  fi
}

ensure_service db
ensure_service wordpress

railway volume add --service db --mount-path /var/lib/mysql --json || echo "Volume db ok/ja existe"

railway variable set --service db --skip-deploys \
  "MYSQL_DATABASE=wordpress" \
  "MYSQL_USER=wpapp" \
  "MYSQL_PASSWORD=${MYSQL_PASSWORD}" \
  "MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}"

railway variable set --service wordpress --skip-deploys \
  "PORT=80" \
  "WORDPRESS_DB_HOST=db:3306" \
  "WORDPRESS_DB_USER=wpapp" \
  "WORDPRESS_DB_PASSWORD=${MYSQL_PASSWORD}" \
  "WORDPRESS_DB_NAME=wordpress"

deploy_with_dockerfile() {
  local service="$1"
  local dockerfile="$2"
  local health="${3:-/}"

  cat > railway.toml <<EOF
[build]
builder = "DOCKERFILE"
dockerfilePath = "${dockerfile}"

[deploy]
healthcheckPath = "${health}"
healthcheckTimeout = 300
restartPolicyType = "ON_FAILURE"
restartPolicyMaxRetries = 5
EOF

  echo "=== Deploy ${service} (${dockerfile}) ==="
  cat railway.toml
  railway up \
    --service "$service" \
    --project "$RAILWAY_PROJECT_ID" \
    --environment "$RAILWAY_ENVIRONMENT" \
    --ci \
    --detach
}

deploy_with_dockerfile db Dockerfile.mysql "/"
deploy_with_dockerfile wordpress Dockerfile "/wp-login.php"

git checkout -- railway.toml 2>/dev/null || true

railway domain --service wordpress --port 80 \
  || railway domain --service wordpress \
  || true

echo "Deploy db + wordpress disparado."
