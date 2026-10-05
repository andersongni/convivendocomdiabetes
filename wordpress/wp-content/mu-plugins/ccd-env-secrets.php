<?php
/**
 * Plugin Name: CCD Env Secrets
 * Description: Expõe secrets de integracoes via variaveis de ambiente (nunca hardcoded).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define uma constante a partir de getenv se ainda nao existir.
 *
 * @param string $constant Constant name.
 * @param string $env_key  Environment variable name.
 */
function ccd_define_from_env( $constant, $env_key ) {
	if ( defined( $constant ) ) {
		return;
	}
	$value = getenv( $env_key );
	if ( is_string( $value ) && $value !== '' ) {
		define( $constant, $value );
	}
}

// tagDiv / Newspaper / Social Counter
ccd_define_from_env( 'TD_YOUTUBE_API_KEY', 'TD_YOUTUBE_API_KEY' );
ccd_define_from_env( 'TD_GOOGLE_API_KEY', 'TD_GOOGLE_API_KEY' );
ccd_define_from_env( 'TD_FACEBOOK_ACCESS_TOKEN', 'TD_FACEBOOK_ACCESS_TOKEN' );

// elFinder (wp-file-manager) — tambem lidos via getenv no connector
ccd_define_from_env( 'ELFINDER_DROPBOX_APPKEY', 'ELFINDER_DROPBOX_APPKEY' );
ccd_define_from_env( 'ELFINDER_DROPBOX_APPSECRET', 'ELFINDER_DROPBOX_APPSECRET' );
ccd_define_from_env( 'ELFINDER_DROPBOX_ACCESS_TOKEN', 'ELFINDER_DROPBOX_ACCESS_TOKEN' );
ccd_define_from_env( 'ELFINDER_GOOGLEDRIVE_CLIENTID', 'ELFINDER_GOOGLEDRIVE_CLIENTID' );
ccd_define_from_env( 'ELFINDER_GOOGLEDRIVE_CLIENTSECRET', 'ELFINDER_GOOGLEDRIVE_CLIENTSECRET' );
