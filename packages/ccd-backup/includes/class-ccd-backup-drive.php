<?php
/**
 * Google Drive OAuth, resumable upload, retention, and pending sync.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CCD_Backup_Drive {

	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=ccd_backup_oauth' );
	}

	/**
	 * @param string              $url
	 * @param array<string,mixed> $args
	 * @return array|WP_Error
	 */
	public static function http( $url, array $args = array() ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return new WP_Error( 'ccd_backup_curl', 'ext-curl indisponível.' );
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
		$raw         = curl_exec( $ch );
		$err         = curl_error( $ch );
		$code        = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		curl_close( $ch );

		if ( $raw === false ) {
			return new WP_Error( 'ccd_backup_http', $err ?: 'Falha HTTP' );
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
	public static function http_json( $url, array $args = array() ) {
		$res = self::http( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( $res['body'], true );
		if ( $res['code'] < 200 || $res['code'] >= 300 ) {
			return new WP_Error( 'ccd_backup_http', 'HTTP ' . $res['code'], array( 'body' => $data ?: $res['body'] ) );
		}
		return is_array( $data ) ? $data : array();
	}

	/**
	 * @return string|WP_Error
	 */
	public static function oauth_authorize_url() {
		$settings = CCD_Backup_Settings::get();
		$secret   = CCD_Backup_Crypto::client_secret();
		if ( $settings['client_id'] === '' || $secret === '' ) {
			return new WP_Error( 'ccd_backup_creds', 'Informe Client ID e Client Secret antes de vincular.' );
		}

		$state = wp_create_nonce( 'ccd_backup_oauth' );
		set_transient( 'ccd_backup_oauth_state', $state, 15 * MINUTE_IN_SECONDS );

		return add_query_arg(
			array(
				'client_id'     => $settings['client_id'],
				'redirect_uri'  => self::redirect_uri(),
				'response_type' => 'code',
				'scope'         => CCD_BACKUP_OAUTH_SCOPE,
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
	public static function exchange_code( $code ) {
		$settings = CCD_Backup_Settings::get();
		$secret   = CCD_Backup_Crypto::client_secret();
		$data     = self::http_json(
			'https://oauth2.googleapis.com/token',
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query(
					array(
						'code'          => $code,
						'client_id'     => $settings['client_id'],
						'client_secret' => $secret,
						'redirect_uri'  => self::redirect_uri(),
						'grant_type'    => 'authorization_code',
					)
				),
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['refresh_token'] ) && empty( $data['access_token'] ) ) {
			return new WP_Error( 'ccd_backup_token', 'Google não devolveu tokens.', array( 'body' => $data ) );
		}

		$patch = array(
			'access_token'   => (string) ( $data['access_token'] ?? '' ),
			'access_expires' => time() + max( 60, (int) ( $data['expires_in'] ?? 3600 ) - 60 ),
		);
		if ( ! empty( $data['refresh_token'] ) ) {
			$patch['refresh_token'] = (string) $data['refresh_token'];
		}
		CCD_Backup_Crypto::update_secrets( $patch );
		return true;
	}

	/**
	 * @return string|WP_Error
	 */
	public static function access_token() {
		$s = CCD_Backup_Crypto::get_secrets();
		if ( $s['access_token'] !== '' && $s['access_expires'] > time() ) {
			return $s['access_token'];
		}
		if ( $s['refresh_token'] === '' ) {
			return new WP_Error( 'ccd_backup_auth', 'Google Drive não vinculado.' );
		}

		$settings = CCD_Backup_Settings::get();
		$secret   = CCD_Backup_Crypto::client_secret();
		$data     = self::http_json(
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
			return new WP_Error( 'ccd_backup_refresh', 'Falha ao renovar access token.', array( 'body' => $data ) );
		}

		CCD_Backup_Crypto::update_secrets(
			array(
				'access_token'   => (string) $data['access_token'],
				'access_expires' => time() + max( 60, (int) ( $data['expires_in'] ?? 3600 ) - 60 ),
			)
		);
		return (string) $data['access_token'];
	}

	/**
	 * @param string $token
	 */
	public static function fetch_account_email( $token ) {
		$data = self::http_json(
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
	 * @return string|WP_Error
	 */
	public static function ensure_folder( $token ) {
		$settings = CCD_Backup_Settings::get();
		if ( $settings['folder_id'] !== '' ) {
			return $settings['folder_id'];
		}

		$name = $settings['folder_name'] !== '' ? $settings['folder_name'] : CCD_BACKUP_FOLDER_NAME;
		$q    = "mimeType='application/vnd.google-apps.folder' and name='" . str_replace( "'", "\\'", $name ) . "' and trashed=false";

		$list = self::http_json(
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
			$created = self::http_json(
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
				return new WP_Error( 'ccd_backup_folder', 'Não foi possível criar a pasta no Drive.' );
			}
		}

		CCD_Backup_Settings::update( array( 'folder_id' => $folder_id ) );
		return $folder_id;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_synced_map() {
		$map = get_option( CCD_BACKUP_SYNCED, array() );
		return is_array( $map ) ? $map : array();
	}

	/**
	 * @param string $basename
	 * @param string $drive_id
	 * @param int    $size
	 * @param int    $mtime
	 */
	public static function mark_synced( $basename, $drive_id, $size, $mtime ) {
		$map              = self::get_synced_map();
		$map[ $basename ] = array(
			'id'    => (string) $drive_id,
			'size'  => (int) $size,
			'mtime' => (int) $mtime,
			'time'  => time(),
		);
		update_option( CCD_BACKUP_SYNCED, $map, false );
	}

	/**
	 * @param string $basename
	 */
	public static function unmark_synced( $basename ) {
		$map = self::get_synced_map();
		unset( $map[ $basename ] );
		update_option( CCD_BACKUP_SYNCED, $map, false );
	}

	/**
	 * @param string $path
	 */
	public static function local_is_synced( $path ) {
		$base = basename( $path );
		$map  = self::get_synced_map();
		if ( empty( $map[ $base ] ) ) {
			return false;
		}
		$size  = filesize( $path );
		$mtime = filemtime( $path );
		$row   = $map[ $base ];
		return (int) ( $row['size'] ?? 0 ) === (int) $size && (int) ( $row['mtime'] ?? 0 ) === (int) $mtime;
	}

	/**
	 * @return string[]
	 */
	public static function collect_pending_backups() {
		$pending = array();
		foreach ( CCD_Backup_Paths::list_local_backups() as $path ) {
			if ( ! self::local_is_synced( $path ) ) {
				$pending[] = $path;
			}
		}
		return $pending;
	}

	/**
	 * @param string $file
	 * @param int    $done
	 * @param int    $total
	 * @param bool   $force
	 */
	public static function update_upload_progress( $file, $done, $total, $force = false ) {
		static $last = 0;
		$now = time();
		if ( ! $force && ( $now - $last ) < 1 ) {
			return;
		}
		$last    = $now;
		$percent = $total > 0 ? (int) min( 100, round( ( $done / $total ) * 100 ) ) : null;
		CCD_Backup_Progress::set_live(
			array(
				'phase'       => 'uploading',
				'file'        => basename( (string) $file ),
				'bytes_done'  => (int) $done,
				'bytes_total' => (int) $total,
				'percent'     => $percent,
				'message'     => sprintf(
					'Enviando %s para o Google Drive…%s',
					basename( (string) $file ),
					$percent !== null ? ' ' . $percent . '%' : ''
				),
			)
		);
	}

	/**
	 * @param string $token
	 * @param string $folder_id
	 * @param string $name
	 * @param int    $size
	 */
	public static function find_remote_backup( $token, $folder_id, $name, $size ) {
		$q = sprintf(
			"'%s' in parents and name='%s' and trashed=false",
			str_replace( "'", "\\'", $folder_id ),
			str_replace( "'", "\\'", $name )
		);
		$list = self::http_json(
			'https://www.googleapis.com/drive/v3/files?' . http_build_query(
				array(
					'q'      => $q,
					'fields' => 'files(id,name,size)',
				)
			),
			array( 'headers' => array( 'Authorization: Bearer ' . $token ) )
		);
		if ( is_wp_error( $list ) || empty( $list['files'] ) ) {
			return '';
		}
		foreach ( $list['files'] as $file ) {
			if ( isset( $file['size'] ) && (int) $file['size'] === (int) $size ) {
				return (string) $file['id'];
			}
		}
		return '';
	}

	/**
	 * @param string $token
	 * @param string $file
	 * @param string $folder_id
	 * @return string|WP_Error Drive file id
	 */
	public static function upload_file( $token, $file, $folder_id ) {
		$size = filesize( $file );
		if ( ! $size ) {
			return new WP_Error( 'ccd_backup_size', 'Não foi possível ler o tamanho do arquivo.' );
		}

		$name  = basename( $file );
		$meta  = wp_json_encode(
			array(
				'name'    => $name,
				'parents' => array( $folder_id ),
			)
		);
		$start = self::http(
			'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable',
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization: Bearer ' . $token,
					'Content-Type: application/json; charset=UTF-8',
					'X-Upload-Content-Type: application/octet-stream',
					'X-Upload-Content-Length: ' . $size,
				),
				'body'    => $meta,
				'timeout' => 120,
			)
		);
		if ( is_wp_error( $start ) ) {
			return $start;
		}
		if ( $start['code'] < 200 || $start['code'] >= 300 ) {
			return new WP_Error( 'ccd_backup_session', 'HTTP ' . $start['code'] . ' ao iniciar upload.', array( 'body' => $start['body'] ) );
		}
		if ( ! preg_match( '/^Location:\s*(.+)$/mi', $start['headers'], $m ) ) {
			return new WP_Error( 'ccd_backup_location', 'Location do upload resumable ausente.' );
		}
		$location = trim( $m[1] );

		$fh = fopen( $file, 'rb' );
		if ( ! $fh ) {
			return new WP_Error( 'ccd_backup_open', 'Não foi possível abrir o backup para leitura.' );
		}

		self::update_upload_progress( $file, 0, (int) $size, true );
		$chunk  = 8 * 1024 * 1024;
		$done   = 0;
		$total  = (int) $size;
		$drive_id = '';

		while ( ! feof( $fh ) ) {
			$part = fread( $fh, $chunk );
			if ( $part === false ) {
				fclose( $fh );
				return new WP_Error( 'ccd_backup_read', 'Falha ao ler o backup.' );
			}
			$len  = strlen( $part );
			$end  = $done + $len - 1;
			$put  = self::http(
				$location,
				array(
					'method'  => 'PUT',
					'headers' => array(
						'Content-Length: ' . $len,
						'Content-Range: bytes ' . $done . '-' . $end . '/' . $total,
					),
					'body'    => $part,
					'timeout' => 300,
				)
			);
			if ( is_wp_error( $put ) ) {
				fclose( $fh );
				return $put;
			}
			$done += $len;
			self::update_upload_progress( $file, $done, $total );

			if ( $put['code'] === 200 || $put['code'] === 201 ) {
				$data = json_decode( $put['body'], true );
				if ( is_array( $data ) && ! empty( $data['id'] ) ) {
					$drive_id = (string) $data['id'];
				}
				break;
			}
			if ( $put['code'] !== 308 ) {
				fclose( $fh );
				return new WP_Error( 'ccd_backup_put', 'HTTP ' . $put['code'] . ' no PUT.', array( 'body' => $put['body'] ) );
			}
		}
		fclose( $fh );

		if ( $drive_id === '' ) {
			return new WP_Error( 'ccd_backup_put', 'Upload concluído sem ID do Drive.' );
		}
		self::update_upload_progress( $file, $total, $total, true );
		return $drive_id;
	}

	/**
	 * @param string $token
	 * @param string $folder_id
	 * @param int    $keep
	 */
	public static function rotate_backups( $token, $folder_id, $keep ) {
		$keep = max( 0, (int) $keep );
		if ( $keep <= 0 ) {
			return true;
		}

		$q = sprintf(
			"'%s' in parents and trashed=false and name contains '.%s'",
			str_replace( "'", "\\'", $folder_id ),
			CCD_BACKUP_FORMAT
		);
		$list = self::http_json(
			'https://www.googleapis.com/drive/v3/files?' . http_build_query(
				array(
					'q'        => $q,
					'fields'   => 'files(id,name,createdTime)',
					'orderBy'  => 'createdTime desc',
					'pageSize' => 100,
				)
			),
			array( 'headers' => array( 'Authorization: Bearer ' . $token ) )
		);
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		$files = array();
		foreach ( ( $list['files'] ?? array() ) as $file ) {
			$name = (string) ( $file['name'] ?? '' );
			if ( str_ends_with( strtolower( $name ), '.' . CCD_BACKUP_FORMAT ) ) {
				$files[] = $file;
			}
		}
		if ( count( $files ) <= $keep ) {
			return true;
		}
		$to_delete = array_slice( $files, $keep );
		foreach ( $to_delete as $file ) {
			if ( empty( $file['id'] ) ) {
				continue;
			}
			self::http(
				'https://www.googleapis.com/drive/v3/files/' . rawurlencode( (string) $file['id'] ),
				array(
					'method'  => 'DELETE',
					'headers' => array( 'Authorization: Bearer ' . $token ),
				)
			);
		}
		return true;
	}

	/**
	 * Find or create a child folder under a Drive parent.
	 *
	 * @param string $token
	 * @param string $parent_id
	 * @param string $name
	 * @return string|WP_Error folder id
	 */
	public static function ensure_child_folder( $token, $parent_id, $name ) {
		$name = trim( (string) $name );
		if ( $name === '' || $parent_id === '' ) {
			return new WP_Error( 'ccd_backup_folder', 'Pasta filha inválida.' );
		}

		$q = sprintf(
			"mimeType='application/vnd.google-apps.folder' and name='%s' and '%s' in parents and trashed=false",
			str_replace( "'", "\\'", $name ),
			str_replace( "'", "\\'", $parent_id )
		);
		$list = self::http_json(
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
			return (string) $list['files'][0]['id'];
		}

		$created = self::http_json(
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
						'parents'  => array( $parent_id ),
					)
				),
			)
		);
		if ( is_wp_error( $created ) || empty( $created['id'] ) ) {
			return new WP_Error( 'ccd_backup_folder', 'Não foi possível criar a pasta plugin no Drive.' );
		}
		return (string) $created['id'];
	}

	/**
	 * @param string $token
	 * @param string $folder_id
	 * @param string $name
	 * @return string file id or empty
	 */
	public static function find_named_file( $token, $folder_id, $name ) {
		$q = sprintf(
			"'%s' in parents and name='%s' and trashed=false",
			str_replace( "'", "\\'", $folder_id ),
			str_replace( "'", "\\'", $name )
		);
		$list = self::http_json(
			'https://www.googleapis.com/drive/v3/files?' . http_build_query(
				array(
					'q'        => $q,
					'fields'   => 'files(id,name,size)',
					'pageSize' => 5,
				)
			),
			array( 'headers' => array( 'Authorization: Bearer ' . $token ) )
		);
		if ( is_wp_error( $list ) || empty( $list['files'][0]['id'] ) ) {
			return '';
		}
		return (string) $list['files'][0]['id'];
	}

	/**
	 * @param string $token
	 * @param string $file_id
	 */
	public static function trash_file( $token, $file_id ) {
		if ( $file_id === '' ) {
			return true;
		}
		self::http(
			'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id ),
			array(
				'method'  => 'DELETE',
				'headers' => array( 'Authorization: Bearer ' . $token ),
			)
		);
		return true;
	}

	/**
	 * Build installable ZIP (ccd-backup/… root) of the currently installed plugin.
	 *
	 * @return string|WP_Error absolute path
	 */
	public static function build_plugin_zip() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'ccd_backup_zip', 'ZipArchive não está disponível.' );
		}
		if ( ! defined( 'CCD_BACKUP_DIR' ) || ! is_dir( CCD_BACKUP_DIR ) ) {
			return new WP_Error( 'ccd_backup_zip', 'Diretório do plugin não encontrado.' );
		}

		CCD_Backup_Paths::ensure_temp_dir();
		$zip_name = 'ccd-backup-' . CCD_BACKUP_VERSION . '.zip';
		$zip_path = trailingslashit( CCD_Backup_Paths::temp_dir() ) . $zip_name;
		if ( file_exists( $zip_path ) ) {
			@unlink( $zip_path );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'ccd_backup_zip', 'Não foi possível criar o ZIP do plugin.' );
		}

		$root = trailingslashit( wp_normalize_path( CCD_BACKUP_DIR ) );
		$skip = array( '.git', '.DS_Store', 'Thumbs.db', '.keep' );
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iter as $file ) {
			/** @var SplFileInfo $file */
			if ( ! $file->isFile() ) {
				continue;
			}
			$full = wp_normalize_path( $file->getPathname() );
			$base = basename( $full );
			if ( in_array( $base, $skip, true ) ) {
				continue;
			}
			$rel = ltrim( substr( $full, strlen( $root ) ), '/' );
			if ( $rel === '' ) {
				continue;
			}
			$zip->addFile( $full, 'ccd-backup/' . str_replace( '\\', '/', $rel ) );
		}
		$zip->close();

		if ( ! is_readable( $zip_path ) || filesize( $zip_path ) < 100 ) {
			@unlink( $zip_path );
			return new WP_Error( 'ccd_backup_zip', 'ZIP do plugin ficou inválido.' );
		}
		return $zip_path;
	}

	/**
	 * Upload ccd-backup-*.zip + LEIA-ME.md into Drive folder/plugin/.
	 * Always replaces previous ZIPs/README in that folder when $force is true
	 * (used after each backup upload). Otherwise skips if content hash unchanged.
	 *
	 * @param string      $token
	 * @param string|null $parent_folder_id Backup root folder id
	 * @param bool        $force            Replace even if fingerprint matches
	 * @return true|WP_Error
	 */
	public static function sync_plugin_bundle( $token, $parent_folder_id = null, $force = false ) {
		if ( $token === '' ) {
			return new WP_Error( 'ccd_backup_token', 'Token ausente.' );
		}
		if ( $parent_folder_id === null || $parent_folder_id === '' ) {
			$parent = self::ensure_folder( $token );
			if ( is_wp_error( $parent ) ) {
				return $parent;
			}
			$parent_folder_id = $parent;
		}

		$settings = CCD_Backup_Settings::get();
		$plugin_folder = $settings['plugin_folder_id'];
		if ( $plugin_folder === '' ) {
			$created = self::ensure_child_folder( $token, $parent_folder_id, 'plugin' );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$plugin_folder = $created;
			CCD_Backup_Settings::update( array( 'plugin_folder_id' => $plugin_folder ) );
		} else {
			// Re-validate; recreate if missing.
			$probe = self::http_json(
				'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $plugin_folder ) . '?fields=id,trashed',
				array( 'headers' => array( 'Authorization: Bearer ' . $token ) )
			);
			if ( is_wp_error( $probe ) || empty( $probe['id'] ) || ! empty( $probe['trashed'] ) ) {
				$created = self::ensure_child_folder( $token, $parent_folder_id, 'plugin' );
				if ( is_wp_error( $created ) ) {
					return $created;
				}
				$plugin_folder = $created;
				CCD_Backup_Settings::update( array( 'plugin_folder_id' => $plugin_folder ) );
			}
		}

		$readme_src = trailingslashit( CCD_BACKUP_DIR ) . 'drive-plugin/LEIA-ME.md';
		if ( ! is_readable( $readme_src ) ) {
			return new WP_Error( 'ccd_backup_readme', 'LEIA-ME.md do plugin não encontrado.' );
		}

		$zip_path = self::build_plugin_zip();
		if ( is_wp_error( $zip_path ) ) {
			return $zip_path;
		}

		$zip_hash    = (string) md5_file( $zip_path );
		$readme_hash = (string) md5_file( $readme_src );
		$fingerprint = CCD_BACKUP_VERSION . '|' . $zip_hash . '|' . $readme_hash;
		$prev        = (string) get_option( 'ccd_backup_plugin_drive_fp', '' );
		if ( ! $force && $prev === $fingerprint ) {
			@unlink( $zip_path );
			return true;
		}

		$zip_name = basename( $zip_path );

		// Upload novos primeiro; só remove os antigos depois (evita ficar sem pacote no Drive).
		$up_zip = self::upload_file( $token, $zip_path, $plugin_folder );
		@unlink( $zip_path );
		if ( is_wp_error( $up_zip ) ) {
			return $up_zip;
		}

		$up_readme = self::upload_file( $token, $readme_src, $plugin_folder );
		if ( is_wp_error( $up_readme ) ) {
			// ZIP novo já está no Drive; mantém o que houver e sinaliza o LEIA-ME.
			return $up_readme;
		}

		$keep_ids = array(
			(string) $up_zip    => true,
			(string) $up_readme => true,
		);

		$list = self::http_json(
			'https://www.googleapis.com/drive/v3/files?' . http_build_query(
				array(
					'q'        => sprintf( "'%s' in parents and trashed=false", str_replace( "'", "\\'", $plugin_folder ) ),
					'fields'   => 'files(id,name)',
					'pageSize' => 50,
				)
			),
			array( 'headers' => array( 'Authorization: Bearer ' . $token ) )
		);
		if ( ! is_wp_error( $list ) ) {
			foreach ( ( $list['files'] ?? array() ) as $f ) {
				$id = (string) ( $f['id'] ?? '' );
				$n  = (string) ( $f['name'] ?? '' );
				if ( $id === '' || isset( $keep_ids[ $id ] ) ) {
					continue;
				}
				if ( preg_match( '/^ccd-backup-.*\.zip$/i', $n ) || strcasecmp( $n, 'LEIA-ME.md' ) === 0 ) {
					self::trash_file( $token, $id );
				}
			}
		}

		update_option( 'ccd_backup_plugin_drive_fp', $fingerprint, false );
		CCD_Backup_Progress::log(
			'Pacote do plugin atualizado no Drive (pasta plugin/).',
			array(
				'zip'    => $zip_name,
				'readme' => 'LEIA-ME.md',
				'force'  => $force ? 1 : 0,
			)
		);
		return true;
	}

	/**
	 * @param string $file
	 * @return true|WP_Error
	 */
	public static function upload_and_finalize( $file ) {
		if ( ! is_readable( $file ) ) {
			return new WP_Error( 'ccd_backup_missing', 'Arquivo de backup não encontrado.' );
		}

		$settings = CCD_Backup_Settings::get();
		$token    = self::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$folder = self::ensure_folder( $token );
		if ( is_wp_error( $folder ) ) {
			return $folder;
		}

		$base  = basename( $file );
		$size  = (int) filesize( $file );
		$mtime = (int) filemtime( $file );

		$remote_id = self::find_remote_backup( $token, $folder, $base, $size );
		if ( $remote_id !== '' ) {
			self::mark_synced( $base, $remote_id, $size, $mtime );
			CCD_Backup_Progress::log( 'Backup já estava no Drive; marcado como sincronizado.', array( 'file' => $base ) );
			$plugin_sync = self::sync_plugin_bundle( $token, $folder, true );
			if ( is_wp_error( $plugin_sync ) ) {
				CCD_Backup_Progress::log( 'Pacote do plugin no Drive: ' . $plugin_sync->get_error_message() );
			}
			if ( ! $settings['keep_local'] ) {
				@unlink( $file );
				self::unmark_synced( $base );
			}
			return true;
		}

		CCD_Backup_Progress::log( 'Iniciando upload para o Google Drive.', array( 'file' => $base ) );
		$result = self::upload_file( $token, $file, $folder );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::mark_synced( $base, $result, $size, $mtime );
		CCD_Backup_Progress::log( 'Upload concluído.', array( 'file' => $base, 'id' => $result ) );

		$rotation = self::rotate_backups( $token, $folder, (int) $settings['retention_count'] );
		if ( is_wp_error( $rotation ) ) {
			CCD_Backup_Progress::log( 'Rotação no Drive falhou: ' . $rotation->get_error_message() );
		}

		$plugin_sync = self::sync_plugin_bundle( $token, $folder, true );
		if ( is_wp_error( $plugin_sync ) ) {
			CCD_Backup_Progress::log( 'Pacote do plugin no Drive: ' . $plugin_sync->get_error_message() );
		}

		if ( ! $settings['keep_local'] ) {
			@unlink( $file );
			self::unmark_synced( $base );
			CCD_Backup_Progress::log( 'Cópia local removida após o upload.', array( 'file' => $base ) );
		}

		return true;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function sync_pending_backups() {
		$settings = CCD_Backup_Settings::get();
		if ( ! $settings['enable'] || ! CCD_Backup_Settings::is_connected() ) {
			return true;
		}
		if ( get_transient( 'ccd_backup_sync_lock' ) ) {
			CCD_Backup_Progress::log( 'Sincronização adiada: upload já em andamento.' );
			return true;
		}

		$pending = self::collect_pending_backups();
		if ( empty( $pending ) ) {
			$token = self::access_token();
			if ( ! is_wp_error( $token ) ) {
				$folder = self::ensure_folder( $token );
				if ( ! is_wp_error( $folder ) ) {
					$plugin_sync = self::sync_plugin_bundle( $token, $folder );
					if ( is_wp_error( $plugin_sync ) ) {
						CCD_Backup_Progress::log( 'Pacote do plugin no Drive: ' . $plugin_sync->get_error_message() );
					}
				}
			}
			return true;
		}

		set_transient( 'ccd_backup_sync_lock', 1, 3 * HOUR_IN_SECONDS );
		CCD_Backup_Progress::set_live(
			array(
				'phase'   => 'uploading',
				'message' => 'Sincronizando backups pendentes com o Google Drive…',
			)
		);
		CCD_Backup_Progress::log(
			'Sincronização pendente iniciada.',
			array( 'count' => count( $pending ) )
		);

		$errors = 0;
		foreach ( $pending as $file ) {
			if ( self::local_is_synced( $file ) ) {
				continue;
			}
			$result = self::upload_and_finalize( $file );
			if ( is_wp_error( $result ) ) {
				++$errors;
				CCD_Backup_Progress::log( $result->get_error_message(), $result->get_error_data() ?: array() );
			}
		}

		delete_transient( 'ccd_backup_sync_lock' );

		$still = self::collect_pending_backups();
		if ( empty( $still ) && $errors === 0 ) {
			CCD_Backup_Progress::set_live(
				array(
					'phase'   => 'idle',
					'message' => '',
					'percent' => null,
				)
			);
		} elseif ( ! empty( $still ) ) {
			CCD_Backup_Progress::set_live(
				array(
					'phase'   => 'uploading',
					'message' => count( $still ) . ' backup(s) ainda pendente(s) de sync.',
				)
			);
		}

		return $errors > 0 ? new WP_Error( 'ccd_backup_sync', 'Falha ao sincronizar um ou mais backups.' ) : true;
	}

	/**
	 * @param string $file Absolute path to finished backup.
	 */
	public static function on_export_done( $file ) {
		$settings = CCD_Backup_Settings::get();
		if ( ! $settings['enable'] || ! CCD_Backup_Settings::is_connected() ) {
			CCD_Backup_Progress::log( 'Export concluído, mas o Google Drive não está vinculado.' );
			return;
		}

		CCD_Backup_Progress::set_live(
			array(
				'phase'   => 'uploading',
				'percent' => 100,
				'message' => 'Export concluído. Preparando envio ao Google Drive…',
			)
		);

		$result = self::upload_and_finalize( $file );
		if ( is_wp_error( $result ) ) {
			CCD_Backup_Progress::log( $result->get_error_message(), $result->get_error_data() ?: array() );
			CCD_Backup_Progress::set_live(
				array(
					'phase'   => 'error',
					'message' => $result->get_error_message(),
				)
			);
			wp_schedule_single_event( time() + 60, CCD_BACKUP_SYNC_CRON );
			return;
		}

		self::sync_pending_backups();
	}
}
