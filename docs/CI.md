# CI / CD — Convivendo com Diabetes

## Fluxo

```
localhost → push/PR main → CI → Railway production (checkSuites)
                              ↓
                       Smoke production (agenda / manual / repository_dispatch)
```

Validação pré-produção é no **localhost** (`docker compose`, `http://localhost:8080`). Não há environment staging no pipeline.

O smoke de produção **não** roda no mesmo `push` do CI. Se rodasse, o Wait for CI do Railway esperaria o smoke, e o smoke esperaria o deploy → deadlock.

## CI (`.github/workflows/ci.yml`)

| Etapa | O que valida |
|-------|----------------|
| PHP lint | `mu-plugins` + `ccd-backup` |
| PHPUnit | Contratos do **núcleo** dos mu-plugins (`tests/Unit`); coverage pcov do núcleo no log/Summary + artifact `phpunit-coverage-core` |
| Build | Dockerfile de produção |
| `/ccdhealth` | Liveness estático, sem DB, sem 301 |
| `/ccdready` | Readiness MySQL (gate blue/green no Railway) |
| MySQL+Redis integration | `wp-boot` + `/login` + www→apex 301 + Redis auth + degradacao + **hero de categoria** |
| Seed + SEO smoke | Conteúdo mínimo + old-slug + robots/sitemap/metas/`/diabetes/` (`scripts/ci-seed-content.sh`, `ci-seo-smoke.sh`) |
| UX smoke | Admin CLI, comentários, upload, blog/contato/nav (`scripts/ci-ux-smoke.sh`) |
| Playwright E2E | Caminhos críticos no mesmo stack (`e2e/`) |

Script local da integração: `scripts/ci-integration-mysql.sh` (precisa da imagem `convivendocomdiabetes:ci`).

Para pular E2E localmente: `CCD_CI_SKIP_E2E=1 ./scripts/ci-integration-mysql.sh`.

Blue/green / overlap: [BLUE_GREEN.md](./BLUE_GREEN.md).

### O que é mu-plugin?

**mu-plugin** (*must-use plugin*): PHP em `wordpress/wp-content/mu-plugins/` que o WordPress **sempre** carrega (não precisa ativar em Plugins). No CCD é onde vive a lógica versionada do site (SEO, cache, login, a11y…).

### PHPUnit (local) — cobertura do núcleo

O `phpunit.xml.dist` mede só o **núcleo testável** (libs/helpers/contratos), não os ~5k linhas de glue WP/HTML. Assim o % é acionável.

| Arquivo / área | Papel |
|----------------|--------|
| `ccd-core/*` (ex.: `PageCacheGuard.php`) | **Único escopo do % de coverage** — classes puras |
| `ccd-page-cache-lib.php` + demais mu-plugins | Testados por Unit/smokes; não entram no % (extrair para `ccd-core/` ao endurecer) |

```bash
composer install
composer test
composer test:coverage        # nucleo → coverage/html + clover.xml (precisa pcov/xdebug)
composer test:coverage:full   # todos mu-plugins (diagnostico; % baixo e esperado)
composer test:mutation        # Infection em ccd-core/PageCacheGuard (MSI coberto >= 70)
```

No CI, o job `unit` imprime o resumo no log e no **Job Summary**, e sobe o artifact **phpunit-coverage-core** (HTML + Clover, 14 dias).

Mutation testing (Infection) é **opcional/local** (`composer test:mutation`) — não roda em todo push (custo). Escopo: `mu-plugins/ccd-core/`.

### Regressoes cobertas

- Redis com `requirepass` sem `WP_REDIS_PASSWORD` **nao** pode derrubar `/ccdhealth` (boot degrada sem object-cache).
- Hero de categoria **nao** pode renderizar `.ccd-category-intro` nem a meta/descricao do termo no banner (altura + mensagem indevida). O CI cria a categoria `ci-hub` com marker e falha se o texto aparecer no hero.
- Hub visual de categoria (`.ccd-hub-pillars` / “Pilares” / “Antes das receitas”) **nao** deve aparecer no HTML. PHPUnit (`EditorialHubTest`) + SEO smoke em `/diabetes/` e `/receitas/`.
- `/diabetes/` **nao** redireciona para post com `_wp_old_slug=diabetes` (seed + SEO smoke + E2E).
- `/category/diabetes/` → **301** limpo para `/diabetes/` (Yoast stripcategorybase).
- Metas uteis em blog/contato/clipping/hipoglicemia; pilares editoriais; schema Organization (E-E-A-T).

## Smoke production (`.github/workflows/smoke-prod.yml`)

Alvos:

- `https://convivendocomdiabetes.com/ccdhealth` → 200 `ok`
- `https://convivendocomdiabetes.com/ccdready` → 200 `ok` (MySQL)
- Home e `/login` no apex → 200
- `https://www.convivendocomdiabetes.com/` → **301** para o apex

Triggers: cron 6h, `workflow_dispatch`, `repository_dispatch` (`railway-deploy`).

Script: `scripts/smoke-prod.sh`.

### Pós-deploy via Railway (opcional)

1. Crie um Personal Access Token (classic) no GitHub com escopo `repo` (ou fine-grained: Actions write no repo).
2. No GitHub: secret do repositório `SMOKE_DISPATCH_TOKEN` = esse token (só se for chamar a API de outro lugar; o webhook Railway usa o token no header).
3. No Railway → Project → Webhooks → Add:
   - URL: `https://api.github.com/repos/andersongni/convivendocomdiabetes/dispatches`
   - Events: `Deployment.deployed` (e opcionalmente `Deployment.redeployed`)
   - Header customizado: `Authorization: Bearer <token>`
   - Header: `Accept: application/vnd.github+json`
   - Body não é configurável em todos os planos; se o Railway só POSTar o payload próprio, use um proxy (Cloudflare Worker / small function) que traduza para:

```json
{ "event_type": "railway-deploy", "client_payload": {} }
```

Alternativa simples: rodar **Actions → Smoke production → Run workflow** após um deploy sensível, ou confiar no cron de 6h.

## CodeQL / Terraform

- **CodeQL first-party** (`.github/workflows/codeql.yml`): PRs + cron; so first-party + `security-extended` (fora do gate Wait for CI).
- **CodeQL** (default setup do GitHub): scan mais amplo do repo com suite `default` — nome diferente de proposito.
- Terraform: validate em paths; apply só via `workflow_dispatch`.
