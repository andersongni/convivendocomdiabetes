<?php
/**
 * Hide Yoast SEO from the frontend admin bar.
 * Prevents the notification badge from leaking into the site header.
 */
add_action( 'admin_bar_menu', function ( $wp_admin_bar ) {
	if ( is_admin() ) {
		return;
	}
	$wp_admin_bar->remove_node( 'wpseo-menu' );
}, 999 );
