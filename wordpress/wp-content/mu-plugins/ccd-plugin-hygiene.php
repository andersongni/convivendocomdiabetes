<?php
/**
 * Plugin Name: CCD Plugin Hygiene
 * Description: Remove plugins aposentados do active_plugins e limpa shortcodes orfaos (ex.: insta-gallery).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCD_PLUGIN_HYGIENE_VERSION = '1';

/**
 * Plugins que nao devem permanecer ativos (mesmo se o diretorio existir).
 *
 * @return string[]
 */
function ccd_plugin_hygiene_retired_slugs() {
	return array(
		'insta-gallery',
		'google-publisher',
		'auto-post-thumbnail-pro',
		'sucuri-scanner',
		'wp-statistics',
		'elementor',
		'wordfence',
		'amp',
		'contact-form-7',
		'separator-shortcode-and-widget',
		'td-composer',
		'td-cloud-library',
		'td-newsletter',
		'td-social-counter',
		'td-mobile-plugin',
		'wp-file-manager',
		'all-in-one-wp-migration',
		'wordpress-importer',
		'regenerate-thumbnails',
		'phoenix-media-rename',
		'health-check',
		'wp-maintenance-mode',
		'glue-for-yoast-seo-amp',
		'sidebar-manager',
	);
}

/**
 * @param string $content Conteudo de post/widget.
 * @return string
 */
function ccd_plugin_hygiene_strip_insta_gallery( $content ) {
	if ( ! is_string( $content ) || $content === '' || stripos( $content, 'insta-gallery' ) === false ) {
		return $content;
	}

	$out = preg_replace( '/\[insta-gallery[^\]]*\]/iu', '', $content );
	if ( ! is_string( $out ) ) {
		return $content;
	}

	// Paragrafo que so envolvia o shortcode.
	$out = preg_replace( '/<p>(?:\s|&nbsp;|\x{00a0})*<\/p>/iu', '', $out );
	return is_string( $out ) ? $out : $content;
}

/**
 * @return bool True se active_plugins mudou.
 */
function ccd_plugin_hygiene_prune_active_plugins() {
	$active  = (array) get_option( 'active_plugins', array() );
	$retired = array_fill_keys( ccd_plugin_hygiene_retired_slugs(), true );
	$keep    = array();
	$changed = false;

	foreach ( $active as $plugin ) {
		$plugin = (string) $plugin;
		if ( $plugin === '' ) {
			$changed = true;
			continue;
		}

		$slug = dirname( $plugin );
		if ( $slug === '.' ) {
			$slug = $plugin;
		}

		if ( isset( $retired[ $slug ] ) ) {
			$changed = true;
			continue;
		}

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			$changed = true;
			continue;
		}

		$keep[] = $plugin;
	}

	if ( $changed ) {
		update_option( 'active_plugins', array_values( array_unique( $keep ) ) );
	}

	return $changed;
}

/**
 * @return int Posts alterados.
 */
function ccd_plugin_hygiene_strip_shortcodes_from_posts() {
	global $wpdb;

	$ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%insta-gallery%'"
	);
	if ( ! is_array( $ids ) || $ids === array() ) {
		return 0;
	}

	$updated = 0;
	foreach ( $ids as $id ) {
		$id   = (int) $id;
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			continue;
		}

		$clean = ccd_plugin_hygiene_strip_insta_gallery( $post->post_content );
		if ( $clean === $post->post_content ) {
			continue;
		}

		$wpdb->update(
			$wpdb->posts,
			array( 'post_content' => $clean ),
			array( 'ID' => $id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $id );
		++$updated;
	}

	return $updated;
}

/**
 * @return int Opcoes/widgets alterados.
 */
function ccd_plugin_hygiene_strip_shortcodes_from_options() {
	global $wpdb;

	$names = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options}
		 WHERE option_value LIKE '%insta-gallery%'
		 AND option_name NOT LIKE '_transient_%'
		 AND option_name NOT LIKE '_site_transient_%'
		 LIMIT 50"
	);
	if ( ! is_array( $names ) || $names === array() ) {
		return 0;
	}

	$updated = 0;
	foreach ( $names as $name ) {
		$value = get_option( $name );
		$clean = ccd_plugin_hygiene_deep_strip( $value );
		if ( $clean !== $value ) {
			update_option( $name, $clean );
			++$updated;
		}
	}

	return $updated;
}

/**
 * @param mixed $value Valor de option (string/array/objeto serializado via WP).
 * @return mixed
 */
function ccd_plugin_hygiene_deep_strip( $value ) {
	if ( is_string( $value ) ) {
		return ccd_plugin_hygiene_strip_insta_gallery( $value );
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = ccd_plugin_hygiene_deep_strip( $v );
		}
	}
	return $value;
}

add_action(
	'init',
	static function () {
		if ( get_option( 'ccd_plugin_hygiene' ) === CCD_PLUGIN_HYGIENE_VERSION ) {
			return;
		}

		ccd_plugin_hygiene_prune_active_plugins();
		ccd_plugin_hygiene_strip_shortcodes_from_posts();
		ccd_plugin_hygiene_strip_shortcodes_from_options();

		update_option( 'ccd_plugin_hygiene', CCD_PLUGIN_HYGIENE_VERSION, false );

		if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
			ccd_page_cache_purge_all();
		}
	},
	5
);
