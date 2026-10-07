<?php
/**
 * Native chunked export into .ccdbackup (ZipArchive).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CCD_Backup_Export {

	const ROWS_PER_TICK   = 500;
	const FILES_PER_TICK  = 40;
	const SQL_HEADER      = "-- CCD Backup SQL dump\n";

	/**
	 * @return true|WP_Error
	 */
	public static function start( $manual = true ) {
		if ( CCD_Backup_Progress::get_job() ) {
			return new WP_Error( 'ccd_backup_busy', 'Já há um backup ou restore em andamento.' );
		}
		if ( get_transient( 'ccd_backup_export_lock' ) ) {
			return new WP_Error( 'ccd_backup_busy', 'Já há um export em andamento.' );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'ccd_backup_zip', 'ZipArchive não está disponível.' );
		}

		CCD_Backup_Paths::ensure_temp_dir();
		CCD_Backup_Paths::ensure_backup_dir();

		$basename   = CCD_Backup_Paths::generate_backup_basename();
		$work_id    = wp_generate_password( 12, false, false );
		$zip_path   = CCD_Backup_Paths::temp_dir() . '/export-' . $work_id . '.zip';
		$sql_path   = CCD_Backup_Paths::temp_dir() . '/export-' . $work_id . '.sql';
		$tables     = CCD_Backup_Paths::list_database_tables();
		$file_paths = CCD_Backup_Paths::collect_content_paths( CCD_Backup_Settings::include_flags() );
		$bytes_total = 0;
		foreach ( $file_paths as $rel ) {
			$full = WP_CONTENT_DIR . '/' . $rel;
			if ( is_file( $full ) ) {
				$bytes_total += (int) filesize( $full );
			}
		}

		$job = array(
			'type'          => 'export',
			'phase'         => 'init',
			'started'       => time(),
			'manual'        => (bool) $manual,
			'basename'      => $basename,
			'work_id'       => $work_id,
			'zip_path'      => $zip_path,
			'sql_path'      => $sql_path,
			'final_path'    => CCD_Backup_Paths::backup_dir() . '/' . $basename,
			'tables'        => $tables,
			'table_index'   => 0,
			'row_offset'    => 0,
			'files'         => $file_paths,
			'file_index'    => 0,
			'bytes_total'   => $bytes_total,
			'bytes_done'    => 0,
			'percent'       => 0,
			'message'       => 'Preparando export…',
			'tick_secret'   => wp_generate_password( 32, false, false ),
			'sql_started'   => false,
		);

		set_transient( 'ccd_backup_export_lock', 1, 2 * HOUR_IN_SECONDS );
		CCD_Backup_Progress::set_job( $job );
		CCD_Backup_Progress::set_live(
			array(
				'phase'       => 'exporting',
				'started'     => time(),
				'percent'     => 0,
				'message'     => 'Iniciando export nativo…',
				'file'        => $basename,
				'bytes_total' => $bytes_total,
				'bytes_done'  => 0,
				'job_type'    => 'export',
			)
		);
		CCD_Backup_Progress::log( 'Export iniciado.', array( 'file' => $basename ) );

		self::kick_async( $job['tick_secret'] );
		return true;
	}

	/**
	 * @param string|null $secret
	 */
	public static function kick_async( $secret = null ) {
		$job = CCD_Backup_Progress::get_job();
		if ( $secret === null && is_array( $job ) ) {
			$secret = $job['tick_secret'] ?? '';
		}
		if ( $secret === '' ) {
			return false;
		}

		$url = CCD_Backup_Paths::fix_loopback_url( admin_url( 'admin-ajax.php' ) );
		$body = http_build_query(
			array(
				'action'      => 'ccd_backup_tick',
				'tick_secret' => $secret,
			)
		);

		if ( ! function_exists( 'curl_init' ) ) {
			return false;
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
		curl_exec( $ch );
		curl_close( $ch );
		return true;
	}

	/**
	 * @param string|null $secret
	 * @return array{done:bool,continue:bool}|WP_Error
	 */
	public static function tick( $secret = null ) {
		$job = CCD_Backup_Progress::get_job();
		if ( ! is_array( $job ) || ( $job['type'] ?? '' ) !== 'export' ) {
			return array( 'done' => true, 'continue' => false );
		}
		if ( $secret !== null && ( ! isset( $job['tick_secret'] ) || ! hash_equals( (string) $job['tick_secret'], (string) $secret ) ) ) {
			return new WP_Error( 'ccd_backup_forbidden', 'Tick secret inválido.' );
		}

		if ( get_transient( 'ccd_backup_tick_running' ) ) {
			return array( 'done' => false, 'continue' => true, 'busy' => true, 'phase' => $job['phase'] ?? '' );
		}
		set_transient( 'ccd_backup_tick_running', time(), 45 );

		try {
			switch ( $job['phase'] ) {
				case 'init':
					$result = self::phase_init( $job );
					break;
				case 'database':
					$result = self::phase_database( $job );
					break;
				case 'files':
					$result = self::phase_files( $job );
					break;
				case 'finalize':
					$result = self::phase_finalize( $job );
					break;
				default:
					return array( 'done' => true, 'continue' => false );
			}

			if ( is_wp_error( $result ) ) {
				self::fail( $job, $result->get_error_message() );
				return $result;
			}

			$job = CCD_Backup_Progress::get_job();
			if ( ! is_array( $job ) ) {
				return array( 'done' => true, 'continue' => false );
			}

			$done = in_array( $job['phase'], array( 'done', 'error' ), true );
			// Only chain loopback when this tick came from the async worker (secret set).
			if ( ! $done && $secret !== null ) {
				self::kick_async( $job['tick_secret'] ?? null );
			}

			return array(
				'done'     => $done,
				'continue' => ! $done,
				'phase'    => $job['phase'] ?? '',
			);
		} catch ( Exception $e ) {
			self::fail( $job, $e->getMessage() );
			return new WP_Error( 'ccd_backup_export', $e->getMessage() );
		} finally {
			delete_transient( 'ccd_backup_tick_running' );
		}
	}

	/**
	 * @param array<string,mixed> $job
	 * @return true|WP_Error
	 */
	private static function phase_init( array $job ) {
		$manifest = array(
			'format'         => CCD_BACKUP_FORMAT,
			'format_version' => CCD_BACKUP_FORMAT_VERSION,
			'plugin_version' => CCD_BACKUP_VERSION,
			'site_url'       => home_url(),
			'wp_version'     => get_bloginfo( 'version' ),
			'created_gmt'    => gmdate( 'c' ),
			'includes'       => CCD_Backup_Settings::include_flags(),
			'tables'         => $job['tables'],
			'files'          => $job['files'],
		);

		$zip = new ZipArchive();
		if ( true !== $zip->open( $job['zip_path'], ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'ccd_backup_zip', 'Não foi possível criar o arquivo ZIP.' );
		}
		$zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		$zip->close();

		if ( ! self::append_sql( $job['sql_path'], self::SQL_HEADER, false ) ) {
			return new WP_Error( 'ccd_backup_sql', 'Não foi possível iniciar o dump SQL.' );
		}

		$job['phase']    = 'database';
		$job['message']  = 'Exportando banco de dados…';
		$job['percent']  = 5;
		CCD_Backup_Progress::set_job( $job );
		CCD_Backup_Progress::update_from_job( 'exporting', $job['message'], $job['percent'] );
		return true;
	}

	/**
	 * @param array<string,mixed> $job
	 * @return true|WP_Error
	 */
	private static function phase_database( array $job ) {
		global $wpdb;

		$tables = $job['tables'];
		$total  = max( 1, count( $tables ) );
		$index  = (int) $job['table_index'];
		$offset = (int) $job['row_offset'];

		if ( $index >= count( $tables ) ) {
			$zip = new ZipArchive();
			if ( true !== $zip->open( $job['zip_path'] ) ) {
				return new WP_Error( 'ccd_backup_zip', 'Não foi possível abrir o ZIP.' );
			}
			$zip->addFile( $job['sql_path'], 'database.sql' );
			$zip->close();

			$job['phase']   = 'files';
			$job['message'] = 'Exportando arquivos…';
			$job['percent'] = 35;
			CCD_Backup_Progress::set_job( $job );
			CCD_Backup_Progress::update_from_job( 'exporting', $job['message'], $job['percent'] );
			return true;
		}

		$table = $tables[ $index ];
		if ( ! self::is_valid_table_name( $table ) ) {
			$job['table_index'] = $index + 1;
			$job['row_offset']  = 0;
			CCD_Backup_Progress::set_job( $job );
			return true;
		}

		if ( $offset === 0 ) {
			$create = $wpdb->get_row( 'SHOW CREATE TABLE `' . esc_sql( $table ) . '`', ARRAY_N );
			if ( is_array( $create ) && ! empty( $create[1] ) ) {
				$chunk  = "\n-- Table: {$table}\nDROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n\n";
				if ( ! self::append_sql( $job['sql_path'], $chunk, true ) ) {
					return new WP_Error( 'ccd_backup_sql', 'Falha ao escrever estrutura da tabela.' );
				}
			}
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $table ) . '` LIMIT %d OFFSET %d',
				self::ROWS_PER_TICK,
				$offset
			),
			ARRAY_A
		);

		if ( ! empty( $rows ) ) {
			$columns = array_keys( $rows[0] );
			$col_sql = '`' . implode( '`,`', array_map( 'esc_sql', $columns ) ) . '`';
			$buffer  = '';
			foreach ( $rows as $row ) {
				$values = array();
				foreach ( $columns as $col ) {
					$values[] = self::sql_value( $row[ $col ] ?? null );
				}
				$buffer .= 'INSERT INTO `' . esc_sql( $table ) . '` (' . $col_sql . ') VALUES (' . implode( ',', $values ) . ");\n";
			}
			if ( ! self::append_sql( $job['sql_path'], $buffer, true ) ) {
				return new WP_Error( 'ccd_backup_sql', 'Falha ao escrever dados da tabela.' );
			}
		}

		if ( count( $rows ) < self::ROWS_PER_TICK ) {
			$job['table_index'] = $index + 1;
			$job['row_offset']  = 0;
		} else {
			$job['row_offset'] = $offset + self::ROWS_PER_TICK;
		}

		$table_progress = ( $job['table_index'] + ( $job['row_offset'] > 0 ? 0.5 : 0 ) ) / $total;
		$job['percent'] = (int) min( 34, max( 5, round( 5 + ( $table_progress * 29 ) ) ) );
		$job['message'] = sprintf( 'Exportando tabela %s (%d/%d)…', $table, min( $job['table_index'] + 1, $total ), $total );
		CCD_Backup_Progress::set_job( $job );
		CCD_Backup_Progress::update_from_job( 'exporting', $job['message'], $job['percent'] );
		return true;
	}

	/**
	 * @param array<string,mixed> $job
	 * @return true|WP_Error
	 */
	private static function phase_files( array $job ) {
		$files  = $job['files'];
		$total  = max( 1, count( $files ) );
		$index  = (int) $job['file_index'];
		$zip    = new ZipArchive();
		if ( true !== $zip->open( $job['zip_path'] ) ) {
			return new WP_Error( 'ccd_backup_zip', 'Não foi possível abrir o ZIP.' );
		}

		$added = 0;
		while ( $index < count( $files ) && $added < self::FILES_PER_TICK ) {
			$rel  = $files[ $index ];
			$full = WP_CONTENT_DIR . '/' . $rel;
			if ( is_file( $full ) && is_readable( $full ) ) {
				$zip->addFile( $full, 'files/' . $rel );
				$job['bytes_done'] = (int) $job['bytes_done'] + (int) filesize( $full );
			}
			++$index;
			++$added;
		}
		$zip->close();

		$job['file_index'] = $index;
		if ( $index >= count( $files ) ) {
			$job['phase']   = 'finalize';
			$job['message'] = 'Finalizando backup…';
			$job['percent'] = 95;
		} else {
			$file_progress  = $index / $total;
			$job['percent'] = (int) min( 94, max( 35, round( 35 + ( $file_progress * 59 ) ) ) );
			$job['message'] = sprintf( 'Exportando arquivos (%d/%d)…', $index, $total );
		}

		CCD_Backup_Progress::set_job( $job );
		CCD_Backup_Progress::update_from_job( 'exporting', $job['message'], $job['percent'] );
		return true;
	}

	/**
	 * @param array<string,mixed> $job
	 * @return true|WP_Error
	 */
	private static function phase_finalize( array $job ) {
		if ( ! @rename( $job['zip_path'], $job['final_path'] ) ) {
			if ( ! @copy( $job['zip_path'], $job['final_path'] ) ) {
				return new WP_Error( 'ccd_backup_finalize', 'Não foi possível mover o backup final.' );
			}
			@unlink( $job['zip_path'] );
		}
		@unlink( $job['sql_path'] );

		$job['phase']   = 'done';
		$job['percent'] = 100;
		$job['message'] = 'Export concluído.';
		CCD_Backup_Progress::set_job( $job );
		CCD_Backup_Progress::set_live(
			array(
				'phase'       => 'exporting',
				'percent'     => 100,
				'message'     => 'Export concluído.',
				'file'        => $job['basename'],
				'bytes_done'  => (int) $job['bytes_done'],
				'bytes_total' => (int) $job['bytes_total'],
			)
		);
		CCD_Backup_Progress::log( 'Export concluído.', array( 'file' => $job['basename'] ) );

		delete_transient( 'ccd_backup_export_lock' );
		CCD_Backup_Progress::clear_job();

		CCD_Backup_Drive::on_export_done( $job['final_path'] );
		return true;
	}

	/**
	 * @param array<string,mixed> $job
	 */
	private static function fail( array $job, $message ) {
		$job['phase']   = 'error';
		$job['message'] = (string) $message;
		CCD_Backup_Progress::set_job( $job );
		CCD_Backup_Progress::set_live(
			array(
				'phase'   => 'error',
				'message' => (string) $message,
			)
		);
		CCD_Backup_Progress::log( 'Export falhou: ' . $message );
		delete_transient( 'ccd_backup_export_lock' );

		if ( ! empty( $job['zip_path'] ) && file_exists( $job['zip_path'] ) ) {
			@unlink( $job['zip_path'] );
		}
		if ( ! empty( $job['sql_path'] ) && file_exists( $job['sql_path'] ) ) {
			@unlink( $job['sql_path'] );
		}
	}

	/**
	 * @param mixed $value
	 */
	private static function sql_value( $value ) {
		if ( $value === null ) {
			return 'NULL';
		}
		global $wpdb;
		$link = null;
		if ( isset( $wpdb->dbh ) && $wpdb->dbh instanceof mysqli ) {
			$link = $wpdb->dbh;
		} elseif ( defined( 'DB_HOST' ) && function_exists( 'mysqli_init' ) ) {
			static $fallback = null;
			if ( $fallback === null ) {
				$host = DB_HOST;
				$port = null;
				if ( str_contains( $host, ':' ) ) {
					list( $host, $port ) = explode( ':', $host, 2 );
				}
				$fallback = @mysqli_connect( $host, DB_USER, DB_PASSWORD, DB_NAME, $port ? (int) $port : ini_get( 'mysqli.default_port' ) );
			}
			$link = $fallback;
		}
		if ( $link instanceof mysqli ) {
			return "'" . mysqli_real_escape_string( $link, (string) $value ) . "'";
		}
		return "'" . esc_sql( (string) $value ) . "'";
	}

	/**
	 * @param string $path
	 * @param string $chunk
	 * @param bool   $append
	 */
	private static function append_sql( $path, $chunk, $append ) {
		$flags = $append ? FILE_APPEND : 0;
		return false !== file_put_contents( $path, $chunk, $flags );
	}

	/**
	 * @param string $table
	 */
	private static function is_valid_table_name( $table ) {
		return (bool) preg_match( '/^[A-Za-z0-9_]+$/', (string) $table );
	}
}
