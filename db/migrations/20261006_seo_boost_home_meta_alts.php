<?php
/**
 * Migration: meta description da home + alts INICIAL/LinkedIn.
 *
 * Preferido em runtime: mu-plugin ccd-seo-boost.php.
 *
 *   wp eval-file db/migrations/20261006_seo_boost_home_meta_alts.php
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

require_once WP_CONTENT_DIR . '/mu-plugins/ccd-seo-boost.php';
delete_option( 'ccd_seo_boost' );
ccd_seo_boost_apply();

$home_id = (int) get_option( 'page_on_front' );
echo 'OK: ccd_seo_boost=' . (string) get_option( 'ccd_seo_boost' ) . "\n";
echo 'metadesc=' . (string) get_post_meta( $home_id, '_yoast_wpseo_metadesc', true ) . "\n";
echo 'alt2726=' . (string) get_post_meta( 2726, '_wp_attachment_image_alt', true ) . "\n";
echo 'alt2937=' . (string) get_post_meta( 2937, '_wp_attachment_image_alt', true ) . "\n";
