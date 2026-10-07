<?php
/**
 * Plugin Name: CCD HTTPS behind proxy
 * Description: Detecta HTTPS no reverse proxy (Railway/Cloudflare) para assets, admin e OAuth.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marca a request como HTTPS quando o proxy envia X-Forwarded-Proto
 * ou quando WP_HOME/WP_SITEURL ja sao https (CLI/cron/OAuth state).
 */
$ccd_forwarded_https = (
	( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' )
	|| ( ! empty( $_SERVER['HTTP_X_FORWARDED_SSL'] ) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on' )
);

$ccd_home_https = (
	( defined( 'WP_HOME' ) && is_string( WP_HOME ) && str_starts_with( WP_HOME, 'https://' ) )
	|| ( defined( 'WP_SITEURL' ) && is_string( WP_SITEURL ) && str_starts_with( WP_SITEURL, 'https://' ) )
);

if ( $ccd_forwarded_https || $ccd_home_https ) {
	$_SERVER['HTTPS'] = 'on';
}

/**
 * Forca https no callback do WP Mail SMTP Gmail (state), evitando http atras do proxy.
 */
add_filter(
	'wp_mail_smtp_gmail_get_plugin_auth_url',
	static function ( $url ) {
		if ( ! is_string( $url ) || $url === '' ) {
			return $url;
		}
		return set_url_scheme( $url, 'https' );
	},
	5
);
