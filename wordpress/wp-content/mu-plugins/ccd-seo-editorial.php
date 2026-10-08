<?php
/**
 * Plugin Name: CCD SEO Editorial
 * Description: Atualiza pilares YMYL, cria posts novos e interlinking em arquivos de categoria (fora do hero).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCD_SEO_EDITORIAL_VERSION = '3';

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
	$slug     = sanitize_title( (string) $slug );
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
		$post_id = (int) $existing[0];
		// Já migrado nesta versão: só garante Yoast se necessário e sai sem wp_update_post.
		if ( (string) get_post_meta( $post_id, '_ccd_seo_editorial', true ) === CCD_SEO_EDITORIAL_VERSION ) {
			return $post_id;
		}
		$payload['ID'] = $post_id;
		$post_id       = wp_update_post( $payload, true );
	} elseif ( $create_if_missing ) {
		// Respeita lixeira: slug__trashed / slug__trashed-N (não recriar após exclusão).
		if ( ccd_seo_editorial_slug_was_trashed( $slug ) ) {
			return 0;
		}
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
 * Description curta das categorias-hub (texto puro).
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
		wp_update_term(
			(int) $term->term_id,
			'category',
			array(
				'description' => wp_strip_all_tags( (string) $map[ $cat_slug ] ),
			)
		);
	}
}

/**
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
 * Posts-pilar para uma categoria: lista curada (se houver) + recentes da própria categoria.
 * Serve qualquer categoria nova sem configuração.
 *
 * @param WP_Term $term Categoria.
 * @return array<int, array{url:string,title:string}>
 */
function ccd_seo_editorial_category_pillar_items( WP_Term $term ) {
	$items   = array();
	$seen    = array();
	$labels  = ccd_seo_editorial_link_labels();
	$curated = ccd_seo_editorial_hub_slugs();

	if ( isset( $curated[ $term->slug ] ) ) {
		foreach ( $curated[ $term->slug ] as $slug ) {
			$found = get_posts(
				array(
					'name'           => $slug,
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
				)
			);
			if ( empty( $found[0] ) ) {
				continue;
			}
			$post = $found[0];
			$id   = (int) $post->ID;
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$title         = isset( $labels[ $slug ] ) ? $labels[ $slug ] : get_the_title( $post );
			$items[]       = array(
				'url'   => get_permalink( $post ),
				'title' => $title,
			);
			if ( count( $items ) >= 6 ) {
				return $items;
			}
		}
	}

	$more = get_posts(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 6,
			'cat'                 => (int) $term->term_id,
			'post__not_in'        => array_keys( $seen ),
			'orderby'             => 'modified',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	foreach ( $more as $post ) {
		$id = (int) $post->ID;
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = true;
		$items[]     = array(
			'url'   => get_permalink( $post ),
			'title' => get_the_title( $post ),
		);
		if ( count( $items ) >= 6 ) {
			break;
		}
	}

	return $items;
}

/**
 * Markup dos pilares (somente para #page-content).
 *
 * @param string $cat_slug Slug da categoria.
 * @return string
 */
function ccd_seo_editorial_hub_pillars_html( $cat_slug ) {
	$term = get_term_by( 'slug', sanitize_title( (string) $cat_slug ), 'category' );
	if ( ! ( $term instanceof WP_Term ) ) {
		return '';
	}
	$items = ccd_seo_editorial_category_pillar_items( $term );
	if ( ! $items ) {
		return '';
	}
	$html  = '<nav class="ccd-hub-pillars" aria-label="Pilares recomendados">';
	$html .= '<p><strong>Pilares para começar:</strong></p><ul>';
	foreach ( $items as $item ) {
		$html .= sprintf(
			'<li><a href="%s">%s</a></li>',
			esc_url( (string) $item['url'] ),
			esc_html( (string) $item['title'] )
		);
	}
	$html .= '</ul></nav>';
	return $html;
}

/**
 * True se já existe post na lixeira para este slug (WordPress renomeia para slug__trashed).
 *
 * @param string $slug Post slug.
 * @return bool
 */
function ccd_seo_editorial_slug_was_trashed( $slug ) {
	global $wpdb;
	$slug = sanitize_title( (string) $slug );
	if ( $slug === '' || ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->posts ) ) {
		return false;
	}
	if ( ! is_callable( array( $wpdb, 'get_var' ) ) || ! is_callable( array( $wpdb, 'prepare' ) ) ) {
		return false;
	}
	$escaped = is_callable( array( $wpdb, 'esc_like' ) )
		? $wpdb->esc_like( $slug )
		: str_replace( array( '%', '_' ), array( '\\%', '\\_' ), $slug );
	$found   = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'trash' AND post_name LIKE %s LIMIT 1",
			$escaped . '__trashed%'
		)
	);
	return ! empty( $found );
}

/**
 * Migração editorial pesada: nunca no front público (causa stampede/timeout).
 *
 * @return void
 */
function ccd_seo_editorial_apply() {
	if ( function_exists( 'ccd_migration_option_matches' )
		? ccd_migration_option_matches( 'ccd_seo_editorial', CCD_SEO_EDITORIAL_VERSION )
		: get_option( 'ccd_seo_editorial' ) === CCD_SEO_EDITORIAL_VERSION ) {
		return;
	}
	if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	// Só WP-CLI / cron / admin — front-end nunca bloqueia PHP workers.
	$allow = ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() || is_admin();
	if ( ! $allow ) {
		return;
	}
	if ( get_transient( 'ccd_seo_editorial_migrating' ) ) {
		return;
	}
	set_transient( 'ccd_seo_editorial_migrating', 1, 10 * MINUTE_IN_SECONDS );
	// Claim da versão primeiro: evita stampede se o trabalho for lento/falhar.
	if ( function_exists( 'ccd_migration_option_claim' ) ) {
		ccd_migration_option_claim( 'ccd_seo_editorial', CCD_SEO_EDITORIAL_VERSION );
	} else {
		update_option( 'ccd_seo_editorial', CCD_SEO_EDITORIAL_VERSION, false );
	}

	foreach ( ccd_seo_editorial_updates() as $slug => $data ) {
		ccd_seo_editorial_upsert_post( $slug, $data, false );
	}
	foreach ( ccd_seo_editorial_new_posts() as $slug => $data ) {
		ccd_seo_editorial_upsert_post( $slug, $data, true );
	}
	ccd_seo_editorial_sync_hub_descriptions();

	delete_transient( 'ccd_seo_editorial_migrating' );

	if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
		ccd_page_cache_purge_all();
	}
}
add_action( 'init', 'ccd_seo_editorial_apply', 30 );

/**
 * Garante que nada editorial seja impresso no hero (hook Mesmerize).
 */
function ccd_seo_editorial_block_header_output() {
	// Intencionalmente vazio: reserva o slot e documenta que o hero não recebe pilares.
}
add_action( 'mesmerize_after_inner_page_header_content', 'ccd_seo_editorial_block_header_output', 1 );

/**
 * Pilares no início do loop — dentro de #page-content (nunca no .header).
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
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
/* Blindagem: nada de pilares no banner azul (header-wrapper / .header). */
.header-wrapper .ccd-hub-pillars,
.header-wrapper .ccd-category-intro,
.header .ccd-hub-pillars,
.header .ccd-category-intro,
.header .ccd-editorial-links {
	display: none !important;
	height: 0 !important;
	max-height: 0 !important;
	margin: 0 !important;
	padding: 0 !important;
	overflow: hidden !important;
	visibility: hidden !important;
}
/* Conteúdo: pilares só em #page-content */
#page-content .ccd-hub-pillars {
	display: block !important;
	visibility: visible !important;
	height: auto !important;
	max-height: none !important;
	max-width: 1100px;
	margin: 1rem auto 1.5rem;
	padding: 0.85rem 1.25rem;
	color: #2b3a42;
	background: #f7fbfd;
	border: 1px solid #d7e3ea;
	border-radius: 8px;
	box-sizing: border-box;
}
#page-content .ccd-hub-pillars ul,
#page-content .ccd-editorial-links {
	margin: 0.4rem 0 0;
	padding-left: 1.2rem;
}
#page-content .ccd-hub-pillars a,
#page-content ul.ccd-editorial-links a {
	color: #0277bd;
	font-weight: 600;
	text-decoration: none;
}
#page-content .ccd-hub-pillars a:hover,
#page-content ul.ccd-editorial-links a:hover {
	text-decoration: underline;
}
.ccd-editorial-links {
	max-width: 720px;
	margin: 1rem auto 1.5rem;
	padding: 0 1.25rem;
}
CSS
		);
	},
	30
);
