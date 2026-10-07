# Performance — Convivendo com Diabetes

Produção: `https://convivendocomdiabetes.com`

## Já no código

| Item | Onde |
|------|------|
| Page cache HTML + Redis | `advanced-cache.php`, `ccd-page-cache`, Railway Redis |
| HTML `s-maxage=3600` (CDN) | Apache + send_headers + advanced-cache |
| CSS tema async (sem double-fetch) | `ccd-perf.php` |
| Fontes self-host (Open Sans, Muli/Mulish, Nunito, Pacifico) | `mu-plugins/ccd-assets/` |
| Preload LCP + font woff2 | `ccd-perf.php` |
| Noptin lazy (idle) | `ccd-perf.php` |
| Avatar Bia 288px WebP | `ccd-perf` + `ccd-noptin-form` |
| reCAPTCHA lazy | `ccd-comment-recaptcha.php` |

## Cloudflare (proxy + cache + speed)

```powershell
$env:CLOUDFLARE_API_TOKEN = '...'  # Zone DNS + Zone Settings + Cache Rules / Rulesets
.\scripts\enable-cloudflare-proxy.ps1
```

O script:

1. Proxy ON (apex + www)
2. SSL Full Strict, Brotli, Early Hints, HTTP/3, Auto Minify
3. Cache Rules: bypass admin/login/cookies; estáticos 30d; HTML GET 1h  
   (`edge_ttl: override_origin` ignora `max-age=0` do WP; `origin_cache_control` é Enterprise-only)

Permissões do token: **Zone.DNS Edit**, **Zone Settings Edit**, **Zone → Cache Rules / Rulesets Edit**.

Manual: `docs/cloudflare-dns-railway-import-proxied.txt` + Cache Rules no dashboard.

```powershell
curl.exe -sI https://convivendocomdiabetes.com/
# Esperado: server cloudflare; 2a request CF-Cache-Status: HIT (HTML)
```

## Medição

```powershell
.\scripts\seo-smoke.ps1 -BaseUrl "https://convivendocomdiabetes.com"
```

PageSpeed: https://pagespeed.web.dev/analysis?url=https%3A%2F%2Fconvivendocomdiabetes.com%2F
