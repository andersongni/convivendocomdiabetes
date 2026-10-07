<?php
/**
 * Plugin settings and schedule intervals.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CCD_Backup_Settings {

	/**
	 * @return array<string,mixed>
	 */
	public static function get() {
		CCD_Backup_Crypto::maybe_migrate_legacy();

		$opt = get_option( CCD_BACKUP_OPTION, array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}

		$client_id = isset( $opt['client_id'] ) ? trim( (string) $opt['client_id'] ) : '';
		if ( $client_id === '' ) {
			$env = getenv( 'CCD_BACKUP_CLIENT_ID' );
			if ( $env ) {
				$client_id = trim( (string) $env );
			}
		}

		$interval = isset( $opt['schedule_interval'] ) ? (string) $opt['schedule_interval'] : 'daily';
		if ( ! array_key_exists( $interval, self::schedule_intervals() ) ) {
			$interval = 'daily';
		}

		return array(
			'enable'                => ! empty( $opt['enable'] ),
			'keep_local'            => array_key_exists( 'keep_local', $opt ) ? ! empty( $opt['keep_local'] ) : true,
			'client_id'             => $client_id,
			'folder_id'             => isset( $opt['folder_id'] ) ? trim( (string) $opt['folder_id'] ) : '',
			'plugin_folder_id'      => isset( $opt['plugin_folder_id'] ) ? trim( (string) $opt['plugin_folder_id'] ) : '',
			'folder_name'           => isset( $opt['folder_name'] ) ? trim( (string) $opt['folder_name'] ) : CCD_BACKUP_FOLDER_NAME,
			'account_email'         => isset( $opt['account_email'] ) ? trim( (string) $opt['account_email'] ) : '',
			'connected_at'          => isset( $opt['connected_at'] ) ? (int) $opt['connected_at'] : 0,
			'schedule_enable'       => ! empty( $opt['schedule_enable'] ),
			'schedule_interval'     => $interval,
			'schedule_hour'         => isset( $opt['schedule_hour'] ) ? max( 0, min( 23, (int) $opt['schedule_hour'] ) ) : 3,
			'retention_count'       => isset( $opt['retention_count'] ) ? max( 0, min( 100, (int) $opt['retention_count'] ) ) : 5,
			'sync_interval_minutes' => self::clamp_sync_interval_minutes( $opt['sync_interval_minutes'] ?? 15 ),
			'include_uploads'       => array_key_exists( 'include_uploads', $opt ) ? ! empty( $opt['include_uploads'] ) : true,
			'include_plugins'       => array_key_exists( 'include_plugins', $opt ) ? ! empty( $opt['include_plugins'] ) : true,
			'include_themes'        => array_key_exists( 'include_themes', $opt ) ? ! empty( $opt['include_themes'] ) : true,
			'include_mu_plugins'    => array_key_exists( 'include_mu_plugins', $opt ) ? ! empty( $opt['include_mu_plugins'] ) : true,
		);
	}

	/**
	 * @param array<string,mixed> $patch
	 */
	public static function update( array $patch ) {
		$current = self::get();
		$merged  = array_merge( $current, $patch );
		update_option( CCD_BACKUP_OPTION, $merged, false );
		return $merged;
	}

	/**
	 * @param mixed $value
	 */
	public static function clamp_sync_interval_minutes( $value ) {
		if ( $value === null || $value === '' ) {
			return 15;
		}
		return max( 1, min( 99, (int) $value ) );
	}

	/**
	 * @param int|null $minutes
	 */
	public static function sync_cron_schedule_key( $minutes = null ) {
		$m = $minutes !== null ? self::clamp_sync_interval_minutes( $minutes ) : (int) self::get()['sync_interval_minutes'];
		return 'ccd_backup_sync_' . $m . 'min';
	}

	/**
	 * @return array<string,string>
	 */
	public static function schedule_intervals() {
		return array(
			'hourly'     => 'A cada hora',
			'twicedaily' => 'Duas vezes ao dia',
			'daily'      => 'Diário',
			'weekly'     => 'Semanal',
			'monthly'    => 'Mensal',
		);
	}

	public static function is_connected() {
		$s = CCD_Backup_Crypto::get_secrets();
		return $s['refresh_token'] !== '';
	}

	/**
	 * @return array<string,bool>
	 */
	public static function include_flags() {
		$s = self::get();
		return array(
			'uploads'    => ! empty( $s['include_uploads'] ),
			'plugins'    => ! empty( $s['include_plugins'] ),
			'themes'     => ! empty( $s['include_themes'] ),
			'mu_plugins' => ! empty( $s['include_mu_plugins'] ),
		);
	}

	public static function schedule_first_run() {
		$settings = self::get();
		$hour     = (int) $settings['schedule_hour'];
		$tz       = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $tz );
		$next     = $now->setTime( $hour, 0, 0 );
		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}
		return $next->getTimestamp();
	}
}
