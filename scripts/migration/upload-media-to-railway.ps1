<#
.SYNOPSIS
  Envia midia local para o volume wp-uploads no Railway (sem nested year/year).
#>
param(
  [string[]]$Dirs = @("2016", "2017", "otwcache", "sites"),
  [string]$Volume = "wp-uploads",
  [string]$UploadsRoot = "wordpress\wp-content\uploads"
)

$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
Set-Location $Root
# Volume atual em producao (apos upgrade Hobby)
if ($Volume -eq "wp-uploads") { $Volume = "wp-uploads-5g" }
$env:RAILWAY_CALLER = "skill:use-railway@1.6.1"

foreach ($d in $Dirs) {
  $local = Join-Path $UploadsRoot $d
  if (-not (Test-Path $local)) {
    Write-Host "==> skip missing $d"
    continue
  }
  $size = (Get-ChildItem $local -Recurse -File -EA SilentlyContinue | Measure-Object Length -Sum).Sum
  Write-Host ("==> upload {0} ({1:N1} MB) -> /{0}" -f $d, ($size / 1MB))

  # Envia conteudo da pasta para /$d (evita /$d/$d)
  $staging = Join-Path $env:TEMP ("ccd-upload-" + $d)
  if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }
  New-Item -ItemType Directory -Path $staging | Out-Null
  Copy-Item -Path (Join-Path $local "*") -Destination $staging -Recurse -Force

  $prev = $ErrorActionPreference
  $ErrorActionPreference = "Continue"
  & railway volume files -v $Volume upload $staging "/$d" --overwrite --json 2>&1 | Out-Host
  $code = $LASTEXITCODE
  $ErrorActionPreference = $prev
  if ($code -ne 0) { throw "upload falhou: $d (exit $code)" }
  Remove-Item $staging -Recurse -Force -EA SilentlyContinue
  Write-Host "==> ok $d"
}

Write-Host "==> removendo .htaccess de fallback (se existir)"
$prev = $ErrorActionPreference
$ErrorActionPreference = "Continue"
& railway volume files -v $Volume delete "/.htaccess" --yes --json 2>&1 | Out-Host
$ErrorActionPreference = $prev

Write-Host "Pronto."
