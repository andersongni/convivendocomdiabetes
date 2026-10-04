# Gera senhas e mostra exatamente o que colar nos Secrets do GitHub.
$ErrorActionPreference = "Stop"

$mysqlPassword = -join ((1..32) | ForEach-Object { "{0:x}" -f (Get-Random -Max 16) })
$mysqlRootPassword = -join ((1..32) | ForEach-Object { "{0:x}" -f (Get-Random -Max 16) })
$projectId = "95fcab4e-8a01-4af2-af47-23407e2f66c9"
$secretsUrl = "https://github.com/andersongni/convivendocomdiabetes/settings/secrets/actions"
$tokenUrl = "https://railway.app/account/tokens"

Write-Host ""
Write-Host "1) Crie o token: $tokenUrl"
Write-Host "2) Abra os secrets: $secretsUrl"
Write-Host "3) Clique New repository secret e cadastre:"
Write-Host ""
Write-Host "Name: RAILWAY_TOKEN"
Write-Host "Value: (cole o token do passo 1)"
Write-Host ""
Write-Host "Name: RAILWAY_PROJECT_ID"
Write-Host "Value: $projectId"
Write-Host ""
Write-Host "Name: RAILWAY_ENVIRONMENT"
Write-Host "Value: production"
Write-Host ""
Write-Host "Name: MYSQL_PASSWORD"
Write-Host "Value: $mysqlPassword"
Write-Host ""
Write-Host "Name: MYSQL_ROOT_PASSWORD"
Write-Host "Value: $mysqlRootPassword"
Write-Host ""
Write-Host "4) Depois rode o workflow: Actions -> Deploy -> Run workflow"
Write-Host "   ou: git commit --allow-empty -m `"deploy`" ; git push"
Write-Host ""

Start-Process $tokenUrl
Start-Process $secretsUrl
