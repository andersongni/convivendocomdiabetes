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

$catHeaders = curl.exe -sSI --max-time 20 "$BaseUrl/diabetes/"
$catFinalCode = curl.exe -sS -o "$env:TEMP\ccd-seo-smoke-cat.html" -w "%{http_code}" --max-time 30 "$BaseUrl/diabetes/"
$catHtml = if (Test-Path "$env:TEMP\ccd-seo-smoke-cat.html") { Get-Content "$env:TEMP\ccd-seo-smoke-cat.html" -Raw } else { "" }
$catTitle = Get-HtmlTitle $catHtml
$catIsPostRedirect = $catHeaders -match '(?i)location:\s*.*diabetes-tipo-2'
Check "categoria /diabetes/ HTTP" ($catFinalCode -eq "200") "status=$catFinalCode"
Check "categoria /diabetes/ nao vira post" (-not $catIsPostRedirect) "sem 301 para post antigo"
Check "categoria title com nome" (
  $catTitle -match '(?i)diabetes' -and $catTitle -notmatch '^\s*-\s*' -and $catTitle -notmatch '(?i)diagnostico'
) "title=$catTitle"

$catBaseHeaders = (curl.exe -sSI --max-time 20 "$BaseUrl/category/diabetes/" | Out-String)
$catBaseLoc = ""
if ($catBaseHeaders -match '(?im)^[Ll]ocation:\s*(\S+)') {
  $catBaseLoc = ([string]$Matches[1]).Trim().TrimEnd("`r")
}
$catBaseOk = (
  $catBaseHeaders -match '(?im)^HTTP/\S+\s+301\b' -and
  $catBaseLoc -match '(?i)/diabetes/?$' -and
  $catBaseLoc -notmatch '/category/'
)
Check "category/diabetes 301 limpo" $catBaseOk "location=$catBaseLoc"

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
  $contatoDesc.Length -ge 70 -and $contatoDesc -notmatch '\[wpforms' -and $contatoDesc -notmatch '&nbsp;' -and $contatoDesc -notmatch '\[ccd_'
) "len=$($contatoDesc.Length)"

$homeTitle = Get-HtmlTitle $html
Check "home title util" (
  $homeTitle -match '(?i)diabetes' -and $homeTitle -notmatch '(?i)^In[ií]cio\b'
) "title=$homeTitle"

$clipPath = "$env:TEMP\ccd-seo-smoke-clipping.html"
$code = curl.exe -sS -o $clipPath -w "%{http_code}" --max-time 30 "$BaseUrl/clipping/"
$clipHtml = if (Test-Path $clipPath) { Get-Content $clipPath -Raw } else { "" }
$clipDesc = Get-HtmlMetaDescription $clipHtml
Check "clipping HTTP" ($code -eq "200") "status=$code"
Check "clipping meta util" (
  $clipDesc.Length -ge 70 -and $clipDesc -notmatch '&nbsp;' -and $clipDesc -notmatch '(?i)^Campanhas'
) "len=$($clipDesc.Length)"

$recHeaders = curl.exe -sSI --max-time 20 "$BaseUrl/receitas/"
$recIsPostRedirect = $recHeaders -match '(?i)location:\s*.*receitas-gostosas'
$recCode = curl.exe -sS -o "$env:TEMP\ccd-seo-smoke-receitas.html" -w "%{http_code}" --max-time 30 "$BaseUrl/receitas/"
$recHtml = if (Test-Path "$env:TEMP\ccd-seo-smoke-receitas.html") { Get-Content "$env:TEMP\ccd-seo-smoke-receitas.html" -Raw } else { "" }
$recTitle = Get-HtmlTitle $recHtml
Check "categoria /receitas/ HTTP" ($recCode -eq "200") "status=$recCode"
Check "categoria /receitas/ nao vira post" (-not $recIsPostRedirect) "sem 301 para post antigo"
Check "categoria /receitas/ title" (
  $recTitle -match '(?i)receitas' -and $recTitle -notmatch '(?i)Ano Novo'
) "title=$recTitle"

$hipPath = "$env:TEMP\ccd-seo-smoke-hipo.html"
$code = curl.exe -sS -o $hipPath -w "%{http_code}" --max-time 30 "$BaseUrl/hipoglicemia/"
$hipHtml = if (Test-Path $hipPath) { Get-Content $hipPath -Raw } else { "" }
$hipDesc = Get-HtmlMetaDescription $hipHtml
Check "hipoglicemia HTTP" ($code -eq "200") "status=$code"
Check "hipoglicemia meta util" (
  $hipDesc.Length -ge 70 -and $hipDesc -match '(?i)hipoglicemia' -and $hipDesc -notmatch '(?i)^Tenho altos'
) "len=$($hipDesc.Length)"

foreach ($pillar in @('alimentacao-e-diabetes-tipo-2','sensor-de-glicose-como-funciona','o-que-e-hba1c-hemoglobina-glicada')) {
  $pPath = "$env:TEMP\ccd-seo-smoke-$pillar.html"
  $code = curl.exe -sS -o $pPath -w "%{http_code}" --max-time 30 "$BaseUrl/$pillar/"
  $pHtml = if (Test-Path $pPath) { Get-Content $pPath -Raw } else { "" }
  $hasLinks = $pHtml -match 'ccd-editorial-links' -or $pHtml -match 'Leia também'
  Check "pilar $pillar HTTP" ($code -eq "200") "status=$code"
  Check "pilar $pillar interlinking" $hasLinks "links internos"
}

$hubHtmlPath = "$env:TEMP\ccd-seo-smoke-hub.html"
curl.exe -sS -o $hubHtmlPath --max-time 30 "$BaseUrl/diabetes/" | Out-Null
$hubHtml = if (Test-Path $hubHtmlPath) { Get-Content $hubHtmlPath -Raw } else { "" }
Check "hub /diabetes/ pilares" ($hubHtml -match 'ccd-hub-pillars' -or $hubHtml -match 'Pilares para começar') "nav pilares"

Write-Host ""
if ($fail -gt 0) {
  Write-Host "Falhou: $fail check(s)" -ForegroundColor Red
  exit 1
}
Write-Host "Todos os checks passaram." -ForegroundColor Green
exit 0
