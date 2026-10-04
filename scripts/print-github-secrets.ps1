# Gera senhas e mostra o que colar nos Secrets do GitHub (2 containers automaticos).
$ErrorActionPreference = "Stop"

$mysqlPassword = -join ((1..32) | ForEach-Object { "{0:x}" -f (Get-Random -Max 16) })
$mysqlRootPassword = -join ((1..32) | ForEach-Object { "{0:x}" -f (Get-Random -Max 16) })
$projectId = "95fcab4e-8a01-4af2-af47-23407e2f66c9"
$secretsUrl = "https://github.com/andersongni/convivendocomdiabetes/settings/secrets/actions"
$tokenUrl = "https://railway.app/account/tokens"

Write-Host ""
Write-Host "Stack automatica: 2 containers (db + wordpress)"
Write-Host ""
Write-Host "1) Token Railway: $tokenUrl"
Write-Host "2) Secrets GitHub: $secretsUrl"
Write-Host ""
Write-Host "RAILWAY_TOKEN          = (cole o token)"
Write-Host "RAILWAY_PROJECT_ID     = $projectId"
Write-Host "RAILWAY_ENVIRONMENT    = production"
Write-Host "MYSQL_PASSWORD         = $mysqlPassword"
Write-Host "MYSQL_ROOT_PASSWORD    = $mysqlRootPassword"
Write-Host ""
Write-Host "3) Actions -> Deploy -> Run workflow"
Write-Host "   O Action cria db + wordpress, volume, vars, dump e dominio."
Write-Host ""

Start-Process $tokenUrl
Start-Process $secretsUrl
