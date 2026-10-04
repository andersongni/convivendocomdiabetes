<#
.SYNOPSIS
  Importa os DADOS do dump (complementar ao schema automatico do deploy).

.EXAMPLE
  # Local (docker compose na porta 3306)
  .\scripts\import-dump.ps1

.EXAMPLE
  # Railway (ative TCP Proxy no MySQL)
  .\scripts\import-dump.ps1 -DbHost xxx.proxy.rlwy.app -DbPort 12345 -User root -Password "senha" -Database railway -SiteUrl "https://seu-app.up.railway.app"
#>
param(
  [string]$DbHost = "127.0.0.1",
  [int]$DbPort = 3306,
  [string]$User = "wordpress",
  [string]$Password = "wordpress",
  [string]$Database = "convivend62e9f3c_ccd",
  [string]$DumpPath = "",
  [string]$SiteUrl = ""
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)

if (-not $DumpPath) {
  if (Test-Path "dump-database.sql") { $DumpPath = "dump-database.sql" }
  elseif (Test-Path "db\init\site-dump.gz") { $DumpPath = "db\init\site-dump.gz" }
  else { throw "Nenhum dump encontrado (dump-database.sql ou db/init/site-dump.gz)." }
}

$sourceDb = "convivend62e9f3c_ccd"
$dumpInContainer = "/work/" + ($DumpPath -replace '\\', '/')
$mysqlHost = $DbHost
if ($DbHost -eq "127.0.0.1" -or $DbHost -eq "localhost") {
  $mysqlHost = "host.docker.internal"
}

Write-Host "Importando '$DumpPath' -> ${User}@${DbHost}:${DbPort}/$Database"

if ($DumpPath -like "*.gz") {
  $bash = "gunzip -c '$dumpInContainer' | sed 's/\`${sourceDb}\`/\`${Database}\`/g' | mysql -h'$mysqlHost' -P$DbPort -u'$User' -p'$Password' --ssl-mode=PREFERRED '$Database'"
} else {
  $bash = "sed 's/\`${sourceDb}\`/\`${Database}\`/g' '$dumpInContainer' | mysql -h'$mysqlHost' -P$DbPort -u'$User' -p'$Password' --ssl-mode=PREFERRED '$Database'"
}

docker run --rm -v "${PWD}:/work" -w /work mysql:8.0 bash -lc $bash
if ($LASTEXITCODE -ne 0) { throw "Import falhou (exit $LASTEXITCODE)" }

if ($SiteUrl) {
  Write-Host "Atualizando siteurl/home -> $SiteUrl"
  docker run --rm mysql:8.0 `
    mysql -h"$mysqlHost" -P$DbPort -u"$User" -p"$Password" --ssl-mode=PREFERRED "$Database" `
    -e "UPDATE wp_options SET option_value='$SiteUrl' WHERE option_name IN ('siteurl','home');"
}

Write-Host "Dump importado com sucesso."
