<?php
/**
 * Plugin Name: CCD SEO Editorial
 * Description: Atualiza pilares YMYL, cria posts novos de intenção alta e reforça interlinking nos hubs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCD_SEO_EDITORIAL_VERSION = '2';

require_once __DIR__ . '/seo-editorial/content.php';

/**
 * @return int
 */
function ccd_seo_editorial_author_id() {
	$posts = get_posts(
		array(
			'name'           => 'hipoglicemia',
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	if ( ! empty( $posts[0] ) ) {
		$author = (int) get_post_field( 'post_author', (int) $posts[0] );
		if ( $author > 0 ) {
			return $author;
		}
	}
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => array( 'ID' ),
		)
	);
	return ! empty( $admins[0]->ID ) ? (int) $admins[0]->ID : 1;
}

/**
 * @param string[] $slugs Category slugs.
 * @return int[]
 */
function ccd_seo_editorial_category_ids( array $slugs ) {
	$ids = array();
	foreach ( $slugs as $slug ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( $term instanceof WP_Term ) {
			$ids[] = (int) $term->term_id;
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * @param int    $post_id Post ID.
 * @param string $desc    Meta description.
 * @param string $focus   Focus keyphrase.
 * @return void
 */
function ccd_seo_editorial_sync_yoast( $post_id, $desc, $focus ) {
	if ( function_exists( 'ccd_seo_sync_yoast_fields' ) ) {
		ccd_seo_sync_yoast_fields( (int) $post_id, $desc, $focus );
		return;
	}
	if ( $desc !== '' ) {
		update_post_meta( (int) $post_id, '_yoast_wpseo_metadesc', $desc );
	}
	if ( $focus !== '' ) {
		update_post_meta( (int) $post_id, '_yoast_wpseo_focuskw', $focus );
	}
}

/**
 * @param string               $slug Slug.
 * @param array<string, mixed> $data Dados.
 * @param bool                 $create_if_missing Criar se não existir.
 * @return int Post ID ou 0.
 */
function ccd_seo_editorial_upsert_post( $slug, array $data, $create_if_missing = false ) {
	$slug = sanitize_title( (string) $slug );
	$existing = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	$now_local = current_time( 'mysql' );
	$now_gmt   = current_time( 'mysql', true );
	$cats      = ccd_seo_editorial_category_ids( isset( $data['categories'] ) ? (array) $data['categories'] : array() );

	$payload = array(
		'post_title'        => (string) $data['title'],
		'post_content'      => (string) $data['content'],
		'post_excerpt'      => (string) $data['excerpt'],
		'post_status'       => 'publish',
		'post_name'         => $slug,
		'post_author'       => ccd_seo_editorial_author_id(),
		'post_modified'     => $now_local,
		'post_modified_gmt' => $now_gmt,
	);

	if ( ! empty( $existing[0] ) ) {
		$payload['ID'] = (int) $existing[0];
		$post_id       = wp_update_post( $payload, true );
	} elseif ( $create_if_missing ) {
		$payload['post_date']     = $now_local;
		$payload['post_date_gmt'] = $now_gmt;
		$post_id                  = wp_insert_post( $payload, true );
	} else {
		return 0;
	}

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return 0;
	}

	$post_id = (int) $post_id;
	if ( $cats ) {
		wp_set_post_categories( $post_id, $cats, false );
	}

	$desc  = (string) $data['excerpt'];
	$focus = isset( $data['focus'] ) ? (string) $data['focus'] : '';
	ccd_seo_editorial_sync_yoast( $post_id, $desc, $focus );
	update_post_meta( $post_id, '_ccd_seo_editorial', CCD_SEO_EDITORIAL_VERSION );

	return $post_id;
}

/**
 * Mantém description dos hubs em texto curto (sem HTML no termo — evita vazamento no hero).
 *
 * @return void
 */
function ccd_seo_editorial_sync_hub_descriptions() {
	if ( ! function_exists( 'ccd_seo_category_descriptions' ) ) {
		return;
	}
	$map = ccd_seo_category_descriptions();
	foreach ( array_keys( ccd_seo_editorial_hub_slugs() ) as $cat_slug ) {
		$term = get_term_by( 'slug', $cat_slug, 'category' );
		if ( ! ( $term instanceof WP_Term ) || ! isset( $map[ $cat_slug ] ) ) {
			continue;
		}
		$plain = wp_strip_all_tags( (string) $map[ $cat_slug ] );
		wp_update_term(
			(int) $term->term_id,
			'category',
			array(
				'description' => $plain,
			)
		);
	}
}

/**
 * Marcadores que não podem aparecer dentro do hero de arquivo.
 *
 * @return string[]
 */
function ccd_seo_editorial_hero_forbidden_markers() {
	return array(
		'ccd-hub-pillars',
		'ccd-category-intro',
		'Pilares para começar',
	);
}

/**
 * Markup dos pilares do hub (somente fora do hero).
 *
 * @param string $cat_slug Slug da categoria.
 * @return string
 */
function ccd_seo_editorial_hub_pillars_html( $cat_slug ) {
	$hubs = ccd_seo_editorial_hub_slugs();
	if ( ! isset( $hubs[ $cat_slug ] ) ) {
		return '';
	}
	$labels = ccd_seo_editorial_link_labels();
	$html   = '<nav class="ccd-hub-pillars" aria-label="Pilares recomendados">';
	$html  .= '<p><strong>Pilares para começar:</strong></p><ul>';
	foreach ( $hubs[ $cat_slug ] as $slug ) {
		$lab   = isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
		$html .= sprintf(
			'<li><a href="%s">%s</a></li>',
			esc_url( home_url( '/' . $slug . '/' ) ),
			esc_html( $lab )
		);
	}
	$html .= '</ul></nav>';
	return $html;
}

/**
 * @return void
 */
function ccd_seo_editorial_apply() {
	if ( get_option( 'ccd_seo_editorial' ) === CCD_SEO_EDITORIAL_VERSION ) {
		return;
	}
	if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_cron() ) {
		return;
	}

	foreach ( ccd_seo_editorial_updates() as $slug => $data ) {
		ccd_seo_editorial_upsert_post( $slug, $data, false );
	}
	foreach ( ccd_seo_editorial_new_posts() as $slug => $data ) {
		ccd_seo_editorial_upsert_post( $slug, $data, true );
	}
	ccd_seo_editorial_sync_hub_descriptions();

	update_option( 'ccd_seo_editorial', CCD_SEO_EDITORIAL_VERSION, false );

	if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
		ccd_page_cache_purge_all();
	}
}
add_action( 'init', 'ccd_seo_editorial_apply', 30 );

/**
 * Pilares no início do loop de conteúdo — nunca dentro do hero.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function ccd_seo_editorial_print_hub_pillars_in_loop( $query ) {
	if ( is_admin() || ! ( $query instanceof WP_Query ) || ! $query->is_main_query() ) {
		return;
	}
	if ( ! is_category() ) {
		return;
	}
	static $done = false;
	if ( $done ) {
		return;
	}
	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) ) {
		return;
	}
	$html = ccd_seo_editorial_hub_pillars_html( $term->slug );
	if ( $html === '' ) {
		return;
	}
	$done = true;
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado no builder.
}
add_action( 'loop_start', 'ccd_seo_editorial_print_hub_pillars_in_loop', 5 );

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() ) {
			return;
		}
		$handle = 'ccd-seo-editorial';
		wp_register_style( $handle, false, array(), CCD_SEO_EDITORIAL_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			<<<'CSS'
/* Nunca renderizar pilares/listas editoriais dentro do hero Mesmerize. */
.header .ccd-hub-pillars,
.header .ccd-editorial-links,
.header .ccd-category-intro {
	display: none !important;
}
.ccd-hub-pillars,
.ccd-editorial-links {
	max-width: 1100px;
	margin: 1rem auto 1.5rem;
	padding: 0 1.25rem;
	color: #2b3a42;
}
.ccd-hub-pillars ul,
.ccd-editorial-links {
	margin: 0.4rem 0 0;
	padding-left: 1.2rem;
}
.ccd-hub-pillars a,
ul.ccd-editorial-links a {
	color: #0277bd;
	font-weight: 600;
	text-decoration: none;
}
.ccd-hub-pillars a:hover,
ul.ccd-editorial-links a:hover {
	text-decoration: underline;
}
CSS
		);
	},
	30
);
