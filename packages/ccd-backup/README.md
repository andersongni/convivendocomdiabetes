# CCD Backup Google Drive

Plugin WordPress instalável: backup/restore nativos + Google Drive.

## Instalar em outro blog

### Pelo Google Drive (recomendado)

Após um backup com Drive vinculado, o site envia automaticamente para a pasta configurada:

```
Pasta do backup/
└── plugin/
    ├── ccd-backup-X.Y.Z.zip
    └── LEIA-ME.md
```

Baixe o ZIP em **Plugins → Adicionar novo → Enviar plugin**. Detalhes em `drive-plugin/LEIA-ME.md`.

### Pelo repositório

1. Gere o ZIP:

```powershell
.\scripts\package-ccd-backup.ps1
```

Arquivos em `packages/releases/ccd-backup-1.0.0.zip` e espelho em `packages/releases/plugin/`.

2. No outro WordPress: **Plugins → Adicionar novo → Enviar plugin** → escolha o ZIP → **Ativar**.

3. Menu **CCD Backup**:
   - Vincular Google Drive (Client ID / Secret)
   - Redirect URI exato mostrado na tela (`…/wp-admin/admin-post.php?action=ccd_backup_oauth`)
   - Ativar Drive API no Google Cloud do mesmo projeto OAuth

## Conteúdo do pacote

```
ccd-backup/
  ccd-backup.php      # entry point
  bootstrap.php
  readme.txt
  README.md
  includes/           # classes
```

## Formato `.ccdbackup`

ZIP com `manifest.json`, `database.sql` e `files/` (relativos a `wp-content`).

## Desenvolvimento neste monorepo

Fonte canônica: `packages/ccd-backup/`.  
No site local o mesmo código vive em `wordpress/wp-content/plugins/ccd-backup/` (sincronize após editar o pacote).
