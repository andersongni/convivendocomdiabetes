# Setup unico no Windows. Depois, todo push na main faz deploy sozinho.
$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)

Write-Host "==> Instalando Railway CLI"
npm install -g @railway/cli | Out-Null

Write-Host "==> Login no Railway (navegador)"
railway login

Write-Host "==> Ligando projeto (escolha convivendocomdiabetes ou crie um novo)"
railway link

$status = railway status 2>&1 | Out-String
Write-Host $status

$mysqlPassword = -join ((1..32) | ForEach-Object { "{0:x}" -f (Get-Random -Max 16) })
$mysqlRootPassword = -join ((1..32) | ForEach-Object { "{0:x}" -f (Get-Random -Max 16) })

Write-Host ""
Write-Host "============================================================"
Write-Host " Configure estes secrets no GitHub (Settings > Secrets):"
Write-Host ""
Write-Host "  RAILWAY_TOKEN          = https://railway.app/account/tokens"
Write-Host "  RAILWAY_PROJECT_ID     = (Project Settings > General > Project ID)"
Write-Host "  MYSQL_PASSWORD         = $mysqlPassword"
Write-Host "  MYSQL_ROOT_PASSWORD    = $mysqlRootPassword"
Write-Host "============================================================"
Write-Host ""

$env:MYSQL_PASSWORD = $mysqlPassword
$env:MYSQL_ROOT_PASSWORD = $mysqlRootPassword

Write-Host "==> Primeiro deploy"
Copy-Item docker-compose.yml docker-compose.local.yml -Force
Copy-Item docker-compose.railway.yml docker-compose.yml -Force
railway up --detach
Move-Item docker-compose.local.yml docker-compose.yml -Force
Write-Host "==> Gerando dominio"
try { railway domain --service wordpress } catch { try { railway domain } catch { Write-Host "Gere o dominio no painel se a CLI falhar." } }

Write-Host ""
Write-Host "Pronto. Proximos deploys: git push origin main"
