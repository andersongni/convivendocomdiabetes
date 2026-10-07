<?php
/**
 * Plugin Name:       CCD Backup Google Drive
 * Plugin URI:        https://github.com/convivendocomdiabetes/ccd-backup
 * Description:       Backup e restore nativos do WordPress (banco + wp-content) com agendamento e envio ao Google Drive.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            CCD
 * Author URI:        https://convivendocomdiabetes.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ccd-backup
 *
 * @package CCD_Backup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CCD_BACKUP_VERSION', '1.0.0' );
define( 'CCD_BACKUP_FILE', __FILE__ );
define( 'CCD_BACKUP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCD_BACKUP_URL', plugin_dir_url( __FILE__ ) );

$ccd_backup_bootstrap = CCD_BACKUP_DIR . 'bootstrap.php';
if ( ! is_readable( $ccd_backup_bootstrap ) ) {
	return;
}

require_once $ccd_backup_bootstrap;

register_activation_hook(
	__FILE__,
	static function () {
		if ( class_exists( 'CCD_Backup_Paths' ) ) {
			CCD_Backup_Paths::ensure_backup_dir();
			CCD_Backup_Paths::ensure_temp_dir();
		}
		if ( class_exists( 'CCD_Backup_Cron' ) ) {
			CCD_Backup_Cron::reschedule_backup_cron();
			CCD_Backup_Cron::reschedule_sync_cron();
			update_option( 'ccd_backup_cron_bootstrapped', 1, false );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		if ( defined( 'CCD_BACKUP_CRON_HOOK' ) ) {
			wp_clear_scheduled_hook( CCD_BACKUP_CRON_HOOK );
		}
		if ( defined( 'CCD_BACKUP_SYNC_CRON' ) ) {
			wp_clear_scheduled_hook( CCD_BACKUP_SYNC_CRON );
		}
		delete_option( 'ccd_backup_cron_bootstrapped' );
	}
);
