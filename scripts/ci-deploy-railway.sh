#!/usr/bin/env bash
# GitHub Actions: deploy com PROJECT TOKEN (RAILWAY_TOKEN).
# Project tokens so fazem deploy — nao usam whoami/link/add.
set -euo pipefail

# Garante que nao ha API token atrapalhando
unset RAILWAY_API_TOKEN || true

: "${RAILWAY_TOKEN:?Defina o secret RAILWAY_TOKEN (Project Token do projeto Railway)}"
: "${MYSQL_PASSWORD:?}"
: "${MYSQL_ROOT_PASSWORD:?}"

# Remove espacos/quebra de linha acidentais ao colar no GitHub
RAILWAY_TOKEN="$(printf '%s' "$RAILWAY_TOKEN" | tr -d '[:space:]')"
export RAILWAY_TOKEN

echo "Usando Project Token (RAILWAY_TOKEN). Escopo ja inclui projeto/environment."

# Variaveis (project token costuma aceitar variable set)
railway variable set --service db --skip-deploys \
  "MYSQL_DATABASE=wordpress" \
  "MYSQL_USER=wpapp" \
  "MYSQL_PASSWORD=${MYSQL_PASSWORD}" \
  "MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}" \
  || echo "Aviso: nao foi possivel setar vars do db (crie/ajuste no painel se falhar)"

railway variable set --service wordpress --skip-deploys \
  "PORT=80" \
  "WORDPRESS_DB_HOST=db:3306" \
  "WORDPRESS_DB_USER=wpapp" \
  "WORDPRESS_DB_PASSWORD=${MYSQL_PASSWORD}" \
  "WORDPRESS_DB_NAME=wordpress" \
  || echo "Aviso: nao foi possivel setar vars do wordpress"

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
  # Project token ja aponta para o projeto/environment — nao passar --project
  railway up --service "$service" --ci --detach
}

deploy_with_dockerfile db Dockerfile.mysql "/"
deploy_with_dockerfile wordpress Dockerfile "/wp-login.php"

git checkout -- railway.toml 2>/dev/null || true

railway domain --service wordpress --port 80 2>/dev/null \
  || railway domain --service wordpress 2>/dev/null \
  || echo "Dominio: gere no painel do servico wordpress se ainda nao existir."

echo "Deploy db + wordpress disparado."
