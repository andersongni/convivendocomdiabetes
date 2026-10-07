=== CCD Backup Google Drive ===
Contributors: ccd
Tags: backup, google drive, restore, schedule, export
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Backup e restore nativos (banco + wp-content) com agendamento e Google Drive.

== Description ==

* Exporta banco (prefixo do site) + pastas do wp-content para um arquivo `.ccdbackup`
* Importa o mesmo formato (substitui SQL e arquivos incluídos)
* OAuth Google Drive, upload resumable, rotação e sync de pendentes
* Agendamento (horário / diário / semanal / etc.)
* Painel de progresso ao vivo no admin

= Instalação rápida =

1. Envie o ZIP em Plugins → Adicionar novo → Enviar plugin
2. Ative **CCD Backup Google Drive**
3. Abra o menu **CCD Backup**
4. Vincule o Google Drive (Client ID/Secret no Google Cloud Console)
5. Redirect URI: `https://SEU-DOMINIO/wp-admin/admin-post.php?action=ccd_backup_oauth`

= Requisitos =

* PHP 8+ com extensões `curl`, `zip` (ZipArchive) e `openssl`
* Google Drive API ativada no projeto OAuth

= Observação sobre WP-Cron =

Se o site usa `DISABLE_WP_CRON`, configure um cron externo batendo em `/wp-cron.php` para o agendamento disparar.

== Changelog ==

= 1.0.0 =
* Primeira versão pública instalável
