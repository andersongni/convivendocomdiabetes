# Copia plugins/themes/mu-plugins/languages do volume Docker → ./wordpress/wp-content (host/git).
# Use depois de atualizar via WP Admin ou wp-cli no container local.
# Depois: git status / commit / push → Railway rebuild.

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

$svc = "wordpress"
$cid = (docker compose ps -q $svc).Trim()
if (-not $cid) {
  Write-Error "Container '$svc' nao esta rodando. Rode: docker compose up -d"
}

$dest = Join-Path $Root "wordpress\wp-content"
$tmp = Join-Path $env:TEMP ("ccd-wp-content-" + [guid]::NewGuid().ToString("n"))
New-Item -ItemType Directory -Path $tmp | Out-Null

try {
  $trees = @("plugins", "themes", "mu-plugins", "languages")
  foreach ($tree in $trees) {
    Write-Host "[pull] $tree ..."
    $srcInContainer = "${cid}:/var/www/html/wp-content/$tree"
    $tmpTree = Join-Path $tmp $tree
    docker cp $srcInContainer $tmpTree
    if (-not (Test-Path $tmpTree)) {
      Write-Warning "Pulando $tree (nao encontrado no container)"
      continue
    }
    $target = Join-Path $dest $tree
    if (Test-Path $target) {
      Remove-Item -Recurse -Force $target
    }
    Move-Item -Path $tmpTree -Destination $target
  }
  Write-Host "[pull] OK → $dest"
  Write-Host "Revise com git status, teste em http://localhost:8080, commit e push para promover."
}
finally {
  if (Test-Path $tmp) {
    Remove-Item -Recurse -Force $tmp -ErrorAction SilentlyContinue
  }
}
