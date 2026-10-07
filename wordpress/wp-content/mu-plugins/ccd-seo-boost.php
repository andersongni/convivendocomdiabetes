<?php
/**
 * Plugin Name: CCD SEO Boost
 * Description: Meta da home, alts, breadcrumbs Yoast e posts relacionados.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCD_SEO_BOOST_VERSION = '1';

/**
 * @return string
 */
function ccd_seo_home_metadesc() {
	return 'Blog da Bia Libonati sobre diabetes tipo 2: rotina, saúde e convivência no dia a dia. Conteúdos práticos e histórias reais.';
}

/**
 * @param string $content  HTML do post.
 * @param int    $image_id Attachment ID (classe wp-image-N).
 * @param string $alt      Texto alternativo.
 * @return string
 */
function ccd_seo_set_content_img_alt( $content, $image_id, $alt ) {
	if ( ! is_string( $content ) || $content === '' ) {
		return $content;
	}
	$image_id = (int) $image_id;
	$alt_esc  = esc_attr( $alt );
	$out      = preg_replace_callback(
		'/<img\b[^>]*\bwp-image-' . $image_id . '\b[^>]*>/i',
		static function ( $m ) use ( $alt_esc ) {
			$tag = $m[0];
			if ( preg_match( '/\balt=/i', $tag ) ) {
				return (string) preg_replace( '/\balt=(["\'])(?:(?!\1).)*\1/i', 'alt="' . $alt_esc . '"', $tag, 1 );
			}
			return (string) preg_replace( '/<img\b/i', '<img alt="' . $alt_esc . '"', $tag, 1 );
		},
		$content,
		1
	);
	return is_string( $out ) ? $out : $content;
}

/**
 * Aplica meta da home + alts (idempotente via option ccd_seo_boost).
 *
 * @return void
 */
/**
 * Sincroniza description no indexable do Yoast 28+.
 *
 * @param int    $post_id Post ID.
 * @param string $desc    Meta description.
 * @return void
 */
function ccd_seo_sync_yoast_description( $post_id, $desc ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || $desc === '' ) {
		return;
	}

	update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );

	global $wpdb;
	$table = $wpdb->prefix . 'yoast_indexable';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists ) {
		$wpdb->update(
			$table,
			array(
				'description' => $desc,
				'updated_at'  => current_time( 'mysql' ),
			),
			array(
				'object_type' => 'post',
				'object_id'   => $post_id,
			),
			array( '%s', '%s' ),
			array( '%s', '%d' )
		);
	}

	if ( ! function_exists( 'YoastSEO' ) ) {
		return;
	}
	try {
		$container = YoastSEO()->classes;
		if ( $container && method_exists( $container, 'get' ) ) {
			$builder = $container->get( 'Yoast\WP\SEO\Builders\Indexable_Builder' );
			if ( $builder && method_exists( $builder, 'build_for_id_and_type' ) ) {
				$builder->build_for_id_and_type( $post_id, 'post' );
			}
		}
	} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// Meta + update direto ja cobrem o caso.
	}
}

function ccd_seo_boost_apply() {
	if ( get_option( 'ccd_seo_boost' ) === CCD_SEO_BOOST_VERSION ) {
		return;
	}

	$home_id = (int) get_option( 'page_on_front' );
	if ( $home_id > 0 ) {
		ccd_seo_sync_yoast_description( $home_id, ccd_seo_home_metadesc() );

		$post = get_post( $home_id );
		if ( $post instanceof WP_Post && is_string( $post->post_content ) ) {
			$content = ccd_seo_set_content_img_alt(
				$post->post_content,
				2726,
				'Beatriz Libonati, autora do blog Convivendo com Diabetes'
			);
			$content = ccd_seo_set_content_img_alt(
				$content,
				2937,
				'Bia Libonati no LinkedIn'
			);
			if ( $content !== $post->post_content ) {
				wp_update_post(
					array(
						'ID'           => $home_id,
						'post_content' => $content,
					)
				);
			}
		}
	}

	update_post_meta( 2726, '_wp_attachment_image_alt', 'Beatriz Libonati, autora do blog Convivendo com Diabetes' );
	update_post_meta( 2937, '_wp_attachment_image_alt', 'Bia Libonati no LinkedIn' );

	update_option( 'ccd_seo_boost', CCD_SEO_BOOST_VERSION, false );

	if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
		ccd_page_cache_purge_all();
	}
}

add_action( 'init', 'ccd_seo_boost_apply', 6 );

/**
 * Breadcrumbs Yoast abaixo do hero (exceto home).
 */
add_action(
	'mesmerize_after_inner_page_header_content',
	static function () {
		if ( is_front_page() || is_home() || ! function_exists( 'yoast_breadcrumb' ) ) {
			return;
		}
		yoast_breadcrumb(
			'<nav class="ccd-breadcrumbs" aria-label="Breadcrumb"><p id="breadcrumbs">',
			'</p></nav>'
		);
	},
	8
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() ) {
			return;
		}
		$handle = 'ccd-seo-boost';
		wp_register_style( $handle, false, array(), CCD_SEO_BOOST_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			<<<'CSS'
.ccd-breadcrumbs {
	max-width: 1100px;
	margin: 0.35rem auto 0.75rem;
	padding: 0 1.25rem;
	font-size: 0.9rem;
	line-height: 1.4;
	color: #4a5d68;
}
.ccd-breadcrumbs #breadcrumbs,
.ccd-breadcrumbs p {
	margin: 0;
}
.ccd-breadcrumbs a {
	color: #0277bd;
	text-decoration: underline;
	text-underline-offset: 2px;
}
.ccd-related-posts {
	max-width: 720px;
	margin: 2rem auto 1.5rem;
	padding: 1.25rem 0 0;
	border-top: 1px solid #d7e3ea;
}
.ccd-related-posts h2 {
	margin: 0 0 0.85rem;
	font-size: 1.25rem;
	color: #243944;
}
.ccd-related-posts ul {
	margin: 0;
	padding: 0;
	list-style: none;
}
.ccd-related-posts li {
	margin: 0 0 0.55rem;
}
.ccd-related-posts a {
	color: #0277bd;
	font-weight: 600;
	text-decoration: none;
}
.ccd-related-posts a:hover,
.ccd-related-posts a:focus {
	text-decoration: underline;
}
CSS
		);
	},
	30
);

/**
 * Posts relacionados no single (malha interna).
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		static $done = false;
		if ( $done ) {
			return $content;
		}
		$done = true;
		$related = ccd_seo_related_posts_html( (int) get_the_ID() );
		return is_string( $content ) ? $content . $related : $content;
	},
	30
);

/**
 * @param int $post_id Post atual.
 * @return string
 */
function ccd_seo_related_posts_html( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return '';
	}

	$cats = wp_get_post_categories( $post_id );
	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'orderby'             => 'date',
	);
	if ( $cats ) {
		$args['category__in'] = $cats;
	}

	$q = new WP_Query( $args );
	if ( ! $q->have_posts() ) {
		wp_reset_postdata();
		return '';
	}

	$html = '<aside class="ccd-related-posts" aria-label="Posts relacionados"><h2>Leia também</h2><ul>';
	while ( $q->have_posts() ) {
		$q->the_post();
		$html .= sprintf(
			'<li><a href="%s">%s</a></li>',
			esc_url( get_permalink() ),
			esc_html( get_the_title() )
		);
	}
	$html .= '</ul></aside>';
	wp_reset_postdata();

	return $html;
}
