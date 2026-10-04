# Rode UMA VEZ no seu PC (abre o browser do Railway).
# Cria db + wordpress + volume. Depois o GitHub Actions so faz deploy.
$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)

Write-Host "==> Instalando Railway CLI"
npm install -g @railway/cli | Out-Null

Write-Host "==> Login no browser"
railway login

Write-Host "==> Ligando ao projeto convivendocomdiabetes"
railway link --project 95fcab4e-8a01-4af2-af47-23407e2f66c9

# Environment: tenta production; se falhar, link interativo
try {
  railway environment production 2>$null
} catch {
  Write-Host "Selecione o environment quando pedido."
  railway environment
}

function Ensure-Service($name) {
  $json = railway service list --json 2>$null
  if ($json -and ($json -match "`"$name`"")) {
    Write-Host "Servico $name ja existe"
  } else {
    Write-Host "Criando servico $name..."
    railway add --service $name
  }
}

Ensure-Service "db"
Ensure-Service "wordpress"

Write-Host "==> Volume MySQL"
railway volume add --service db --mount-path /var/lib/mysql 2>$null
if ($LASTEXITCODE -ne 0) { Write-Host "Volume ja existe ou sera criado no painel." }

$mysqlPassword = -join ((1..32) | ForEach-Object { "{0:x}" -f (Get-Random -Max 16) })
$mysqlRootPassword = -join ((1..32) | ForEach-Object { "{0:x}" -f (Get-Random -Max 16) })

railway variable set --service db --skip-deploys `
  "MYSQL_DATABASE=wordpress" `
  "MYSQL_USER=wpapp" `
  "MYSQL_PASSWORD=$mysqlPassword" `
  "MYSQL_ROOT_PASSWORD=$mysqlRootPassword"

railway variable set --service wordpress --skip-deploys `
  "PORT=80" `
  "WORDPRESS_DB_HOST=db:3306" `
  "WORDPRESS_DB_USER=wpapp" `
  "WORDPRESS_DB_PASSWORD=$mysqlPassword" `
  "WORDPRESS_DB_NAME=wordpress"

Write-Host "==> Tentando gerar dominio"
railway domain --service wordpress --port 80 2>$null
if ($LASTEXITCODE -ne 0) { railway domain --service wordpress 2>$null }

$secretsUrl = "https://github.com/andersongni/convivendocomdiabetes/settings/secrets/actions"
$projectTokens = "https://railway.app/project/95fcab4e-8a01-4af2-af47-23407e2f66c9/settings"

Write-Host ""
Write-Host "============================================================"
Write-Host " AGORA CRIE O PROJECT TOKEN (nao Account Token):"
Write-Host " 1) Abra o projeto no Railway"
Write-Host " 2) Settings do PROJETO (nao Account) -> Tokens"
Write-Host " 3) Create Token (environment production)"
Write-Host " 4) Cole no GitHub como RAILWAY_TOKEN"
Write-Host ""
Write-Host " Secrets GitHub ($secretsUrl):"
Write-Host "   RAILWAY_TOKEN          = (Project Token que voce criar)"
Write-Host "   MYSQL_PASSWORD         = $mysqlPassword"
Write-Host "   MYSQL_ROOT_PASSWORD    = $mysqlRootPassword"
Write-Host ""
Write-Host " Apague RAILWAY_API_TOKEN e RAILWAY_PROJECT_ID se existirem"
Write-Host " (nao sao mais necessarios neste fluxo)."
Write-Host "============================================================"
Write-Host ""

Start-Process $secretsUrl
Start-Process "https://railway.app"
