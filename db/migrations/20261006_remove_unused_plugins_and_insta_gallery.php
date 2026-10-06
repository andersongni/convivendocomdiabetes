<?php
/**
 * Migration: remove plugins aposentados do active_plugins e shortcode insta-gallery.
 *
 * Preferido em runtime: mu-plugin ccd-plugin-hygiene.php (roda sozinho no deploy).
 *
 * Uso manual (container WordPress):
 *   wp eval-file /path/to/db/migrations/20261006_remove_unused_plugins_and_insta_gallery.php
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

$hygiene = WP_CONTENT_DIR . '/mu-plugins/ccd-plugin-hygiene.php';
if ( ! is_readable( $hygiene ) ) {
	fwrite( STDERR, "ccd-plugin-hygiene.php not found\n" );
	exit( 1 );
}
require_once $hygiene;

delete_option( 'ccd_plugin_hygiene' );

$pruned  = ccd_plugin_hygiene_prune_active_plugins();
$posts   = ccd_plugin_hygiene_strip_shortcodes_from_posts();
$options = ccd_plugin_hygiene_strip_shortcodes_from_options();

update_option( 'ccd_plugin_hygiene', CCD_PLUGIN_HYGIENE_VERSION, false );

if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
	ccd_page_cache_purge_all();
}

echo 'OK: pruned_active=' . ( $pruned ? 'yes' : 'no' )
	. " posts={$posts} options={$options}\n";
