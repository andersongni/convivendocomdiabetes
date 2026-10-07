<?php
/**
 * Plugin Name: CCD Site Icon
 * Description: Instala/atualiza o favicon (site_icon) a partir de asset versionado no repo.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bump para reaplicar o ícone em localhost/Railway. */
const CCD_SITE_ICON_VERSION = '20261007';

/** Arquivo-fonte (PNG 1024×1024) relativo a este mu-plugin. */
const CCD_SITE_ICON_FILE = 'assets/brand/site-icon.png';

/**
 * Caminho absoluto do PNG versionado.
 *
 * @return string
 */
function ccd_site_icon_source_path() {
	return __DIR__ . '/' . CCD_SITE_ICON_FILE;
}

/**
 * Instala o ícone no media library, gera tamanhos de site icon e define site_icon.
 *
 * @return int|\WP_Error Attachment ID ou erro.
 */
function ccd_site_icon_install() {
	$src = ccd_site_icon_source_path();
	if ( ! is_readable( $src ) ) {
		return new WP_Error( 'ccd_icon_missing', 'Site icon source not readable: ' . $src );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';

	$bits = wp_upload_bits(
		'site-icon-convivendo-com-diabetes.png',
		null,
		file_get_contents( $src ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	);
	if ( ! empty( $bits['error'] ) ) {
		return new WP_Error( 'ccd_icon_upload', (string) $bits['error'] );
	}

	$filetype = wp_check_filetype( $bits['file'], null );
	$wp_site_icon = new WP_Site_Icon();

	add_filter( 'intermediate_image_sizes_advanced', array( $wp_site_icon, 'additional_sizes' ) );
	$attach_id = $wp_site_icon->insert_attachment(
		array(
			'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/png',
			'post_title'     => 'Ícone do site — Convivendo com Diabetes',
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_excerpt'   => 'Favicon Convivendo com Diabetes',
			'context'        => 'site-icon',
		),
		$bits['file']
	);
	remove_filter( 'intermediate_image_sizes_advanced', array( $wp_site_icon, 'additional_sizes' ) );

	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		return is_wp_error( $attach_id )
			? $attach_id
			: new WP_Error( 'ccd_icon_attach', 'wp_insert_attachment failed' );
	}

	update_post_meta( $attach_id, '_wp_attachment_image_alt', 'Convivendo com Diabetes' );
	update_option( 'site_icon', (int) $attach_id );

	if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
		ccd_page_cache_purge_all();
	}

	return (int) $attach_id;
}

/**
 * Aplica o ícone uma vez por versão (idempotente).
 */
function ccd_site_icon_maybe_apply() {
	if ( get_option( 'ccd_site_icon_version' ) === CCD_SITE_ICON_VERSION ) {
		return;
	}
	if ( ! is_readable( ccd_site_icon_source_path() ) ) {
		return;
	}

	$result = ccd_site_icon_install();
	if ( is_wp_error( $result ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'ccd-site-icon: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return;
	}

	update_option( 'ccd_site_icon_version', CCD_SITE_ICON_VERSION, false );
	update_option( 'ccd_site_icon_attachment_id', (int) $result, false );
}

add_action( 'init', 'ccd_site_icon_maybe_apply', 5 );
