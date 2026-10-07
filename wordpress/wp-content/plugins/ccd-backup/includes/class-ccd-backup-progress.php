<?php
/**
 * Live progress and status log.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CCD_Backup_Progress {

	/**
	 * @return array<string,mixed>
	 */
	public static function default_live() {
		return array(
			'phase'       => 'idle',
			'started'     => 0,
			'percent'     => null,
			'message'     => '',
			'detail'      => '',
			'file'        => '',
			'bytes_total' => 0,
			'bytes_done'  => 0,
			'job_type'    => '',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get_live() {
		$raw = get_option( CCD_BACKUP_LIVE, array() );
		if ( ! is_array( $raw ) ) {
			return self::default_live();
		}
		return array_merge( self::default_live(), $raw );
	}

	/**
	 * @param array<string,mixed> $patch
	 * @return array<string,mixed>
	 */
	public static function set_live( array $patch ) {
		$current = self::get_live();
		$merged  = array_merge( $current, $patch );
		update_option( CCD_BACKUP_LIVE, $merged, false );
		return $merged;
	}

	public static function reset_live() {
		self::set_live( self::default_live() );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_log() {
		$log = get_option( CCD_BACKUP_STATUS_LOG, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * @param string              $message
	 * @param array<string,mixed> $context
	 */
	public static function log( $message, array $context = array() ) {
		$entry = array(
			'time'    => time(),
			'message' => (string) $message,
			'context' => $context,
		);

		$log   = self::get_log();
		$log[] = $entry;
		if ( count( $log ) > CCD_BACKUP_STATUS_MAX ) {
			$log = array_slice( $log, -1 * CCD_BACKUP_STATUS_MAX );
		}
		update_option( CCD_BACKUP_STATUS_LOG, $log, false );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get_job() {
		$job = get_option( CCD_BACKUP_JOB, null );
		return is_array( $job ) ? $job : null;
	}

	/**
	 * @param array<string,mixed> $job
	 */
	public static function set_job( array $job ) {
		update_option( CCD_BACKUP_JOB, $job, false );
	}

	public static function clear_job() {
		delete_option( CCD_BACKUP_JOB );
	}

	/**
	 * @param string $phase
	 * @param string $message
	 * @param int|null $percent
	 */
	public static function update_from_job( $phase, $message, $percent = null ) {
		$job = self::get_job();
		$patch = array(
			'phase'   => $phase,
			'message' => $message,
		);
		if ( $percent !== null ) {
			$patch['percent'] = max( 0, min( 100, (int) $percent ) );
		}
		if ( is_array( $job ) ) {
			if ( ! empty( $job['started'] ) ) {
				$patch['started'] = (int) $job['started'];
			}
			if ( ! empty( $job['type'] ) ) {
				$patch['job_type'] = (string) $job['type'];
			}
			if ( ! empty( $job['basename'] ) ) {
				$patch['file'] = (string) $job['basename'];
			}
			if ( isset( $job['bytes_done'] ) ) {
				$patch['bytes_done'] = (int) $job['bytes_done'];
			}
			if ( isset( $job['bytes_total'] ) ) {
				$patch['bytes_total'] = (int) $job['bytes_total'];
			}
		}
		self::set_live( $patch );
	}

	/**
	 * UI-facing phase (exporting|importing|uploading|done|error|idle).
	 * Job stores internal steps (init|database|files|…) — do not expose those as phase.
	 *
	 * @return array<string,mixed>
	 */
	public static function payload() {
		$live      = self::get_live();
		$job       = self::get_job();
		$uploading = (bool) get_transient( 'ccd_backup_sync_lock' );
		$started   = (int) ( $live['started'] ?? 0 );
		$phase     = (string) ( $live['phase'] ?? 'idle' );
		$job_phase = is_array( $job ) ? (string) ( $job['phase'] ?? '' ) : '';
		$job_type  = is_array( $job ) ? (string) ( $job['type'] ?? '' ) : '';

		if ( is_array( $job ) ) {
			if ( ! empty( $job['started'] ) ) {
				$started = (int) $job['started'];
			}
			if ( ! empty( $job['message'] ) ) {
				$live['message'] = (string) $job['message'];
			}
			if ( isset( $job['percent'] ) ) {
				$live['percent'] = (int) $job['percent'];
			}
			if ( isset( $job['bytes_done'] ) ) {
				$live['bytes_done'] = (int) $job['bytes_done'];
			}
			if ( isset( $job['bytes_total'] ) ) {
				$live['bytes_total'] = (int) $job['bytes_total'];
			}
			if ( ! empty( $job['basename'] ) ) {
				$live['file'] = (string) $job['basename'];
			}

			if ( $job_phase === 'error' ) {
				$phase = 'error';
			} elseif ( $job_phase === 'done' ) {
				$phase = $uploading ? 'uploading' : 'done';
			} elseif ( $job_phase !== '' ) {
				$phase = ( $job_type === 'import' ) ? 'importing' : 'exporting';
			}
		}

		if ( $uploading && $phase !== 'error' ) {
			$phase = 'uploading';
		}

		$elapsed = $started > 0 ? max( 0, time() - $started ) : 0;
		$active  = in_array( $phase, array( 'exporting', 'importing', 'uploading' ), true );

		$pending = array();
		if ( class_exists( 'CCD_Backup_Drive' ) ) {
			$pending = array_map( 'basename', CCD_Backup_Drive::collect_pending_backups() );
		}

		$safe_job = null;
		if ( is_array( $job ) ) {
			$safe_job = array(
				'type'    => $job_type,
				'phase'   => $job_phase,
				'message' => (string) ( $job['message'] ?? '' ),
				'percent' => isset( $job['percent'] ) ? (int) $job['percent'] : null,
				'file'    => (string) ( $job['basename'] ?? '' ),
			);
		}

		return array(
			'phase'         => $phase,
			'job_phase'     => $job_phase,
			'active'        => $active,
			'busy'          => $active || is_array( $job ),
			'percent'       => isset( $live['percent'] ) ? $live['percent'] : null,
			'message'       => (string) ( $live['message'] ?? '' ),
			'detail'        => (string) ( $live['detail'] ?? '' ),
			'file'          => (string) ( $live['file'] ?? '' ),
			'bytes_done'    => (int) ( $live['bytes_done'] ?? 0 ),
			'bytes_total'   => (int) ( $live['bytes_total'] ?? 0 ),
			'bytes_label'   => CCD_Backup_Paths::format_bytes_pair( (int) ( $live['bytes_done'] ?? 0 ), (int) ( $live['bytes_total'] ?? 0 ) ),
			'elapsed'       => $elapsed,
			'elapsed_label' => CCD_Backup_Paths::format_elapsed( $elapsed ),
			'uploading'     => $uploading,
			'pending'       => $pending,
			'log'           => self::get_log(),
			'job'           => $safe_job,
		);
	}
}
