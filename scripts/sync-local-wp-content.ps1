# Forca re-sincronizar plugins/themes do host para o volume rapido do Docker.
# Uso: .\scripts\sync-local-wp-content.ps1

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

Write-Host "==> Forcando sync wp-content (host -> volume Linux)..."
docker compose exec -e SYNC_WP_CONTENT=1 -T wordpress bash /usr/local/bin/sync-wp-content.sh
if ($LASTEXITCODE -ne 0) {
  Write-Host "Container nao esta rodando; recriando com sync..."
  $env:SYNC_WP_CONTENT = "1"
  docker compose up -d --force-recreate wordpress
  Remove-Item Env:SYNC_WP_CONTENT -ErrorAction SilentlyContinue
}

Write-Host "==> Aquecendo cache da home..."
Start-Sleep -Seconds 1
curl.exe -s -o NUL -m 60 "http://localhost:8080/" | Out-Null
curl.exe -s -o NUL -m 30 "http://localhost:8080/" | Out-Null

Write-Host "==> Pronto. Abra http://localhost:8080/"
