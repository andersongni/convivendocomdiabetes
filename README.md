# Convivendo com Diabetes

Site WordPress do [Convivendo com Diabetes](https://convivendocomdiabetes.com), com ambiente local em Docker e produção no [Railway](https://railway.com).

Produção: [https://convivendocomdiabetes.com](https://convivendocomdiabetes.com) (`www` → 301 para o apex).

SEO: ver [docs/SEO.md](./docs/SEO.md) (Yoast, sitemaps, E-E-A-T, Search Console / CWV).  
Performance: ver [docs/PERFORMANCE.md](./docs/PERFORMANCE.md) (Redis, page cache, Cloudflare proxy).

## Stack

| Camada | Tecnologia |
|--------|------------|
| App | WordPress (PHP 8.2 + Apache) |
| Banco | MySQL 8 |
| Local | Docker Compose |
| Produção | Railway (Dockerfile + volume de uploads) |
| Infra | Terraform (provider Railway) + GitHub Actions |
| Deploy | Auto-deploy GitHub `main` → Railway |

O código customizado do repositório (Docker, scripts, Terraform, mu-plugins) está sob [MIT](./LICENSE). WordPress, temas e plugins mantêm as licenças dos respectivos projetos.

## Estrutura

```
├── Dockerfile              # Imagem de produção
├── docker-compose.yml      # WordPress + MySQL local
├── .railway/railway.ts     # Infrastructure as Code (Railway)
├── db/schema.sql           # Schema versionado (bootstrap)
├── wordpress/              # Core + wp-content do site
├── terraform/              # Provider Railway (vars/serviços)
├── scripts/
│   ├── prod-entrypoint.sh  # Entrypoint do container
│   ├── wp-boot.sh          # Boot WP em produção
│   └── migration/          # Sync local → Railway (ver README lá)
└── .github/workflows/      # CI + Terraform
```

Uploads (`wordpress/wp-content/uploads/`) e dumps SQL **não** entram no Git nem na imagem Docker. Em produção ficam no volume `wp-uploads-5g`.

## Requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- (Opcional) [Railway CLI](https://docs.railway.com/guides/cli) para sync/migração
- (Opcional) Terraform 1.x para infra

## Variáveis de ambiente

| Arquivo | Ambiente | Uso |
|---------|----------|-----|
| `.env` / `.env.example` | **Localhost** | Docker Compose (`http://localhost:8080`) |
| `.env.railway.example` | **Railway** | Referência; valores no dashboard / IaC (`.railway/railway.ts`) |

Não misturar: o Compose não lê `.env.railway*`. Produção canônica: `https://convivendocomdiabetes.com` (`www` → 301).

## Rodar local

```bash
cp .env.example .env   # so vars de localhost
docker compose up -d
```

Abra [http://localhost:8080](http://localhost:8080).

- O `docker-compose.yml` pode montar `dump-database.sql` na primeira inicialização do MySQL (arquivo local, não versionado).
- Sem dump, o banco sobe vazio/schema conforme o fluxo do container.

Parar:

```bash
docker compose down
```

## Produção (Railway)

1. Validar no localhost (`docker compose`, `http://localhost:8080`).
2. Merge/`push` na `main` → CI → Railway **production** (Wait for CI) — ver [docs/CI.md](./docs/CI.md).
3. Smoke de produção (cron 6h / manual / `repository_dispatch`): apex + 301 de `www`.
4. Variáveis: dashboard Railway / Terraform / `.railway/railway.ts` — catálogo em `.env.railway.example` (não usar `.env` local).

Healthcheck: `GET /ccdhealth` → 200 text/plain. Produção canônica: `https://convivendocomdiabetes.com`.

Alterações de infra Railway via CLI:

```bash
npm install
railway config plan
railway config apply
```

### Migrar conteúdo local → Railway

Banco e mídia **não** sobem a cada push. Use o fluxo documentado em:

**[scripts/migration/README.md](./scripts/migration/README.md)**

Resumo:

```powershell
# Dump + import seguro (túnel SSH, sem MySQL público)
.\scripts\migration\sync-local-to-railway.ps1

# Mídia para o volume
.\scripts\migration\upload-media-to-railway.ps1 -Volume wp-uploads-5g
```

## Terraform / CI

- `terraform/` — serviços, variáveis e domínio no Railway.
- `.github/workflows/` — CI e apply Terraform (secrets: token Railway, etc.).
- Import one-shot: `scripts/terraform-import.ps1` / `.sh`.

## Domínio customizado

No serviço WordPress → Settings → Networking → Custom Domain. Crie no DNS (UOL, Registro.br, etc.) o **CNAME** e o **TXT** indicados pelo Railway. SSL é emitido automaticamente.

## Licença

[MIT](./LICENSE) © 2026 Convivendo com Diabetes / Anderson Ibrahim
