# Aplica no WordPress local (Docker) o que foi feito direto na producao Railway.
# Uso: .\scripts\apply-runtime-fixes-local.ps1

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

$seedPath = Join-Path $PSScriptRoot "..\db\seeds\runtime-fixes-from-railway.json"
$seed = Get-Content -Raw $seedPath | ConvertFrom-Json

Write-Host "==> Sync wp-content host -> container..."
& "$PSScriptRoot\sync-local-wp-content.ps1"

Write-Host "==> Applying Simple CSS option + theme mods..."
$css = $seed.simple_css.css
$theme = $seed.simple_css.theme

# Local container has no WP-CLI; bootstrap WordPress via php + wp-load.php
$php = @'
<?php
require "/var/www/html/wp-load.php";
$css = file_get_contents("/tmp/ccd-simple-css.txt");
update_option("simple_css", array("css" => $css, "theme" => "__THEME__"));
set_theme_mod("header_offscreen_nav_on_desktop", "0");
set_theme_mod("header_offscreen_nav_on_tablet", "0");
if (function_exists("mesmerize_clear_cached_values")) { mesmerize_clear_cached_values(); }
$opt = get_option("simple_css");
echo "simple_css_len=" . strlen($opt["css"] ?? "") . "\n";
echo "offscreen_desktop=" . get_theme_mod("header_offscreen_nav_on_desktop") . " tablet=" . get_theme_mod("header_offscreen_nav_on_tablet") . "\n";
echo "OK\n";
'@
$php = $php.Replace("__THEME__", $theme)

$cssFile = Join-Path $env:TEMP "ccd-simple-css.txt"
$tmp = Join-Path $env:TEMP "ccd-apply-runtime-fixes.php"
Set-Content -Path $cssFile -Value $css -Encoding UTF8
Set-Content -Path $tmp -Value $php -Encoding UTF8

docker compose cp $cssFile wordpress:/tmp/ccd-simple-css.txt
docker compose cp $tmp wordpress:/tmp/ccd-apply-runtime-fixes.php
docker compose exec -T wordpress php /tmp/ccd-apply-runtime-fixes.php

Write-Host "==> Runtime fixes applied locally."
