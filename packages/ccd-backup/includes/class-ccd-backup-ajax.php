<?php
/**
 * AJAX and admin-post handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CCD_Backup_Ajax {

	public static function init() {
		add_action( 'wp_ajax_ccd_backup_progress', array( __CLASS__, 'progress' ) );
		add_action( 'wp_ajax_ccd_backup_run_now', array( __CLASS__, 'run_now' ) );
		add_action( 'wp_ajax_ccd_backup_advance', array( __CLASS__, 'advance' ) );
		add_action( 'wp_ajax_ccd_backup_tick', array( __CLASS__, 'tick' ) );
		add_action( 'wp_ajax_nopriv_ccd_backup_tick', array( __CLASS__, 'tick' ) );
		add_action( 'wp_ajax_ccd_backup_import', array( __CLASS__, 'import' ) );
		add_action( 'wp_ajax_ccd_backup_delete', array( __CLASS__, 'delete' ) );
		add_action( 'wp_ajax_ccd_backup_autosave', array( __CLASS__, 'autosave' ) );

		add_action( 'admin_post_ccd_backup_oauth', array( __CLASS__, 'oauth_callback' ) );
		add_action( 'admin_post_ccd_backup_connect', array( __CLASS__, 'connect' ) );
		add_action( 'admin_post_ccd_backup_disconnect', array( __CLASS__, 'disconnect' ) );
		add_action( 'admin_post_ccd_backup_download', array( __CLASS__, 'download' ) );
	}

	public static function can_manage() {
		return current_user_can( 'export' ) || current_user_can( 'manage_options' );
	}

	public static function progress() {
		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}
		check_ajax_referer( 'ccd_backup_progress', 'nonce' );
		wp_send_json_success( CCD_Backup_Progress::payload() );
	}

	/**
	 * Admin-driven tick: keeps export/import moving when loopback curl stalls.
	 */
	public static function advance() {
		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}
		check_ajax_referer( 'ccd_backup_advance', 'nonce' );

		$job = CCD_Backup_Progress::get_job();
		if ( ! is_array( $job ) ) {
			wp_send_json_success(
				array(
					'done'     => true,
					'continue' => false,
					'progress' => CCD_Backup_Progress::payload(),
				)
			);
		}

		$type = $job['type'] ?? '';
		if ( $type === 'export' ) {
			$result = CCD_Backup_Export::tick( null );
		} elseif ( $type === 'import' ) {
			$result = CCD_Backup_Import::tick( null );
		} else {
			$result = array( 'done' => true, 'continue' => false );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message'  => $result->get_error_message(),
					'progress' => CCD_Backup_Progress::payload(),
				)
			);
		}

		wp_send_json_success(
			array_merge(
				is_array( $result ) ? $result : array(),
				array( 'progress' => CCD_Backup_Progress::payload() )
			)
		);
	}

	public static function run_now() {
		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}
		check_ajax_referer( 'ccd_backup_run_now', 'nonce' );

		$result = CCD_Backup_Export::start( true );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'  => 'Backup iniciado. Acompanhe o progresso abaixo.',
				'when'     => wp_date( 'Y-m-d H:i:s T' ),
				'progress' => CCD_Backup_Progress::payload(),
			)
		);
	}

	public static function tick() {
		$secret = isset( $_REQUEST['tick_secret'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['tick_secret'] ) ) : '';
		if ( $secret === '' ) {
			wp_send_json_error( array( 'message' => 'Secret ausente.' ), 400 );
		}

		$job = CCD_Backup_Progress::get_job();
		if ( ! is_array( $job ) ) {
			wp_send_json_success( array( 'done' => true, 'continue' => false ) );
		}

		$type = $job['type'] ?? '';
		if ( $type === 'export' ) {
			$result = CCD_Backup_Export::tick( $secret );
		} elseif ( $type === 'import' ) {
			$result = CCD_Backup_Import::tick( $secret );
		} else {
			wp_send_json_success( array( 'done' => true, 'continue' => false ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	public static function import() {
		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}
		check_ajax_referer( 'ccd_backup_import', 'nonce' );

		if ( empty( $_FILES['backup_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'Selecione um arquivo .ccdbackup.' ) );
		}

		$name = sanitize_file_name( wp_unslash( $_FILES['backup_file']['name'] ?? '' ) );
		if ( ! str_ends_with( strtolower( $name ), '.' . CCD_BACKUP_FORMAT ) ) {
			wp_send_json_error( array( 'message' => 'Extensão inválida. Use .ccdbackup.' ) );
		}

		CCD_Backup_Paths::ensure_temp_dir();
		$dest = CCD_Backup_Paths::temp_dir() . '/upload-' . wp_generate_password( 8, false, false ) . '-' . $name;
		if ( ! move_uploaded_file( $_FILES['backup_file']['tmp_name'], $dest ) ) {
			wp_send_json_error( array( 'message' => 'Falha ao receber o upload.' ) );
		}

		$result = CCD_Backup_Import::start( $dest );
		if ( is_wp_error( $result ) ) {
			@unlink( $dest );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'  => 'Restore iniciado. Acompanhe o progresso abaixo.',
				'progress' => CCD_Backup_Progress::payload(),
			)
		);
	}

	public static function delete() {
		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}
		check_ajax_referer( 'ccd_backup_delete', 'nonce' );

		$basename = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
		$path     = CCD_Backup_Paths::resolve_backup_file( $basename );
		if ( is_wp_error( $path ) ) {
			wp_send_json_error( array( 'message' => $path->get_error_message() ) );
		}

		if ( ! @unlink( $path ) ) {
			wp_send_json_error( array( 'message' => 'Não foi possível excluir o backup.' ) );
		}
		CCD_Backup_Drive::unmark_synced( $basename );
		CCD_Backup_Progress::log( 'Backup local excluído.', array( 'file' => $basename ) );

		wp_send_json_success( array( 'message' => 'Backup excluído.' ) );
	}

	public static function autosave() {
		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}
		check_ajax_referer( 'ccd_backup_autosave', 'nonce' );

		$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';
		$opt     = get_option( CCD_BACKUP_OPTION, array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}

		$save_options  = ( $section === 'options' || $section === 'all' );
		$save_schedule = ( $section === 'schedule' || $section === 'all' );

		if ( ! $save_options && ! $save_schedule ) {
			wp_send_json_error( array( 'message' => 'Seção inválida.' ), 400 );
		}

		if ( $save_options ) {
			$prev_name = isset( $opt['folder_name'] ) ? (string) $opt['folder_name'] : '';
			$opt['enable']                = ! empty( $_POST['enable'] );
			$opt['keep_local']            = empty( $_POST['delete_after_upload'] );
			$opt['client_id']             = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : ( $opt['client_id'] ?? '' );
			$opt['folder_name']           = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : CCD_BACKUP_FOLDER_NAME;
			$opt['retention_count']       = isset( $_POST['retention_count'] ) ? max( 0, min( 100, (int) $_POST['retention_count'] ) ) : 5;
			$opt['sync_interval_minutes'] = CCD_Backup_Settings::clamp_sync_interval_minutes( $_POST['sync_interval_minutes'] ?? ( $opt['sync_interval_minutes'] ?? 15 ) );
			$opt['include_uploads']       = ! empty( $_POST['include_uploads'] );
			$opt['include_plugins']       = ! empty( $_POST['include_plugins'] );
			$opt['include_themes']        = ! empty( $_POST['include_themes'] );
			$opt['include_mu_plugins']    = ! empty( $_POST['include_mu_plugins'] );
			if ( $prev_name !== $opt['folder_name'] ) {
				$opt['folder_id']        = '';
				$opt['plugin_folder_id'] = '';
			}
			if ( isset( $_POST['client_secret'] ) ) {
				$secret_in = trim( (string) wp_unslash( $_POST['client_secret'] ) );
				if ( $secret_in !== '' && $secret_in !== '********' ) {
					CCD_Backup_Crypto::update_secrets( array( 'client_secret' => $secret_in ) );
				}
			}
		}

		if ( $save_schedule ) {
			$opt['schedule_enable']   = ! empty( $_POST['schedule_enable'] );
			$interval                 = isset( $_POST['schedule_interval'] ) ? sanitize_key( wp_unslash( $_POST['schedule_interval'] ) ) : 'daily';
			$opt['schedule_interval'] = array_key_exists( $interval, CCD_Backup_Settings::schedule_intervals() ) ? $interval : 'daily';
			if ( isset( $_POST['schedule_time'] ) && is_string( $_POST['schedule_time'] ) ) {
				$parts = explode( ':', sanitize_text_field( wp_unslash( $_POST['schedule_time'] ) ) );
				$opt['schedule_hour'] = max( 0, min( 23, (int) ( $parts[0] ?? 3 ) ) );
			} else {
				$opt['schedule_hour'] = isset( $_POST['schedule_hour'] ) ? max( 0, min( 23, (int) $_POST['schedule_hour'] ) ) : 3;
			}
		}

		update_option( CCD_BACKUP_OPTION, $opt, false );
		if ( $save_options ) {
			CCD_Backup_Cron::reschedule_sync_cron();
		}
		if ( $save_schedule ) {
			CCD_Backup_Cron::reschedule_backup_cron();
		}

		$next      = wp_next_scheduled( CCD_BACKUP_CRON_HOOK );
		$next_sync = wp_next_scheduled( CCD_BACKUP_SYNC_CRON );
		wp_send_json_success(
			array(
				'message'   => 'Configurações salvas.',
				'next_run'  => $next ? wp_date( 'd/m/Y \à\s H:i', $next ) : '',
				'next_sync' => $next_sync ? wp_date( 'Y-m-d H:i:s T', $next_sync ) : '',
			)
		);
	}

	public static function oauth_callback() {
		if ( ! self::can_manage() ) {
			wp_die( 'Sem permissão.' );
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$saved = get_transient( 'ccd_backup_oauth_state' );
		delete_transient( 'ccd_backup_oauth_state' );
		if ( ! $state || ! $saved || ! hash_equals( (string) $saved, $state ) ) {
			wp_safe_redirect( add_query_arg( 'ccd_backup_err', 'state', admin_url( 'admin.php?page=ccd-backup' ) ) );
			exit;
		}

		if ( ! empty( $_GET['error'] ) ) {
			wp_safe_redirect( add_query_arg( 'ccd_backup_err', sanitize_key( wp_unslash( $_GET['error'] ) ), admin_url( 'admin.php?page=ccd-backup' ) ) );
			exit;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( $code === '' ) {
			wp_safe_redirect( add_query_arg( 'ccd_backup_err', 'code', admin_url( 'admin.php?page=ccd-backup' ) ) );
			exit;
		}

		$result = CCD_Backup_Drive::exchange_code( $code );
		if ( is_wp_error( $result ) ) {
			CCD_Backup_Progress::log( $result->get_error_message(), $result->get_error_data() ?: array() );
			wp_safe_redirect( add_query_arg( 'ccd_backup_err', 'token', admin_url( 'admin.php?page=ccd-backup' ) ) );
			exit;
		}

		$token = CCD_Backup_Drive::access_token();
		$email = ! is_wp_error( $token ) ? CCD_Backup_Drive::fetch_account_email( $token ) : '';
		if ( ! is_wp_error( $token ) ) {
			CCD_Backup_Drive::ensure_folder( $token );
		}

		CCD_Backup_Settings::update(
			array(
				'enable'        => true,
				'account_email' => $email,
				'connected_at'  => time(),
			)
		);
		CCD_Backup_Cron::reschedule_sync_cron();
		wp_schedule_single_event( time() + 30, CCD_BACKUP_SYNC_CRON );

		CCD_Backup_Progress::log( 'Google Drive vinculado.', array( 'email' => $email ) );
		wp_safe_redirect( add_query_arg( 'ccd_backup_ok', '1', admin_url( 'admin.php?page=ccd-backup' ) ) );
		exit;
	}

	public static function connect() {
		if ( ! self::can_manage() ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'ccd_backup_connect' );

		$patch = array();
		if ( isset( $_POST['client_id'] ) ) {
			$patch['client_id'] = sanitize_text_field( wp_unslash( $_POST['client_id'] ) );
		}
		$patch['enable']     = ! isset( $_POST['enable'] ) || ! empty( $_POST['enable'] );
		$patch['keep_local'] = ! isset( $_POST['keep_local'] ) || ! empty( $_POST['keep_local'] );
		CCD_Backup_Settings::update( $patch );

		if ( isset( $_POST['client_secret'] ) ) {
			$secret_in = trim( (string) wp_unslash( $_POST['client_secret'] ) );
			if ( $secret_in !== '' && $secret_in !== '********' ) {
				CCD_Backup_Crypto::update_secrets( array( 'client_secret' => $secret_in ) );
			}
		}

		$url = CCD_Backup_Drive::oauth_authorize_url();
		if ( is_wp_error( $url ) ) {
			wp_safe_redirect( add_query_arg( 'ccd_backup_err', 'creds', admin_url( 'admin.php?page=ccd-backup' ) ) );
			exit;
		}
		wp_redirect( $url );
		exit;
	}

	public static function disconnect() {
		if ( ! self::can_manage() ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'ccd_backup_disconnect' );

		$keep_secret = CCD_Backup_Crypto::client_secret();
		delete_option( CCD_BACKUP_SECRETS );
		if ( $keep_secret !== '' ) {
			CCD_Backup_Crypto::update_secrets( array( 'client_secret' => $keep_secret ) );
		}

		CCD_Backup_Settings::update(
			array(
				'account_email' => '',
				'folder_id'     => '',
				'connected_at'  => 0,
				'enable'        => false,
			)
		);
		CCD_Backup_Cron::reschedule_sync_cron();
		CCD_Backup_Progress::log( 'Google Drive desvinculado.' );
		wp_safe_redirect( add_query_arg( 'ccd_backup_disconnected', '1', admin_url( 'admin.php?page=ccd-backup' ) ) );
		exit;
	}

	public static function download() {
		if ( ! self::can_manage() ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'ccd_backup_download' );

		$basename = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
		$path     = CCD_Backup_Paths::resolve_backup_file( $basename );
		if ( is_wp_error( $path ) ) {
			wp_die( esc_html( $path->get_error_message() ) );
		}

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path );
		exit;
	}
}
