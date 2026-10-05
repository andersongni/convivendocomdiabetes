<#
.SYNOPSIS
  Envia midia local para o volume wp-uploads no Railway.

  O CLI coloca o nome da pasta local dentro do REMOTE_PATH.
  Por isso enviamos cada ano para "/" (vira /2016, /2017, ...).
#>
param(
  [string[]]$Dirs = @("2015", "2016", "2017", "2018", "2019", "2020", "2021", "2022", "2026", "sites", "otwcache", "ccd-avatars"),
  [string]$Volume = "wp-uploads",
  [string]$UploadsRoot = "wordpress\wp-content\uploads",
  [int]$Concurrency = 16
)

$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
Set-Location $Root
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

  $attempt = 0
  $ok = $false
  while (-not $ok -and $attempt -lt 3) {
    $attempt++
    $prev = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    & railway volume files -v $Volume upload $local "/" --overwrite --concurrency $Concurrency --json 2>&1 | Out-Host
    $code = $LASTEXITCODE
    $ErrorActionPreference = $prev
    if ($code -eq 0) {
      $ok = $true
    } else {
      Write-Host "==> retry $d (attempt $attempt failed, exit $code)"
      Start-Sleep -Seconds 5
    }
  }
  if (-not $ok) { throw "upload falhou: $d" }
  Write-Host "==> ok $d"
}

# Limpa pastas aninhadas de tentativas antigas do script
$nestedJunk = @(
  "/2015/ccd-upload-2015", "/2016/ccd-upload-2016", "/2017/ccd-upload-2017",
  "/2018/ccd-upload-2018", "/2019/ccd-upload-2019", "/2020/ccd-upload-2020",
  "/2021/ccd-upload-2021", "/2022/ccd-upload-2022", "/2021/2021", "/2022/2022"
)
Write-Host "==> limpando pastas aninhadas antigas (se existirem)"
foreach ($p in $nestedJunk) {
  $prev = $ErrorActionPreference
  $ErrorActionPreference = "Continue"
  & railway volume files -v $Volume delete $p --yes --json 2>&1 | Out-Null
  $ErrorActionPreference = $prev
}

Write-Host "==> removendo .htaccess de fallback (se existir; ignore se o CLI negar)"
$prev = $ErrorActionPreference
$ErrorActionPreference = "Continue"
& railway volume files -v $Volume delete "/.htaccess" --yes --json 2>&1 | Out-Host
$ErrorActionPreference = $prev

Write-Host "Pronto."
exit 0
