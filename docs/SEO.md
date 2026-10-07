# SEO — Convivendo com Diabetes

Produção canônica: `https://convivendocomdiabetes.com`  
`https://www.convivendocomdiabetes.com` redireciona (301) para o apex.

## O que o código já promove (git)

| Item | Onde |
|------|------|
| Yoast SEO **28.6** | `wordpress/wp-content/plugins/wordpress-seo/` |
| Sitemap/robots estáveis | `ccd-sitemap-fix.php` |
| Meta/focus/alts no acervo + posts novos | `ccd-seo-content.php` |
| Meta da home + `/blog/` + `/contato/` + titles de categorias + breadcrumbs + related | `ccd-seo-boost.php` |
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

### 6) Revisões editoriais contínuas (não versionadas)

O mu-plugin preenche gaps, mas qualidade “nota 10” ainda pede:

- Revisar focus keyphrase nos posts estratégicos (intenção de busca).
- Atualizar posts de saúde desatualizados (data visível + conteúdo).
- Manter `/sobre/` e canais oficiais alinhados (Instagram/LinkedIn).

## Top 20 URLs para reescrever primeiro

Prioridade = intenção de busca + meta fraca + frescor (YMYL). Para cada URL: meta description manual (120–155 chars), focus keyphrase, revisão factual e data “Atualizado em”.

| # | URL | Por quê |
|---|-----|---------|
| 1 | `/diabetes/` | Arquivo-pilar; title/snippet já vazaram errados no Google |
| 2 | `/diabetes/diabetes-tipo-1-e-tipo-2/` | Alta intenção informacional |
| 3 | `/diabetes/alimentacao/` | Cluster de comida + diabetes |
| 4 | `/receitas/` | Hub de receitas; snippet genérico de post antigo |
| 5 | `/blog/` | Listagem principal (meta já coberta no código; revisar intro) |
| 6 | `/contato/` | Meta com shortcode (já coberta no código; revisar corpo) |
| 7 | `/diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico/` | Pillar “o que é / diagnóstico” |
| 8 | `/diabetes-tipo-2-tem-cura-saiba-mais-sobre-os-tratamentos-disponiveis/` | Query YMYL forte; atualizar tratamentos |
| 9 | `/hipoglicemia/` | Termo de cabeça curto |
| 10 | `/quantas-vezes-por-dia-devo-medir-minha-glicemia/` | Intenção prática / how-to |
| 11 | `/jejum-intermitente-e-diabetes-e-permitido-ou-nao/` | Query em alta; conteúdo sensível |
| 12 | `/a-logica-do-cuidado-no-tratamento-de-diabetes/` | Tratamento / cuidado contínuo |
| 13 | `/novidades-no-tratamento-do-diabetes-falta-pouco-para-o-pancreas-artificial/` | Desatualizado (ATTD 2017); reescrever ou arquivar |
| 14 | `/brasileiros-mais-proximos-do-pancreas-artificial/` | Tecnologia; checar datas/produtos |
| 15 | `/entenda-como-o-diabetes-pode-afetar-a-visao/` | Complicações (YMYL) |
| 16 | `/metade-dos-diabeticos-morre-devido-a-infarto-ou-avc-mas-apenas-3-temem-essa-complicacao/` | CV + diabetes; atualizar fontes |
| 17 | `/viagens-e-diabetes-um-guia-para-se-dar-bem-quando-estiver-longe-de-casa/` | Guia evergreen |
| 18 | `/clipping/` | Meta com `&nbsp;` / pouco útil |
| 19 | `/resenha-de-livros/` | Meta genérica igual ao title |
| 20 | `/eventos-e-campanhas/` | Meta genérica; alinhar com arquivo de categoria |

Depois dessas 20: receitas com meta começando em “Ingredientes…” (ex.: cupcake de maçã, bolo red velvet, bolo de fubá).

## Host canônico

1. Propriedade GSC / sitemap no apex: `https://convivendocomdiabetes.com`.
2. `WP_HOME` / `WP_SITEURL` = `https://convivendocomdiabetes.com`.
3. Confirmar 301 de `www` → apex e reenviar `sitemap_index.xml` se a propriedade GSC mudou.

## Variáveis

| Var | Onde | Uso |
|-----|------|-----|
| `CCD_GOOGLE_SITE_VERIFICATION` | Railway vars / `.env.railway.example` | Meta tag GSC |
| (opcional local) mesma var | `.env` | Testar meta no localhost |
