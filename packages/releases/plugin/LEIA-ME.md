# CCD Backup Google Drive — como usar

Este pacote instala o plugin **CCD Backup Google Drive** em qualquer WordPress.

## O que está nesta pasta

| Arquivo | Para quê |
|---------|----------|
| `ccd-backup-X.Y.Z.zip` | Instalador do plugin (envie no WordPress) |
| `LEIA-ME.md` | Este guia |

## Instalar em outro blog

1. Baixe o arquivo `ccd-backup-*.zip` desta pasta (`plugin` no Google Drive).
2. No WordPress de destino: **Plugins → Adicionar novo → Enviar plugin**.
3. Escolha o ZIP → **Instalar agora** → **Ativar**.
4. No menu lateral, abra **CCD Backup**.

## Vincular o Google Drive

1. Crie (ou reutilize) um projeto no [Google Cloud Console](https://console.cloud.google.com/).
2. Ative a **Google Drive API**.
3. Crie credenciais OAuth **Aplicativo da Web**:
   - Em **URIs de redirecionamento autorizados**, cole exatamente o *Redirect URI* mostrado na tela do plugin (termina em `admin-post.php?action=ccd_backup_oauth`).
4. Em **CCD Backup → Avançado**, preencha **Client ID** e **Client Secret**.
5. Clique em **Vincular Google Drive** e autorize a conta.
6. Defina a pasta no Drive (mesmo nome usado neste backup, se quiser reutilizar).

## Backup e restauração

- **Criar backup**: gera um arquivo `.ccdbackup` (banco + arquivos escolhidos) e envia ao Drive.
- **Restaurar**: escolha um `.ccdbackup` local; isso **substitui** banco e arquivos incluídos — faça um backup antes.
- **Agendamento**: em Configurações, ative backup automático, frequência e horário do site.
- **Retenção**: quantos backups manter na pasta do Drive (os mais antigos são removidos).

## Requisitos

- WordPress 6.0+
- PHP 8.0+ com `curl`, `zip` (ZipArchive) e `openssl`
- Se o site usa `DISABLE_WP_CRON`, configure um cron externo em `/wp-cron.php`

## Formato `.ccdbackup`

É um ZIP com `manifest.json`, `database.sql` e pasta `files/` (caminhos relativos a `wp-content`).  
Não é compatível com All-in-One WP Migration (`.wpress`).

## Onde ficam os arquivos no Drive

```
Pasta configurada no plugin/
├── *.ccdbackup          ← backups do site
└── plugin/
    ├── ccd-backup-*.zip ← instalador deste plugin
    └── LEIA-ME.md       ← este arquivo
```

A pasta `plugin/` é atualizada a cada backup enviado ao Drive: o site sobe o ZIP e o LEIA-ME novos e só então remove os arquivos antigos, para a pasta nunca ficar sem o instalador atual.
