# Limpa cache/redirect/cookies do Edge e Chrome nos dominios do projeto CCD.
# Uso (PowerShell):
#   .\scripts\clear-browser-site-cache.ps1
#   .\scripts\clear-browser-site-cache.ps1 -Yes   # sem prompts
#
# Feche abas importantes antes: o script encerra Edge/Chrome.

[CmdletBinding()]
param(
  [switch]$Yes
)

$ErrorActionPreference = "Stop"

$Domains = @(
  "localhost",
  "127.0.0.1",
  "convivendocomdiabetes-production.up.railway.app",
  "convivendocomdiabetes.com.br",
  "www.convivendocomdiabetes.com.br",
  "convivendocomdiabetes.com",
  "www.convivendocomdiabetes.com",
  "hostinger.titan.email",
  "mail.titan.email"
)

# Pads usados em nomes de pastas do Chromium (IndexedDB, Storage, etc.)
$DomainMatchers = @(
  "localhost",
  "127.0.0.1",
  "convivendocomdiabetes-production\.up\.railway\.app",
  "convivendocomdiabetes\.com\.br",
  "convivendocomdiabetes\.com",
  "hostinger\.titan\.email",
  "mail\.titan\.email",
  "up\.railway\.app"
)

$Browsers = @(
  @{ Name = "Edge";   Root = Join-Path $env:LOCALAPPDATA "Microsoft\Edge\User Data"; Process = "msedge" },
  @{ Name = "Chrome"; Root = Join-Path $env:LOCALAPPDATA "Google\Chrome\User Data";  Process = "chrome" }
)

function Write-Step([string]$Message) {
  Write-Host "==> $Message"
}

function Confirm-OrExit([string]$Message) {
  if ($Yes) { return }
  $r = Read-Host "$Message [s/N]"
  if ($r -notmatch '^[sS]') {
    Write-Host "Cancelado."
    exit 1
  }
}

function Get-ChromeHostHash([string]$HostName) {
  $sha = [System.Security.Cryptography.SHA256]::Create()
  try {
    $bytes = $sha.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($HostName.ToLowerInvariant()))
    return [Convert]::ToBase64String($bytes)
  } finally {
    $sha.Dispose()
  }
}

function Clear-TransportSecurity([string]$FilePath) {
  if (-not (Test-Path -LiteralPath $FilePath)) { return 0 }
  $raw = Get-Content -LiteralPath $FilePath -Raw -ErrorAction SilentlyContinue
  if ([string]::IsNullOrWhiteSpace($raw)) { return 0 }

  try {
    $json = $raw | ConvertFrom-Json -ErrorAction Stop
  } catch {
    return 0
  }

  $removed = 0
  foreach ($d in $Domains) {
    $key = Get-ChromeHostHash $d
    if ($null -ne $json.PSObject.Properties[$key]) {
      $json.PSObject.Properties.Remove($key)
      $removed++
    }
  }

  if ($removed -gt 0) {
    $json | ConvertTo-Json -Depth 20 -Compress | Set-Content -LiteralPath $FilePath -Encoding UTF8
  }
  return $removed
}

function Clear-ChromiumCookies([string]$CookiesDb) {
  if (-not (Test-Path -LiteralPath $CookiesDb)) { return 0 }

  $sqlite = Get-Command sqlite3 -ErrorAction SilentlyContinue
  if (-not $sqlite) {
    Write-Host "    sqlite3 nao encontrado - pulando Cookies DB; cache HTTP/HSTS ainda sao limpos"
    return 0
  }

  $clauses = New-Object System.Collections.Generic.List[string]
  foreach ($d in $Domains) {
    $esc = $d.Replace("'", "''")
    $dot = "." + $esc
    $like = "%." + $esc
    $clauses.Add(("host_key = '{0}' OR host_key = '{1}' OR host_key LIKE '{2}'" -f $esc, $dot, $like))
  }
  $where = [string]::Join(" OR ", $clauses)
  $sql = "DELETE FROM cookies WHERE $where; SELECT changes();"

  $out = & sqlite3 $CookiesDb $sql 2>$null
  $n = 0
  [void][int]::TryParse(("" + $out).Trim(), [ref]$n)
  return $n
}

function Test-NameMatch([string]$Name) {
  foreach ($m in $DomainMatchers) {
    if ($Name -match $m) { return $true }
  }
  return $false
}

function Clear-DomainNamedDirs([string]$ProfileDir) {
  $removed = 0
  $roots = @(
    (Join-Path $ProfileDir "IndexedDB"),
    (Join-Path $ProfileDir "Local Storage"),
    (Join-Path $ProfileDir "Session Storage"),
    (Join-Path $ProfileDir "Service Worker\CacheStorage"),
    (Join-Path $ProfileDir "Service Worker\ScriptCache"),
    (Join-Path $ProfileDir "Storage\default")
  )

  foreach ($root in $roots) {
    if (-not (Test-Path -LiteralPath $root)) { continue }
    Get-ChildItem -LiteralPath $root -Force -ErrorAction SilentlyContinue | ForEach-Object {
      if (Test-NameMatch $_.Name) {
        Remove-Item -LiteralPath $_.FullName -Recurse -Force -ErrorAction SilentlyContinue
        $removed++
      }
    }
  }
  return $removed
}

function Clear-HttpCache([string]$ProfileDir) {
  $targets = @(
    (Join-Path $ProfileDir "Cache\Cache_Data"),
    (Join-Path $ProfileDir "Cache\No_Vary_Search"),
    (Join-Path $ProfileDir "Code Cache"),
    (Join-Path $ProfileDir "GPUCache"),
    (Join-Path $ProfileDir "Network\Http Cache")
  )
  $count = 0
  foreach ($t in $targets) {
    if (Test-Path -LiteralPath $t) {
      Get-ChildItem -LiteralPath $t -Force -ErrorAction SilentlyContinue |
        Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
      $count++
    }
  }
  return $count
}

Write-Host "Dominios alvo:"
$Domains | ForEach-Object { Write-Host "  - $_" }
Write-Host ""

Confirm-OrExit "Isso vai FECHAR Edge/Chrome e limpar cache/dados desses sites. Continuar?"

Write-Step "Limpando cache DNS do Windows..."
try { Clear-DnsClientCache } catch { Write-Host "  (Clear-DnsClientCache falhou: $($_.Exception.Message))" }

Write-Step "Encerrando navegadores..."
foreach ($b in $Browsers) {
  Get-Process -Name $b.Process -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
}
Start-Sleep -Seconds 2

foreach ($b in $Browsers) {
  if (-not (Test-Path -LiteralPath $b.Root)) {
    Write-Host "-- $($b.Name): perfil nao encontrado, pulando"
    continue
  }

  Write-Step "$($b.Name): limpando perfis em $($b.Root)"
  $profiles = Get-ChildItem -LiteralPath $b.Root -Directory -Force |
    Where-Object { $_.Name -eq "Default" -or $_.Name -like "Profile *" }

  foreach ($p in $profiles) {
    Write-Host "  Perfil: $($p.Name)"
    $cacheDirs = Clear-HttpCache $p.FullName
    $named = Clear-DomainNamedDirs $p.FullName
    $hsts = Clear-TransportSecurity (Join-Path $p.FullName "TransportSecurity")
    $cookiesPath = Join-Path $p.FullName "Network\Cookies"
    if (-not (Test-Path -LiteralPath $cookiesPath)) {
      $cookiesPath = Join-Path $p.FullName "Cookies"
    }
    $cookies = Clear-ChromiumCookies $cookiesPath
    Write-Host "    cache dirs tocados=$cacheDirs | pastas site=$named | HSTS removidos=$hsts | cookies deletados=$cookies"
  }
}

Write-Step "Pronto."
Write-Host ""
Write-Host "Teste agora (de preferencia aba anonima):"
Write-Host "  https://convivendocomdiabetes-production.up.railway.app/"
Write-Host "  http://localhost:8080/"
Write-Host ""
Write-Host "Dica: se o 301 ainda aparecer, abra Ctrl+Shift+N e cole a URL."
Write-Host "Opcional: instale sqlite3 no PATH para limpar cookies com precisao."
