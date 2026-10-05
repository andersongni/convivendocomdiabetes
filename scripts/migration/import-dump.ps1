<#
.SYNOPSIS
  Importa os DADOS do dump para um MySQL (local ou tunel Railway).
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
Set-Location (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path

if (-not (Test-Path $DumpPath)) {
  throw "Dump nao encontrado: $DumpPath"
}

$sourceDb = "convivend62e9f3c_ccd"
$dumpFile = "/work/" + ($DumpPath -replace '\\', '/')
$mysqlHost = if ($DbHost -eq "127.0.0.1" -or $DbHost -eq "localhost") { "host.docker.internal" } else { $DbHost }
$bt = [string][char]96

Write-Host "Importando '$DumpPath' -> ${User}@${DbHost}:${DbPort}/$Database"

$runner = Join-Path $env:TEMP "ccd-import-runner.sh"
$scriptBody = @(
  "#!/bin/bash"
  "set -euo pipefail"
  "sed 's/${bt}${sourceDb}${bt}/${bt}${Database}${bt}/g' '$dumpFile' | mysql --protocol=TCP -h '$mysqlHost' -P $DbPort -u '$User' -p'$Password' --ssl-mode=PREFERRED '$Database'"
) -join "`n"
[System.IO.File]::WriteAllText($runner, $scriptBody + "`n")

docker run --rm -v "${PWD}:/work" -v "${runner}:/import.sh:ro" -w /work mysql:8.0 bash /import.sh
if ($LASTEXITCODE -ne 0) { throw "Import falhou (exit $LASTEXITCODE)" }

if ($SiteUrl) {
  Write-Host "Atualizando siteurl/home -> $SiteUrl"
  $sql = "UPDATE wp_options SET option_value='$SiteUrl' WHERE option_name IN ('siteurl','home');"
  docker run --rm mysql:8.0 `
    mysql --protocol=TCP -h "$mysqlHost" -P "$DbPort" -u "$User" "-p$Password" --ssl-mode=PREFERRED "$Database" `
    -e "$sql"
  if ($LASTEXITCODE -ne 0) { throw "UPDATE siteurl/home falhou (exit $LASTEXITCODE)" }
}

Write-Host "Dump importado com sucesso."
