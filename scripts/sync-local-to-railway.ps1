<#
.SYNOPSIS
  Copia o WordPress local (dump + opcionalmente midia) para o Railway com seguranca.

.DESCRIPTION
  1) Exporta o MySQL local (ou usa dump-database.sql)
  2) Abre tunel SSH para o MySQL do Railway (sem TCP proxy publico)
  3) Importa o dump e reescreve URLs para o dominio Railway
  4) Opcional: empacota uploads e sobe para volume/servico

.EXAMPLE
  .\scripts\sync-local-to-railway.ps1

.EXAMPLE
  .\scripts\sync-local-to-railway.ps1 -SyncUploads
#>
param(
  [string]$SiteUrl = "https://convivendocomdiabetes-production.up.railway.app",
  [string]$DumpPath = "",
  [switch]$UseExistingDump,
  [switch]$SyncUploads,
  [int]$TunnelPort = 3307
)

$ErrorActionPreference = "Stop"
$Root = Split-Path $PSScriptRoot -Parent
Set-Location $Root

function Assert-Cmd($name) {
  if (-not (Get-Command $name -ErrorAction SilentlyContinue)) {
    throw "Comando nao encontrado: $name"
  }
}

Assert-Cmd docker
Assert-Cmd railway

Write-Host "==> Garantindo chave SSH no Railway (GitHub)..."
railway ssh keys github 2>&1 | Out-Host

# Credenciais MySQL do Railway
Write-Host "==> Lendo variaveis do MySQL no Railway..."
$mysqlKv = railway variable list --service MySQL --kv
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

# Tunel SSH
Write-Host "==> Abrindo tunel SSH MySQL em 127.0.0.1:$TunnelPort ..."
$tunnel = Start-Process -FilePath "railway" `
  -ArgumentList @("connect", "MySQL", "--ssh", "--tunnel-only", "-P", "$TunnelPort") `
  -PassThru -WindowStyle Hidden `
  -RedirectStandardOutput "$env:TEMP\railway-tunnel-out.log" `
  -RedirectStandardError "$env:TEMP\railway-tunnel-err.log"

try {
  $ready = $false
  for ($i = 1; $i -le 60; $i++) {
    Start-Sleep -Seconds 2
    try {
      $tcp = Test-NetConnection -ComputerName 127.0.0.1 -Port $TunnelPort -WarningAction SilentlyContinue
      if ($tcp.TcpTestSucceeded) { $ready = $true; break }
    } catch {}
    if ($tunnel.HasExited) {
      Get-Content "$env:TEMP\railway-tunnel-err.log" -ErrorAction SilentlyContinue | Write-Host
      throw "Tunel SSH encerrou cedo. Rode: railway ssh keys github"
    }
  }
  if (-not $ready) { throw "Tunel SSH nao ficou pronto na porta $TunnelPort." }

  Write-Host "==> Importando dump no Railway (rede privada via SSH)..."
  & "$PSScriptRoot\import-dump.ps1" `
    -DbHost "127.0.0.1" `
    -DbPort $TunnelPort `
    -User $dbUser `
    -Password $dbPass `
    -Database $dbName `
    -DumpPath $DumpPath `
    -SiteUrl $SiteUrl

  Write-Host "==> Search-replace de dominios UOL -> Railway..."
  $sql = @"
UPDATE wp_options SET option_value='$SiteUrl' WHERE option_name IN ('siteurl','home');
"@
  docker run --rm mysql:8.0 `
    mysql -h"host.docker.internal" -P$TunnelPort -u"$dbUser" -p"$dbPass" --ssl-mode=PREFERRED "$dbName" `
    -e $sql | Out-Host

  # Substitui URLs antigas no conteudo (via WP-CLI no container remoto seria ideal; aqui via SQL simples nao cobre serialized.
  # Dispara restart do WP para o entrypoint rodar search-replace com wp-cli.
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
railway restart --service convivendocomdiabetes --yes 2>&1 | Out-Host

Write-Host ""
Write-Host "Pronto."
Write-Host "Site: $SiteUrl"
Write-Host "Login: os mesmos usuarios do LOCAL (dump), nao o admin bootstrap."
Write-Host ""
Write-Host "Fluxo seguro usado: tunel SSH (MySQL nao ficou publico)."
