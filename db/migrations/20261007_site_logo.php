<?php
/**
 * Migration: aplica logo do site (custom_logo) a partir do asset versionado.
 *
 *   php db/migrations/20261007_site_logo.php
 *   # ou no container:
 *   php /var/www/html/wp-content/mu-plugins/../..  (use eval-file via script abaixo)
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

require_once WP_CONTENT_DIR . '/mu-plugins/ccd-site-logo.php';
delete_option( 'ccd_site_logo_version' );
$result = ccd_site_logo_install();
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, $result->get_error_message() . "\n" );
	exit( 1 );
}
update_option( 'ccd_site_logo_version', CCD_SITE_LOGO_VERSION, false );
update_option( 'ccd_site_logo_attachment_id', (int) $result, false );

echo 'OK: attachment_id=' . (int) $result . "\n";
echo 'custom_logo=' . (int) get_theme_mod( 'custom_logo' ) . "\n";
echo 'url=' . (string) wp_get_attachment_url( (int) $result ) . "\n";
