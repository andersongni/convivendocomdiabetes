<?php
/**
 * Encrypted secrets storage (AES-256) and one-time option migration helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CCD_Backup_Crypto {

	/**
	 * @param string $suffix
	 */
	public static function key( $suffix = '|ccd-backup' ) {
		return hash( 'sha256', wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . $suffix, true );
	}

	/**
	 * @param string      $plain
	 * @param string|null $suffix
	 */
	public static function encrypt( $plain, $suffix = null ) {
		$plain = (string) $plain;
		if ( $plain === '' ) {
			return '';
		}
		$key    = self::key( $suffix ?? '|ccd-backup' );
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( $cipher === false ) {
			return '';
		}
		return base64_encode( $iv . $cipher );
	}

	/**
	 * @param string      $blob
	 * @param string|null $suffix
	 */
	public static function decrypt( $blob, $suffix = null ) {
		$blob = (string) $blob;
		if ( $blob === '' ) {
			return '';
		}
		$raw = base64_decode( $blob, true );
		if ( $raw === false || strlen( $raw ) < 17 ) {
			return '';
		}
		$key    = self::key( $suffix ?? '|ccd-backup' );
		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * @return array{client_secret:string,refresh_token:string,access_token:string,access_expires:int}
	 */
	public static function get_secrets() {
		self::maybe_migrate_legacy();

		$raw = get_option( CCD_BACKUP_SECRETS, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		return array(
			'client_secret'  => self::decrypt( $raw['client_secret'] ?? '' ),
			'refresh_token'  => self::decrypt( $raw['refresh_token'] ?? '' ),
			'access_token'   => self::decrypt( $raw['access_token'] ?? '' ),
			'access_expires' => isset( $raw['access_expires'] ) ? (int) $raw['access_expires'] : 0,
		);
	}

	/**
	 * @param array<string,mixed> $secrets Plaintext partial secrets.
	 * @param bool                $replace If true, do not merge with existing (avoids re-entrancy in migrate).
	 */
	public static function update_secrets( array $secrets, $replace = false ) {
		if ( $replace ) {
			$merged = array_merge(
				array(
					'client_secret'  => '',
					'refresh_token'  => '',
					'access_token'   => '',
					'access_expires' => 0,
				),
				$secrets
			);
		} else {
			$current = self::get_secrets();
			$merged  = array_merge( $current, $secrets );
		}
		update_option(
			CCD_BACKUP_SECRETS,
			array(
				'client_secret'  => self::encrypt( (string) $merged['client_secret'] ),
				'refresh_token'  => self::encrypt( (string) $merged['refresh_token'] ),
				'access_token'   => self::encrypt( (string) $merged['access_token'] ),
				'access_expires' => (int) $merged['access_expires'],
			),
			false
		);
	}

	public static function client_secret() {
		$s = self::get_secrets();
		if ( $s['client_secret'] !== '' ) {
			return $s['client_secret'];
		}
		$env = getenv( 'CCD_BACKUP_CLIENT_SECRET' );
		return $env ? trim( (string) $env ) : '';
	}

	public static function maybe_migrate_legacy() {
		static $running = false;
		if ( $running || get_option( 'ccd_backup_legacy_migrated' ) ) {
			return;
		}
		$running = true;

		$legacy_secrets = get_option( 'ccd_gdrive_secrets', null );
		$current        = get_option( CCD_BACKUP_SECRETS, null );

		if ( is_array( $legacy_secrets ) && ( ! is_array( $current ) || empty( $current['refresh_token'] ) ) ) {
			$plain = array(
				'client_secret'  => self::decrypt( $legacy_secrets['client_secret'] ?? '', '|ccd-gdrive' ),
				'refresh_token'  => self::decrypt( $legacy_secrets['refresh_token'] ?? '', '|ccd-gdrive' ),
				'access_token'   => self::decrypt( $legacy_secrets['access_token'] ?? '', '|ccd-gdrive' ),
				'access_expires' => isset( $legacy_secrets['access_expires'] ) ? (int) $legacy_secrets['access_expires'] : 0,
			);
			self::update_secrets( $plain, true );
		}

		$legacy_settings = get_option( 'ccd_gdrive_settings', null );
		$new_settings  = get_option( CCD_BACKUP_OPTION, null );
		if ( is_array( $legacy_settings ) && ! is_array( $new_settings ) ) {
			$mapped = array(
				'enable'                => ! empty( $legacy_settings['enable'] ),
				'keep_local'            => array_key_exists( 'keep_local', $legacy_settings ) ? ! empty( $legacy_settings['keep_local'] ) : true,
				'client_id'             => isset( $legacy_settings['client_id'] ) ? (string) $legacy_settings['client_id'] : '',
				'folder_id'             => isset( $legacy_settings['folder_id'] ) ? (string) $legacy_settings['folder_id'] : '',
				'folder_name'           => isset( $legacy_settings['folder_name'] ) ? (string) $legacy_settings['folder_name'] : CCD_BACKUP_FOLDER_NAME,
				'account_email'         => isset( $legacy_settings['account_email'] ) ? (string) $legacy_settings['account_email'] : '',
				'connected_at'          => isset( $legacy_settings['connected_at'] ) ? (int) $legacy_settings['connected_at'] : 0,
				'schedule_enable'       => ! empty( $legacy_settings['schedule_enable'] ),
				'schedule_interval'     => isset( $legacy_settings['schedule_interval'] ) ? (string) $legacy_settings['schedule_interval'] : 'daily',
				'schedule_hour'         => isset( $legacy_settings['schedule_hour'] ) ? (int) $legacy_settings['schedule_hour'] : 3,
				'retention_count'       => isset( $legacy_settings['retention_count'] ) ? (int) $legacy_settings['retention_count'] : 5,
				'sync_interval_minutes' => isset( $legacy_settings['sync_interval_minutes'] ) ? (int) $legacy_settings['sync_interval_minutes'] : 15,
				'include_uploads'       => true,
				'include_plugins'       => true,
				'include_themes'        => true,
				'include_mu_plugins'    => true,
			);
			update_option( CCD_BACKUP_OPTION, $mapped, false );
		}

		$legacy_synced = get_option( 'ccd_gdrive_synced_files', null );
		$new_synced    = get_option( CCD_BACKUP_SYNCED, null );
		if ( is_array( $legacy_synced ) && ! is_array( $new_synced ) ) {
			update_option( CCD_BACKUP_SYNCED, $legacy_synced, false );
		}

		update_option( 'ccd_backup_legacy_migrated', 1, false );
		$running = false;
	}
}
