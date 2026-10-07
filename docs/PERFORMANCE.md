# Performance — Convivendo com Diabetes

Produção: `https://convivendocomdiabetes.com`

## O que o código já faz

| Item | Onde |
|------|------|
| Page cache HTML (visitantes) | `advanced-cache.php` + `ccd-page-cache.php` (TTL 1h) |
| Redis object cache | Plugin `redis-cache` + `WP_REDIS_*` (Railway) |
| OPcache / gzip / cache de estáticos | `docker/opcache.ini`, `docker/apache-performance.conf` |
| HTML `s-maxage=3600` (CDN) + browser revalidate | Apache + `ccd-frontend-fixes` + advanced-cache |
| Trim / async CSS tema + defer jQuery/masonry | `ccd-perf.php` |
| Noptin lazy (idle) quando sem shortcode | `ccd-perf.php` |
| WebP LCP + avatar Bia 288px | `ccd-perf.php`, `ccd-noptin-form.php` |
| reCAPTCHA lazy | `ccd-comment-recaptcha.php` |
| Fontes enxutas + preconnect | `ccd-perf.php`, `ccd-a11y.php` |

## Redis (Railway)

1. Serviço **Redis** no environment (produção já provisionado).
2. Vars: `WP_REDIS_HOST`, `WP_REDIS_PORT`, `WP_REDIS_PASSWORD`, `WP_REDIS_USERNAME`, `WP_REDIS_PREFIX`.
3. Boot (`wp-boot.sh`): probe de auth → drop-in só se OK.
4. IaC: `.railway/railway.ts`.

## Cloudflare (proxy + cache)

### A) DNS only → B) Proxy ON

1. SSL Railway OK no apex (fase A: `docs/cloudflare-dns-railway-import.txt`).
2. Ligar proxy:

```powershell
$env:CLOUDFLARE_API_TOKEN = '...'   # Zone.DNS Edit + Zone Settings Edit
# opcional: $env:CLOUDFLARE_ZONE_ID = '...'
.\scripts\enable-cloudflare-proxy.ps1
```

Ou manual: DNS → orange cloud em apex + www; SSL/TLS → **Full (strict)**.  
Import DNS fase B: `docs/cloudflare-dns-railway-import-proxied.txt`.

### Cache Rules (dashboard)

| Rule | When | Then |
|------|------|------|
| Bypass admin/login | URI Path starts with `/wp-admin` OR `/login` OR `/wp-login.php` OR Cookie name contains `wordpress_logged_in` | Bypass cache |
| Cache HTML anônimo | Hostname in apex/www AND GET AND not bypass | Eligible for cache; Edge TTL = Override 1 hour; Browser TTL = Respect origin |
| Cache estáticos | URI Path contains `/wp-content/` OR `/wp-includes/` | Edge TTL 1 month |

O origin já envia `Cache-Control: public, max-age=0, s-maxage=3600, must-revalidate` no HTML.

Opcional: Speed → Brotli ON; Early Hints ON.

```powershell
curl.exe -sI https://convivendocomdiabetes.com/
# Esperado: server: cloudflare  e  CF-Cache-Status: HIT|MISS|DYNAMIC
```

Healthcheck Railway (`/ccdhealth`) usa o domínio interno — não depende do proxy do apex.

## Medição

```powershell
.\scripts\seo-smoke.ps1 -BaseUrl "https://convivendocomdiabetes.com"
```

PageSpeed: https://pagespeed.web.dev/analysis?url=https%3A%2F%2Fconvivendocomdiabetes.com%2F
