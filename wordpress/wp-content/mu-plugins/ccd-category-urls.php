<?php
/**
 * Plugin Name: CCD Category URLs
 * Description: Arquivos de categoria em /slug/ (sem /category/) e sem conflito com _wp_old_slug / guess 404.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCD_CATEGORY_URLS_VERSION = '2';

/**
 * @return string[]
 */
function ccd_category_urls_slugs() {
	static $slugs = null;
	if ( is_array( $slugs ) ) {
		return $slugs;
	}
	global $wpdb;
	$rows = $wpdb->get_col(
		"SELECT t.slug
		 FROM {$wpdb->terms} AS t
		 INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
		 WHERE tt.taxonomy = 'category'"
	);
	$slugs = array();
	if ( is_array( $rows ) ) {
		foreach ( $rows as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( $slug !== '' ) {
				$slugs[ $slug ] = true;
			}
		}
	}
	return $slugs;
}

/**
 * Path de um segmento (ex.: diabetes) a partir do REQUEST_URI.
 *
 * @return string
 */
function ccd_category_urls_request_slug() {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
	$path = trim( (string) $path, '/' );
	if ( $path === '' || str_contains( $path, '/' ) ) {
		return '';
	}
	return sanitize_title( $path );
}

/**
 * Liga stripcategorybase do Yoast, limpa old slugs que colidem com categorias e faz flush.
 *
 * @return void
 */
function ccd_category_urls_ensure() {
	$stored = (string) get_option( 'ccd_category_urls', '' );
	$dirty  = ( $stored !== CCD_CATEGORY_URLS_VERSION );

	if ( class_exists( 'WPSEO_Options' ) && WPSEO_Options::get( 'stripcategorybase' ) !== true ) {
		WPSEO_Options::set( 'stripcategorybase', true );
		$dirty = true;
	}

	if ( ! $dirty ) {
		return;
	}

	ccd_category_urls_purge_conflicting_old_slugs();
	flush_rewrite_rules( false );
	update_option( 'ccd_category_urls', CCD_CATEGORY_URLS_VERSION, false );

	if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
		ccd_page_cache_purge_all();
	}
}
add_action( 'init', 'ccd_category_urls_ensure', 20 );

/**
 * Remove _wp_old_slug iguais a slugs de categoria (ex.: diabetes → post longo).
 *
 * @return void
 */
function ccd_category_urls_purge_conflicting_old_slugs() {
	global $wpdb;

	foreach ( array_keys( ccd_category_urls_slugs() ) as $slug ) {
		$wpdb->delete(
			$wpdb->postmeta,
			array(
				'meta_key'   => '_wp_old_slug',
				'meta_value' => $slug,
			),
			array( '%s', '%s' )
		);
	}
}

/**
 * Após trocar slug de post, reaplica limpeza se o antigo colidir com categoria.
 *
 * @param int     $post_id     Post ID.
 * @param WP_Post $post_after  Post novo.
 * @param WP_Post $post_before Post anterior.
 * @return void
 */
function ccd_category_urls_on_post_updated( $post_id, $post_after, $post_before ) {
	unset( $post_id );
	if ( ! ( $post_after instanceof WP_Post ) || $post_after->post_type !== 'post' ) {
		return;
	}
	if ( $post_before instanceof WP_Post && $post_before->post_name === $post_after->post_name ) {
		return;
	}
	ccd_category_urls_purge_conflicting_old_slugs();
}
add_action( 'post_updated', 'ccd_category_urls_on_post_updated', 20, 3 );

/**
 * Cancela redirect de slug antigo quando o path pedido é uma categoria.
 *
 * @param string $link Destino do 301.
 * @return string
 */
function ccd_category_urls_block_old_slug( $link ) {
	$slug = ccd_category_urls_request_slug();
	if ( $slug === '' ) {
		return $link;
	}
	$known = ccd_category_urls_slugs();
	if ( isset( $known[ $slug ] ) ) {
		return '';
	}
	return $link;
}
add_filter( 'old_slug_redirect_url', 'ccd_category_urls_block_old_slug' );

/**
 * Impede guess 404 (LIKE post_name%) quando o path é slug de categoria.
 *
 * @param mixed $pre Valor prévio do filtro.
 * @return mixed
 */
function ccd_category_urls_block_guess_404( $pre ) {
	if ( null !== $pre ) {
		return $pre;
	}
	$slug = ccd_category_urls_request_slug();
	if ( $slug === '' ) {
		return $pre;
	}
	$known = ccd_category_urls_slugs();
	if ( isset( $known[ $slug ] ) ) {
		return false;
	}
	return $pre;
}
add_filter( 'pre_redirect_guess_404_permalink', 'ccd_category_urls_block_guess_404' );
