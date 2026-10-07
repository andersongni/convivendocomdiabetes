<?php
/**
 * Plugin Name: CCD Gmail OAuth Fix
 * Description: Aceita scopes extras (ex. drive.file) no callback do WP Mail SMTP e forca https/www.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google devolve scopes ja concedidos (include_granted_scopes). O WP Mail SMTP
 * so aceita igualdade exata com mail.google.com — normalizamos antes do process().
 */
add_action(
	'admin_init',
	static function () {
		if ( empty( $_GET['page'] ) || $_GET['page'] !== 'wp-mail-smtp' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( empty( $_GET['tab'] ) || $_GET['tab'] !== 'auth' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( empty( $_GET['code'] ) || empty( $_GET['scope'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$raw = wp_unslash( (string) $_GET['scope'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$decoded = rawurldecode( base64_decode( rawurldecode( $raw ) ) );
		if ( ! is_string( $decoded ) || $decoded === '' ) {
			return;
		}

		$mail_scope = 'https://mail.google.com/';
		if ( strpos( $decoded, $mail_scope ) === false ) {
			return;
		}

		// Plugin espera scope base64(urlencode) exatamente igual a mail.google.com.
		$_GET['scope'] = rawurlencode( base64_encode( rawurlencode( $mail_scope ) ) );
	},
	0
);

add_filter(
	'wp_mail_smtp_gmail_get_plugin_auth_url',
	static function ( $url ) {
		if ( ! is_string( $url ) || $url === '' ) {
			return $url;
		}
		$url = set_url_scheme( $url, 'https' );
		// Canonico: www (apex tambem aponta ao Railway, mas cookies/admin preferem www).
		$url = preg_replace( '#^https://convivendocomdiabetes\.com/#', 'https://www.convivendocomdiabetes.com/', $url );
		return $url;
	},
	20
);
