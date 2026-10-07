<?php
/**
 * Migration: aplica favicon (site_icon) a partir do asset versionado.
 *
 *   php db/migrations/20261007_site_icon.php
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

require_once WP_CONTENT_DIR . '/mu-plugins/ccd-site-icon.php';
delete_option( 'ccd_site_icon_version' );
$result = ccd_site_icon_install();
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, $result->get_error_message() . "\n" );
	exit( 1 );
}
update_option( 'ccd_site_icon_version', CCD_SITE_ICON_VERSION, false );
update_option( 'ccd_site_icon_attachment_id', (int) $result, false );

echo 'OK: attachment_id=' . (int) $result . "\n";
echo 'site_icon=' . (int) get_option( 'site_icon' ) . "\n";
echo 'url_32=' . (string) get_site_icon_url( 32 ) . "\n";
echo 'url_192=' . (string) get_site_icon_url( 192 ) . "\n";
