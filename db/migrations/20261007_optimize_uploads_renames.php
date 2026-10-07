<?php
/**
 * Aplica renomes PNG→JPEG gerados por scripts/optimize-uploads.py
 * (arquivo uploads/ccd-optimize-renames.json).
 *
 *   php db/migrations/20261007_optimize_uploads_renames.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	$candidates = array(
		dirname( __DIR__, 2 ) . '/wordpress/wp-load.php',
		'/var/www/html/wp-load.php',
	);
	foreach ( $candidates as $wp_load ) {
		if ( is_readable( $wp_load ) ) {
			require $wp_load;
			break;
		}
	}
}
if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "wp-load.php not found\n" );
	exit( 1 );
}

$map_file = WP_CONTENT_DIR . '/uploads/ccd-optimize-renames.json';
if ( ! is_readable( $map_file ) ) {
	fwrite( STDERR, "map not found: {$map_file}\n" );
	exit( 1 );
}

$renames = json_decode( (string) file_get_contents( $map_file ), true );
if ( ! is_array( $renames ) || ! $renames ) {
	fwrite( STDERR, "empty rename map\n" );
	exit( 1 );
}

global $wpdb;

$updated_meta    = 0;
$updated_posts   = 0;
$updated_guid    = 0;
$updated_options = 0;

foreach ( $renames as $row ) {
	$from = isset( $row['from'] ) ? (string) $row['from'] : '';
	$to   = isset( $row['to'] ) ? (string) $row['to'] : '';
	if ( $from === '' || $to === '' || $from === $to ) {
		continue;
	}

	// _wp_attached_file
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
			$from
		)
	);
	foreach ( $ids as $post_id ) {
		$post_id = (int) $post_id;
		update_post_meta( $post_id, '_wp_attached_file', $to );
		wp_update_post(
			array(
				'ID'             => $post_id,
				'post_mime_type' => 'image/jpeg',
			)
		);
		$meta = wp_get_attachment_metadata( $post_id );
		if ( is_array( $meta ) ) {
			$meta['file'] = $to;
			if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $size => $info ) {
					if ( empty( $info['file'] ) || ! is_string( $info['file'] ) ) {
						continue;
					}
					$base = wp_basename( $from );
					$newb = wp_basename( $to );
					if ( $info['file'] === $base ) {
						$meta['sizes'][ $size ]['file'] = $newb;
						$meta['sizes'][ $size ]['mime-type'] = 'image/jpeg';
					}
				}
			}
			wp_update_attachment_metadata( $post_id, $meta );
		}
		$guid = get_post_field( 'guid', $post_id );
		if ( is_string( $guid ) && str_contains( $guid, $from ) ) {
			$wpdb->update(
				$wpdb->posts,
				array( 'guid' => str_replace( $from, $to, $guid ) ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
			++$updated_guid;
		}
		++$updated_meta;
	}

	// Conteúdo de posts / Elementor etc. (path relativo e URL).
	$like_from = '%' . $wpdb->esc_like( $from ) . '%';
	$posts     = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s",
			$like_from
		)
	);
	foreach ( $posts as $post ) {
		$new_content = str_replace( $from, $to, $post->post_content );
		if ( $new_content !== $post->post_content ) {
			$wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $new_content ),
				array( 'ID' => (int) $post->ID ),
				array( '%s' ),
				array( '%d' )
			);
			++$updated_posts;
		}
	}

	// postmeta serializado/URL (Elementor _elementor_data etc.)
	$metas = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
			$like_from
		)
	);
	foreach ( $metas as $meta ) {
		$val = $meta->meta_value;
		if ( ! is_string( $val ) || ! str_contains( $val, $from ) ) {
			continue;
		}
		$new_val = str_replace( $from, $to, $val );
		if ( $new_val === $val ) {
			continue;
		}
		$wpdb->update(
			$wpdb->postmeta,
			array( 'meta_value' => $new_val ),
			array( 'meta_id' => (int) $meta->meta_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	// theme_mods / options (hero Mesmerize, logos, etc.)
	$opts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_value LIKE %s",
			$like_from
		)
	);
	foreach ( $opts as $opt ) {
		$val = $opt->option_value;
		if ( ! is_string( $val ) || ! str_contains( $val, $from ) ) {
			continue;
		}
		$new_val = str_replace( $from, $to, $val );
		if ( $new_val === $val ) {
			continue;
		}
		$wpdb->update(
			$wpdb->options,
			array( 'option_value' => $new_val ),
			array( 'option_id' => (int) $opt->option_id ),
			array( '%s' ),
			array( '%d' )
		);
		wp_cache_delete( $opt->option_name, 'options' );
		++$updated_options;
	}
}

if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
	ccd_page_cache_purge_all();
}

echo 'OK: attachments=' . $updated_meta
	. ' guid=' . $updated_guid
	. ' posts_content=' . $updated_posts
	. ' options=' . $updated_options
	. ' renames=' . count( $renames ) . "\n";
