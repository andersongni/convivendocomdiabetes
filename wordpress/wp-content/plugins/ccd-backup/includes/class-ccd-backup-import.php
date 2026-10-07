<?php
/**
 * Native chunked import from .ccdbackup (ZipArchive).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CCD_Backup_Import {

	const SQL_BYTES_PER_TICK = 512000;
	const FILES_PER_TICK     = 40;

	/**
	 * @param string $source Absolute path to uploaded or local .ccdbackup
	 * @return true|WP_Error
	 */
	public static function start( $source ) {
		if ( CCD_Backup_Progress::get_job() ) {
			return new WP_Error( 'ccd_backup_busy', 'Já há um backup ou restore em andamento.' );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'ccd_backup_zip', 'ZipArchive não está disponível.' );
		}
		if ( ! is_readable( $source ) ) {
			return new WP_Error( 'ccd_backup_missing', 'Arquivo de backup não encontrado.' );
		}

		CCD_Backup_Paths::ensure_temp_dir();
		$work_id   = wp_generate_password( 12, false, false );
		$extract   = CCD_Backup_Paths::temp_dir() . '/import-' . $work_id;
		wp_mkdir_p( $extract );

		$zip = new ZipArchive();
		if ( true !== $zip->open( $source ) ) {
			return new WP_Error( 'ccd_backup_zip', 'Não foi possível abrir o backup.' );
		}

		$manifest_raw = $zip->getFromName( 'manifest.json' );
		if ( ! is_string( $manifest_raw ) || $manifest_raw === '' ) {
			$zip->close();
			return new WP_Error( 'ccd_backup_manifest', 'Manifest ausente no backup.' );
		}
		$manifest = json_decode( $manifest_raw, true );
		if ( ! is_array( $manifest ) ) {
			$zip->close();
			return new WP_Error( 'ccd_backup_manifest', 'Manifest inválido.' );
		}
		if ( ( $manifest['format'] ?? '' ) !== CCD_BACKUP_FORMAT ) {
			$zip->close();
			return new WP_Error( 'ccd_backup_format', 'Formato de backup não suportado.' );
		}

		$sql_path = $extract . '/database.sql';
		$stream   = $zip->getStream( 'database.sql' );
		if ( ! $stream ) {
			$zip->close();
			return new WP_Error( 'ccd_backup_sql', 'Dump SQL ausente no backup.' );
		}
		$out = fopen( $sql_path, 'wb' );
		if ( ! $out ) {
			fclose( $stream );
			$zip->close();
			return new WP_Error( 'ccd_backup_sql', 'Não foi possível extrair o SQL.' );
		}
		while ( ! feof( $stream ) ) {
			$chunk = fread( $stream, 1024 * 1024 );
			if ( $chunk === false ) {
				break;
			}
			fwrite( $out, $chunk );
		}
		fclose( $out );
		fclose( $stream );
		$zip->close();

		$file_list = array();
		if ( ! empty( $manifest['files'] ) && is_array( $manifest['files'] ) ) {
			foreach ( $manifest['files'] as $rel ) {
				$rel = str_replace( '\\', '/', ltrim( (string) $rel, '/' ) );
				if ( $rel !== '' ) {
					$file_list[] = $rel;
				}
			}
		}

		$sql_size = (int) filesize( $sql_path );
		$job = array(
			'type'          => 'import',
			'phase'         => 'database',
			'started'       => time(),
			'source'        => $source,
			'basename'      => basename( $source ),
			'work_id'       => $work_id,
			'extract_dir'   => $extract,
			'zip_source'    => $source,
			'sql_path'      => $sql_path,
			'sql_offset'    => 0,
			'sql_size'      => $sql_size,
			'files'         => $file_list,
			'file_index'    => 0,
			'bytes_total'   => $sql_size,
			'bytes_done'    => 0,
			'percent'       => 0,
			'message'       => 'Restaurando banco de dados…',
			'tick_secret'   => wp_generate_password( 32, false, false ),
			'manifest'      => $manifest,
			'pending_sql'   => '',
		);

		set_transient( 'ccd_backup_import_lock', 1, 2 * HOUR_IN_SECONDS );
		CCD_Backup_Progress::set_job( $job );
		CCD_Backup_Progress::set_live(
			array(
				'phase'       => 'importing',
				'started'     => time(),
				'percent'     => 0,
				'message'     => 'Iniciando restore…',
				'file'        => basename( $source ),
				'bytes_total' => $sql_size,
				'bytes_done'  => 0,
				'job_type'    => 'import',
			)
		);
		CCD_Backup_Progress::log( 'Import iniciado.', array( 'file' => basename( $source ) ) );

		CCD_Backup_Export::kick_async( $job['tick_secret'] );
		return true;
	}

	/**
	 * @param string|null $secret
	 * @return array{done:bool,continue:bool}|WP_Error
	 */
	public static function tick( $secret = null ) {
		$job = CCD_Backup_Progress::get_job();
		if ( ! is_array( $job ) || ( $job['type'] ?? '' ) !== 'import' ) {
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
			if ( ! $done && $secret !== null ) {
				CCD_Backup_Export::kick_async( $job['tick_secret'] ?? null );
			}

			return array(
				'done'     => $done,
				'continue' => ! $done,
				'phase'    => $job['phase'] ?? '',
			);
		} catch ( Exception $e ) {
			self::fail( $job, $e->getMessage() );
			return new WP_Error( 'ccd_backup_import', $e->getMessage() );
		} finally {
			delete_transient( 'ccd_backup_tick_running' );
		}
	}

	/**
	 * @param array<string,mixed> $job
	 * @return true|WP_Error
	 */
	private static function phase_database( array $job ) {
		global $wpdb;

		$fh = fopen( $job['sql_path'], 'rb' );
		if ( ! $fh ) {
			return new WP_Error( 'ccd_backup_sql', 'Não foi possível abrir o SQL.' );
		}
		fseek( $fh, (int) $job['sql_offset'] );

		$read   = 0;
		$buffer = (string) ( $job['pending_sql'] ?? '' );
		while ( ! feof( $fh ) && $read < self::SQL_BYTES_PER_TICK ) {
			$chunk = fread( $fh, min( 65536, self::SQL_BYTES_PER_TICK - $read ) );
			if ( $chunk === false ) {
				break;
			}
			$read   += strlen( $chunk );
			$buffer .= $chunk;
		}
		$new_offset = ftell( $fh );
		fclose( $fh );

		$statements = self::split_sql_statements( $buffer );
		$pending    = array_pop( $statements );
		if ( $pending === null ) {
			$pending = '';
		}

		foreach ( $statements as $sql ) {
			$sql = trim( $sql );
			if ( $sql === '' || str_starts_with( $sql, '--' ) ) {
				continue;
			}
			$wpdb->query( $sql );
		}

		$job['sql_offset']  = (int) $new_offset;
		$job['pending_sql'] = $pending;
		$job['bytes_done']  = min( (int) $job['sql_size'], (int) $new_offset );
		$job['percent']     = (int) min( 60, max( 1, round( ( $job['bytes_done'] / max( 1, (int) $job['sql_size'] ) ) * 60 ) ) );
		$job['message']     = 'Restaurando banco de dados…';

		if ( $job['sql_offset'] >= (int) $job['sql_size'] && $pending === '' ) {
			$job['phase']   = 'files';
			$job['message'] = 'Restaurando arquivos…';
			$job['percent'] = 60;
		}

		CCD_Backup_Progress::set_job( $job );
		CCD_Backup_Progress::update_from_job( 'importing', $job['message'], $job['percent'] );
		return true;
	}

	/**
	 * @param array<string,mixed> $job
	 * @return true|WP_Error
	 */
	private static function phase_files( array $job ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $job['zip_source'] ) ) {
			return new WP_Error( 'ccd_backup_zip', 'Não foi possível abrir o backup.' );
		}

		$files = $job['files'];
		$total = max( 1, count( $files ) );
		$index = (int) $job['file_index'];
		$added = 0;

		while ( $index < count( $files ) && $added < self::FILES_PER_TICK ) {
			$rel      = $files[ $index ];
			$zip_name = 'files/' . $rel;
			$dest     = WP_CONTENT_DIR . '/' . $rel;
			$dir      = dirname( $dest );
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			$stream = $zip->getStream( $zip_name );
			if ( $stream ) {
				$out = fopen( $dest, 'wb' );
				if ( $out ) {
					while ( ! feof( $stream ) ) {
						$chunk = fread( $stream, 1024 * 1024 );
						if ( $chunk === false ) {
							break;
						}
						fwrite( $out, $chunk );
					}
					fclose( $out );
					$job['bytes_done'] = (int) $job['bytes_done'] + (int) filesize( $dest );
				}
				fclose( $stream );
			}
			++$index;
			++$added;
		}
		$zip->close();

		$job['file_index'] = $index;
		if ( $index >= count( $files ) ) {
			$job['phase']   = 'finalize';
			$job['message'] = 'Finalizando restore…';
			$job['percent'] = 95;
		} else {
			$file_progress  = $index / $total;
			$job['percent'] = (int) min( 94, max( 60, round( 60 + ( $file_progress * 34 ) ) ) );
			$job['message'] = sprintf( 'Restaurando arquivos (%d/%d)…', $index, $total );
		}

		CCD_Backup_Progress::set_job( $job );
		CCD_Backup_Progress::update_from_job( 'importing', $job['message'], $job['percent'] );
		return true;
	}

	/**
	 * @param array<string,mixed> $job
	 * @return true|WP_Error
	 */
	private static function phase_finalize( array $job ) {
		self::cleanup_workdir( $job );

		$job['phase']   = 'done';
		$job['percent'] = 100;
		$job['message'] = 'Restore concluído.';
		CCD_Backup_Progress::set_job( $job );
		CCD_Backup_Progress::set_live(
			array(
				'phase'   => 'importing',
				'percent' => 100,
				'message' => 'Restore concluído.',
				'file'    => $job['basename'],
			)
		);
		CCD_Backup_Progress::log( 'Import concluído.', array( 'file' => $job['basename'] ) );

		delete_transient( 'ccd_backup_import_lock' );
		CCD_Backup_Progress::clear_job();
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
		CCD_Backup_Progress::log( 'Import falhou: ' . $message );
		delete_transient( 'ccd_backup_import_lock' );
		self::cleanup_workdir( $job );
	}

	/**
	 * @param array<string,mixed> $job
	 */
	private static function cleanup_workdir( array $job ) {
		if ( empty( $job['extract_dir'] ) || ! is_dir( $job['extract_dir'] ) ) {
			return;
		}
		self::rrmdir( $job['extract_dir'] );
	}

	/**
	 * @param string $dir
	 */
	private static function rrmdir( $dir ) {
		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				self::rrmdir( $path );
			} else {
				@unlink( $path );
			}
		}
		@rmdir( $dir );
	}

	/**
	 * Split SQL buffer into executable statements, keeping trailing partial statement.
	 *
	 * @param string $buffer
	 * @return string[]
	 */
	private static function split_sql_statements( $buffer ) {
		$parts  = preg_split( '/;\s*\n/', $buffer ) ?: array();
		$result = array();
		foreach ( $parts as $part ) {
			$result[] = $part;
		}
		return $result;
	}
}
