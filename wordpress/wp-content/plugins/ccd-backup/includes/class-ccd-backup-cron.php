<?php
/**
 * Scheduled backup and Drive sync crons.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CCD_Backup_Cron {

	public static function register_schedules( $schedules ) {
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

		$opt = get_option( CCD_BACKUP_OPTION, array() );
		$m   = CCD_Backup_Settings::clamp_sync_interval_minutes( is_array( $opt ) ? ( $opt['sync_interval_minutes'] ?? 15 ) : 15 );
		$key = CCD_Backup_Settings::sync_cron_schedule_key( $m );
		$schedules[ $key ] = array(
			'interval' => $m * MINUTE_IN_SECONDS,
			'display'  => sprintf( 'A cada %d minuto(s) (sync Drive)', $m ),
		);

		return $schedules;
	}

	public static function reschedule_backup_cron() {
		$hook = CCD_BACKUP_CRON_HOOK;
		wp_clear_scheduled_hook( $hook );

		$settings = CCD_Backup_Settings::get();
		if ( ! $settings['enable'] || ! $settings['schedule_enable'] ) {
			return;
		}

		$interval = $settings['schedule_interval'];
		if ( ! array_key_exists( $interval, CCD_Backup_Settings::schedule_intervals() ) ) {
			$interval = 'daily';
		}

		wp_schedule_event( CCD_Backup_Settings::schedule_first_run(), $interval, $hook );
		self::reschedule_sync_cron();
	}

	public static function reschedule_sync_cron() {
		$hook = CCD_BACKUP_SYNC_CRON;
		wp_clear_scheduled_hook( $hook );

		$settings = CCD_Backup_Settings::get();
		if ( ! $settings['enable'] || ! CCD_Backup_Settings::is_connected() ) {
			return;
		}

		$minutes  = (int) $settings['sync_interval_minutes'];
		$schedule = CCD_Backup_Settings::sync_cron_schedule_key( $minutes );
		$delay    = min( 2 * MINUTE_IN_SECONDS, $minutes * MINUTE_IN_SECONDS );
		wp_schedule_event( time() + $delay, $schedule, $hook );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function run_scheduled_backup() {
		$settings = CCD_Backup_Settings::get();
		if ( ! $settings['enable'] ) {
			CCD_Backup_Progress::log( 'Agendamento cancelado: backup desabilitado.' );
			return new WP_Error( 'ccd_backup_disabled', 'Backup desabilitado.' );
		}

		if ( CCD_Backup_Progress::get_job() || get_transient( 'ccd_backup_export_lock' ) ) {
			CCD_Backup_Progress::log( 'Backup agendado ignorado: já há um job em andamento.' );
			return new WP_Error( 'ccd_backup_busy', 'Já há um backup em andamento.' );
		}

		CCD_Backup_Progress::log( 'Agendamento: iniciando export do backup.' );
		return CCD_Backup_Export::start( false );
	}

	public static function maybe_align_sync_cron() {
		$settings = CCD_Backup_Settings::get();
		if ( ! $settings['enable'] || ! CCD_Backup_Settings::is_connected() ) {
			return;
		}
		$want  = CCD_Backup_Settings::sync_cron_schedule_key( (int) $settings['sync_interval_minutes'] );
		$event = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( CCD_BACKUP_SYNC_CRON ) : false;
		if ( ! $event || ( isset( $event->schedule ) && $event->schedule !== $want ) ) {
			self::reschedule_sync_cron();
		}
	}
}
