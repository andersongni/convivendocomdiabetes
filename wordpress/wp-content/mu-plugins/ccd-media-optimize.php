<?php
/**
 * Plugin Name: CCD Media Optimize
 * Description: Comprime e limita novos uploads (quality, max edge, WebP). Kill-switch: CCD_MEDIA_OPTIMIZE_DISABLE.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bump quando a política de compressão mudar (migration / docs). */
const CCD_MEDIA_OPTIMIZE_VERSION = '20261007';

/** Qualidade JPEG/WebP (alinhada a scripts/optimize-uploads.py). */
const CCD_MEDIA_OPTIMIZE_QUALITY = 82;

/** Lado maior máximo do original e do -scaled (px). */
const CCD_MEDIA_OPTIMIZE_MAX_EDGE = 1920;

/**
 * Kill-switch por constante ou env.
 *
 * @return bool
 */
function ccd_media_optimize_enabled() {
	if ( defined( 'CCD_MEDIA_OPTIMIZE_DISABLE' ) && CCD_MEDIA_OPTIMIZE_DISABLE ) {
		return false;
	}
	$env = getenv( 'CCD_MEDIA_OPTIMIZE_DISABLE' );
	if ( is_string( $env ) && in_array( strtolower( $env ), array( '1', 'true', 'yes' ), true ) ) {
		return false;
	}
	return true;
}

/**
 * WebP no output (desligar com CCD_MEDIA_WEBP_DISABLE).
 *
 * @return bool
 */
function ccd_media_optimize_webp_enabled() {
	if ( ! ccd_media_optimize_enabled() ) {
		return false;
	}
	if ( defined( 'CCD_MEDIA_WEBP_DISABLE' ) && CCD_MEDIA_WEBP_DISABLE ) {
		return false;
	}
	$env = getenv( 'CCD_MEDIA_WEBP_DISABLE' );
	if ( is_string( $env ) && in_array( strtolower( $env ), array( '1', 'true', 'yes' ), true ) ) {
		return false;
	}
	if ( ! function_exists( 'wp_image_editor_supports' ) ) {
		return false;
	}
	return (bool) wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
}

/**
 * Qualidade de encode para JPEG/WebP.
 *
 * @param int    $quality   Qualidade atual.
 * @param string $mime_type MIME de saída.
 * @return int
 */
function ccd_media_optimize_quality( $quality, $mime_type = '' ) {
	if ( ! ccd_media_optimize_enabled() ) {
		return $quality;
	}
	$mime = is_string( $mime_type ) ? $mime_type : '';
	if ( $mime !== '' && ! in_array( $mime, array( 'image/jpeg', 'image/webp' ), true ) ) {
		return $quality;
	}
	return CCD_MEDIA_OPTIMIZE_QUALITY;
}

/**
 * @param int $quality Qualidade.
 * @return int
 */
function ccd_media_optimize_jpeg_quality( $quality ) {
	return ccd_media_optimize_enabled() ? CCD_MEDIA_OPTIMIZE_QUALITY : $quality;
}

/**
 * Limite do -scaled / big image.
 *
 * @param int|false $threshold Threshold atual.
 * @return int|false
 */
function ccd_media_optimize_big_image_threshold( $threshold ) {
	if ( ! ccd_media_optimize_enabled() ) {
		return $threshold;
	}
	return CCD_MEDIA_OPTIMIZE_MAX_EDGE;
}

/**
 * Converte JPEG/PNG → WebP nos saves do editor (sub-sizes e scaled).
 *
 * @param array  $formats   Mapa mime → mime.
 * @param string $filename  Arquivo.
 * @param string $mime_type MIME de origem.
 * @return array
 */
function ccd_media_optimize_output_format( $formats, $filename = '', $mime_type = '' ) {
	if ( ! ccd_media_optimize_webp_enabled() ) {
		return $formats;
	}
	if ( ! is_array( $formats ) ) {
		$formats = array();
	}
	$formats['image/jpeg'] = 'image/webp';
	$formats['image/png']  = 'image/webp';
	return $formats;
}

/**
 * Redimensiona/reencode o arquivo recém-enviado (antes dos sub-sizes).
 *
 * @param array $upload Dados do upload (file, url, type).
 * @return array
 */
function ccd_media_optimize_handle_upload( $upload ) {
	if ( ! ccd_media_optimize_enabled() ) {
		return $upload;
	}
	if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) || ! is_string( $upload['file'] ) ) {
		return $upload;
	}

	$type = isset( $upload['type'] ) ? (string) $upload['type'] : '';
	if ( ! in_array( $type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
		return $upload;
	}

	$file = $upload['file'];
	if ( ! is_readable( $file ) ) {
		return $upload;
	}

	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return $upload;
	}

	$size = $editor->get_size();
	if ( is_array( $size ) && ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
		$max = CCD_MEDIA_OPTIMIZE_MAX_EDGE;
		if ( (int) $size['width'] > $max || (int) $size['height'] > $max ) {
			$resized = $editor->resize( $max, $max, false );
			if ( is_wp_error( $resized ) ) {
				return $upload;
			}
		}
	}

	$editor->set_quality( CCD_MEDIA_OPTIMIZE_QUALITY );

	$dest_mime = $type;
	if ( ccd_media_optimize_webp_enabled() && in_array( $type, array( 'image/jpeg', 'image/png' ), true ) ) {
		$dest_mime = 'image/webp';
	}

	$saved = $editor->save( $file, $dest_mime );
	if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
		return $upload;
	}

	$new_path = $saved['path'];
	if ( $new_path !== $file && is_readable( $file ) && is_readable( $new_path ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@unlink( $file );
	}

	$upload['file'] = $new_path;
	$upload['type'] = ! empty( $saved['mime-type'] ) ? $saved['mime-type'] : $dest_mime;

	if ( ! empty( $upload['url'] ) && is_string( $upload['url'] ) ) {
		$upload['url'] = trailingslashit( dirname( $upload['url'] ) ) . wp_basename( $new_path );
	}

	return $upload;
}

/**
 * Remove opções órfãs de Smush / reSmush.it (legado).
 *
 * @return int Quantidade de options removidas.
 */
function ccd_media_optimize_purge_legacy_options() {
	$keys = array(
		'wp-smush-settings',
		'wp-smush-image_sizes',
		'wp-smush-resize_sizes',
		'wp-smush-transparent_png',
		'wp-smush-dir_path',
		'wp-smush-scan',
		'wp_smush_dir_images',
		'wp-smush-hide_banner',
		'smush_global_stats',
		'wp-smush-png_to_jpg',
		'resmushit_on_upload',
		'resmushit_qlty',
		'resmushit_cron',
		'resmushit_disable_rebuild',
		'resmushit_removeexif',
	);
	$removed = 0;
	foreach ( $keys as $key ) {
		if ( delete_option( $key ) ) {
			++$removed;
		}
	}
	return $removed;
}

if ( ccd_media_optimize_enabled() ) {
	add_filter( 'jpeg_quality', 'ccd_media_optimize_jpeg_quality', 20 );
	add_filter( 'wp_editor_set_quality', 'ccd_media_optimize_quality', 20, 2 );
	add_filter( 'big_image_size_threshold', 'ccd_media_optimize_big_image_threshold', 20 );
	add_filter( 'image_editor_output_format', 'ccd_media_optimize_output_format', 20, 3 );
	add_filter( 'wp_handle_upload', 'ccd_media_optimize_handle_upload', 20 );
}
