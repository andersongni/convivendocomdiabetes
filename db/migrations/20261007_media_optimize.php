<?php
/**
 * Migration: ativa política CCD de compressão de mídia e limpa Smush legado.
 *
 *   php db/migrations/20261007_media_optimize.php
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

require_once WP_CONTENT_DIR . '/mu-plugins/ccd-media-optimize.php';

$removed = ccd_media_optimize_purge_legacy_options();
update_option( 'ccd_media_optimize_version', CCD_MEDIA_OPTIMIZE_VERSION, false );

echo 'OK: version=' . CCD_MEDIA_OPTIMIZE_VERSION . "\n";
echo 'enabled=' . ( ccd_media_optimize_enabled() ? 'yes' : 'no' ) . "\n";
echo 'webp=' . ( ccd_media_optimize_webp_enabled() ? 'yes' : 'no' ) . "\n";
echo 'quality=' . CCD_MEDIA_OPTIMIZE_QUALITY . "\n";
echo 'max_edge=' . CCD_MEDIA_OPTIMIZE_MAX_EDGE . "\n";
echo 'legacy_options_removed=' . (int) $removed . "\n";
