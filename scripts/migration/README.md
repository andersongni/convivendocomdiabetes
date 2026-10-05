# Migração local → Railway

Scripts **one-shot / operacionais** usados para copiar o WordPress (banco + mídia) para o Railway **sem expor MySQL na internet** (túnel SSH).

Pré-requisitos: Docker Desktop, CLI `railway` logado, chave SSH registrada (`railway ssh keys list`), projeto linkado (`railway link`).

Volume de mídia em produção: **`wp-uploads-5g`** (mount `/var/www/html/wp-content/uploads`).

---

## Fluxo principal (recomendado)

Na raiz do repositório:

```powershell
# 1) Sobe o WordPress local (se for exportar dump fresco)
docker compose up -d db

# 2) Sync seguro: dump → túnel SSH → MySQL Railway → restart WP
.\scripts\migration\sync-local-to-railway.ps1

# Usar dump já existente:
.\scripts\migration\sync-local-to-railway.ps1 -UseExistingDump -DumpPath dump-database.sql

# 3) Mídia (uploads) — preferível tarball + extract no volume (mais estável)
#    ou o script abaixo por pastas:
.\scripts\migration\upload-media-to-railway.ps1 -Volume wp-uploads-5g -Dirs 2015,2016,2017,2018,2019,2020,2021,2022
```

O sync:

1. Garante chave SSH no Railway  
2. Lê credenciais do serviço MySQL  
3. Exporta o MySQL local **ou** usa dump informado  
4. Filtra tabelas pesadas (Wordfence/stats) com `filter-dump.py`  
5. Abre túnel `railway connect MySQL --ssh --tunnel-only`  
6. Recria o database e importa  
7. Ajusta `siteurl` / `home`  
8. Reinicia o serviço WordPress  

---

## Scripts

| Script | Uso |
|--------|-----|
| `sync-local-to-railway.ps1` | Fluxo completo banco → Railway via SSH |
| `import-dump.ps1` / `import-dump.sh` | Só importa um `.sql` (local ou host:porta do túnel) |
| `filter-dump.py` | Gera `dump-filtered.sql` sem INSERTs de Wordfence/stats |
| `upload-media-to-railway.ps1` | Sobe pastas de `wordpress/wp-content/uploads` para o volume |
| `list-old-host-links.py` | Lista URLs `/uploads/...` no dump que não estão no volume |
| `fetch-sites-from-old-host.py` | Baixa demos `sites/*` listadas em `old-host-upload-links.txt` |
| `inspect-dump.py` | Mostra CREATE/INSERT por tabela num dump |
| `table-sizes.py` | Estima tamanho dos INSERTs (rodar na raiz com `dump-database.sql`) |
| `check-old-host.sh` | No container WP: dry-run de search-replace do domínio antigo |
| `fix-uploads-perms.sh` | No container: flatten pastas aninhadas + `chown www-data` |

### Exemplos pontuais

```powershell
# Filtrar dump
python .\scripts\migration\filter-dump.py dump-database.sql dump-filtered.sql

# Import local (Docker MySQL na 3306)
.\scripts\migration\import-dump.ps1 -DumpPath dump-filtered.sql

# Import via túnel já aberto na 3307
.\scripts\migration\import-dump.ps1 `
  -DbHost 127.0.0.1 -DbPort 3307 `
  -User root -Password "<MYSQLPASSWORD>" -Database railway `
  -DumpPath dump-filtered.sql `
  -SiteUrl https://convivendocomdiabetes-production.up.railway.app

# Inspecionar dump
python .\scripts\migration\inspect-dump.py dump-filtered.sql
```

No container (após `railway ssh -s convivendocomdiabetes`):

```bash
bash /caminho/fix-uploads-perms.sh
# ou copiar check-old-host.sh para o volume e executar
```

---

## O que **não** vai neste pasta

Runtime do app (sempre no deploy):

- `scripts/prod-entrypoint.sh`
- `scripts/wp-boot.sh`

Terraform:

- `scripts/terraform-import.ps1` / `.sh`

---

## Notas

- Dumps (`.sql`) e tarballs de uploads **não** devem ser commitados (ver `.gitignore`).
- Não use TCP proxy público no MySQL; só túnel SSH.
- Após import, o boot do WP remove URLs do host antigo do banco (`wp search-replace`).
- Backups locais em `uploads/fw-backup`, logs `sucuri`, etc. não precisam ir para o Railway.
