<?php
/**
 * CCD Backup bootstrap — native backup/restore + Google Drive.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CCD_BACKUP_OPTION' ) ) {
	define( 'CCD_BACKUP_OPTION', 'ccd_backup_settings' );
}
if ( ! defined( 'CCD_BACKUP_SECRETS' ) ) {
	define( 'CCD_BACKUP_SECRETS', 'ccd_backup_secrets' );
}
if ( ! defined( 'CCD_BACKUP_SYNCED' ) ) {
	define( 'CCD_BACKUP_SYNCED', 'ccd_backup_synced_files' );
}
if ( ! defined( 'CCD_BACKUP_STATUS_LOG' ) ) {
	define( 'CCD_BACKUP_STATUS_LOG', 'ccd_backup_status_log' );
}
if ( ! defined( 'CCD_BACKUP_LIVE' ) ) {
	define( 'CCD_BACKUP_LIVE', 'ccd_backup_live_progress' );
}
if ( ! defined( 'CCD_BACKUP_JOB' ) ) {
	define( 'CCD_BACKUP_JOB', 'ccd_backup_job' );
}
if ( ! defined( 'CCD_BACKUP_OAUTH_SCOPE' ) ) {
	define( 'CCD_BACKUP_OAUTH_SCOPE', 'https://www.googleapis.com/auth/drive.file' );
}
if ( ! defined( 'CCD_BACKUP_FOLDER_NAME' ) ) {
	define( 'CCD_BACKUP_FOLDER_NAME', 'Backup Google Drive' );
}
if ( ! defined( 'CCD_BACKUP_CRON_HOOK' ) ) {
	define( 'CCD_BACKUP_CRON_HOOK', 'ccd_backup_scheduled_run' );
}
if ( ! defined( 'CCD_BACKUP_SYNC_CRON' ) ) {
	define( 'CCD_BACKUP_SYNC_CRON', 'ccd_backup_sync_pending' );
}
if ( ! defined( 'CCD_BACKUP_STATUS_MAX' ) ) {
	define( 'CCD_BACKUP_STATUS_MAX', 8 );
}
if ( ! defined( 'CCD_BACKUP_FORMAT' ) ) {
	define( 'CCD_BACKUP_FORMAT', 'ccdbackup' );
}
if ( ! defined( 'CCD_BACKUP_FORMAT_VERSION' ) ) {
	define( 'CCD_BACKUP_FORMAT_VERSION', 1 );
}

$dir = trailingslashit( CCD_BACKUP_DIR );
require_once $dir . 'includes/class-ccd-backup-paths.php';
require_once $dir . 'includes/class-ccd-backup-crypto.php';
require_once $dir . 'includes/class-ccd-backup-settings.php';
require_once $dir . 'includes/class-ccd-backup-progress.php';
require_once $dir . 'includes/class-ccd-backup-drive.php';
require_once $dir . 'includes/class-ccd-backup-export.php';
require_once $dir . 'includes/class-ccd-backup-import.php';
require_once $dir . 'includes/class-ccd-backup-cron.php';
require_once $dir . 'includes/class-ccd-backup-ajax.php';
require_once $dir . 'includes/class-ccd-backup-admin.php';

add_filter( 'cron_schedules', array( 'CCD_Backup_Cron', 'register_schedules' ), 9999 );

add_action( CCD_BACKUP_CRON_HOOK, array( 'CCD_Backup_Cron', 'run_scheduled_backup' ) );
add_action( CCD_BACKUP_SYNC_CRON, array( 'CCD_Backup_Drive', 'sync_pending_backups' ) );

add_action(
	'init',
	static function () {
		CCD_Backup_Crypto::maybe_migrate_legacy();
		CCD_Backup_Cron::maybe_align_sync_cron();
	},
	30
);

add_action(
	'admin_init',
	static function () {
		if ( ! get_option( 'ccd_backup_cron_bootstrapped' ) ) {
			CCD_Backup_Cron::reschedule_backup_cron();
			CCD_Backup_Cron::reschedule_sync_cron();
			update_option( 'ccd_backup_cron_bootstrapped', 1, false );
		}
	}
);

CCD_Backup_Ajax::init();
CCD_Backup_Admin::init();
