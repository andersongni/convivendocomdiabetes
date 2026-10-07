# Performance — Convivendo com Diabetes

Produção: `https://convivendocomdiabetes.com`

## O que o código já faz

| Item | Onde |
|------|------|
| Page cache HTML (visitantes) | `advanced-cache.php` + `ccd-page-cache.php` (TTL 1h) |
| Redis object cache | Plugin `redis-cache` + `WP_REDIS_*` (Railway) |
| OPcache / gzip / cache de estáticos | `docker/opcache.ini`, `docker/apache-performance.conf` |
| Trim de assets front | `ccd-front-trim.php`, `ccd-perf.php` |
| WebP uploads novos + LCP legado | `ccd-media-optimize.php`, `ccd-perf.php` |
| reCAPTCHA lazy (idle/interação) | `ccd-comment-recaptcha.php` |
| Fontes enxutas + preconnect | `ccd-perf.php`, `ccd-a11y.php` |

## Redis (Railway)

1. Serviço **Redis** no environment (produção já provisionado).
2. Vars no WordPress: `WP_REDIS_HOST`, `WP_REDIS_PORT`, `WP_REDIS_PASSWORD`, `WP_REDIS_USERNAME`, `WP_REDIS_PREFIX`.
3. No boot (`wp-boot.sh`), o drop-in `object-cache.php` é instalado se `WP_REDIS_HOST` existir.
4. IaC: `.railway/railway.ts` (`redis("Redis")` + refs).

Staging: aplicar o mesmo template Redis ou `railway config apply` após o plan incluir Redis.

## Cloudflare (proxy + cache) — passo manual

O DNS importado em `docs/cloudflare-dns-railway-import.txt` usa **DNS only** (grey cloud) para o Railway emitir certificado. Depois do SSL Railway estável:

1. Cloudflare → DNS → `convivendocomdiabetes.com` e `www` → **Proxy ON** (orange cloud).
2. SSL/TLS → **Full (strict)**.
3. Caching → Configuration → Browser Cache TTL: Respect Existing Headers (ou 4 hours).
4. Rules → Cache Rules (sugerido):

| Rule | When | Then |
|------|------|------|
| Bypass admin/login | URI Path starts with `/wp-admin` OR `/login` OR `/wp-login.php` OR Cookie `wordpress_logged_in_*` | Bypass cache |
| Cache HTML anônimo | Hostname = apex/www AND method GET | Eligible for cache, Edge TTL 1 hour, Browser TTL respect origin |
| Cache estáticos | URI Path contains `/wp-content/` or `/wp-includes/` | Edge TTL 1 month |

5. Opcional: Speed → Optimization → Brotli ON; Early Hints ON.

Após ligar o proxy, confirme:

```powershell
# Deve aparecer CF-Cache-Status e server cloudflare
curl.exe -sI https://convivendocomdiabetes.com/
```

Healthcheck Railway (`/ccdhealth`) e deploy continuam no domínio `*.up.railway.app` / private network — não dependem do proxy do apex.

## Medição

```powershell
.\scripts\seo-smoke.ps1 -BaseUrl "https://convivendocomdiabetes.com"
```

PageSpeed: https://pagespeed.web.dev/analysis?url=https%3A%2F%2Fconvivendocomdiabetes.com%2F
