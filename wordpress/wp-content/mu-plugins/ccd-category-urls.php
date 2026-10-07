<?php
/**
 * Plugin Name: CCD Category URLs
 * Description: Arquivos de categoria em /slug/ (sem /category/) e sem conflito com _wp_old_slug de posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCD_CATEGORY_URLS_VERSION = '1';

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
}
add_action( 'init', 'ccd_category_urls_ensure', 20 );

/**
 * Remove _wp_old_slug iguais a slugs de categoria (ex.: diabetes → post longo).
 *
 * @return void
 */
function ccd_category_urls_purge_conflicting_old_slugs() {
	global $wpdb;

	$slugs = $wpdb->get_col(
		"SELECT t.slug
		 FROM {$wpdb->terms} AS t
		 INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
		 WHERE tt.taxonomy = 'category'"
	);
	if ( ! is_array( $slugs ) || $slugs === array() ) {
		return;
	}

	foreach ( $slugs as $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( $slug === '' ) {
			continue;
		}
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
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
	$path = trim( (string) $path, '/' );
	if ( $path === '' || str_contains( $path, '/' ) ) {
		return $link;
	}
	$term = get_term_by( 'slug', $path, 'category' );
	if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
		return '';
	}
	return $link;
}
add_filter( 'old_slug_redirect_url', 'ccd_category_urls_block_old_slug' );
