<#
.SYNOPSIS
  Importa os DADOS do dump (complementar ao schema automatico do deploy).

.EXAMPLE
  .\scripts\import-dump.ps1

.EXAMPLE
  .\scripts\import-dump.ps1 -DbHost xxx.proxy.rlwy.app -DbPort 12345 -User root -Password "senha" -Database railway -SiteUrl "https://seu-app.up.railway.app"
#>
param(
  [string]$DbHost = "127.0.0.1",
  [int]$DbPort = 3306,
  [string]$User = "wordpress",
  [string]$Password = "wordpress",
  [string]$Database = "convivend62e9f3c_ccd",
  [string]$DumpPath = "dump-database.sql",
  [string]$SiteUrl = ""
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)

if (-not (Test-Path $DumpPath)) {
  throw "Dump nao encontrado: $DumpPath"
}

$sourceDb = "convivend62e9f3c_ccd"
$dumpInContainer = "/work/" + ($DumpPath -replace '\\', '/')
$mysqlHost = if ($DbHost -eq "127.0.0.1" -or $DbHost -eq "localhost") { "host.docker.internal" } else { $DbHost }

Write-Host "Importando '$DumpPath' -> ${User}@${DbHost}:${DbPort}/$Database"

$bash = "sed 's/\`${sourceDb}\`/\`${Database}\`/g' '$dumpInContainer' | mysql -h'$mysqlHost' -P$DbPort -u'$User' -p'$Password' --ssl-mode=PREFERRED '$Database'"
docker run --rm -v "${PWD}:/work" -w /work mysql:8.0 bash -lc $bash
if ($LASTEXITCODE -ne 0) { throw "Import falhou (exit $LASTEXITCODE)" }

if ($SiteUrl) {
  Write-Host "Atualizando siteurl/home -> $SiteUrl"
  docker run --rm mysql:8.0 `
    mysql -h"$mysqlHost" -P$DbPort -u"$User" -p"$Password" --ssl-mode=PREFERRED "$Database" `
    -e "UPDATE wp_options SET option_value='$SiteUrl' WHERE option_name IN ('siteurl','home');"
}

Write-Host "Dump importado com sucesso."
