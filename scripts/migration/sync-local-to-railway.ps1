<#
.SYNOPSIS
  Copia o WordPress local (dump + opcionalmente midia) para o Railway com seguranca.

.DESCRIPTION
  1) Exporta o MySQL local (ou usa dump-database.sql)
  2) Abre tunel SSH para o MySQL do Railway (sem TCP proxy publico)
  3) Importa o dump e reescreve URLs para o dominio Railway
  4) Opcional: empacota uploads e sobe para volume/servico

.EXAMPLE
  .\scripts\migration\sync-local-to-railway.ps1

.EXAMPLE
  .\scripts\migration\sync-local-to-railway.ps1 -SyncUploads
#>
param(
  [string]$SiteUrl = "https://convivendocomdiabetes.com",
  [string]$DumpPath = "",
  [switch]$UseExistingDump,
  [switch]$SyncUploads,
  [int]$TunnelPort = 3307
)

$ErrorActionPreference = "Stop"
# scripts/migration -> raiz do repo
$Root = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
Set-Location $Root

function Assert-Cmd($name) {
  if (-not (Get-Command $name -ErrorAction SilentlyContinue)) {
    throw "Comando nao encontrado: $name"
  }
}

function Invoke-Railway {
  param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Args)
  # CLI escreve warnings no stderr; no PowerShell isso vira erro nativo se Stop.
  $prev = $ErrorActionPreference
  $ErrorActionPreference = "Continue"
  try {
    $out = & railway @Args 2>&1
    $text = ($out | ForEach-Object { "$_" }) -join "`n"
    return $text
  } finally {
    $ErrorActionPreference = $prev
  }
}

Assert-Cmd docker
Assert-Cmd railway

Write-Host "==> Checando chaves SSH no Railway..."
$keys = Invoke-Railway ssh keys list
if ($keys -notmatch "Fingerprint:") {
  Write-Host "Nenhuma chave registrada. Tentando importar do GitHub..."
  Invoke-Railway ssh keys github | Out-Host
  $keys = Invoke-Railway ssh keys list
  if ($keys -notmatch "Fingerprint:") {
    throw "Registre uma chave: railway ssh keys add -k `$env:USERPROFILE\.ssh\railway_ed25519.pub"
  }
} else {
  Write-Host "Chave SSH ja registrada."
}

# Credenciais MySQL do Railway
Write-Host "==> Lendo variaveis do MySQL no Railway..."
$mysqlKv = Invoke-Railway variable list --service MySQL --kv
$map = @{}
foreach ($line in ($mysqlKv -split "`n")) {
  if ($line -match '^(MYSQL[A-Z_]+)=(.*)$') {
    $map[$Matches[1]] = $Matches[2].Trim()
  }
}
$dbUser = $map["MYSQLUSER"]; if (-not $dbUser) { $dbUser = "root" }
$dbPass = $map["MYSQLPASSWORD"]; if (-not $dbPass) { $dbPass = $map["MYSQL_ROOT_PASSWORD"] }
$dbName = $map["MYSQLDATABASE"]; if (-not $dbName) { $dbName = "railway" }
if (-not $dbPass) { throw "Nao foi possivel ler MYSQLPASSWORD do servico MySQL." }

# Dump
if ($UseExistingDump) {
  if (-not $DumpPath) { $DumpPath = "dump-database.sql" }
  if (-not (Test-Path $DumpPath)) { throw "Dump nao encontrado: $DumpPath" }
  Write-Host "==> Usando dump existente: $DumpPath"
} elseif ($DumpPath -and (Test-Path $DumpPath)) {
  Write-Host "==> Usando dump informado: $DumpPath"
} else {
  $DumpPath = "backup-local-$(Get-Date -Format 'yyyyMMdd-HHmmss').sql"
  Write-Host "==> Exportando MySQL local -> $DumpPath"
  docker compose up -d db | Out-Host
  docker compose exec -T db mysqldump `
    -uwordpress -pwordpress `
    --single-transaction --routines --triggers `
    convivend62e9f3c_ccd > $DumpPath
  if (-not (Test-Path $DumpPath) -or (Get-Item $DumpPath).Length -lt 1MB) {
    throw "Export local falhou ou dump muito pequeno."
  }
}

# Tunel SSH (railway no Windows e shim npm/.ps1 — usa cmd.exe)
Write-Host "==> Abrindo tunel SSH MySQL em 127.0.0.1:$TunnelPort ..."
$outLog = Join-Path $env:TEMP "railway-tunnel-out.log"
$errLog = Join-Path $env:TEMP "railway-tunnel-err.log"
Remove-Item $outLog, $errLog -ErrorAction SilentlyContinue
$tunnel = Start-Process -FilePath "cmd.exe" `
  -ArgumentList @("/c", "railway connect MySQL --ssh --tunnel-only -P $TunnelPort") `
  -PassThru -WindowStyle Hidden `
  -RedirectStandardOutput $outLog `
  -RedirectStandardError $errLog

try {
  $ready = $false
  for ($i = 1; $i -le 60; $i++) {
    Start-Sleep -Seconds 2
    try {
      $tcp = Test-NetConnection -ComputerName 127.0.0.1 -Port $TunnelPort -WarningAction SilentlyContinue
      if ($tcp.TcpTestSucceeded) { $ready = $true; break }
    } catch {}
    if ($tunnel.HasExited) {
      if (Test-Path $errLog) { Get-Content $errLog | Write-Host }
      if (Test-Path $outLog) { Get-Content $outLog | Write-Host }
      throw "Tunel SSH encerrou cedo. Verifique: railway ssh keys list"
    }
  }
  if (-not $ready) { throw "Tunel SSH nao ficou pronto na porta $TunnelPort." }

  # Volume MySQL no Railway e 500MB: remove dados pesados (Wordfence/stats) antes do import.
  $filteredDump = Join-Path $Root "dump-filtered.sql"
  Write-Host "==> Filtrando tabelas pesadas (Wordfence/stats) -> dump-filtered.sql"
  python "$PSScriptRoot\filter-dump.py" $DumpPath $filteredDump
  if ($LASTEXITCODE -ne 0 -or -not (Test-Path $filteredDump)) {
    throw "Falha ao filtrar dump."
  }

  $bt = [string][char]96
  Write-Host "==> Recriando database $dbName no Railway..."
  $wipeSql = "DROP DATABASE IF EXISTS ${bt}${dbName}${bt}; CREATE DATABASE ${bt}${dbName}${bt} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  docker run --rm mysql:8.0 `
    mysql --protocol=TCP -h"host.docker.internal" "-P$TunnelPort" -u"$dbUser" "-p$dbPass" --ssl-mode=PREFERRED `
    -e "$wipeSql"
  if ($LASTEXITCODE -ne 0) { throw "DROP/CREATE database falhou." }

  Write-Host "==> Importando dump filtrado no Railway (rede privada via SSH)..."
  & "$PSScriptRoot\import-dump.ps1" `
    -DbHost "127.0.0.1" `
    -DbPort $TunnelPort `
    -User $dbUser `
    -Password $dbPass `
    -Database $dbName `
    -DumpPath $filteredDump `
    -SiteUrl $SiteUrl

  Write-Host "==> Garantindo siteurl/home -> $SiteUrl"
  $sql = "UPDATE wp_options SET option_value='$SiteUrl' WHERE option_name IN ('siteurl','home');"
  docker run --rm mysql:8.0 `
    mysql --protocol=TCP -h"host.docker.internal" "-P$TunnelPort" -u"$dbUser" "-p$dbPass" --ssl-mode=PREFERRED "$dbName" `
    -e "$sql" | Out-Host
} finally {
  if ($tunnel -and -not $tunnel.HasExited) {
    Write-Host "==> Encerrando tunel SSH..."
    Stop-Process -Id $tunnel.Id -Force -ErrorAction SilentlyContinue
  }
}

if ($SyncUploads) {
  Write-Host "==> Empacotando uploads (~pode demorar)..."
  $tar = Join-Path $Root "uploads-sync.tgz"
  if (Test-Path $tar) { Remove-Item $tar -Force }
  docker run --rm -v "${Root}/wordpress/wp-content/uploads:/uploads:ro" -v "${Root}:/out" alpine `
    tar -czf /out/uploads-sync.tgz -C /uploads .
  Write-Host "==> Enviando uploads via railway volume files (se houver volume) / ssh..."
  Write-Host "    Arquivo gerado: $tar"
  Write-Host "    Suba com: railway volume files upload ./uploads-sync.tgz /uploads-sync.tgz --overwrite"
  Write-Host "    Depois no servico WP: tar -xzf /path e mova para wp-content/uploads"
}

Write-Host "==> Reiniciando WordPress no Railway..."
Invoke-Railway restart --service convivendocomdiabetes --yes --json | Out-Host

Write-Host ""
Write-Host "Pronto."
Write-Host "Site: $SiteUrl"
Write-Host "Login: os mesmos usuarios do LOCAL (dump), nao o admin bootstrap."
Write-Host ""
Write-Host "Fluxo seguro usado: tunel SSH (MySQL nao ficou publico)."
