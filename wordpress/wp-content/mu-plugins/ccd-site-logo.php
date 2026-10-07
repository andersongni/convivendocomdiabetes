<?php
/**
 * Plugin Name: CCD Site Logo
 * Description: Instala/atualiza o logo do site (custom_logo) a partir de asset versionado no repo.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bump para reaplicar o logo em localhost/Railway. */
const CCD_SITE_LOGO_VERSION = '20261007';

/** Arquivo-fonte (PNG comprimido) relativo a este mu-plugin. */
const CCD_SITE_LOGO_FILE = 'assets/brand/logo-convivendo-com-diabetes.png';

/**
 * Caminho absoluto do PNG versionado.
 *
 * @return string
 */
function ccd_site_logo_source_path() {
	return __DIR__ . '/' . CCD_SITE_LOGO_FILE;
}

/**
 * Instala o logo no media library e define custom_logo (+ Yoast se presente).
 *
 * @return int|\WP_Error Attachment ID ou erro.
 */
function ccd_site_logo_install() {
	$src = ccd_site_logo_source_path();
	if ( ! is_readable( $src ) ) {
		return new WP_Error( 'ccd_logo_missing', 'Logo source not readable: ' . $src );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$bits = wp_upload_bits(
		'logo-convivendo-com-diabetes.png',
		null,
		file_get_contents( $src ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	);
	if ( ! empty( $bits['error'] ) ) {
		return new WP_Error( 'ccd_logo_upload', (string) $bits['error'] );
	}

	$filetype = wp_check_filetype( $bits['file'], null );
	$attach_id = wp_insert_attachment(
		array(
			'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/png',
			'post_title'     => 'Logo Convivendo com Diabetes',
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_excerpt'   => 'Logo Convivendo com Diabetes',
		),
		$bits['file']
	);
	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		return is_wp_error( $attach_id )
			? $attach_id
			: new WP_Error( 'ccd_logo_attach', 'wp_insert_attachment failed' );
	}

	$meta = wp_generate_attachment_metadata( $attach_id, $bits['file'] );
	if ( is_array( $meta ) ) {
		wp_update_attachment_metadata( $attach_id, $meta );
	}
	update_post_meta( $attach_id, '_wp_attachment_image_alt', 'Convivendo com Diabetes' );

	set_theme_mod( 'custom_logo', (int) $attach_id );

	$yoast = get_option( 'wpseo_titles' );
	if ( is_array( $yoast ) ) {
		$yoast['company_logo']    = wp_get_attachment_url( $attach_id );
		$yoast['company_logo_id'] = (int) $attach_id;
		update_option( 'wpseo_titles', $yoast, false );
	}

	if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
		ccd_page_cache_purge_all();
	}

	return (int) $attach_id;
}

/**
 * Aplica o logo uma vez por versão (idempotente).
 */
function ccd_site_logo_maybe_apply() {
	if ( get_option( 'ccd_site_logo_version' ) === CCD_SITE_LOGO_VERSION ) {
		return;
	}
	if ( ! is_readable( ccd_site_logo_source_path() ) ) {
		return;
	}

	$result = ccd_site_logo_install();
	if ( is_wp_error( $result ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'ccd-site-logo: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return;
	}

	update_option( 'ccd_site_logo_version', CCD_SITE_LOGO_VERSION, false );
	update_option( 'ccd_site_logo_attachment_id', (int) $result, false );
}

add_action( 'init', 'ccd_site_logo_maybe_apply', 5 );
