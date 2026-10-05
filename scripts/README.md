# scripts/

| Pasta / arquivo | Função |
|-----------------|--------|
| **`migration/`** | Migração local → Railway (dump, mídia, diagnósticos). Ver [migration/README.md](./migration/README.md). |
| `prod-entrypoint.sh` | Entrypoint do container WordPress no Railway |
| `wp-boot.sh` | Boot WP (config, tema, URLs, permissões de uploads) |
| `terraform-import.ps1` / `.sh` | Import one-shot do estado Terraform do projeto Railway |
