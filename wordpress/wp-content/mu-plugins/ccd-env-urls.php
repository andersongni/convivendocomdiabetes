<?php
/**
 * Plugin Name: CCD Env URLs
 * Description: Reescreve links/assets do dominio legado para WP_HOME / home_url do ambiente.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ccd_env_urls_lib = dirname( __DIR__ ) . '/ccd-env-urls-lib.php';
if ( is_readable( $ccd_env_urls_lib ) ) {
	require_once $ccd_env_urls_lib;
}

/**
 * @param mixed $value Valor a reescrever.
 * @return mixed
 */
function ccd_env_urls_map( $value ) {
	if ( ! function_exists( 'ccd_env_url_rewrite' ) ) {
		return $value;
	}
	if ( is_string( $value ) ) {
		return ccd_env_url_rewrite( $value );
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = ccd_env_urls_map( $v );
		}
	}
	return $value;
}

/**
 * Garante que home_url() seja a fonte da verdade do ambiente.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! function_exists( 'ccd_env_url_rewrite' ) ) {
			return;
		}

		$filters = array(
			'the_content',
			'the_excerpt',
			'widget_text',
			'widget_text_content',
			'widget_block_content',
			'wp_get_attachment_url',
			'wp_get_attachment_image_url',
			'wp_get_attachment_thumb_url',
			'wp_get_attachment_caption',
			'post_thumbnail_html',
			'get_header_image',
			'theme_mod_header_image',
			'theme_mod_custom_logo',
			'style_loader_src',
			'script_loader_src',
			'wp_get_custom_css',
		);

		foreach ( $filters as $hook ) {
			add_filter( $hook, 'ccd_env_url_rewrite', 99 );
		}

		add_filter(
			'wp_calculate_image_srcset',
			static function ( $sources ) {
				if ( ! is_array( $sources ) ) {
					return $sources;
				}
				foreach ( $sources as $width => $source ) {
					if ( isset( $source['url'] ) ) {
						$sources[ $width ]['url'] = ccd_env_url_rewrite( $source['url'] );
					}
				}
				return $sources;
			},
			99
		);

		add_filter(
			'wp_get_attachment_image_src',
			static function ( $image ) {
				if ( is_array( $image ) && isset( $image[0] ) && is_string( $image[0] ) ) {
					$image[0] = ccd_env_url_rewrite( $image[0] );
				}
				return $image;
			},
			99
		);

		// Theme mods com URLs de imagem (header Mesmerize, etc.).
		add_filter(
			'theme_mods_' . get_option( 'stylesheet' ),
			static function ( $mods ) {
				return ccd_env_urls_map( $mods );
			},
			99
		);
		add_filter(
			'theme_mods_' . get_option( 'template' ),
			static function ( $mods ) {
				return ccd_env_urls_map( $mods );
			},
			99
		);
	},
	5
);

/**
 * Rede de seguranca: reescreve o HTML final (conteudo + CSS inline do tema).
 */
add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || ! function_exists( 'ccd_env_url_rewrite' ) ) {
			return;
		}
		ob_start(
			static function ( $html ) {
				return ccd_env_url_rewrite( $html );
			}
		);
	},
	1
);
