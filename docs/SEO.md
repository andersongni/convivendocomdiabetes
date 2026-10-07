# SEO — Convivendo com Diabetes

Produção canônica: `https://convivendocomdiabetes.com`  
`https://www.convivendocomdiabetes.com` redireciona (301) para o apex.

## O que o código já promove (git)

| Item | Onde |
|------|------|
| Yoast SEO **28.6** | `wordpress/wp-content/plugins/wordpress-seo/` |
| Sitemap/robots estáveis | `ccd-sitemap-fix.php` |
| Meta/focus/alts no acervo + posts novos | `ccd-seo-content.php` |
| Meta da home + breadcrumbs + related | `ccd-seo-boost.php` |
| E-E-A-T (disclaimer, autor, datas, Organization/Person) | `ccd-eeat.php` |
| Lazy-load scoped / prev-next / paginação crawlável | `ccd-frontend-fixes`, `ccd-a11y`, `ccd-blog-infinite-scroll` |
| Smoke de medição (local/URL) | `scripts/seo-smoke.ps1` |

Após deploy, o backfill de conteúdo roda sozinho em lotes nos primeiros requests (`ccd_seo_content` option).

## Checklist manual em produção (Railway)

Faça **uma vez** após o deploy que trouxer Yoast 28.6 + mu-plugins SEO:

### 1) Search Console (Google)

1. Abra [Google Search Console](https://search.google.com/search-console).
2. Adicione propriedade **URL prefix**:  
   `https://convivendocomdiabetes-production.up.railway.app`
3. Escolha verificação por **meta tag**.
4. Copie só o valor de `content="..."` para a variável de ambiente do serviço WordPress:

```text
CCD_GOOGLE_SITE_VERIFICATION=cole_aqui_o_token
```

5. No Railway: Variables → set → redeploy (ou restart) do serviço.
6. Confirme a propriedade no GSC.
7. Em **Sitemaps**, envie:  
   `https://convivendocomdiabetes-production.up.railway.app/sitemap_index.xml`

### 2) Bing Webmaster (opcional, recomendado)

1. [Bing Webmaster Tools](https://www.bing.com/webmasters) → Add site (mesma URL Railway).
2. Pode importar do GSC ou verificar por meta/xml.
3. Envie o mesmo `sitemap_index.xml`.

### 3) Yoast — otimização de dados (indexables)

No wp-admin do Railway:

1. **Yoast SEO → Tools / Ferramentas** (ou “Start SEO data optimization”).
2. Rode a indexação completa até 100%.
3. Confira **Yoast SEO → Settings** se XML sitemaps está ON.

### 4) Core Web Vitals / PageSpeed (campo)

1. Rode [PageSpeed Insights](https://pagespeed.web.dev/) na home e em 1 post típico (URL Railway).
2. Anote LCP / INP / CLS mobile.
3. Repita em 7–14 dias pelo relatório **Core Web Vitals** do Search Console (precisa de tráfego real).

Alvo inicial (paralelo, não legado):

- LCP &lt; 2.5s (mobile)
- INP &lt; 200ms
- CLS &lt; 0.1

### 5) Smoke automatizado pós-deploy

No PC (apontando para Railway):

```powershell
.\scripts\seo-smoke.ps1 -BaseUrl "https://convivendocomdiabetes-production.up.railway.app"
```

Local:

```powershell
.\scripts\seo-smoke.ps1
```

### 6) Revisões editoriais contínuas (não versionadas)

O mu-plugin preenche gaps, mas qualidade “nota 10” ainda pede:

- Revisar focus keyphrase nos posts estratégicos (intenção de busca).
- Atualizar posts de saúde desatualizados (data visível + conteúdo).
- Manter `/sobre/` e canais oficiais alinhados (Instagram/LinkedIn).

## Host canônico

1. Propriedade GSC / sitemap no apex: `https://convivendocomdiabetes.com`.
2. `WP_HOME` / `WP_SITEURL` = `https://convivendocomdiabetes.com`.
3. Confirmar 301 de `www` → apex e reenviar `sitemap_index.xml` se a propriedade GSC mudou.

## Variáveis

| Var | Onde | Uso |
|-----|------|-----|
| `CCD_GOOGLE_SITE_VERIFICATION` | Railway vars / `.env.railway.example` | Meta tag GSC |
| (opcional local) mesma var | `.env` | Testar meta no localhost |
