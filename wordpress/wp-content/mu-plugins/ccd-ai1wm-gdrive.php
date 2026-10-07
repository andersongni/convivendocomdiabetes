<?php
/**
 * Plugin Name: Backup Google Drive
 * Description: Export AI1WM para Google Drive com botao "Vincular". Secrets criptografadas no banco. Menu: Backup Google Drive.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCD_GDRIVE_OPTION       = 'ccd_gdrive_settings';
const CCD_GDRIVE_SECRETS      = 'ccd_gdrive_secrets';
const CCD_GDRIVE_SYNCED       = 'ccd_gdrive_synced_files';
const CCD_GDRIVE_STATUS_LOG   = 'ccd_gdrive_status_log';
const CCD_GDRIVE_OAUTH_SCOPE  = 'https://www.googleapis.com/auth/drive.file';
const CCD_GDRIVE_FOLDER_NAME  = 'Backup Google Drive';
const CCD_GDRIVE_CRON_HOOK    = 'ccd_ai1wm_scheduled_backup';
const CCD_GDRIVE_SYNC_CRON    = 'ccd_gdrive_sync_pending';
const CCD_GDRIVE_STATUS_MAX   = 5;

/* -------------------------------------------------------------------------- */
/* Crypto                                                                     */
/* -------------------------------------------------------------------------- */

function ccd_gdrive_crypto_key() {
	return hash( 'sha256', wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . '|ccd-gdrive', true );
}

/**
 * @param string $plain
 * @return string
 */
function ccd_gdrive_encrypt( $plain ) {
	$plain = (string) $plain;
	if ( $plain === '' ) {
		return '';
	}
	$iv     = random_bytes( 16 );
	$cipher = openssl_encrypt( $plain, 'AES-256-CBC', ccd_gdrive_crypto_key(), OPENSSL_RAW_DATA, $iv );
	if ( $cipher === false ) {
		return '';
	}
	return base64_encode( $iv . $cipher );
}

/**
 * @param string $blob
 * @return string
 */
function ccd_gdrive_decrypt( $blob ) {
	$blob = (string) $blob;
	if ( $blob === '' ) {
		return '';
	}
	$raw = base64_decode( $blob, true );
	if ( $raw === false || strlen( $raw ) < 17 ) {
		return '';
	}
	$iv     = substr( $raw, 0, 16 );
	$cipher = substr( $raw, 16 );
	$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', ccd_gdrive_crypto_key(), OPENSSL_RAW_DATA, $iv );
	return is_string( $plain ) ? $plain : '';
}

/**
 * @return array{client_secret:string,refresh_token:string,access_token:string,access_expires:int}
 */
function ccd_gdrive_get_secrets() {
	$raw = get_option( CCD_GDRIVE_SECRETS, array() );
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}
	return array(
		'client_secret'  => ccd_gdrive_decrypt( $raw['client_secret'] ?? '' ),
		'refresh_token'  => ccd_gdrive_decrypt( $raw['refresh_token'] ?? '' ),
		'access_token'   => ccd_gdrive_decrypt( $raw['access_token'] ?? '' ),
		'access_expires' => isset( $raw['access_expires'] ) ? (int) $raw['access_expires'] : 0,
	);
}

/**
 * @param array $secrets Partial plaintext secrets.
 */
function ccd_gdrive_update_secrets( array $secrets ) {
	$current = ccd_gdrive_get_secrets();
	$merged  = array_merge( $current, $secrets );
	update_option(
		CCD_GDRIVE_SECRETS,
		array(
			'client_secret'  => ccd_gdrive_encrypt( $merged['client_secret'] ),
			'refresh_token'  => ccd_gdrive_encrypt( $merged['refresh_token'] ),
			'access_token'   => ccd_gdrive_encrypt( $merged['access_token'] ),
			'access_expires' => (int) $merged['access_expires'],
		),
		false
	);
}

/* -------------------------------------------------------------------------- */
/* Settings                                                                   */
/* -------------------------------------------------------------------------- */

function ccd_gdrive_settings() {
	$opt = get_option( CCD_GDRIVE_OPTION, array() );
	if ( ! is_array( $opt ) ) {
		$opt = array();
	}

	$client_id = isset( $opt['client_id'] ) ? trim( (string) $opt['client_id'] ) : '';
	if ( $client_id === '' ) {
		$env = getenv( 'CCD_GDRIVE_CLIENT_ID' );
		if ( $env ) {
			$client_id = trim( (string) $env );
		}
	}

	$interval = isset( $opt['schedule_interval'] ) ? (string) $opt['schedule_interval'] : 'daily';
	if ( ! array_key_exists( $interval, ccd_gdrive_schedule_intervals() ) ) {
		$interval = 'daily';
	}

	return array(
		'enable'             => ! empty( $opt['enable'] ),
		'keep_local'         => array_key_exists( 'keep_local', $opt ) ? ! empty( $opt['keep_local'] ) : true,
		'client_id'          => $client_id,
		'folder_id'          => isset( $opt['folder_id'] ) ? trim( (string) $opt['folder_id'] ) : '',
		'folder_name'        => isset( $opt['folder_name'] ) ? trim( (string) $opt['folder_name'] ) : CCD_GDRIVE_FOLDER_NAME,
		'account_email'      => isset( $opt['account_email'] ) ? trim( (string) $opt['account_email'] ) : '',
		'connected_at'       => isset( $opt['connected_at'] ) ? (int) $opt['connected_at'] : 0,
		'schedule_enable'    => ! empty( $opt['schedule_enable'] ),
		'schedule_interval'  => $interval,
		'schedule_hour'      => isset( $opt['schedule_hour'] ) ? max( 0, min( 23, (int) $opt['schedule_hour'] ) ) : 3,
		// 0 = sem rotacao; padrao 5.
		'retention_count'    => isset( $opt['retention_count'] ) ? max( 0, min( 100, (int) $opt['retention_count'] ) ) : 5,
	);
}

/**
 * @return array<string,string> interval => label
 */
function ccd_gdrive_schedule_intervals() {
	return array(
		'hourly'     => 'A cada hora',
		'twicedaily' => 'Duas vezes ao dia',
		'daily'      => 'Diário',
		'weekly'     => 'Semanal',
		'monthly'    => 'Mensal',
	);
}

/**
 * Ensure weekly/monthly exist even if AI1WM ainda nao registrou.
 *
 * @param array<string,array> $schedules
 * @return array<string,array>
 */
function ccd_gdrive_register_cron_schedules( $schedules ) {
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => 'Once Weekly',
		);
	}
	if ( ! isset( $schedules['monthly'] ) ) {
		$schedules['monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => 'Once Monthly',
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'ccd_gdrive_register_cron_schedules', 9999 );

/**
 * @return int Unix timestamp for first run (site timezone).
 */
function ccd_gdrive_schedule_first_run() {
	$settings = ccd_gdrive_settings();
	$hour     = (int) $settings['schedule_hour'];
	$tz       = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );

	try {
		$now  = new DateTimeImmutable( 'now', $tz );
		$next = $now->setTime( $hour, 0, 0 );
		if ( $settings['schedule_interval'] === 'hourly' ) {
			$next = $now->modify( '+1 hour' );
		} elseif ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}
		return $next->getTimestamp();
	} catch ( Exception $e ) {
		return time() + MINUTE_IN_SECONDS;
	}
}

function ccd_gdrive_reschedule_cron() {
	$hook = CCD_GDRIVE_CRON_HOOK;
	wp_clear_scheduled_hook( $hook );

	$settings = ccd_gdrive_settings();
	if ( ! $settings['schedule_enable'] ) {
		return;
	}

	$interval = $settings['schedule_interval'];
	if ( ! array_key_exists( $interval, ccd_gdrive_schedule_intervals() ) ) {
		$interval = 'daily';
	}

	wp_schedule_event( ccd_gdrive_schedule_first_run(), $interval, $hook );
	ccd_gdrive_reschedule_sync_cron();
}

/**
 * Cron horário: procura .wpress locais ainda nao enviados ao Drive.
 */
function ccd_gdrive_reschedule_sync_cron() {
	$hook = CCD_GDRIVE_SYNC_CRON;
	wp_clear_scheduled_hook( $hook );

	$settings = ccd_gdrive_settings();
	if ( ! $settings['enable'] || ! ccd_gdrive_is_connected() ) {
		return;
	}

	wp_schedule_event( time() + 2 * MINUTE_IN_SECONDS, 'hourly', $hook );
}

/**
 * Kick off AI1WM file export (async chain). Drive upload hooks fire on completion.
 *
 * @return true|WP_Error
 */
function ccd_ai1wm_run_scheduled_export() {
	if ( ! defined( 'AI1WM_SECRET_KEY' ) || ! class_exists( 'Ai1wm_Export_Controller' ) ) {
		ccd_gdrive_log( 'Agendamento cancelado: o All-in-One WP Migration não está ativo.' );
		return new WP_Error( 'ccd_ai1wm_missing', 'All-in-One WP Migration não está disponível.' );
	}

	if ( get_transient( 'ccd_ai1wm_export_lock' ) ) {
		ccd_gdrive_log( 'Backup agendado ignorado: já há um export em andamento.' );
		return new WP_Error( 'ccd_ai1wm_busy', 'Já há um export em andamento.' );
	}

	$secret = get_option( AI1WM_SECRET_KEY );
	if ( ! $secret ) {
		ccd_gdrive_log( 'Agendamento cancelado: chave secreta do All-in-One WP Migration ausente.' );
		return new WP_Error( 'ccd_ai1wm_secret', 'Chave secreta do AI1WM ausente.' );
	}

	set_transient( 'ccd_ai1wm_export_lock', 1, 2 * HOUR_IN_SECONDS );
	update_option( 'ccd_ai1wm_last_schedule', array( 'time' => time(), 'status' => 'started' ), false );
	ccd_gdrive_log( 'Agendamento: iniciando export do backup.' );

	$url  = ccd_ai1wm_fix_loopback_url( admin_url( 'admin-ajax.php' ) );
	$body = http_build_query(
		array(
			'action'     => 'ai1wm_export',
			'secret_key' => $secret,
			'priority'   => 5,
		)
	);

	if ( ! function_exists( 'curl_init' ) ) {
		delete_transient( 'ccd_ai1wm_export_lock' );
		return new WP_Error( 'ccd_ai1wm_curl', 'cURL indisponível.' );
	}

	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_HTTPHEADER     => array( 'Content-Type: application/x-www-form-urlencoded' ),
		)
	);
	$raw  = curl_exec( $ch );
	$err  = curl_error( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );

	if ( $raw === false && $err ) {
		delete_transient( 'ccd_ai1wm_export_lock' );
		ccd_gdrive_log( 'Agendamento: falha ao iniciar o export.', array( 'error' => $err ) );
		return new WP_Error( 'ccd_ai1wm_http', $err );
	}

	ccd_gdrive_log(
		'Agendamento: export iniciado.',
		array(
			'http' => $code,
		)
	);
	return true;
}

/**
 * Local Docker: WP_HOME e :8080 no host, mas Apache no container escuta :80.
 *
 * @param string $url
 * @return string
 */
function ccd_ai1wm_fix_loopback_url( $url ) {
	return (string) preg_replace( '#^https?://localhost:8080#i', 'http://127.0.0.1', $url );
}
add_filter( 'ai1wm_http_export_url', 'ccd_ai1wm_fix_loopback_url' );
add_filter( 'ai1wm_http_import_url', 'ccd_ai1wm_fix_loopback_url' );

add_action( CCD_GDRIVE_CRON_HOOK, 'ccd_ai1wm_run_scheduled_export' );
add_action( CCD_GDRIVE_SYNC_CRON, 'ccd_gdrive_sync_pending_backups' );

add_action(
	'init',
	static function () {
		// Garante cron de sync se Drive estiver ativo e o evento sumiu.
		$settings = ccd_gdrive_settings();
		if ( $settings['enable'] && ccd_gdrive_is_connected() && ! wp_next_scheduled( CCD_GDRIVE_SYNC_CRON ) ) {
			ccd_gdrive_reschedule_sync_cron();
		}
	},
	30
);

add_action(
	'ai1wm_status_export_done',
	static function () {
		delete_transient( 'ccd_ai1wm_export_lock' );
		update_option( 'ccd_ai1wm_last_schedule', array( 'time' => time(), 'status' => 'done' ), false );
	},
	5
);

add_action(
	'ai1wm_status_export_error',
	static function () {
		delete_transient( 'ccd_ai1wm_export_lock' );
		update_option( 'ccd_ai1wm_last_schedule', array( 'time' => time(), 'status' => 'error' ), false );
	},
	5
);

function ccd_gdrive_is_connected() {
	$s = ccd_gdrive_get_secrets();
	return $s['refresh_token'] !== '';
}

/**
 * @return array<int,array{time:int,message:string,context:array}>
 */
function ccd_gdrive_get_status_log() {
	$log = get_option( CCD_GDRIVE_STATUS_LOG, null );
	if ( ! is_array( $log ) ) {
		$log = array();
		$old = get_option( 'ccd_gdrive_last_status' );
		if ( is_array( $old ) && ! empty( $old['message'] ) ) {
			$log[] = array(
				'time'    => isset( $old['time'] ) ? (int) $old['time'] : time(),
				'message' => (string) $old['message'],
				'context' => isset( $old['context'] ) && is_array( $old['context'] ) ? $old['context'] : array(),
			);
			update_option( CCD_GDRIVE_STATUS_LOG, $log, false );
		}
	}
	return array_values( $log );
}

function ccd_gdrive_log( $message, $context = array() ) {
	$line = '[ccd-ai1wm-gdrive] ' . $message;
	if ( $context ) {
		$line .= ' ' . wp_json_encode( $context );
	}
	error_log( $line );

	$entry = array(
		'time'    => time(),
		'message' => (string) $message,
		'context' => is_array( $context ) ? $context : array(),
	);

	// Compat: ultimo status isolado.
	update_option( 'ccd_gdrive_last_status', $entry, false );

	$log = ccd_gdrive_get_status_log();
	array_unshift( $log, $entry );
	$log = array_slice( $log, 0, CCD_GDRIVE_STATUS_MAX );
	update_option( CCD_GDRIVE_STATUS_LOG, $log, false );
}

function ccd_gdrive_redirect_uri() {
	return admin_url( 'admin-post.php?action=ccd_gdrive_oauth' );
}

function ccd_gdrive_client_secret() {
	$s = ccd_gdrive_get_secrets();
	if ( $s['client_secret'] !== '' ) {
		return $s['client_secret'];
	}
	$env = getenv( 'CCD_GDRIVE_CLIENT_SECRET' );
	return $env ? trim( (string) $env ) : '';
}

/**
 * Verifica se a Google Drive API responde (cache curto).
 *
 * @param bool $force Ignora cache.
 * @return array{ok:bool,disabled:bool,checked:bool,message:string}
 */
function ccd_gdrive_probe_drive_api( $force = false ) {
	$cache_key = 'ccd_gdrive_api_probe';
	if ( ! $force ) {
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['checked'] ) ) {
			return $cached;
		}
	}

	$result = array(
		'ok'       => false,
		'disabled' => false,
		'checked'  => false,
		'message'  => '',
	);

	if ( ! ccd_gdrive_is_connected() ) {
		// Sem token nao da para afirmar; nao mostra o aviso de API desativada.
		$result['checked'] = false;
		return $result;
	}

	$token = ccd_gdrive_access_token();
	if ( is_wp_error( $token ) ) {
		$result['checked'] = true;
		$result['message'] = $token->get_error_message();
		set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
		return $result;
	}

	$about = ccd_gdrive_http_json(
		'https://www.googleapis.com/drive/v3/about?fields=user',
		array(
			'headers' => array( 'Authorization: Bearer ' . $token ),
			'timeout' => 20,
		)
	);

	$result['checked'] = true;
	if ( ! is_wp_error( $about ) ) {
		$result['ok']      = true;
		$result['message'] = 'Google Drive API ativa.';
		set_transient( $cache_key, $result, 30 * MINUTE_IN_SECONDS );
		return $result;
	}

	$data = $about->get_error_data();
	$msg  = '';
	if ( is_array( $data ) && isset( $data['body']['error']['message'] ) ) {
		$msg = (string) $data['body']['error']['message'];
	} else {
		$msg = $about->get_error_message();
	}
	$result['message'] = $msg;

	$blob = strtolower( $msg . ' ' . wp_json_encode( $data ) );
	if (
		strpos( $blob, 'has not been used' ) !== false
		|| strpos( $blob, 'is disabled' ) !== false
		|| strpos( $blob, 'accessnotconfigured' ) !== false
		|| strpos( $blob, 'service_disabled' ) !== false
	) {
		$result['disabled'] = true;
	}

	set_transient( $cache_key, $result, 10 * MINUTE_IN_SECONDS );
	return $result;
}

function ccd_gdrive_clear_api_probe_cache() {
	delete_transient( 'ccd_gdrive_api_probe' );
}

/* -------------------------------------------------------------------------- */
/* HTTP (curl — contorna WP_HTTP_BLOCK_EXTERNAL)                              */
/* -------------------------------------------------------------------------- */

/**
 * @param string              $url
 * @param array<string,mixed> $args
 * @return array|WP_Error {code,body,headers}
 */
function ccd_gdrive_http( $url, array $args = array() ) {
	if ( ! function_exists( 'curl_init' ) ) {
		return new WP_Error( 'ccd_gdrive_curl', 'ext-curl indisponível.' );
	}

	$method  = strtoupper( $args['method'] ?? 'GET' );
	$headers = $args['headers'] ?? array();
	$body    = $args['body'] ?? null;
	$timeout = isset( $args['timeout'] ) ? (int) $args['timeout'] : 60;

	$header_lines = array();
	foreach ( $headers as $k => $v ) {
		$header_lines[] = is_int( $k ) ? (string) $v : ( $k . ': ' . $v );
	}

	$ch = curl_init( $url );
	$opts = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER         => true,
		CURLOPT_TIMEOUT        => $timeout,
		CURLOPT_HTTPHEADER     => $header_lines,
		CURLOPT_CUSTOMREQUEST  => $method,
	);
	if ( $body !== null && $method !== 'GET' ) {
		$opts[ CURLOPT_POSTFIELDS ] = $body;
	}
	curl_setopt_array( $ch, $opts );
	$raw = curl_exec( $ch );
	$err = curl_error( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	curl_close( $ch );

	if ( $raw === false ) {
		return new WP_Error( 'ccd_gdrive_http', $err ?: 'Falha HTTP' );
	}

	return array(
		'code'    => $code,
		'headers' => substr( $raw, 0, $header_size ),
		'body'    => substr( $raw, $header_size ),
	);
}

/**
 * @param string              $url
 * @param array<string,mixed> $args
 * @return array|WP_Error
 */
function ccd_gdrive_http_json( $url, array $args = array() ) {
	$res = ccd_gdrive_http( $url, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$data = json_decode( $res['body'], true );
	if ( $res['code'] < 200 || $res['code'] >= 300 ) {
		return new WP_Error( 'ccd_gdrive_http', 'HTTP ' . $res['code'], array( 'body' => $data ?: $res['body'] ) );
	}
	return is_array( $data ) ? $data : array();
}

/* -------------------------------------------------------------------------- */
/* OAuth                                                                      */
/* -------------------------------------------------------------------------- */

function ccd_gdrive_oauth_authorize_url() {
	$settings = ccd_gdrive_settings();
	$secret   = ccd_gdrive_client_secret();
	if ( $settings['client_id'] === '' || $secret === '' ) {
		return new WP_Error( 'ccd_gdrive_creds', 'Informe Client ID e Client Secret antes de vincular.' );
	}

	$state = wp_create_nonce( 'ccd_gdrive_oauth' );
	set_transient( 'ccd_gdrive_oauth_state', $state, 15 * MINUTE_IN_SECONDS );

	return add_query_arg(
		array(
			'client_id'     => $settings['client_id'],
			'redirect_uri'  => ccd_gdrive_redirect_uri(),
			'response_type' => 'code',
			'scope'         => CCD_GDRIVE_OAUTH_SCOPE,
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		),
		'https://accounts.google.com/o/oauth2/v2/auth'
	);
}

/**
 * @param string $code
 * @return true|WP_Error
 */
function ccd_gdrive_exchange_code( $code ) {
	$settings = ccd_gdrive_settings();
	$secret   = ccd_gdrive_client_secret();
	$data     = ccd_gdrive_http_json(
		'https://oauth2.googleapis.com/token',
		array(
			'method'  => 'POST',
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => http_build_query(
				array(
					'code'          => $code,
					'client_id'     => $settings['client_id'],
					'client_secret' => $secret,
					'redirect_uri'  => ccd_gdrive_redirect_uri(),
					'grant_type'    => 'authorization_code',
				)
			),
		)
	);
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	if ( empty( $data['refresh_token'] ) && empty( $data['access_token'] ) ) {
		return new WP_Error( 'ccd_gdrive_token', 'Google não devolveu tokens.', array( 'body' => $data ) );
	}

	$patch = array(
		'access_token'   => (string) ( $data['access_token'] ?? '' ),
		'access_expires' => time() + max( 60, (int) ( $data['expires_in'] ?? 3600 ) - 60 ),
	);
	if ( ! empty( $data['refresh_token'] ) ) {
		$patch['refresh_token'] = (string) $data['refresh_token'];
	}
	ccd_gdrive_update_secrets( $patch );
	return true;
}

/**
 * @return string|WP_Error
 */
function ccd_gdrive_access_token() {
	$s = ccd_gdrive_get_secrets();
	if ( $s['access_token'] !== '' && $s['access_expires'] > time() ) {
		return $s['access_token'];
	}
	if ( $s['refresh_token'] === '' ) {
		return new WP_Error( 'ccd_gdrive_auth', 'Google Drive não vinculado.' );
	}

	$settings = ccd_gdrive_settings();
	$secret   = ccd_gdrive_client_secret();
	$data     = ccd_gdrive_http_json(
		'https://oauth2.googleapis.com/token',
		array(
			'method'  => 'POST',
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => http_build_query(
				array(
					'client_id'     => $settings['client_id'],
					'client_secret' => $secret,
					'refresh_token' => $s['refresh_token'],
					'grant_type'    => 'refresh_token',
				)
			),
		)
	);
	if ( is_wp_error( $data ) || empty( $data['access_token'] ) ) {
		return new WP_Error( 'ccd_gdrive_refresh', 'Falha ao renovar access token.', array( 'body' => $data ) );
	}

	ccd_gdrive_update_secrets(
		array(
			'access_token'   => (string) $data['access_token'],
			'access_expires' => time() + max( 60, (int) ( $data['expires_in'] ?? 3600 ) - 60 ),
		)
	);
	return (string) $data['access_token'];
}

/**
 * @param string $token
 * @return string
 */
function ccd_gdrive_fetch_account_email( $token ) {
	$data = ccd_gdrive_http_json(
		'https://www.googleapis.com/drive/v3/about?fields=user',
		array(
			'headers' => array( 'Authorization: Bearer ' . $token ),
		)
	);
	if ( is_wp_error( $data ) ) {
		return '';
	}
	return isset( $data['user']['emailAddress'] ) ? (string) $data['user']['emailAddress'] : '';
}

/**
 * @param string $token
 * @return string|WP_Error folder id
 */
function ccd_gdrive_ensure_folder( $token ) {
	$settings = ccd_gdrive_settings();
	if ( $settings['folder_id'] !== '' ) {
		return $settings['folder_id'];
	}

	$name = $settings['folder_name'] !== '' ? $settings['folder_name'] : CCD_GDRIVE_FOLDER_NAME;
	$q    = "mimeType='application/vnd.google-apps.folder' and name='" . str_replace( "'", "\\'", $name ) . "' and trashed=false";

	$list = ccd_gdrive_http_json(
		'https://www.googleapis.com/drive/v3/files?' . http_build_query(
			array(
				'q'        => $q,
				'spaces'   => 'drive',
				'fields'   => 'files(id,name)',
				'pageSize' => 1,
			)
		),
		array( 'headers' => array( 'Authorization: Bearer ' . $token ) )
	);
	if ( ! is_wp_error( $list ) && ! empty( $list['files'][0]['id'] ) ) {
		$folder_id = (string) $list['files'][0]['id'];
	} else {
		$created = ccd_gdrive_http_json(
			'https://www.googleapis.com/drive/v3/files',
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization: Bearer ' . $token,
					'Content-Type: application/json; charset=UTF-8',
				),
				'body'    => wp_json_encode(
					array(
						'name'     => $name,
						'mimeType' => 'application/vnd.google-apps.folder',
					)
				),
			)
		);
		if ( ! is_wp_error( $created ) && ! empty( $created['id'] ) ) {
			$folder_id = (string) $created['id'];
		} else {
			$detail = 'Não foi possível criar a pasta no Drive.';
			if ( is_wp_error( $created ) ) {
				$data = $created->get_error_data();
				$api  = is_array( $data ) ? ( $data['body']['error']['message'] ?? '' ) : '';
				if ( is_string( $api ) && $api !== '' ) {
					$detail .= ' ' . $api;
				} else {
					$detail .= ' ' . $created->get_error_message();
				}
			}
			return new WP_Error( 'ccd_gdrive_folder', $detail, is_wp_error( $created ) ? $created->get_error_data() : array() );
		}
	}

	$opt = get_option( CCD_GDRIVE_OPTION, array() );
	if ( ! is_array( $opt ) ) {
		$opt = array();
	}
	$opt['folder_id'] = $folder_id;
	update_option( CCD_GDRIVE_OPTION, $opt, false );
	return $folder_id;
}

/* -------------------------------------------------------------------------- */
/* Upload                                                                     */
/* -------------------------------------------------------------------------- */

function ccd_gdrive_resolve_backup_file( $params ) {
	if ( ! function_exists( 'ai1wm_backup_path' ) ) {
		return new WP_Error( 'ccd_gdrive_no_ai1wm', 'All-in-One WP Migration não está carregado.' );
	}
	try {
		$path = ai1wm_backup_path( $params );
	} catch ( Exception $e ) {
		return new WP_Error( 'ccd_gdrive_path', $e->getMessage() );
	}
	if ( ! is_string( $path ) || ! is_readable( $path ) ) {
		return new WP_Error( 'ccd_gdrive_missing', 'Arquivo de backup não encontrado.', array( 'path' => $path ) );
	}
	return $path;
}

/**
 * @param string $token
 * @param string $file
 * @param string $folder_id
 * @return array|WP_Error
 */
function ccd_gdrive_upload_file( $token, $file, $folder_id ) {
	$name = basename( $file );
	$size = filesize( $file );
	if ( $size === false ) {
		return new WP_Error( 'ccd_gdrive_size', 'Não foi possível ler o tamanho do arquivo.' );
	}

	$start = ccd_gdrive_http(
		'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable',
		array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization: Bearer ' . $token,
				'Content-Type: application/json; charset=UTF-8',
				'X-Upload-Content-Type: application/octet-stream',
				'X-Upload-Content-Length: ' . $size,
			),
			'body'    => wp_json_encode(
				array(
					'name'    => $name,
					'parents' => array( $folder_id ),
				)
			),
			'timeout' => 120,
		)
	);
	if ( is_wp_error( $start ) ) {
		return $start;
	}
	if ( $start['code'] < 200 || $start['code'] >= 300 ) {
		return new WP_Error( 'ccd_gdrive_session', 'HTTP ' . $start['code'] . ' ao iniciar upload.', array( 'body' => $start['body'] ) );
	}
	if ( ! preg_match( '/^location:\s*(.+)$/im', $start['headers'], $m ) ) {
		return new WP_Error( 'ccd_gdrive_location', 'Location do upload resumable ausente.' );
	}
	$session = trim( $m[1] );

	$fh = fopen( $file, 'rb' );
	if ( ! $fh ) {
		return new WP_Error( 'ccd_gdrive_open', 'Não foi possível abrir o backup para leitura.' );
	}

	$ch = curl_init( $session );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_PUT            => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_INFILE         => $fh,
			CURLOPT_INFILESIZE     => $size,
			CURLOPT_HTTPHEADER     => array(
				'Authorization: Bearer ' . $token,
				'Content-Type: application/octet-stream',
				'Content-Length: ' . $size,
			),
		)
	);
	$body = curl_exec( $ch );
	$err  = curl_error( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
	fclose( $fh );

	if ( $body === false ) {
		return new WP_Error( 'ccd_gdrive_put', $err ?: 'Upload PUT falhou.' );
	}
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'ccd_gdrive_put', 'HTTP ' . $code . ' no PUT.', array( 'body' => $body ) );
	}
	$data = json_decode( $body, true );
	return is_array( $data ) ? $data : array( 'raw' => $body );
}

/**
 * Mantém apenas os N backups .wpress mais recentes na pasta do Drive.
 *
 * @param string $token
 * @param string $folder_id
 * @param int    $keep
 * @return array{kept:int,deleted:int,errors:array}|WP_Error
 */
function ccd_gdrive_rotate_backups( $token, $folder_id, $keep ) {
	$keep = (int) $keep;
	if ( $keep < 1 ) {
		return array(
			'kept'    => 0,
			'deleted' => 0,
			'errors'  => array(),
		);
	}

	$q = sprintf(
		"'%s' in parents and trashed=false and mimeType!='application/vnd.google-apps.folder'",
		str_replace( "'", "\\'", $folder_id )
	);

	$files = array();
	$page  = null;
	do {
		$query = array(
			'q'        => $q,
			'spaces'   => 'drive',
			'fields'   => 'nextPageToken,files(id,name,createdTime,modifiedTime)',
			'orderBy'  => 'createdTime desc',
			'pageSize' => 100,
		);
		if ( $page ) {
			$query['pageToken'] = $page;
		}
		$list = ccd_gdrive_http_json(
			'https://www.googleapis.com/drive/v3/files?' . http_build_query( $query ),
			array( 'headers' => array( 'Authorization: Bearer ' . $token ) )
		);
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		foreach ( ( $list['files'] ?? array() ) as $f ) {
			$name = (string) ( $f['name'] ?? '' );
			// Prefer backups AI1WM; ainda assim rotaciona outros arquivos da pasta do app.
			if ( $name === '' ) {
				continue;
			}
			$files[] = $f;
		}
		$page = ! empty( $list['nextPageToken'] ) ? (string) $list['nextPageToken'] : null;
	} while ( $page );

	// Preferir .wpress; se nao houver, usa todos os arquivos listados.
	$wpress = array_values(
		array_filter(
			$files,
			static function ( $f ) {
				return (bool) preg_match( '/\.wpress$/i', (string) ( $f['name'] ?? '' ) );
			}
		)
	);
	$pool = $wpress ? $wpress : $files;

	usort(
		$pool,
		static function ( $a, $b ) {
			$ta = strtotime( (string) ( $a['createdTime'] ?? $a['modifiedTime'] ?? '' ) ) ?: 0;
			$tb = strtotime( (string) ( $b['createdTime'] ?? $b['modifiedTime'] ?? '' ) ) ?: 0;
			return $tb <=> $ta;
		}
	);

	$deleted = 0;
	$errors  = array();
	$extra   = array_slice( $pool, $keep );
	foreach ( $extra as $f ) {
		$id = (string) ( $f['id'] ?? '' );
		if ( $id === '' ) {
			continue;
		}
		$del = ccd_gdrive_http(
			'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $id ),
			array(
				'method'  => 'DELETE',
				'headers' => array( 'Authorization: Bearer ' . $token ),
				'timeout' => 60,
			)
		);
		if ( is_wp_error( $del ) || (int) ( $del['code'] ?? 0 ) >= 300 ) {
			$errors[] = array(
				'id'   => $id,
				'name' => $f['name'] ?? '',
				'err'  => is_wp_error( $del ) ? $del->get_error_message() : ( 'HTTP ' . ( $del['code'] ?? '?' ) ),
			);
			continue;
		}
		$deleted++;
	}

	return array(
		'kept'    => min( $keep, count( $pool ) ),
		'deleted' => $deleted,
		'errors'  => $errors,
	);
}

/**
 * @return array<string,array{id:string,size:int,mtime:int,synced_at:int}>
 */
function ccd_gdrive_get_synced_map() {
	$map = get_option( CCD_GDRIVE_SYNCED, array() );
	return is_array( $map ) ? $map : array();
}

/**
 * @param string $basename
 * @param string $drive_id
 * @param int    $size
 * @param int    $mtime
 */
function ccd_gdrive_mark_synced( $basename, $drive_id, $size, $mtime ) {
	$map              = ccd_gdrive_get_synced_map();
	$map[ $basename ] = array(
		'id'        => (string) $drive_id,
		'size'      => (int) $size,
		'mtime'     => (int) $mtime,
		'synced_at' => time(),
	);
	update_option( CCD_GDRIVE_SYNCED, $map, false );
}

/**
 * @param string $basename
 */
function ccd_gdrive_unmark_synced( $basename ) {
	$map = ccd_gdrive_get_synced_map();
	if ( isset( $map[ $basename ] ) ) {
		unset( $map[ $basename ] );
		update_option( CCD_GDRIVE_SYNCED, $map, false );
	}
}

/**
 * @return string[] Absolute paths to .wpress in AI1WM backups dir.
 */
function ccd_gdrive_list_local_backups() {
	if ( ! defined( 'AI1WM_BACKUPS_PATH' ) || ! is_dir( AI1WM_BACKUPS_PATH ) ) {
		return array();
	}
	$files = glob( trailingslashit( AI1WM_BACKUPS_PATH ) . '*.wpress' );
	if ( ! is_array( $files ) ) {
		return array();
	}
	$out = array();
	foreach ( $files as $path ) {
		if ( is_string( $path ) && is_readable( $path ) && is_file( $path ) ) {
			$out[] = $path;
		}
	}
	return $out;
}

/**
 * @param string $token
 * @param string $folder_id
 * @param string $name
 * @param int    $size
 * @return string|false Drive file id if found.
 */
function ccd_gdrive_find_remote_backup( $token, $folder_id, $name, $size ) {
	$q = sprintf(
		"'%s' in parents and trashed=false and name='%s'",
		str_replace( "'", "\\'", $folder_id ),
		str_replace( "'", "\\'", $name )
	);
	$list = ccd_gdrive_http_json(
		'https://www.googleapis.com/drive/v3/files?' . http_build_query(
			array(
				'q'        => $q,
				'spaces'   => 'drive',
				'fields'   => 'files(id,name,size)',
				'pageSize' => 10,
			)
		),
		array( 'headers' => array( 'Authorization: Bearer ' . $token ) )
	);
	if ( is_wp_error( $list ) || empty( $list['files'] ) ) {
		return false;
	}
	foreach ( $list['files'] as $f ) {
		if ( (string) ( $f['name'] ?? '' ) !== $name ) {
			continue;
		}
		if ( $size > 0 && isset( $f['size'] ) && (int) $f['size'] !== (int) $size ) {
			continue;
		}
		return (string) $f['id'];
	}
	return false;
}

/**
 * @param string $path
 * @return bool
 */
function ccd_gdrive_local_is_synced( $path ) {
	$base = basename( $path );
	$size = (int) filesize( $path );
	$map  = ccd_gdrive_get_synced_map();
	if ( isset( $map[ $base ] ) && (int) ( $map[ $base ]['size'] ?? 0 ) === $size ) {
		return true;
	}
	return false;
}

/**
 * Upload local .wpress, rotate, optionally delete local, mark synced.
 *
 * @param string $file Absolute path.
 * @return true|WP_Error
 */
function ccd_gdrive_upload_and_finalize( $file ) {
	$settings = ccd_gdrive_settings();
	if ( ! is_string( $file ) || ! is_readable( $file ) ) {
		return new WP_Error( 'ccd_gdrive_missing', 'Arquivo de backup não encontrado.' );
	}

	$token = ccd_gdrive_access_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$folder = ccd_gdrive_ensure_folder( $token );
	if ( is_wp_error( $folder ) ) {
		return $folder;
	}

	$base  = basename( $file );
	$size  = (int) filesize( $file );
	$mtime = (int) filemtime( $file );

	// Ja existe no Drive com mesmo nome/tamanho → so marca e aplica politica local.
	$remote_id = ccd_gdrive_find_remote_backup( $token, $folder, $base, $size );
	if ( $remote_id ) {
		ccd_gdrive_mark_synced( $base, $remote_id, $size, $mtime );
		ccd_gdrive_log( 'Backup já estava no Drive; marcado como sincronizado.', array( 'file' => $base, 'id' => $remote_id ) );
		if ( ! $settings['keep_local'] && is_writable( $file ) ) {
			@unlink( $file );
			ccd_gdrive_unmark_synced( $base );
			ccd_gdrive_log( 'Cópia local removida (já estava no Drive).', array( 'file' => $base ) );
		}
		return true;
	}

	ccd_gdrive_log( 'Iniciando upload para o Google Drive.', array( 'file' => $base, 'bytes' => $size ) );

	$result = ccd_gdrive_upload_file( $token, $file, $folder );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$drive_id = (string) ( $result['id'] ?? '' );
	ccd_gdrive_mark_synced( $base, $drive_id, $size, $mtime );
	ccd_gdrive_log(
		'Upload para o Google Drive concluído.',
		array(
			'file'   => $base,
			'id'     => $drive_id ?: null,
			'name'   => $result['name'] ?? null,
			'folder' => $folder,
		)
	);

	if ( (int) $settings['retention_count'] > 0 ) {
		$rotation = ccd_gdrive_rotate_backups( $token, $folder, (int) $settings['retention_count'] );
		if ( is_wp_error( $rotation ) ) {
			ccd_gdrive_log( 'Rotação no Drive falhou: ' . $rotation->get_error_message(), $rotation->get_error_data() ?: array() );
		} else {
			ccd_gdrive_log(
				'Rotação no Drive concluída.',
				array(
					'keep'    => (int) $settings['retention_count'],
					'kept'    => $rotation['kept'],
					'deleted' => $rotation['deleted'],
					'errors'  => $rotation['errors'],
				)
			);
		}
	}

	if ( ! $settings['keep_local'] && is_writable( $file ) ) {
		@unlink( $file );
		ccd_gdrive_unmark_synced( $base );
		ccd_gdrive_log( 'Cópia local removida após o upload.', array( 'file' => $base ) );
	}

	return true;
}

/**
 * Cron: envia o backup local pendente mais antigo (1 por execucao).
 */
function ccd_gdrive_sync_pending_backups() {
	$settings = ccd_gdrive_settings();
	if ( ! $settings['enable'] ) {
		return;
	}
	if ( ! ccd_gdrive_is_connected() ) {
		return;
	}
	if ( get_transient( 'ccd_ai1wm_export_lock' ) || get_transient( 'ccd_gdrive_sync_lock' ) ) {
		ccd_gdrive_log( 'Sincronização adiada: outro processo em andamento.' );
		return;
	}

	$pending = array();
	foreach ( ccd_gdrive_list_local_backups() as $path ) {
		if ( ! ccd_gdrive_local_is_synced( $path ) ) {
			$pending[] = $path;
		}
	}
	if ( ! $pending ) {
		return;
	}

	usort(
		$pending,
		static function ( $a, $b ) {
			return ( filemtime( $a ) ?: 0 ) <=> ( filemtime( $b ) ?: 0 );
		}
	);

	$file = $pending[0];
	set_transient( 'ccd_gdrive_sync_lock', 1, 3 * HOUR_IN_SECONDS );
	ccd_gdrive_log(
		'Sincronização: backup local pendente encontrado.',
		array(
			'file'     => basename( $file ),
			'bytes'    => filesize( $file ),
			'pending'  => count( $pending ),
		)
	);

	$result = ccd_gdrive_upload_and_finalize( $file );
	delete_transient( 'ccd_gdrive_sync_lock' );

	if ( is_wp_error( $result ) ) {
		ccd_gdrive_log( 'Sincronização falhou: ' . $result->get_error_message(), $result->get_error_data() ?: array() );
		return;
	}

	// Se ainda houver pendentes, agenda nova passagem em breve.
	$still = 0;
	foreach ( ccd_gdrive_list_local_backups() as $path ) {
		if ( ! ccd_gdrive_local_is_synced( $path ) ) {
			$still++;
		}
	}
	if ( $still > 0 ) {
		wp_schedule_single_event( time() + 60, CCD_GDRIVE_SYNC_CRON );
	}
}

function ccd_gdrive_on_export_done( $params ) {
	$settings = ccd_gdrive_settings();
	if ( ! $settings['enable'] ) {
		return;
	}
	if ( ! ccd_gdrive_is_connected() ) {
		ccd_gdrive_log( 'Export concluído, mas o Google Drive não está vinculado.' );
		return;
	}

	$file = ccd_gdrive_resolve_backup_file( $params );
	if ( is_wp_error( $file ) ) {
		ccd_gdrive_log( $file->get_error_message(), $file->get_error_data() ?: array() );
		return;
	}

	static $done = array();
	$key         = md5( $file . '|' . (string) filesize( $file ) );
	if ( isset( $done[ $key ] ) ) {
		return;
	}
	$done[ $key ] = true;

	$result = ccd_gdrive_upload_and_finalize( $file );
	if ( is_wp_error( $result ) ) {
		ccd_gdrive_log( $result->get_error_message(), $result->get_error_data() ?: array() );
	}
}

add_action( 'ai1wm_status_export_done', 'ccd_gdrive_on_export_done', 20 );
add_action( 'ai1wm_status_backup_created', 'ccd_gdrive_on_export_done', 20 );

/* -------------------------------------------------------------------------- */
/* Admin                                                                      */
/* -------------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		$parent    = 'ai1wm';
		$has_ai1wm = false;
		global $menu;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && $item[2] === 'ai1wm' ) {
					$has_ai1wm = true;
					break;
				}
			}
		}

		$title = 'Backup Google Drive';
		$cap   = 'export';
		$slug  = 'ccd-gdrive';
		$cb    = 'ccd_gdrive_render_settings_page';

		if ( $has_ai1wm ) {
			add_submenu_page( $parent, 'Backup Google Drive', $title, $cap, $slug, $cb );
		} else {
			add_options_page( 'Backup Google Drive', 'Backup Google Drive', 'manage_options', $slug, $cb );
		}
	},
	60
);

add_action(
	'admin_post_ccd_gdrive_oauth',
	static function () {
		if ( ! current_user_can( 'export' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissao.' );
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$saved = get_transient( 'ccd_gdrive_oauth_state' );
		delete_transient( 'ccd_gdrive_oauth_state' );
		if ( ! $state || ! $saved || ! hash_equals( (string) $saved, $state ) ) {
			wp_safe_redirect( add_query_arg( 'ccd_gdrive_err', 'state', admin_url( 'admin.php?page=ccd-gdrive' ) ) );
			exit;
		}

		if ( ! empty( $_GET['error'] ) ) {
			wp_safe_redirect( add_query_arg( 'ccd_gdrive_err', sanitize_key( wp_unslash( $_GET['error'] ) ), admin_url( 'admin.php?page=ccd-gdrive' ) ) );
			exit;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( $code === '' ) {
			wp_safe_redirect( add_query_arg( 'ccd_gdrive_err', 'code', admin_url( 'admin.php?page=ccd-gdrive' ) ) );
			exit;
		}

		$result = ccd_gdrive_exchange_code( $code );
		if ( is_wp_error( $result ) ) {
			ccd_gdrive_log( $result->get_error_message(), $result->get_error_data() ?: array() );
			wp_safe_redirect( add_query_arg( 'ccd_gdrive_err', 'token', admin_url( 'admin.php?page=ccd-gdrive' ) ) );
			exit;
		}

		$token = ccd_gdrive_access_token();
		$email = ! is_wp_error( $token ) ? ccd_gdrive_fetch_account_email( $token ) : '';
		if ( ! is_wp_error( $token ) ) {
			ccd_gdrive_ensure_folder( $token );
		}

		$opt = get_option( CCD_GDRIVE_OPTION, array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}
		$opt['enable']        = true;
		$opt['account_email'] = $email;
		$opt['connected_at']  = time();
		update_option( CCD_GDRIVE_OPTION, $opt, false );
		ccd_gdrive_reschedule_sync_cron();
		ccd_gdrive_clear_api_probe_cache();
		// Tenta enviar backups locais que ficaram pendentes.
		wp_schedule_single_event( time() + 30, CCD_GDRIVE_SYNC_CRON );

		ccd_gdrive_log( 'Google Drive vinculado.', array( 'email' => $email ) );
		wp_safe_redirect( add_query_arg( 'ccd_gdrive_ok', '1', admin_url( 'admin.php?page=ccd-gdrive' ) ) );
		exit;
	}
);

add_action(
	'admin_post_ccd_gdrive_save',
	static function () {
		if ( ! current_user_can( 'export' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissao.' );
		}
		check_admin_referer( 'ccd_gdrive_save' );

		$opt = get_option( CCD_GDRIVE_OPTION, array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}

		$opt['enable']          = ! empty( $_POST['enable'] );
		// Checkbox "Excluir apos copiar" → keep_local invertido.
		$opt['keep_local']      = empty( $_POST['delete_after_upload'] );
		$opt['client_id']       = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		$opt['folder_name']     = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : CCD_GDRIVE_FOLDER_NAME;
		$opt['retention_count'] = isset( $_POST['retention_count'] ) ? max( 0, min( 100, (int) $_POST['retention_count'] ) ) : 5;

		if ( isset( $_POST['client_secret'] ) ) {
			$secret_in = trim( (string) wp_unslash( $_POST['client_secret'] ) );
			if ( $secret_in !== '' && $secret_in !== '********' ) {
				ccd_gdrive_update_secrets( array( 'client_secret' => $secret_in ) );
			}
		}

		update_option( CCD_GDRIVE_OPTION, $opt, false );
		ccd_gdrive_reschedule_sync_cron();
		wp_safe_redirect( add_query_arg( 'ccd_gdrive_saved', '1', admin_url( 'admin.php?page=ccd-gdrive' ) ) );
		exit;
	}
);

add_action(
	'admin_post_ccd_gdrive_connect',
	static function () {
		if ( ! current_user_can( 'export' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissao.' );
		}
		check_admin_referer( 'ccd_gdrive_connect' );

		$opt = get_option( CCD_GDRIVE_OPTION, array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}
		if ( isset( $_POST['client_id'] ) ) {
			$opt['client_id'] = sanitize_text_field( wp_unslash( $_POST['client_id'] ) );
		}
		$opt['enable']     = ! isset( $_POST['enable'] ) || ! empty( $_POST['enable'] );
		$opt['keep_local'] = ! isset( $_POST['keep_local'] ) || ! empty( $_POST['keep_local'] );
		update_option( CCD_GDRIVE_OPTION, $opt, false );

		if ( isset( $_POST['client_secret'] ) ) {
			$secret_in = trim( (string) wp_unslash( $_POST['client_secret'] ) );
			if ( $secret_in !== '' && $secret_in !== '********' ) {
				ccd_gdrive_update_secrets( array( 'client_secret' => $secret_in ) );
			}
		}

		$url = ccd_gdrive_oauth_authorize_url();
		if ( is_wp_error( $url ) ) {
			wp_safe_redirect( add_query_arg( 'ccd_gdrive_err', 'creds', admin_url( 'admin.php?page=ccd-gdrive' ) ) );
			exit;
		}
		wp_redirect( $url );
		exit;
	}
);

add_action(
	'admin_post_ccd_gdrive_disconnect',
	static function () {
		if ( ! current_user_can( 'export' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissao.' );
		}
		check_admin_referer( 'ccd_gdrive_disconnect' );

		$keep_secret = ccd_gdrive_client_secret();
		delete_option( CCD_GDRIVE_SECRETS );
		if ( $keep_secret !== '' ) {
			ccd_gdrive_update_secrets( array( 'client_secret' => $keep_secret ) );
		}

		$opt = get_option( CCD_GDRIVE_OPTION, array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}
		$opt['account_email'] = '';
		$opt['folder_id']     = '';
		$opt['connected_at']  = 0;
		$opt['enable']        = false;
		update_option( CCD_GDRIVE_OPTION, $opt, false );
		ccd_gdrive_reschedule_sync_cron();

		ccd_gdrive_log( 'Google Drive desvinculado.' );
		wp_safe_redirect( add_query_arg( 'ccd_gdrive_disconnected', '1', admin_url( 'admin.php?page=ccd-gdrive' ) ) );
		exit;
	}
);

add_action(
	'admin_post_ccd_gdrive_schedule',
	static function () {
		if ( ! current_user_can( 'export' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissao.' );
		}
		check_admin_referer( 'ccd_gdrive_schedule' );

		$opt = get_option( CCD_GDRIVE_OPTION, array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}

		$opt['schedule_enable']   = ! empty( $_POST['schedule_enable'] );
		$interval                 = isset( $_POST['schedule_interval'] ) ? sanitize_key( wp_unslash( $_POST['schedule_interval'] ) ) : 'daily';
		$opt['schedule_interval'] = array_key_exists( $interval, ccd_gdrive_schedule_intervals() ) ? $interval : 'daily';
		$opt['schedule_hour']     = isset( $_POST['schedule_hour'] ) ? max( 0, min( 23, (int) $_POST['schedule_hour'] ) ) : 3;

		update_option( CCD_GDRIVE_OPTION, $opt, false );
		ccd_gdrive_reschedule_cron();

		wp_safe_redirect( add_query_arg( 'ccd_gdrive_sched', '1', admin_url( 'admin.php?page=ccd-gdrive' ) ) );
		exit;
	}
);

add_action(
	'wp_ajax_ccd_gdrive_run_now',
	static function () {
		if ( ! current_user_can( 'export' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissao.' ), 403 );
		}
		check_ajax_referer( 'ccd_gdrive_run_now', 'nonce' );

		$result = ccd_ai1wm_run_scheduled_export();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		$msg = 'Backup iniciado. O upload para o Drive ocorre ao terminar o export.';
		wp_send_json_success(
			array(
				'message' => $msg,
				'when'    => wp_date( 'Y-m-d H:i:s T' ),
			)
		);
	}
);

add_action(
	'wp_ajax_ccd_gdrive_autosave',
	static function () {
		if ( ! current_user_can( 'export' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissao.' ), 403 );
		}
		check_ajax_referer( 'ccd_gdrive_autosave', 'nonce' );

		$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';
		$opt     = get_option( CCD_GDRIVE_OPTION, array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}

		if ( $section === 'options' ) {
			$prev_name              = isset( $opt['folder_name'] ) ? (string) $opt['folder_name'] : '';
			$opt['enable']          = ! empty( $_POST['enable'] );
			$opt['keep_local']      = empty( $_POST['delete_after_upload'] );
			$opt['client_id']       = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : ( $opt['client_id'] ?? '' );
			$opt['folder_name']     = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : CCD_GDRIVE_FOLDER_NAME;
			$opt['retention_count'] = isset( $_POST['retention_count'] ) ? max( 0, min( 100, (int) $_POST['retention_count'] ) ) : 5;
			if ( $prev_name !== $opt['folder_name'] ) {
				$opt['folder_id'] = '';
			}
			if ( isset( $_POST['client_secret'] ) ) {
				$secret_in = trim( (string) wp_unslash( $_POST['client_secret'] ) );
				if ( $secret_in !== '' && $secret_in !== '********' ) {
					ccd_gdrive_update_secrets( array( 'client_secret' => $secret_in ) );
				}
			}
			update_option( CCD_GDRIVE_OPTION, $opt, false );
			ccd_gdrive_reschedule_sync_cron();
			wp_send_json_success( array( 'message' => 'Opcoes salvas.' ) );
		}

		if ( $section === 'schedule' ) {
			$opt['schedule_enable']   = ! empty( $_POST['schedule_enable'] );
			$interval                 = isset( $_POST['schedule_interval'] ) ? sanitize_key( wp_unslash( $_POST['schedule_interval'] ) ) : 'daily';
			$opt['schedule_interval'] = array_key_exists( $interval, ccd_gdrive_schedule_intervals() ) ? $interval : 'daily';
			$opt['schedule_hour']     = isset( $_POST['schedule_hour'] ) ? max( 0, min( 23, (int) $_POST['schedule_hour'] ) ) : 3;
			update_option( CCD_GDRIVE_OPTION, $opt, false );
			ccd_gdrive_reschedule_cron();
			$next = wp_next_scheduled( CCD_GDRIVE_CRON_HOOK );
			wp_send_json_success(
				array(
					'message'  => 'Agendamento salvo.',
					'next_run' => $next ? wp_date( 'Y-m-d H:i:s T', $next ) : '',
				)
			);
		}

		wp_send_json_error( array( 'message' => 'Secao invalida.' ), 400 );
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( empty( $_GET['page'] ) || $_GET['page'] !== 'ccd-gdrive' ) {
			return;
		}
		$js = <<<'JS'
(function () {
	var cfg = window.ccdGdriveAutosave || {};
	function toast(msg, isError) {
		var el = document.getElementById('ccd-gdrive-autosave-status');
		if (!el) return;
		el.textContent = msg;
		el.style.color = isError ? '#b32d2e' : '#1d2327';
		el.setAttribute('data-state', isError ? 'error' : 'ok');
	}
	function collect(form) {
		var data = new FormData(form);
		data.set('action', 'ccd_gdrive_autosave');
		data.set('nonce', cfg.nonce || '');
		return data;
	}
	function save(form) {
		toast('Salvando…');
		fetch(cfg.ajaxUrl || ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: collect(form)
		})
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json || !json.success) {
					toast((json && json.data && json.data.message) || 'Falha ao salvar.', true);
					return;
				}
				toast((json.data && json.data.message) || 'Salvo.');
				if (json.data && json.data.next_run) {
					var next = document.getElementById('ccd-gdrive-next-run');
					if (next) {
						next.hidden = false;
						next.textContent = json.data.next_run;
					}
				}
			})
			.catch(function () { toast('Falha ao salvar.', true); });
	}
	function bind(form) {
		var t = null;
		function schedule() {
			clearTimeout(t);
			t = setTimeout(function () { save(form); }, 450);
		}
		form.addEventListener('change', schedule);
		form.addEventListener('input', function (e) {
			var tag = (e.target && e.target.tagName) || '';
			if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') schedule();
		});
	}
	function runBackup(btn) {
		if (!btn || btn.disabled) return;
		btn.disabled = true;
		var prev = btn.textContent;
		btn.textContent = 'Disparando…';
		toast('Disparando backup…');
		var data = new FormData();
		data.set('action', 'ccd_gdrive_run_now');
		data.set('nonce', cfg.runNonce || '');
		fetch(cfg.ajaxUrl || ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		})
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json || !json.success) {
					toast((json && json.data && json.data.message) || 'Falha ao disparar backup.', true);
					return;
				}
				toast((json.data && json.data.message) || 'Backup iniciado.');
				var msg = (json.data && json.data.message) || 'Backup iniciado.';
				var statusEl = document.getElementById('ccd-gdrive-last-status-text');
				if (statusEl) statusEl.textContent = msg;
				var list = document.getElementById('ccd-gdrive-status-list');
				if (list && json.data && json.data.when) {
					var li = document.createElement('li');
					li.style.margin = '0 0 .6rem';
					li.innerHTML = '<strong></strong> — <code></code>';
					li.querySelector('strong').textContent = json.data.when;
					li.querySelector('code').textContent = msg;
					li.querySelector('code').id = 'ccd-gdrive-last-status-text';
					var old = document.getElementById('ccd-gdrive-last-status-text');
					if (old) old.removeAttribute('id');
					list.insertBefore(li, list.firstChild);
					while (list.children.length > 5) list.removeChild(list.lastChild);
				}
			})
			.catch(function () { toast('Falha ao disparar backup.', true); })
			.finally(function () {
				btn.disabled = false;
				btn.textContent = prev;
			});
	}
	function syncHourRow() {
		var sel = document.getElementById('ccd_schedule_interval');
		var row = document.getElementById('ccd-gdrive-schedule-hour-row');
		if (!sel || !row) return;
		row.hidden = sel.value === 'hourly';
	}
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('form.ccd-gdrive-autosave').forEach(bind);
		document.querySelectorAll('.ccd-gdrive-run-now').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				runBackup(btn);
			});
		});
		var interval = document.getElementById('ccd_schedule_interval');
		if (interval) {
			interval.addEventListener('change', syncHourRow);
			syncHourRow();
		}
	});
})();
JS;
		wp_register_script( 'ccd-gdrive-autosave', false, array(), null, true );
		wp_enqueue_script( 'ccd-gdrive-autosave' );
		wp_add_inline_script( 'ccd-gdrive-autosave', $js );
		wp_localize_script(
			'ccd-gdrive-autosave',
			'ccdGdriveAutosave',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ccd_gdrive_autosave' ),
				'runNonce' => wp_create_nonce( 'ccd_gdrive_run_now' ),
			)
		);
	}
);

function ccd_gdrive_render_settings_page() {
	if ( ! current_user_can( 'export' ) && ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings   = ccd_gdrive_settings();
	$connected  = ccd_gdrive_is_connected();
	$has_secret = ccd_gdrive_client_secret() !== '';
	$status_log = ccd_gdrive_get_status_log();
	$status     = $status_log[0] ?? get_option( 'ccd_gdrive_last_status' );
	$redirect   = ccd_gdrive_redirect_uri();

	if ( ! empty( $_GET['ccd_gdrive_ok'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Google Drive vinculado com sucesso.</p></div>';
	}
	if ( ! empty( $_GET['ccd_gdrive_saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas.</p></div>';
	}
	if ( ! empty( $_GET['ccd_gdrive_disconnected'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Conta desvinculada.</p></div>';
	}
	if ( ! empty( $_GET['ccd_gdrive_sched'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Agendamento salvo.</p></div>';
	}
	if ( ! empty( $_GET['ccd_gdrive_err'] ) ) {
		$err = sanitize_key( wp_unslash( $_GET['ccd_gdrive_err'] ) );
		$hint = 'Confira Client ID/Secret, Redirect URI e se o e-mail está em Usuários de teste (app em modo Testing).';
		if ( $err === 'access_denied' ) {
			$hint = 'Acesso negado: adicione este e-mail em Google Auth Platform → Público-alvo → Usuários de teste.';
		}
		echo '<div class="notice notice-error is-dismissible"><p>Falha na vinculação (' . esc_html( $err ) . '). ' . esc_html( $hint ) . '</p></div>';
	}
	?>
	<div class="wrap">
		<h1>Backup Google Drive</h1>
		<p>Backups do All-in-One WP Migration sobem sozinhos para a pasta <strong><?php echo esc_html( $settings['folder_name'] ?: CCD_GDRIVE_FOLDER_NAME ); ?></strong> no Drive.</p>
		<p id="ccd-gdrive-autosave-status" class="description" aria-live="polite" style="min-height:1.4em;font-weight:600;">Alterações nas opções e no agendamento são salvas automaticamente.</p>

		<div class="card" style="max-width:52rem;padding:1rem 1.25rem;margin:1rem 0;">
			<?php if ( ! $connected ) : ?>
			<h2 style="margin-top:0;">Vincular com Google Drive</h2>
			<details style="margin-bottom:1rem;" open>
				<summary style="cursor:pointer;font-weight:600;">Como obter Client ID e Secret</summary>
				<ol style="margin-top:.5rem;">
					<li>No <a href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank" rel="noopener noreferrer">Google Cloud Console</a>, ative a <strong>Google Drive API</strong> no projeto (obrigatório — sem isso o backup falha com 403).</li>
					<li>Configure a <strong>tela de permissão OAuth</strong> (tipo Externo) e adicione o e-mail da conta em <strong>Usuários de teste</strong>.</li>
					<li>Credenciais → <strong>ID do cliente OAuth</strong> → <strong>Aplicativo da Web</strong>.</li>
					<li>Origem JS: <code>http://localhost:8080</code></li>
					<li>Redirect URI (cole exatamente):
						<br /><code style="user-select:all;word-break:break-all;"><?php echo esc_html( $redirect ); ?></code>
					</li>
					<li>Cole Client ID/Secret abaixo e clique em <strong>Vincular com Google Drive</strong> — autentique com a conta do Drive.</li>
				</ol>
			</details>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ccd_gdrive_connect' ); ?>
				<input type="hidden" name="action" value="ccd_gdrive_connect" />
				<input type="hidden" name="enable" value="1" />
				<input type="hidden" name="keep_local" value="1" />
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ccd_client_id">Client ID</label></th>
						<td><input type="text" class="large-text code" id="ccd_client_id" name="client_id" value="<?php echo esc_attr( $settings['client_id'] ); ?>" autocomplete="off" required /></td>
					</tr>
					<tr>
						<th><label for="ccd_client_secret">Client Secret</label></th>
						<td>
							<input type="password" class="large-text code" id="ccd_client_secret" name="client_secret" value="<?php echo $has_secret ? '********' : ''; ?>" autocomplete="new-password" <?php echo $has_secret ? '' : 'required'; ?> />
							<p class="description">Criptografado no banco (AES-256).</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Vincular com Google Drive', 'primary large', 'submit', true ); ?>
			</form>
			<?php else : ?>
			<h2 style="margin-top:0;">Credenciais do app</h2>
			<p style="margin:0 0 1rem;">
				<span class="dashicons dashicons-yes-alt" style="color:#00a32a;vertical-align:middle;"></span>
				<strong>Vinculado</strong><?php echo $settings['account_email'] ? ' — ' . esc_html( $settings['account_email'] ) : ''; ?>
			</p>
			<form class="ccd-gdrive-autosave" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ccd_gdrive_save' ); ?>
				<input type="hidden" name="action" value="ccd_gdrive_save" />
				<input type="hidden" name="section" value="options" />
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ccd_client_id">Client ID</label></th>
						<td><input type="text" class="large-text code" id="ccd_client_id" name="client_id" value="<?php echo esc_attr( $settings['client_id'] ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th><label for="ccd_client_secret">Client Secret</label></th>
						<td>
							<input type="password" class="large-text code" id="ccd_client_secret" name="client_secret" value="<?php echo $has_secret ? '********' : ''; ?>" autocomplete="new-password" />
							<p class="description">Criptografado. Deixe ******** para manter.</p>
						</td>
					</tr>
					<tr>
						<th><label for="ccd_folder_name">Pasta no Drive</label></th>
						<td><input type="text" class="regular-text" id="ccd_folder_name" name="folder_name" value="<?php echo esc_attr( $settings['folder_name'] ?: CCD_GDRIVE_FOLDER_NAME ); ?>" /></td>
					</tr>
					<tr>
						<th>Apos o export</th>
						<td>
							<label><input type="checkbox" name="enable" value="1" <?php checked( $settings['enable'] ); ?> /> Enviar automaticamente para o Google Drive</label><br />
							<label><input type="checkbox" name="delete_after_upload" value="1" <?php checked( ! $settings['keep_local'] ); ?> /> Excluir o backup local depois de copiar para o Google Drive</label>
							<p class="description">Se marcado, o arquivo <code>.wpress</code> é apagado do servidor só após upload com sucesso.</p>
						</td>
					</tr>
					<tr>
						<th><label for="ccd_retention_count">Rotação no Drive</label></th>
						<td>
							<input type="number" class="small-text" id="ccd_retention_count" name="retention_count" min="0" max="100" step="1" value="<?php echo esc_attr( (string) $settings['retention_count'] ); ?>" />
							<span>backups mais recentes</span>
							<p class="description">Mantém só os <strong>X</strong> arquivos mais novos na pasta do Drive e apaga o restante. Use <code>0</code> para não rotacionar.</p>
						</td>
					</tr>
					<tr>
						<th>Sync de pendentes</th>
						<td>
							<?php
							$pending_n = 0;
							foreach ( ccd_gdrive_list_local_backups() as $p ) {
								if ( ! ccd_gdrive_local_is_synced( $p ) ) {
									$pending_n++;
								}
							}
							$next_sync = wp_next_scheduled( CCD_GDRIVE_SYNC_CRON );
							?>
							<p style="margin:0 0 .5rem;">
								O site verifica <strong>a cada hora</strong> se há <code>.wpress</code> local ainda não enviado ao Drive.
								Se encontrar, faz o upload e aplica a exclusão local (se marcada acima).
							</p>
							<p class="description" style="margin:0;">
								Pendentes agora: <strong><?php echo (int) $pending_n; ?></strong>
								<?php if ( $next_sync ) : ?>
									— próxima verificação: <?php echo esc_html( wp_date( 'Y-m-d H:i:s T', $next_sync ) ); ?>
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>
				<p class="description">Salvamento automático ativo — não é preciso clicar em salvar.</p>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1rem;">
				<?php wp_nonce_field( 'ccd_gdrive_disconnect' ); ?>
				<input type="hidden" name="action" value="ccd_gdrive_disconnect" />
				<?php submit_button( 'Desvincular Google Drive', 'delete', 'submit', false ); ?>
			</form>
			<?php endif; ?>
			<?php
			$api_probe = ccd_gdrive_probe_drive_api( ! empty( $_GET['ccd_gdrive_recheck_api'] ) );
			if ( ! empty( $api_probe['disabled'] ) ) :
				$drive_api_url = 'https://console.cloud.google.com/apis/library/drive.googleapis.com';
				if ( $settings['client_id'] !== '' && preg_match( '/^(\d+)-/', $settings['client_id'], $m ) ) {
					$drive_api_url = 'https://console.developers.google.com/apis/api/drive.googleapis.com/overview?project=' . rawurlencode( $m[1] );
				}
				?>
			<div class="notice notice-warning inline" style="margin:1rem 0 0;padding:8px 12px;">
				<p style="margin:0 0 .5rem;">
					<strong>Google Drive API desativada neste projeto</strong><br />
					O WordPress testou a API e recebeu erro 403. Sem ativá-la, não é possível criar pasta nem enviar backup.
				</p>
				<?php if ( ! empty( $api_probe['message'] ) ) : ?>
					<p class="description" style="margin:0 0 .5rem;"><code><?php echo esc_html( $api_probe['message'] ); ?></code></p>
				<?php endif; ?>
				<p style="margin:0;">
					<a class="button button-secondary" href="<?php echo esc_url( $drive_api_url ); ?>" target="_blank" rel="noopener noreferrer">Ativar Google Drive API neste projeto</a>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'ccd_gdrive_recheck_api', '1', admin_url( 'admin.php?page=ccd-gdrive' ) ) ); ?>">Verificar de novo</a>
					<span class="description" style="margin-left:8px;">Depois de ativar, espere 1–2 minutos e clique em verificar.</span>
				</p>
			</div>
			<?php elseif ( $connected && ! empty( $api_probe['ok'] ) ) : ?>
			<p class="description" style="margin:1rem 0 0;color:#00a32a;">Google Drive API ativa neste projeto.</p>
			<?php endif; ?>
		</div>

		<?php if ( ! $connected ) : ?>
		<p class="description">Redirect URI: <code style="user-select:all;"><?php echo esc_html( $redirect ); ?></code></p>
		<?php endif; ?>

		<?php
		$next_cron   = wp_next_scheduled( CCD_GDRIVE_CRON_HOOK );
		$last_sched  = get_option( 'ccd_ai1wm_last_schedule' );
		$intervals   = ccd_gdrive_schedule_intervals();
		?>
		<div class="card" style="max-width:52rem;padding:1rem 1.25rem;margin:1rem 0;">
			<h2 style="margin-top:0;">Agendamento automático</h2>
			<p>Dispara o export do All-in-One WP Migration na periodicidade escolhida. Com o Drive vinculado e o envio automático marcado, o <code>.wpress</code> sobe depois do export.</p>
			<form class="ccd-gdrive-autosave" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ccd_gdrive_schedule' ); ?>
				<input type="hidden" name="action" value="ccd_gdrive_schedule" />
				<input type="hidden" name="section" value="schedule" />
				<table class="form-table" role="presentation">
					<tr>
						<th>Ativar</th>
						<td>
							<label>
								<input type="checkbox" name="schedule_enable" value="1" <?php checked( $settings['schedule_enable'] ); ?> />
								Rodar backup periodicamente
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="ccd_schedule_interval">Periodicidade</label></th>
						<td>
							<select name="schedule_interval" id="ccd_schedule_interval">
								<?php foreach ( $intervals as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['schedule_interval'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="ccd-gdrive-schedule-hour-row"<?php echo $settings['schedule_interval'] === 'hourly' ? ' hidden' : ''; ?>>
						<th><label for="ccd_schedule_hour">Horário (aprox.)</label></th>
						<td>
							<select name="schedule_hour" id="ccd_schedule_hour">
								<?php for ( $h = 0; $h < 24; $h++ ) : ?>
									<option value="<?php echo (int) $h; ?>" <?php selected( $settings['schedule_hour'], $h ); ?>>
										<?php echo esc_html( sprintf( '%02d:00', $h ) ); ?>
									</option>
								<?php endfor; ?>
							</select>
							<p class="description">Fuso do WordPress (<?php echo esc_html( wp_timezone_string() ); ?>).</p>
						</td>
					</tr>
				</table>
				<p class="description">Salvamento automático ativo.</p>
			</form>
			<p style="margin-top:1rem;">
				<?php if ( $settings['schedule_enable'] && $next_cron ) : ?>
					<strong>Próxima execução:</strong>
					<span id="ccd-gdrive-next-run"><?php echo esc_html( wp_date( 'Y-m-d H:i:s T', $next_cron ) ); ?></span>
				<?php elseif ( $settings['schedule_enable'] ) : ?>
					<strong>Próxima execução:</strong>
					<span id="ccd-gdrive-next-run">aguardando…</span>
				<?php else : ?>
					<strong>Agendamento:</strong> desligado.
					<span id="ccd-gdrive-next-run" hidden></span>
				<?php endif; ?>
			</p>
			<?php if ( is_array( $last_sched ) && ! empty( $last_sched['time'] ) ) : ?>
				<p class="description">Último disparo automático: <?php echo esc_html( wp_date( 'Y-m-d H:i:s T', (int) $last_sched['time'] ) ); ?>
					(<?php echo esc_html( (string) ( $last_sched['status'] ?? '?' ) ); ?>)</p>
			<?php endif; ?>
			<p style="margin-top:8px;">
				<button type="button" class="button button-secondary ccd-gdrive-run-now">Executar backup agora</button>
			</p>
			<p class="description">O horário é aproximado: o WP-Cron só roda quando há visita ao site. Se ninguém acessar na hora programada, o backup dispara na próxima visita.</p>
		</div>

		<div class="card" style="max-width:52rem;padding:1rem 1.25rem;margin:1rem 0;">
			<h2 style="margin-top:0;">Últimas ações</h2>
			<p class="description" style="margin-top:0;">Histórico do agendamento, export e envio ao Drive (fuso <?php echo esc_html( wp_timezone_string() ); ?>). Até <?php echo (int) CCD_GDRIVE_STATUS_MAX; ?> registros.</p>
			<?php if ( $status_log ) : ?>
			<ol id="ccd-gdrive-status-list" style="margin:0;padding-left:1.25rem;">
				<?php foreach ( $status_log as $i => $row ) : ?>
					<li style="margin:0 0 .6rem;">
						<strong><?php echo esc_html( ! empty( $row['time'] ) ? wp_date( 'Y-m-d H:i:s T', (int) $row['time'] ) : '?' ); ?></strong>
						— <code<?php echo 0 === $i ? ' id="ccd-gdrive-last-status-text"' : ''; ?>><?php echo esc_html( (string) ( $row['message'] ?? '' ) ); ?></code>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php else : ?>
			<p class="description" style="margin:0;">Nenhuma ação registrada ainda. <code id="ccd-gdrive-last-status-text"></code></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'export' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || strpos( (string) $screen->id, 'ai1wm' ) === false ) {
			return;
		}
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'ccd-gdrive' ) {
			return;
		}

		$link = admin_url( 'admin.php?page=ccd-gdrive' );
		if ( ! ccd_gdrive_is_connected() ) {
			echo '<div class="notice notice-info"><p><strong>Backup → Google Drive:</strong> <a class="button button-primary" href="' . esc_url( $link ) . '">Vincular com Google Drive</a></p></div>';
			return;
		}
		$settings = ccd_gdrive_settings();
		if ( $settings['enable'] ) {
			echo '<div class="notice notice-success"><p><strong>Backup → Google Drive:</strong> ativo'
				. ( $settings['account_email'] ? ' (' . esc_html( $settings['account_email'] ) . ')' : '' )
				. '. <a href="' . esc_url( $link ) . '">Gerenciar</a></p></div>';
		}
	}
);
