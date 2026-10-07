# Smoke SEO: robots, sitemap, meta description da home, alts vazios.
# Uso:
#   .\scripts\seo-smoke.ps1
#   .\scripts\seo-smoke.ps1 -BaseUrl "https://convivendocomdiabetes.com"

param(
  [string]$BaseUrl = "http://localhost:8080"
)

$ErrorActionPreference = "Stop"
$BaseUrl = $BaseUrl.TrimEnd("/")
$fail = 0

function Check([string]$name, [bool]$ok, [string]$detail = "") {
  if ($ok) {
    Write-Host "OK  $name $(if ($detail) { "- $detail" })" -ForegroundColor Green
  } else {
    Write-Host "FAIL $name $(if ($detail) { "- $detail" })" -ForegroundColor Red
    $script:fail++
  }
}

Write-Host "SEO smoke => $BaseUrl"
Write-Host ""

$robots = curl.exe -sS --max-time 20 "$BaseUrl/robots.txt"
Check "robots.txt" ($LASTEXITCODE -eq 0 -and $robots -match "sitemap_index\.xml") "aponta sitemap_index.xml"
$sitemapLines = @($robots -split "`r?`n" | Where-Object { $_ -match "^\s*Sitemap:" })
Check "robots Sitemap unico" ($sitemapLines.Count -eq 1) "linhas=$($sitemapLines.Count)"

$code = curl.exe -sS -o "$env:TEMP\ccd-seo-smoke-sitemap.xml" -w "%{http_code}" --max-time 25 "$BaseUrl/sitemap_index.xml"
Check "sitemap_index.xml HTTP" ($code -eq "200") "status=$code"
if (Test-Path "$env:TEMP\ccd-seo-smoke-sitemap.xml") {
  $body = Get-Content "$env:TEMP\ccd-seo-smoke-sitemap.xml" -Raw
  Check "sitemap XML" ($body -match "<sitemapindex") "sitemapindex presente"
}

$htmlPath = "$env:TEMP\ccd-seo-smoke-home.html"
$code = curl.exe -sS -o $htmlPath -w "%{http_code}" --max-time 30 "$BaseUrl/"
Check "home HTTP" ($code -eq "200") "status=$code"
$html = Get-Content $htmlPath -Raw
Check "meta description" ($html -match '(?i)name=["'']description["'']') 
Check "canonical" ($html -match '(?i)rel=["'']canonical["'']')
$emptyAlt = ([regex]::Matches($html, '(?i)\balt=["'']\s*["'']')).Count
Check "home sem alt vazio" ($emptyAlt -eq 0) "empty=$emptyAlt"
Check "Organization/Person schema" ($html -match "ccd-eeat-schema" -or $html -match '"@type"\s*:\s*"Organization"')

Write-Host ""
if ($fail -gt 0) {
  Write-Host "Falhou: $fail check(s)" -ForegroundColor Red
  exit 1
}
Write-Host "Todos os checks passaram." -ForegroundColor Green
exit 0
