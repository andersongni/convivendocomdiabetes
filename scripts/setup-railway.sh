#!/usr/bin/env bash
# Setup unico (roda no seu PC). Depois disso, todo push na main faz deploy sozinho.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> Instalando Railway CLI (se preciso)"
if ! command -v railway >/dev/null 2>&1; then
  npm install -g @railway/cli
fi

echo "==> Login no Railway (navegador)"
railway login

echo "==> Criando/ligando projeto"
if [ -n "${RAILWAY_PROJECT_ID:-}" ]; then
  railway link --project "$RAILWAY_PROJECT_ID"
else
  echo "Se o projeto ja existe, escolha-o. Senao, crie um novo quando pedido."
  railway init || railway link
fi

PROJECT_ID="$(railway status --json 2>/dev/null | sed -n 's/.*"projectId":"\([^"]*\)".*/\1/p' | head -1 || true)"
if [ -z "$PROJECT_ID" ]; then
  PROJECT_ID="$(railway status 2>/dev/null | sed -n 's/.*Project[[:space:]]*ID:[[:space:]]*//p' | head -1 || true)"
fi

echo "==> Gerando senhas fortes"
MYSQL_PASSWORD="$(openssl rand -hex 16)"
MYSQL_ROOT_PASSWORD="$(openssl rand -hex 16)"
TOKEN_HINT="crie em https://railway.app/account/tokens"

echo ""
echo "============================================================"
echo " Configure estes secrets no GitHub (repo > Settings > Secrets):"
echo ""
echo "  RAILWAY_TOKEN          = (Account/Project Token) ${TOKEN_HINT}"
echo "  RAILWAY_PROJECT_ID     = ${PROJECT_ID:-"(rode: railway status)"}"
echo "  MYSQL_PASSWORD         = ${MYSQL_PASSWORD}"
echo "  MYSQL_ROOT_PASSWORD    = ${MYSQL_ROOT_PASSWORD}"
echo "============================================================"
echo ""

if command -v gh >/dev/null 2>&1; then
  read -r -p "Colar RAILWAY_TOKEN agora para gravar os secrets via gh? (deixe vazio para pular): " RAILWAY_TOKEN || true
  if [ -n "${RAILWAY_TOKEN:-}" ]; then
    gh secret set RAILWAY_TOKEN --body "$RAILWAY_TOKEN"
    [ -n "${PROJECT_ID:-}" ] && gh secret set RAILWAY_PROJECT_ID --body "$PROJECT_ID"
    gh secret set MYSQL_PASSWORD --body "$MYSQL_PASSWORD"
    gh secret set MYSQL_ROOT_PASSWORD --body "$MYSQL_ROOT_PASSWORD"
    echo "==> Secrets gravados no GitHub."
  fi
else
  echo "Dica: instale GitHub CLI (gh) para gravar secrets automaticamente."
fi

echo "==> Primeiro deploy do stack"
export MYSQL_PASSWORD MYSQL_ROOT_PASSWORD
cp docker-compose.yml docker-compose.local.yml
cp docker-compose.railway.yml docker-compose.yml
railway up --detach
mv docker-compose.local.yml docker-compose.yml

echo "==> Gerando dominio publico"
railway domain --service wordpress || railway domain || true

echo ""
echo "Pronto. Proximos deploys: git push origin main"
echo "URL: railway domain / painel Railway -> servico wordpress -> Settings -> Networking"
