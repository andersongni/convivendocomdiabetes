# Staging — Convivendo com Diabetes

Environment Railway **`staging`** (isolado de production): MySQL + WordPress + volumes próprios.

| Item | Valor |
|------|--------|
| Branch | `staging` |
| URL | https://convivendocomdiabetes-staging.up.railway.app |
| Health | https://convivendocomdiabetes-staging.up.railway.app/ccdhealth |
| Wait for CI | sim (`checkSuites`) |
| Volumes | `mysql-staging` (500 MB), `wp-uploads-staging` (1 GB) |

## Fluxo

```
localhost → push origin staging → CI verde → deploy Railway staging
         → validar URL staging
         → PR staging → main (ou merge) → CI → deploy production
```

## Como usar

```powershell
git checkout staging
git merge main   # ou commits locais
git push origin staging
```

Depois do CI verde, abra a URL de staging. Promova para produção só com o que passou lá:

```powershell
git checkout main
git merge staging
git push origin main
```

## O que NÃO compartilha com production

- Banco (volume MySQL separado; dados iniciais vazios até você importar)
- Uploads (`wp-uploads-staging`)
- `WP_HOME` / `WP_SITEURL` (domínio Railway de staging)
- Domínios customizados do apex/`www` (não existem em staging)

Secrets (admin, DB password, reCAPTCHA) foram copiados na criação do environment; ajuste no dashboard se precisar valores distintos.

## Notas

- Staging usa `*.up.railway.app` de propósito; produção continua no apex.
- MySQL staging: start command com `chmod 777 /var/lib/mysql` + `docker-entrypoint.sh` (volume novo do Railway começa sem permissão de escrita para o uid do MySQL).
- Custo: roda MySQL + WordPress a mais no projeto Railway.
