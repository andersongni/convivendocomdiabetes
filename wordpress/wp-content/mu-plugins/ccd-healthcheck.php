<?php
/**
 * Plugin Name: CCD Healthcheck
 * Description: Endpoints /health e /ccdhealth (HTTP 200) para o Railway, sem redirects do WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Responde antes de canonical/SSL redirects (WP_HOME etc.).
 * Preferir Alias Apache /ccdhealth (sem bootstrap WP); isto e fallback.
 */
add_action(
	'muplugins_loaded',
	static function () {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path = (string) parse_url( $uri, PHP_URL_PATH );
		$path = untrailingslashit( $path );
		if ( $path !== '/health' && $path !== '/ccdhealth' ) {
			return;
		}

		if ( function_exists( 'status_header' ) ) {
			status_header( 200 );
		} else {
			header( 'HTTP/1.1 200 OK' );
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo 'ok';
		exit;
	},
	0
);
