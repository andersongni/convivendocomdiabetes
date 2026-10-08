<?php
/**
 * Bridge WP: carrega PageCacheGuard e expoe funcoes procedurais ao drop-in/mu-plugins.
 *
 * @package CCD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/ccd-core/PageCacheGuard.php';

if ( ! function_exists( 'ccd_page_cache_html_looks_private' ) ) {
	/**
	 * @param string $html HTML.
	 * @return bool
	 */
	function ccd_page_cache_html_looks_private( $html ) {
		return \Ccd\PageCacheGuard::htmlLooksPrivate( is_string( $html ) ? $html : '' );
	}
}

if ( ! function_exists( 'ccd_html_cache_control_value' ) ) {
	/**
	 * @param bool $is_admin     is_admin().
	 * @param bool $is_logged_in is_user_logged_in().
	 * @return string|null
	 */
	function ccd_html_cache_control_value( $is_admin, $is_logged_in ) {
		return \Ccd\PageCacheGuard::cacheControlValue( (bool) $is_admin, (bool) $is_logged_in );
	}
}
