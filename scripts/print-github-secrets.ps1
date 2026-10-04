# Gera senhas e mostra o que colar nos Secrets do GitHub.
$ErrorActionPreference = "Stop"

$mysqlPassword = -join ((1..32) | ForEach-Object { "{0:x}" -f (Get-Random -Max 16) })
$mysqlRootPassword = -join ((1..32) | ForEach-Object { "{0:x}" -f (Get-Random -Max 16) })
$projectId = "95fcab4e-8a01-4af2-af47-23407e2f66c9"
$secretsUrl = "https://github.com/andersongni/convivendocomdiabetes/settings/secrets/actions"
$tokenUrl = "https://railway.app/account/tokens"

Write-Host ""
Write-Host "=== TOKEN CERTO (obrigatorio) ==="
Write-Host "1) Abra: $tokenUrl"
Write-Host "2) Create Token"
Write-Host "3) NAO selecione workspace / NAO use Project Token"
Write-Host "4) Tem que ser Account token"
Write-Host ""
Write-Host "=== Secrets no GitHub ==="
Write-Host $secretsUrl
Write-Host ""
Write-Host "RAILWAY_API_TOKEN      = (cole o Account token)"
Write-Host "RAILWAY_PROJECT_ID     = $projectId"
Write-Host "RAILWAY_ENVIRONMENT    = production"
Write-Host "MYSQL_PASSWORD         = $mysqlPassword"
Write-Host "MYSQL_ROOT_PASSWORD    = $mysqlRootPassword"
Write-Host ""
Write-Host "Apague o secret antigo RAILWAY_TOKEN se existir (ele atrapalha)."
Write-Host "Depois: Actions -> Deploy -> Run workflow"
Write-Host ""

Start-Process $tokenUrl
Start-Process $secretsUrl
