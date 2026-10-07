# Gera ZIP instalavel do plugin CCD Backup (Plugins > Enviar plugin).
param(
	[string]$Version = ""
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$src = Join-Path $root "packages\ccd-backup"
$outDir = Join-Path $root "packages\releases"

if (-not (Test-Path (Join-Path $src "ccd-backup.php"))) {
	throw "Fonte nao encontrada: $src"
}

if (-not $Version) {
	$main = Get-Content (Join-Path $src "ccd-backup.php") -Raw
	if ($main -match 'Version:\s*([0-9.]+)') {
		$Version = $Matches[1]
	} else {
		$Version = "0.0.0"
	}
}

New-Item -ItemType Directory -Force -Path $outDir | Out-Null
$zipName = "ccd-backup-$Version.zip"
$zipPath = Join-Path $outDir $zipName

if (Test-Path $zipPath) {
	Remove-Item -Force $zipPath
}

# Pasta raiz dentro do zip deve ser "ccd-backup/" (padrao WP).
$stage = Join-Path $env:TEMP ("ccd-backup-pack-" + [guid]::NewGuid().ToString("N"))
$stagePlugin = Join-Path $stage "ccd-backup"
New-Item -ItemType Directory -Force -Path $stagePlugin | Out-Null
Copy-Item -Path (Join-Path $src "*") -Destination $stagePlugin -Recurse -Force
# Nao incluir lixo de editor
Get-ChildItem -Path $stagePlugin -Recurse -Force -Include ".keep",".DS_Store","Thumbs.db" -ErrorAction SilentlyContinue |
	Remove-Item -Force -ErrorAction SilentlyContinue

Compress-Archive -Path (Join-Path $stage "ccd-backup") -DestinationPath $zipPath -Force
Remove-Item -Recurse -Force $stage

# Espelha no plugins/ local do WordPress deste repo (opcional, util em Docker).
$wpPlugin = Join-Path $root "wordpress\wp-content\plugins\ccd-backup"
if (Test-Path (Split-Path $wpPlugin -Parent)) {
	if (Test-Path $wpPlugin) {
		Remove-Item -Recurse -Force $wpPlugin
	}
	New-Item -ItemType Directory -Force -Path $wpPlugin | Out-Null
	Copy-Item -Path (Join-Path $src "*") -Destination $wpPlugin -Recurse -Force
	Write-Host "Espelhado em wordpress/wp-content/plugins/ccd-backup/"
}

# Copia ZIP + LEIA-ME para packages/releases/plugin/ (espelho local da pasta do Drive).
$pluginOut = Join-Path $outDir "plugin"
New-Item -ItemType Directory -Force -Path $pluginOut | Out-Null
Copy-Item -Path $zipPath -Destination (Join-Path $pluginOut $zipName) -Force
$readmeSrc = Join-Path $src "drive-plugin\LEIA-ME.md"
if (Test-Path $readmeSrc) {
	Copy-Item -Path $readmeSrc -Destination (Join-Path $pluginOut "LEIA-ME.md") -Force
}

Write-Host "OK: $zipPath"
Write-Host "Espelho Drive: $pluginOut"
Write-Host "Instale em outro blog via Plugins > Adicionar novo > Enviar plugin."
