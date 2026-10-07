# Updates (localhost → git → Railway)

## Por que o admin pedia update o tempo todo

1. Plugins/temas versionados em `wordpress/wp-content/` ficam atrás do wordpress.org.
2. O core em produção vem da imagem Docker `wordpress:php8.3-apache` (não do admin “Atualizar”).
3. Em produção **não** se atualiza pelo painel: o filesystem do container é efêmero; só `uploads` é volume. Um “Atualizar agora” no Railway some no próximo deploy.

Fluxo correto: **atualizar no localhost → gravar no git → push → rebuild IaC/Dockerfile**.

## Política

| Ambiente | Updates pelo admin | Como promover |
|----------|--------------------|---------------|
| Localhost (`http://localhost:8080`) | Permitido | Commit + push |
| Railway | Bloqueado (`DISALLOW_FILE_MODS` + mu-plugin `ccd-update-policy`) | Só via git |

## Plugins e temas

### Opção A — editar no host (preferida)

1. Baixe/substitua pastas em `wordpress/wp-content/plugins/` ou `themes/`.
2. Sincronize para o volume Docker:
   ```powershell
   .\scripts\sync-local-wp-content.ps1
   ```
3. Teste em `http://localhost:8080`.
4. `git add` + commit + push `main` → Railway rebuilda com `COPY wordpress/wp-content/`.

### Opção B — atualizar no WP Admin / wp-cli do container

1. Atualize no admin local (ou `docker compose exec wordpress wp plugin update --all --allow-root`).
2. **Puxe** o volume de volta para o git:
   ```powershell
   .\scripts\pull-wp-content-from-container.ps1
   ```
3. Revise o diff, teste, commit e push.

Sem o passo 2, o próximo sync do host **reverte** as atualizações.

## Core do WordPress

- Produção: bump da tag/digest em `Dockerfile` / `docker/wordpress-local.Dockerfile` (`FROM wordpress:php8.3-apache`).
- Rebuild local: `docker compose build wordpress && docker compose up -d`.
- O tree `wordpress/wp-includes` no git é referência; o deploy usa o core da imagem oficial.

## Checklist rápido

- [ ] Update só no localhost
- [ ] Arquivos em `wordpress/wp-content/` (host) batem com o que foi testado
- [ ] Smoke local OK
- [ ] Commit + push `main`
- [ ] Deploy Railway verde (healthcheck `/ccdhealth`)
