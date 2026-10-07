# SEO — Convivendo com Diabetes

Produção canônica: `https://convivendocomdiabetes.com`  
`https://www.convivendocomdiabetes.com` redireciona (301) para o apex.

## O que o código já promove (git)

| Item | Onde |
|------|------|
| Yoast SEO **28.6** | `wordpress/wp-content/plugins/wordpress-seo/` |
| Sitemap/robots estáveis | `ccd-sitemap-fix.php` |
| Meta/focus/alts no acervo + posts novos | `ccd-seo-content.php` |
| Meta da home + páginas-chave + posts top + titles/descs de categorias + breadcrumbs + related | `ccd-seo-boost.php` |
| Categorias em `/slug/` sem 301 para posts | `ccd-category-urls.php` |
| Pilares YMYL atualizados + posts novos + interlinking de hubs | `ccd-seo-editorial.php` |
| E-E-A-T (disclaimer, autor, datas, Organization/Person) | `ccd-eeat.php` |
| Lazy-load scoped / prev-next / paginação crawlável | `ccd-frontend-fixes`, `ccd-a11y`, `ccd-blog-infinite-scroll` |
| Smoke de medição (local/URL) | `scripts/seo-smoke.ps1` |

Após deploy, o backfill de conteúdo roda sozinho em lotes nos primeiros requests (`ccd_seo_content` option).

## Checklist manual em produção (Railway)

Faça **uma vez** após o deploy que trouxer Yoast 28.6 + mu-plugins SEO:

### 1) Search Console (Google)

1. Abra [Google Search Console](https://search.google.com/search-console).
2. Adicione propriedade **URL prefix**:  
   `https://convivendocomdiabetes.com`
3. Escolha verificação por **meta tag**.
4. Copie só o valor de `content="..."` para a variável de ambiente do serviço WordPress:

```text
CCD_GOOGLE_SITE_VERIFICATION=cole_aqui_o_token
```

5. No Railway: Variables → set → redeploy (ou restart) do serviço.
6. Confirme a propriedade no GSC.
7. Em **Sitemaps**, envie:  
   `https://convivendocomdiabetes.com/sitemap_index.xml`

### 2) Bing Webmaster (opcional, recomendado)

1. [Bing Webmaster Tools](https://www.bing.com/webmasters) → Add site (`https://convivendocomdiabetes.com`).
2. Pode importar do GSC ou verificar por meta/xml.
3. Envie o mesmo `sitemap_index.xml`.

### 3) Yoast — otimização de dados (indexables)

No wp-admin de produção (`https://convivendocomdiabetes.com/login`):

1. **Yoast SEO → Tools / Ferramentas** (ou “Start SEO data optimization”).
2. Rode a indexação completa até 100%.
3. Confira **Yoast SEO → Settings** se XML sitemaps está ON.

### 4) Core Web Vitals / PageSpeed (campo)

1. Rode [PageSpeed Insights](https://pagespeed.web.dev/) na home e em 1 post típico no apex.
2. Anote LCP / INP / CLS mobile.
3. Repita em 7–14 dias pelo relatório **Core Web Vitals** do Search Console (precisa de tráfego real).

Alvo inicial:

- LCP &lt; 2.5s (mobile)
- INP &lt; 200ms
- CLS &lt; 0.1

### 5) Smoke automatizado pós-deploy

No PC (apontando para produção):

```powershell
.\scripts\seo-smoke.ps1 -BaseUrl "https://convivendocomdiabetes.com"
```

Local:

```powershell
.\scripts\seo-smoke.ps1
```

### 6) O que o código já corrige automaticamente

- Hubs `/diabetes/`, `/receitas/` etc. em 200 (sem 301 para posts).
- Titles/metas de categorias, home, páginas-chave e posts top 20.
- Rewrite de metas fracas (“Oi…”, “Ingredientes…”, shortcodes, `&nbsp;`).
- Intro nos arquivos de categoria + breadcrumbs + related posts.
- Cache HTML/`s-maxage` via `ccd-perf` + `ccd-frontend-fixes`.

### 7) Revisões editoriais contínuas (não versionadas)

Metas e URLs técnicas estão cobertas; qualidade YMYL “nota 10” ainda pede revisão humana do **corpo**:

- Atualizar fatos/fontes nos posts de saúde (tratamento, tecnologia, complicações).
- Marcar “Atualizado em” ao republicar (já aparece no bloco E-E-A-T quando `post_modified` muda).
- Manter `/sobre/` e canais oficiais alinhados (Instagram/LinkedIn).

## Top 20 — status técnico

| # | URL | Código |
|---|-----|--------|
| 1–4 | Hubs de categoria | `ccd-category-urls` + descs/titles |
| 5–6, 18–20 | Páginas | metas manuais em `ccd-seo-boost` |
| 7–17 | Posts estratégicos | metas manuais + backfill anti-meta-fraca |
| receitas | cupcake/bolos | metas manuais + template “receita diet…” |

## Host canônico

1. Propriedade GSC / sitemap no apex: `https://convivendocomdiabetes.com`.
2. `WP_HOME` / `WP_SITEURL` = `https://convivendocomdiabetes.com`.
3. Confirmar 301 de `www` → apex e reenviar `sitemap_index.xml` se a propriedade GSC mudou.

## Variáveis

| Var | Onde | Uso |
|-----|------|-----|
| `CCD_GOOGLE_SITE_VERIFICATION` | Railway vars / `.env.railway.example` | Meta tag GSC |
| (opcional local) mesma var | `.env` | Testar meta no localhost |
