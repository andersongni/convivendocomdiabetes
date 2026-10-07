# Deploy blue/green (zero-downtime) — Railway

## O que está configurado

| Mecanismo | Valor | Efeito |
|-----------|--------|--------|
| Wait for CI | `checkSuites: true` | Deploy só depois do GitHub Actions verde |
| Healthcheck | `GET /ccdready` | Novo deploy só recebe tráfego se MySQL responder |
| Liveness | `GET /ccdhealth` | Probe estático (CI / monitoramento) |
| Overlap | 90s | Deploy anterior ainda serve após o novo ficar healthy |
| Draining | 40s | SIGTERM → grace → SIGKILL no anterior |
| Restart | `ALWAYS` (10 retries) | Crash em runtime sobe de novo |

Se o healthcheck **falhar**, o Railway marca o deploy como failed e **mantém o anterior** servindo (desde que o remount do volume não tenha derrubado o anterior — ver abaixo).

## Limite do volume de uploads

O serviço WordPress monta `wp-uploads-5g` em `/var/www/html/wp-content/uploads`.

A Railway **não permite dois deploys montados no mesmo volume**. Por isso, no cutover ainda pode haver um **remount breve** (segundos), mesmo com healthcheck e overlap.

- **Caso o deploy novo falhe o `/ccdready`**: o ideal é o anterior continuar; com volume o gap de remount ainda existe.
- **Zero-downtime absoluto** exige mídia fora do volume (object storage público/CDN) e **desmontar** o volume do serviço WordPress. Aí overlap + drain funcionam de ponta a ponta.

Staging → main continua sendo o promote seguro de *código* antes da produção.

## Fluxo prático

```
push main → CI → build novo (anterior ainda no ar)
           → sobe container → /ccdready 200?
                sim → trafego para o novo → overlap 90s → drain anterior
                nao → deploy FAILED → anterior permanece (quando possível)
```

## Probes

```bash
# Liveness (estático)
curl -fsS https://convivendocomdiabetes.com/ccdhealth

# Readiness (MySQL)
curl -fsS https://convivendocomdiabetes.com/ccdready
```
