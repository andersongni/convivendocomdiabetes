<?php
/**
 * Helpers puros de page cache / Cache-Control HTML (usados por advanced-cache + mu-plugins).
 * Sem side effects — seguros para PHPUnit e para o drop-in early-load.
 *
 * @package CCD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True se o HTML parece visão de usuário que vê posts private/draft
 * (badge WordPress "Privado:" / "Private:" etc.).
 *
 * @param string $html HTML completo ou fragmento.
 * @return bool
 */
function ccd_page_cache_html_looks_private( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return false;
	}
	return (bool) preg_match( '/\b(?:Privado|Protegido|Private|Protected):\s/u', $html );
}

/**
 * Valor de Cache-Control para HTML do front.
 * null = não emitir (ex.: wp-admin).
 *
 * @param bool $is_admin     is_admin().
 * @param bool $is_logged_in is_user_logged_in().
 * @return string|null
 */
function ccd_html_cache_control_value( $is_admin, $is_logged_in ) {
	if ( $is_admin ) {
		return null;
	}
	if ( $is_logged_in ) {
		// Nunca public/s-maxage: CDN cachearia loop com posts private.
		return 'private, no-store, no-cache, must-revalidate, max-age=0';
	}
	if ( defined( 'CCD_PERF_HTML_CACHE' ) ) {
		return (string) CCD_PERF_HTML_CACHE;
	}
	return 'public, max-age=0, s-maxage=3600, must-revalidate';
}
