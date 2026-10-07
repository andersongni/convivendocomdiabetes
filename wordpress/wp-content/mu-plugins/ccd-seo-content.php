<?php
/**
 * Plugin Name: CCD SEO Content
 * Description: Preenche meta description, focus keyphrase e alts no acervo; garante o mesmo em posts novos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCD_SEO_CONTENT_VERSION = '2';

/**
 * @return string[]
 */
function ccd_seo_pt_stopwords() {
	return array(
		'a', 'o', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'da', 'do', 'das', 'dos',
		'e', 'em', 'no', 'na', 'nos', 'nas', 'por', 'para', 'com', 'sem', 'sob', 'sobre',
		'que', 'ou', 'ao', 'aos', 'à', 'às', 'se', 'sua', 'seu', 'suas', 'seus', 'meu',
		'minha', 'é', 'ser', 'foi', 'são', 'como', 'mais', 'menos', 'já', 'não', 'sim',
		'the', 'and', 'of', 'in', 'to', 'for',
	);
}

/**
 * @param string $text Texto livre.
 * @param int    $max  Tamanho maximo.
 * @return string
 */
function ccd_seo_truncate_words( $text, $max = 155 ) {
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
	if ( $text === '' ) {
		return '';
	}
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) <= $max ) {
		return $text;
	}
	if ( strlen( $text ) <= $max ) {
		return $text;
	}
	$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	$cut = preg_replace( '/\s+\S*$/u', '', $cut );
	return rtrim( (string) $cut, " \t\n\r\0\x0B.,;:" ) . '…';
}

/**
 * @param string $title Titulo do post.
 * @return string
 */
function ccd_seo_focus_from_title( $title ) {
	$title = mb_strtolower( wp_strip_all_tags( (string) $title ), 'UTF-8' );
	$title = preg_replace( '/[^\p{L}\p{N}\s\-]/u', ' ', $title );
	$parts = preg_split( '/\s+/u', trim( (string) $title ) ) ?: array();
	$stop  = array_fill_keys( ccd_seo_pt_stopwords(), true );
	$keep  = array();
	foreach ( $parts as $part ) {
		if ( $part === '' || isset( $stop[ $part ] ) || ( function_exists( 'mb_strlen' ) ? mb_strlen( $part ) < 2 : strlen( $part ) < 2 ) ) {
			continue;
		}
		$keep[] = $part;
		if ( count( $keep ) >= 4 ) {
			break;
		}
	}
	if ( ! $keep && $parts ) {
		$keep = array_slice( $parts, 0, 3 );
	}
	$focus = trim( implode( ' ', $keep ) );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $focus ) > 60 ) {
		$focus = mb_substr( $focus, 0, 60 );
	}
	return $focus;
}

/**
 * Meta fraca (shortcode, saudação, lista de ingredientes, etc.).
 *
 * @param string $desc Meta atual.
 * @return bool
 */
function ccd_seo_metadesc_is_weak( $desc ) {
	$desc = trim( html_entity_decode( wp_strip_all_tags( (string) $desc ), ENT_QUOTES, 'UTF-8' ) );
	$desc = preg_replace( '/\s+/u', ' ', $desc );
	if ( ! is_string( $desc ) || $desc === '' ) {
		return true;
	}
	$len = function_exists( 'mb_strlen' ) ? mb_strlen( $desc ) : strlen( $desc );
	if ( $len < 70 ) {
		return true;
	}
	if ( preg_match( '/\[(?:wpforms|ccd_|\/?\w+)/i', $desc ) ) {
		return true;
	}
	if ( stripos( $desc, '&nbsp;' ) !== false || str_contains( $desc, "\xc2\xa0" ) ) {
		return true;
	}
	if ( preg_match( '/^(Oi|Ol[aá]|Ingredientes|Blog\s*[—\-]|\.{0,3}\s*$)/iu', $desc ) ) {
		return true;
	}
	return false;
}

/**
 * Extrai trecho útil do corpo (pula saudações / ingredientes).
 *
 * @param string $html Conteúdo.
 * @return string
 */
function ccd_seo_body_snippet( $html ) {
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $html ) ) );
	if ( $text === '' ) {
		return '';
	}
	$parts = preg_split( '/(?<=[.!?])\s+/u', $text ) ?: array( $text );
	$keep  = array();
	foreach ( $parts as $part ) {
		$part = trim( (string) $part );
		if ( $part === '' ) {
			continue;
		}
		if ( preg_match( '/^(Oi|Ol[aá]|Ingredientes|Modo de preparo|Preparo)\b/iu', $part ) ) {
			continue;
		}
		$keep[] = $part;
		$joined = implode( ' ', $keep );
		$len    = function_exists( 'mb_strlen' ) ? mb_strlen( $joined ) : strlen( $joined );
		if ( $len >= 110 ) {
			break;
		}
	}
	$out = trim( implode( ' ', $keep ) );
	if ( $out === '' ) {
		$out = $text;
	}
	return ccd_seo_truncate_words( $out, 155 );
}

/**
 * @param WP_Post $post Post.
 * @return string
 */
function ccd_seo_metadesc_from_post( WP_Post $post ) {
	if ( function_exists( 'ccd_seo_post_metadescs' ) ) {
		$map = ccd_seo_post_metadescs();
		if ( isset( $map[ $post->post_name ] ) ) {
			return $map[ $post->post_name ];
		}
	}
	if ( function_exists( 'ccd_seo_page_metadescs' ) && $post->post_type === 'page' ) {
		$map = ccd_seo_page_metadescs();
		if ( isset( $map[ $post->post_name ] ) ) {
			return $map[ $post->post_name ];
		}
	}

	$excerpt = trim( (string) $post->post_excerpt );
	if ( $excerpt !== '' && ! ccd_seo_metadesc_is_weak( $excerpt ) ) {
		return ccd_seo_truncate_words( $excerpt, 155 );
	}

	$body = ccd_seo_body_snippet( $post->post_content );
	if ( $body !== '' && ! ccd_seo_metadesc_is_weak( $body ) ) {
		return $body;
	}

	$title = trim( wp_strip_all_tags( (string) $post->post_title ) );
	if ( preg_match( '/\b(receita|bolo|cupcake|torta|biscoito|p[aã]o|brigadeiro|pudim)\b/iu', $title ) ) {
		return ccd_seo_truncate_words(
			$title . ' — receita diet sem açúcar do blog Convivendo com Diabetes.',
			155
		);
	}

	return ccd_seo_truncate_words( $title . ' — Convivendo com Diabetes', 155 );
}

/**
 * @param int    $post_id Post/attachment ID.
 * @param string $desc    Description.
 * @param string $focus   Focus keyphrase.
 * @return void
 */
function ccd_seo_sync_yoast_fields( $post_id, $desc, $focus ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return;
	}
	if ( $desc !== '' ) {
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );
	}
	if ( $focus !== '' ) {
		update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus );
	}

	global $wpdb;
	$table  = $wpdb->prefix . 'yoast_indexable';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( ! $exists ) {
		return;
	}

	$data = array( 'updated_at' => current_time( 'mysql' ) );
	$fmt  = array( '%s' );
	if ( $desc !== '' ) {
		$data['description'] = $desc;
		$fmt[]               = '%s';
	}
	if ( $focus !== '' ) {
		$data['primary_focus_keyword'] = $focus;
		$fmt[]                         = '%s';
	}
	$wpdb->update(
		$table,
		$data,
		array(
			'object_type' => 'post',
			'object_id'   => $post_id,
		),
		$fmt,
		array( '%s', '%d' )
	);
}

/**
 * Garante meta + focus de um post/page.
 *
 * @param int  $post_id Post ID.
 * @param bool $force   Sobrescreve valores existentes.
 * @return bool True se alterou algo.
 */
function ccd_seo_ensure_post( $post_id, $force = false ) {
	$post = get_post( (int) $post_id );
	if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return false;
	}
	if ( ! in_array( $post->post_status, array( 'publish', 'future', 'draft', 'pending', 'private' ), true ) ) {
		return false;
	}

	$changed = false;
	$desc    = (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
	$focus   = (string) get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true );

	$new_desc  = $desc;
	$new_focus = $focus;
	$is_home   = (int) get_option( 'page_on_front' ) === (int) $post->ID;
	$weak      = ccd_seo_metadesc_is_weak( $desc );

	if ( $force || $desc === '' || $weak ) {
		if ( $is_home && function_exists( 'ccd_seo_home_metadesc' ) ) {
			$new_desc = ccd_seo_home_metadesc();
		} else {
			$new_desc = ccd_seo_metadesc_from_post( $post );
		}
	}
	if ( $force || $focus === '' ) {
		$new_focus = $is_home ? 'convivendo com diabetes' : ccd_seo_focus_from_title( $post->post_title );
	}

	if ( $new_desc !== $desc || $new_focus !== $focus ) {
		ccd_seo_sync_yoast_fields( $post->ID, $new_desc, $new_focus );
		$changed = true;
	}

	// Alts vazios no HTML do conteudo, a partir do attachment.
	if ( is_string( $post->post_content ) && stripos( $post->post_content, 'wp-image-' ) !== false ) {
		$content = preg_replace_callback(
			'/<img\b[^>]*\bwp-image-(\d+)\b[^>]*>/i',
			static function ( $m ) {
				$id  = (int) $m[1];
				$tag = $m[0];
				$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
				if ( $alt === '' ) {
					$att = get_post( $id );
					$alt = $att instanceof WP_Post ? ccd_seo_alt_from_attachment( $att ) : '';
					if ( $alt !== '' ) {
						update_post_meta( $id, '_wp_attachment_image_alt', $alt );
					}
				}
				if ( $alt === '' ) {
					return $tag;
				}
				$alt_esc = esc_attr( $alt );
				if ( preg_match( '/\balt=(["\'])\s*\1/i', $tag ) || preg_match( '/\balt=(["\'])\1/i', $tag ) ) {
					return (string) preg_replace( '/\balt=(["\'])\s*\1/i', 'alt="' . $alt_esc . '"', $tag, 1 );
				}
				if ( ! preg_match( '/\balt=/i', $tag ) ) {
					return (string) preg_replace( '/<img\b/i', '<img alt="' . $alt_esc . '"', $tag, 1 );
				}
				return $tag;
			},
			$post->post_content
		);
		if ( is_string( $content ) && $content !== $post->post_content ) {
			wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => $content,
				)
			);
			$changed = true;
		}
	}

	return $changed;
}

/**
 * @param WP_Post $att Attachment.
 * @return string
 */
function ccd_seo_alt_from_attachment( WP_Post $att ) {
	$title = trim( wp_strip_all_tags( (string) $att->post_title ) );
	$title = preg_replace( '/[-_]+/', ' ', $title );
	$title = trim( preg_replace( '/\s+/u', ' ', (string) $title ) );
	if ( $title === '' || preg_match( '/^\d+$/', $title ) ) {
		$file = basename( (string) get_attached_file( $att->ID ) );
		$title = preg_replace( '/\.[^.]+$/', '', $file );
		$title = preg_replace( '/[-_]+/', ' ', (string) $title );
		$title = trim( preg_replace( '/\s+/u', ' ', (string) $title ) );
	}
	if ( $title === '' ) {
		return 'Imagem do site Convivendo com Diabetes';
	}
	// Evita alts genericos de hash.
	if ( preg_match( '/^[a-f0-9]{16,}$/i', str_replace( ' ', '', $title ) ) ) {
		$parent = $att->post_parent ? get_post( (int) $att->post_parent ) : null;
		if ( $parent instanceof WP_Post && $parent->post_title ) {
			return ccd_seo_truncate_words( $parent->post_title, 100 );
		}
		return 'Imagem ilustrativa — Convivendo com Diabetes';
	}
	return ccd_seo_truncate_words( $title, 120 );
}

/**
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function ccd_seo_ensure_attachment_alt( $attachment_id ) {
	$att = get_post( (int) $attachment_id );
	if ( ! $att instanceof WP_Post || $att->post_type !== 'attachment' ) {
		return false;
	}
	if ( strpos( (string) $att->post_mime_type, 'image/' ) !== 0 ) {
		return false;
	}
	$current = (string) get_post_meta( $att->ID, '_wp_attachment_image_alt', true );
	if ( $current !== '' ) {
		return false;
	}
	$alt = ccd_seo_alt_from_attachment( $att );
	update_post_meta( $att->ID, '_wp_attachment_image_alt', $alt );
	return true;
}

/**
 * Backfill do acervo (idempotente).
 *
 * @return array{posts:int,attachments:int}
 */
function ccd_seo_content_backfill() {
	$posts_changed = 0;
	$q             = new WP_Query(
		array(
			'post_type'              => array( 'post', 'page' ),
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $q->posts as $pid ) {
		if ( ccd_seo_ensure_post( (int) $pid, false ) ) {
			++$posts_changed;
		}
	}

	$atts_changed = 0;
	$aq           = new WP_Query(
		array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'post_mime_type'         => 'image',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $aq->posts as $aid ) {
		if ( ccd_seo_ensure_attachment_alt( (int) $aid ) ) {
			++$atts_changed;
		}
	}

	return array(
		'posts'       => $posts_changed,
		'attachments' => $atts_changed,
	);
}

/**
 * Backfill em lotes (seguro no boot / primeiros requests).
 *
 * @param int $batch Tamanho do lote.
 * @return bool True se concluiu todo o acervo.
 */
function ccd_seo_content_backfill_step( $batch = 40 ) {
	$state = get_option(
		'ccd_seo_content_state',
		array(
			'phase'       => 'posts',
			'offset'      => 0,
			'posts'       => 0,
			'attachments' => 0,
		)
	);
	if ( ! is_array( $state ) ) {
		$state = array(
			'phase'       => 'posts',
			'offset'      => 0,
			'posts'       => 0,
			'attachments' => 0,
		);
	}

	$batch = max( 10, (int) $batch );

	if ( ( $state['phase'] ?? '' ) === 'posts' ) {
		$q = new WP_Query(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'         => $batch,
				'offset'                 => (int) $state['offset'],
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! $q->posts ) {
			$state['phase']  = 'attachments';
			$state['offset'] = 0;
		} else {
			foreach ( $q->posts as $pid ) {
				if ( ccd_seo_ensure_post( (int) $pid, false ) ) {
					++$state['posts'];
				}
			}
			$state['offset'] = (int) $state['offset'] + count( $q->posts );
			update_option( 'ccd_seo_content_state', $state, false );
			return false;
		}
	}

	if ( ( $state['phase'] ?? '' ) === 'attachments' ) {
		$q = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => 'image',
				'posts_per_page'         => $batch,
				'offset'                 => (int) $state['offset'],
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! $q->posts ) {
			update_option( 'ccd_seo_content', CCD_SEO_CONTENT_VERSION, false );
			update_option(
				'ccd_seo_content_last_run',
				array(
					'at'          => gmdate( 'c' ),
					'posts'       => (int) $state['posts'],
					'attachments' => (int) $state['attachments'],
				),
				false
			);
			delete_option( 'ccd_seo_content_state' );
			if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
				ccd_page_cache_purge_all();
			}
			return true;
		}
		foreach ( $q->posts as $aid ) {
			if ( ccd_seo_ensure_attachment_alt( (int) $aid ) ) {
				++$state['attachments'];
			}
		}
		$state['offset'] = (int) $state['offset'] + count( $q->posts );
		update_option( 'ccd_seo_content_state', $state, false );
		return false;
	}

	return true;
}

add_action(
	'init',
	static function () {
		if ( get_option( 'ccd_seo_content' ) === CCD_SEO_CONTENT_VERSION ) {
			return;
		}
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		// Ate 3 lotes por request para nao estourar timeout no boot.
		for ( $i = 0; $i < 3; $i++ ) {
			if ( ccd_seo_content_backfill_step( 50 ) ) {
				break;
			}
		}
	},
	20
);

add_action(
	'save_post',
	static function ( $post_id, $post, $update ) {
		unset( $update );
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		ccd_seo_ensure_post( (int) $post_id, false );
	},
	30,
	3
);

add_action(
	'add_attachment',
	static function ( $attachment_id ) {
		ccd_seo_ensure_attachment_alt( (int) $attachment_id );
	},
	20
);

add_action(
	'edit_attachment',
	static function ( $attachment_id ) {
		ccd_seo_ensure_attachment_alt( (int) $attachment_id );
	},
	20
);

/**
 * Aviso no editor se ainda faltar algo (fallback).
 */
add_action(
	'admin_notices',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->base, array( 'post' ), true ) ) {
			return;
		}
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id <= 0 ) {
			return;
		}
		$desc  = (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		$focus = (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
		if ( $desc !== '' && $focus !== '' ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>CCD SEO:</strong> este conteúdo ainda precisa de meta description e/ou focus keyphrase no Yoast. Salve o post para o preenchimento automático ou complete manualmente.</p></div>';
	}
);
