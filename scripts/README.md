# scripts/

| Pasta / arquivo | Função |
|-----------------|--------|
| **`migration/`** | Migração local → Railway (dump, mídia, diagnósticos). Ver [migration/README.md](./migration/README.md). |
| `prod-entrypoint.sh` | Entrypoint do container WordPress no Railway |
| `wp-boot.sh` | Boot WP (config, tema, URLs, permissões de uploads) |
| `local-entrypoint.sh` / `sync-wp-content.sh` | Local rápido: copia plugins/themes do host → volume Linux |
| `sync-local-wp-content.ps1` | Força o re-sync local após editar plugins/temas no host |
| `pull-wp-content-from-container.ps1` | Volume Docker → host/git (manual; automático via mu-plugin) |
| `ccd-persist-to-host.sh` | Usado pelo container apos update no WP Admin |
| [docs/UPDATES.md](../docs/UPDATES.md) | Política: atualizar local e promover via git/IaC |
| `optimize-uploads.py` | Comprime/redimensiona imagens em `wp-content/uploads` (Pillow) |
| `seo-smoke.ps1` | Smoke SEO (robots/sitemap/meta/alts) local ou Railway — ver [docs/SEO.md](../docs/SEO.md) |
| `terraform-import.ps1` / `.sh` | Import one-shot do estado Terraform do projeto Railway |

### Local rápido (Windows/Docker)

O `docker-compose.yml` mantém `plugins`/`themes` num volume Docker (filesystem Linux) e só usa o bind mount de `C:\` para sync. Uploads continuam no host.

```powershell
docker compose up -d
# Depois de mudar plugin/tema no disco:
.\scripts\sync-local-wp-content.ps1
```
