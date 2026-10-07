#Requires -Version 5.1
<#
.SYNOPSIS
  Liga Proxy (orange cloud) nos CNAMEs apex/www no Cloudflare + SSL Full Strict.

.DESCRIPTION
  Requer API Token com Zone.DNS Edit + Zone.Settings Edit.
  Variaveis: CLOUDFLARE_API_TOKEN, CLOUDFLARE_ZONE_ID (ou detecta pelo dominio).

.EXAMPLE
  $env:CLOUDFLARE_API_TOKEN = '...'
  .\scripts\enable-cloudflare-proxy.ps1
#>
param(
	[string] $Domain = 'convivendocomdiabetes.com',
	[string] $ApiToken = $env:CLOUDFLARE_API_TOKEN,
	[string] $ZoneId = $env:CLOUDFLARE_ZONE_ID
)

$ErrorActionPreference = 'Stop'
if ([string]::IsNullOrWhiteSpace($ApiToken)) {
	Write-Error 'Defina CLOUDFLARE_API_TOKEN (Zone.DNS Edit + Zone Settings Edit).'
}

$headers = @{
	Authorization  = "Bearer $ApiToken"
	'Content-Type' = 'application/json'
}

function Invoke-Cf([string] $Method, [string] $Path, $Body = $null) {
	$uri = "https://api.cloudflare.com/client/v4$Path"
	$params = @{
		Uri     = $uri
		Method  = $Method
		Headers = $headers
	}
	if ($null -ne $Body) {
		$params.Body = ($Body | ConvertTo-Json -Compress -Depth 8)
	}
	$resp = Invoke-RestMethod @params
	if (-not $resp.success) {
		throw ("Cloudflare API error: " + ($resp.errors | ConvertTo-Json -Compress))
	}
	return $resp
}

if ([string]::IsNullOrWhiteSpace($ZoneId)) {
	$z = Invoke-Cf GET "/zones?name=$Domain"
	if (-not $z.result -or $z.result.Count -lt 1) {
		Write-Error "Zone nao encontrada para $Domain"
	}
	$ZoneId = $z.result[0].id
	Write-Host "Zone ID: $ZoneId"
}

# SSL Full Strict
Invoke-Cf PATCH "/zones/$ZoneId/settings/ssl" @{ value = 'strict' } | Out-Null
Write-Host 'SSL/TLS: full (strict)'

# Brotli
try {
	Invoke-Cf PATCH "/zones/$ZoneId/settings/brotli" @{ value = 'on' } | Out-Null
	Write-Host 'Brotli: on'
} catch {
	Write-Warning "Brotli: $($_.Exception.Message)"
}

$dns = Invoke-Cf GET "/zones/$ZoneId/dns_records?type=CNAME&per_page=100"
$targets = @($Domain, "www.$Domain")
foreach ($name in $targets) {
	$rec = @($dns.result | Where-Object { $_.name -eq $name }) | Select-Object -First 1
	if (-not $rec) {
		Write-Warning "CNAME nao encontrado: $name"
		continue
	}
	if ($rec.proxied) {
		Write-Host "Ja proxied: $name"
		continue
	}
	Invoke-Cf PUT "/zones/$ZoneId/dns_records/$($rec.id)" @{
		type    = 'CNAME'
		name    = $rec.name
		content = $rec.content
		ttl     = 1
		proxied = $true
	} | Out-Null
	Write-Host "Proxy ON: $name → $($rec.content)"
}

Write-Host ''
Write-Host 'Proximo: criar Cache Rules no dashboard (docs/PERFORMANCE.md).'
Write-Host 'Teste: curl.exe -sI https://convivendocomdiabetes.com/ | findstr /i "cf-cache server"'
