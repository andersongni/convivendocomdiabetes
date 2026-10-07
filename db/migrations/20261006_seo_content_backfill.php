<?php
/**
 * Migration: backfill meta/focus/alts do acervo.
 *
 * Preferido em runtime: mu-plugin ccd-seo-content.php (lotes).
 *
 * Forcar agora:
 *   wp eval-file db/migrations/20261006_seo_content_backfill.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	$candidates = array(
		dirname( __DIR__, 2 ) . '/wordpress/wp-load.php',
		'/var/www/html/wp-load.php',
	);
	foreach ( $candidates as $wp_load ) {
		if ( is_readable( $wp_load ) ) {
			require $wp_load;
			break;
		}
	}
}
if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "wp-load.php not found\n" );
	exit( 1 );
}

require_once WP_CONTENT_DIR . '/mu-plugins/ccd-seo-content.php';

delete_option( 'ccd_seo_content' );
delete_option( 'ccd_seo_content_state' );

$steps = 0;
while ( $steps < 200 ) {
	++$steps;
	if ( ccd_seo_content_backfill_step( 80 ) ) {
		break;
	}
}

$last = get_option( 'ccd_seo_content_last_run' );
echo 'OK: version=' . (string) get_option( 'ccd_seo_content' ) . " steps={$steps}\n";
echo 'last_run=' . wp_json_encode( $last ) . "\n";
