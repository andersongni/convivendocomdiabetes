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
| Build | Dockerfile de produção |
| `/ccdhealth` | Alias Apache, sem DB, sem 301 |
| MySQL+Redis integration | `wp-boot` + `/login` + www→apex 301 + Redis auth + degradacao sem senha |

Script local da integração: `scripts/ci-integration-mysql.sh` (precisa da imagem `convivendocomdiabetes:ci`).

**Regressao coberta:** Redis com `requirepass` sem `WP_REDIS_PASSWORD` **nao** pode derrubar `/ccdhealth` (boot degrada sem object-cache).

## Smoke production (`.github/workflows/smoke-prod.yml`)

Alvos:

- `https://convivendocomdiabetes.com/ccdhealth` → 200 `ok`
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

- CodeQL: PRs + cron (fora do gate Wait for CI).
- Terraform: validate em paths; apply só via `workflow_dispatch`.
