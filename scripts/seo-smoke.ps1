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

function Get-HtmlTitle([string]$pageHtml) {
  if ($pageHtml -match '(?s)<title[^>]*>(.*?)</title>') {
    return (($Matches[1] -replace '<[^>]+>', '').Trim())
  }
  return ""
}

function Get-HtmlMetaDescription([string]$pageHtml) {
  if ($pageHtml -match '(?i)<meta[^>]+name=["'']description["''][^>]+content=["'']([^"'']*)["'']') {
    return $Matches[1].Trim()
  }
  if ($pageHtml -match '(?i)<meta[^>]+content=["'']([^"'']*)["''][^>]+name=["'']description["'']') {
    return $Matches[1].Trim()
  }
  return ""
}

$catPath = "$env:TEMP\ccd-seo-smoke-cat.html"
$code = curl.exe -sS -o $catPath -w "%{http_code}" --max-time 30 "$BaseUrl/diabetes/"
$catHtml = if (Test-Path $catPath) { Get-Content $catPath -Raw } else { "" }
$catTitle = Get-HtmlTitle $catHtml
Check "categoria /diabetes/ HTTP" ($code -eq "200") "status=$code"
Check "categoria title com nome" (
  $catTitle -match '(?i)diabetes' -and $catTitle -notmatch '^\s*-\s*'
) "title=$catTitle"

$blogPath = "$env:TEMP\ccd-seo-smoke-blog.html"
$code = curl.exe -sS -o $blogPath -w "%{http_code}" --max-time 30 "$BaseUrl/blog/"
$blogHtml = if (Test-Path $blogPath) { Get-Content $blogPath -Raw } else { "" }
$blogDesc = Get-HtmlMetaDescription $blogHtml
Check "blog HTTP" ($code -eq "200") "status=$code"
Check "blog meta util" (
  $blogDesc.Length -ge 70 -and $blogDesc -notmatch '(?i)^Blog\s*[—\-]'
) "len=$($blogDesc.Length)"

$contatoPath = "$env:TEMP\ccd-seo-smoke-contato.html"
$code = curl.exe -sS -o $contatoPath -w "%{http_code}" --max-time 30 "$BaseUrl/contato/"
$contatoHtml = if (Test-Path $contatoPath) { Get-Content $contatoPath -Raw } else { "" }
$contatoDesc = Get-HtmlMetaDescription $contatoHtml
Check "contato HTTP" ($code -eq "200") "status=$code"
Check "contato meta sem shortcode" (
  $contatoDesc.Length -ge 70 -and $contatoDesc -notmatch '\[wpforms' -and $contatoDesc -notmatch '&nbsp;'
) "len=$($contatoDesc.Length)"

Write-Host ""
if ($fail -gt 0) {
  Write-Host "Falhou: $fail check(s)" -ForegroundColor Red
  exit 1
}
Write-Host "Todos os checks passaram." -ForegroundColor Green
exit 0
