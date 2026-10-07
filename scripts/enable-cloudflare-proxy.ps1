#Requires -Version 5.1
<#
.SYNOPSIS
  Cloudflare: proxy ON, SSL Full Strict, Brotli, Early Hints, Cache Rules CCD.

.DESCRIPTION
  Token com: Zone.DNS Edit, Zone Settings Edit, Zone.Cache Rules Edit
  (ou "Cache Purge" + Rulesets conforme plano).

  Vars: CLOUDFLARE_API_TOKEN, CLOUDFLARE_ZONE_ID (opcional)

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
	Write-Error 'Defina CLOUDFLARE_API_TOKEN.'
}

$headers = @{
	Authorization  = "Bearer $ApiToken"
	'Content-Type' = 'application/json'
}

function Invoke-Cf([string] $Method, [string] $Path, $Body = $null) {
	$uri = "https://api.cloudflare.com/client/v4$Path"
	$params = @{
		Uri             = $uri
		Method          = $Method
		Headers         = $headers
		UseBasicParsing = $true
	}
	if ($null -ne $Body) {
		# Depth alto para rulesets
		$params.Body = ($Body | ConvertTo-Json -Compress -Depth 20)
	}
	try {
		return Invoke-RestMethod @params
	} catch {
		$msg = $_.ErrorDetails.Message
		if (-not $msg) { $msg = $_.Exception.Message }
		throw "CF $Method $Path => $msg"
	}
}

if ([string]::IsNullOrWhiteSpace($ZoneId)) {
	$z = Invoke-Cf GET "/zones?name=$Domain"
	if (-not $z.result -or $z.result.Count -lt 1) {
		Write-Error "Zone nao encontrada para $Domain"
	}
	$ZoneId = $z.result[0].id
	Write-Host "Zone ID: $ZoneId"
}

function Set-CfSetting([string] $Id, $Value, [string] $Label) {
	try {
		Invoke-Cf PATCH "/zones/$ZoneId/settings/$Id" @{ value = $Value } | Out-Null
		Write-Host "OK  $Label"
	} catch {
		Write-Warning "$Label : $($_.Exception.Message)"
	}
}

Set-CfSetting 'ssl' 'strict' 'SSL/TLS Full (strict)'
Set-CfSetting 'brotli' 'on' 'Brotli'
Set-CfSetting 'early_hints' 'on' 'Early Hints'
Set-CfSetting 'http3' 'on' 'HTTP/3'
Set-CfSetting 'minify' @{ css = 'on'; js = 'on'; html = 'on' } 'Auto Minify'
Set-CfSetting 'websockets' 'on' 'WebSockets'

# Desliga Web Analytics beacon se existir (nao quebra se endpoint falhar)
try {
	$wa = Invoke-Cf GET "/zones/$ZoneId/settings/web_analytics"
	Write-Host "Web Analytics setting: $($wa.result.value)"
} catch {
	# ignore
}

# DNS proxy
$dns = Invoke-Cf GET "/zones/$ZoneId/dns_records?type=CNAME&per_page=100"
foreach ($name in @($Domain, "www.$Domain")) {
	$rec = @($dns.result | Where-Object { $_.name -eq $name }) | Select-Object -First 1
	if (-not $rec) {
		Write-Warning "CNAME ausente: $name"
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
	Write-Host "Proxy ON: $name"
}

# Cache Rules via entrypoint http_request_cache_settings
Write-Host ''
Write-Host 'Configurando Cache Rules...'
try {
	try {
		$phases = Invoke-Cf GET "/zones/$ZoneId/rulesets/phases/http_request_cache_settings/entrypoint"
		$rulesetId = $phases.result.id
	} catch {
		$created = Invoke-Cf POST "/zones/$ZoneId/rulesets" @{
			name  = 'CCD cache settings'
			kind  = 'zone'
			phase = 'http_request_cache_settings'
			rules = @()
		}
		$rulesetId = $created.result.id
	}

	$rules = @(
		@{
			description = 'CCD bypass admin/login/cookies'
			expression  = '(http.request.uri.path contains "/wp-admin") or (http.request.uri.path eq "/login") or (http.request.uri.path contains "/wp-login.php") or (http.request.uri.path eq "/ccdhealth") or (http.cookie contains "wordpress_logged_in")'
			action      = 'set_cache_settings'
			action_parameters = @{
				cache = $false
			}
			enabled = $true
		},
		@{
			description = 'CCD cache static wp-content/includes'
			expression  = '(http.request.uri.path contains "/wp-content/") or (http.request.uri.path contains "/wp-includes/")'
			action      = 'set_cache_settings'
			action_parameters = @{
				cache       = $true
				edge_ttl    = @{ mode = 'override_origin'; default = 2592000 }
				browser_ttl = @{ mode = 'override_origin'; default = 2592000 }
			}
			enabled = $true
		},
		@{
			description = 'CCD cache HTML anonymous GET'
			# origin_cache_control=false: ignora max-age=0/Expires do WP (senao fica DYNAMIC).
			expression  = '(http.request.method eq "GET") and (http.host eq "convivendocomdiabetes.com" or http.host eq "www.convivendocomdiabetes.com") and not starts_with(http.request.uri.path, "/wp-admin") and not starts_with(http.request.uri.path, "/wp-json")'
			action      = 'set_cache_settings'
			action_parameters = @{
				cache                = $true
				origin_cache_control = $false
				edge_ttl             = @{ mode = 'override_origin'; default = 3600 }
				browser_ttl          = @{ mode = 'override_origin'; default = 0 }
			}
			enabled = $true
		}
	)

	Invoke-Cf PUT "/zones/$ZoneId/rulesets/$rulesetId" @{
		rules = $rules
	} | Out-Null
	Write-Host 'OK  Cache Rules (bypass admin, static 30d, HTML 1h)'
} catch {
	Write-Warning @"
Cache Rules falhou (token sem permissao Rulesets/Cache Rules):
$($_.Exception.Message)

Edite o token em Cloudflare → Account API tokens → Permissions:
  - Zone → DNS → Edit
  - Zone → Zone Settings → Edit
  - Zone → Cache Rules → Edit
  - Zone → Zone → Read
Zone Resources: Include → Specific zone → convivendocomdiabetes.com
Depois rode este script de novo.
"@
}

Write-Host ''
Write-Host 'Teste:'
Write-Host '  curl.exe -sI https://convivendocomdiabetes.com/ | findstr /i "cf-cache server"'
Write-Host '  (2a request deve tender a CF-Cache-Status: HIT)'
