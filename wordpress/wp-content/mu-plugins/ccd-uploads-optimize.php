<?php
/**
 * Plugin Name: CCD Uploads Optimize
 * Description: Otimiza a biblioteca de imagens (backup → comprimir → restaurar / excluir backup). Tools → Otimizar uploads.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCD_UO_VERSION   = '1.0.0';

/**
 * PNG→JPEG: se o .png sumiu na otimizacao mas o .jpg existe, redireciona.
 * Cobre theme_mods/hero e URLs antigas que ainda pedem .png.
 */
add_action(
	'init',
	static function () {
		if ( empty( $_SERVER['REQUEST_URI'] ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$path = (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( ! preg_match( '#/wp-content/uploads/(.+\.png)$#i', $path, $m ) ) {
			return;
		}
		$rel = rawurldecode( $m[1] );
		if ( str_contains( $rel, '..' ) ) {
			return;
		}
		$upload = wp_upload_dir( null, false );
		if ( empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
			return;
		}
		$png = trailingslashit( (string) $upload['basedir'] ) . str_replace( '\\', '/', $rel );
		if ( is_file( $png ) ) {
			return;
		}
		$jpg = (string) preg_replace( '/\.png$/i', '.jpg', $png );
		if ( ! is_file( $jpg ) ) {
			return;
		}
		$dest = trailingslashit( (string) $upload['baseurl'] ) . (string) preg_replace( '/\.png$/i', '.jpg', $rel );
		wp_safe_redirect( $dest, 301 );
		exit;
	},
	0
);
const CCD_UO_OPTION    = 'ccd_uploads_optimize_state';
const CCD_UO_QUALITY   = 82;
const CCD_UO_MAX_EDGE  = 1920;
const CCD_UO_MIN_BYTES = 200000;
const CCD_UO_BATCH     = 12;

/**
 * Diretório de backup (fora de uploads/, para não ser reprocessado).
 *
 * @return string
 */
function ccd_uo_backup_root() {
	return trailingslashit( WP_CONTENT_DIR ) . 'ccd-uploads-optimize-backup';
}

/**
 * @return string
 */
function ccd_uo_manifest_path() {
	return ccd_uo_backup_root() . '/manifest.json';
}

/**
 * @return string
 */
function ccd_uo_uploads_root() {
	$upload = wp_upload_dir( null, false );
	return ! empty( $upload['basedir'] ) ? (string) $upload['basedir'] : ( WP_CONTENT_DIR . '/uploads' );
}

/**
 * @return bool
 */
function ccd_uo_backup_exists() {
	return is_readable( ccd_uo_manifest_path() );
}

/**
 * @param mixed $bytes Bytes.
 * @return string
 */
function ccd_uo_human_bytes( $bytes ) {
	$bytes = (float) $bytes;
	foreach ( array( 'B', 'KB', 'MB', 'GB' ) as $unit ) {
		if ( $bytes < 1024 || 'GB' === $unit ) {
			return ( 'B' === $unit ? (string) (int) $bytes : number_format( $bytes, 1 ) ) . $unit;
		}
		$bytes /= 1024;
	}
	return '0B';
}

/**
 * Estado do job (option).
 *
 * @return array<string,mixed>
 */
function ccd_uo_get_state() {
	$state = get_option( CCD_UO_OPTION, array() );
	return is_array( $state ) ? $state : array();
}

/**
 * @param array<string,mixed> $state State.
 */
function ccd_uo_set_state( array $state ) {
	update_option( CCD_UO_OPTION, $state, false );
}

/**
 * Garante pasta de backup + proteção básica.
 *
 * @return true|\WP_Error
 */
function ccd_uo_ensure_backup_dir() {
	$dir = ccd_uo_backup_root();
	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'ccd_uo_mkdir', 'Não foi possível criar o diretório de backup.' );
	}
	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $index, "<?php\n// Silence is golden.\n" );
	}
	$ht = $dir . '/.htaccess';
	if ( ! file_exists( $ht ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $ht, "Require all denied\n" );
	}
	return true;
}

/**
 * Lista relativa de candidatos (jpg/png/webp >= min bytes).
 *
 * @return string[] Paths relativos a uploads/.
 */
function ccd_uo_scan_candidates() {
	$root = ccd_uo_uploads_root();
	if ( ! is_dir( $root ) ) {
		return array();
	}

	$out  = array();
	$min  = CCD_UO_MIN_BYTES;
	$skip = array( 'ccd-optimize-renames.json' );

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		/** @var SplFileInfo $file */
		if ( ! $file->isFile() ) {
			continue;
		}
		$name = $file->getFilename();
		if ( in_array( $name, $skip, true ) || str_contains( $name, '.ccdtmp' ) ) {
			continue;
		}
		$ext = strtolower( $file->getExtension() );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
			continue;
		}
		$size = $file->getSize();
		if ( $size < $min ) {
			continue;
		}
		$full = $file->getPathname();
		$rel  = ltrim( str_replace( '\\', '/', substr( $full, strlen( $root ) ) ), '/' );
		if ( $rel === '' || str_starts_with( $rel, 'ccd-uploads-optimize-backup/' ) ) {
			continue;
		}
		$out[] = array(
			'rel'  => $rel,
			'size' => (int) $size,
		);
	}

	usort(
		$out,
		static function ( $a, $b ) {
			return $b['size'] <=> $a['size'];
		}
	);

	return $out;
}

/**
 * Copia um arquivo para o backup (preserva path relativo).
 *
 * @param string $rel Relative path under uploads.
 * @return true|\WP_Error
 */
function ccd_uo_backup_one( $rel ) {
	$rel = ltrim( str_replace( '\\', '/', (string) $rel ), '/' );
	$src = trailingslashit( ccd_uo_uploads_root() ) . $rel;
	if ( ! is_readable( $src ) ) {
		return new WP_Error( 'ccd_uo_missing', 'Arquivo ausente: ' . $rel );
	}
	$dest = trailingslashit( ccd_uo_backup_root() ) . 'files/' . $rel;
	$ok   = ccd_uo_ensure_backup_dir();
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	if ( ! wp_mkdir_p( dirname( $dest ) ) ) {
		return new WP_Error( 'ccd_uo_mkdir', 'Falha ao criar pasta no backup.' );
	}
	if ( ! file_exists( $dest ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		if ( ! copy( $src, $dest ) ) {
			return new WP_Error( 'ccd_uo_copy', 'Falha ao copiar para backup: ' . $rel );
		}
	}
	return true;
}

/**
 * PNG tem canal alpha útil? (quase opaco → false → pode virar JPEG).
 *
 * @param string $file Absolute path.
 * @return bool
 */
function ccd_uo_png_has_useful_alpha( $file ) {
	if ( class_exists( 'Imagick' ) ) {
		try {
			$im = new Imagick( $file );
			if ( ! $im->getImageAlphaChannel() ) {
				$im->clear();
				return false;
			}
			$w    = $im->getImageWidth();
			$h    = $im->getImageHeight();
			$step = max( 1, (int) floor( min( $w, $h ) / 128 ) );
			$total  = 0;
			$opaque = 0;
			for ( $y = 0; $y < $h; $y += $step ) {
				for ( $x = 0; $x < $w; $x += $step ) {
					$pixel = $im->getImagePixelColor( $x, $y );
					$a     = (int) round( $pixel->getColorValue( Imagick::COLOR_ALPHA ) * 255 );
					++$total;
					if ( $a >= 250 ) {
						++$opaque;
					}
				}
			}
			$im->clear();
			if ( $total < 1 ) {
				return true;
			}
			return ( $opaque / $total ) < 0.995;
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// GD abaixo.
		}
	}

	if ( ! function_exists( 'imagecreatefrompng' ) ) {
		return true;
	}
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$gd = @imagecreatefrompng( $file );
	if ( ! $gd ) {
		return true;
	}
	$w      = imagesx( $gd );
	$h      = imagesy( $gd );
	$total  = 0;
	$opaque = 0;
	$step   = max( 1, (int) floor( min( $w, $h ) / 128 ) );
	for ( $y = 0; $y < $h; $y += $step ) {
		for ( $x = 0; $x < $w; $x += $step ) {
			$rgba = imagecolorat( $gd, $x, $y );
			++$total;
			$a = ( $rgba & 0x7F000000 ) >> 24;
			if ( 0 === $a ) {
				++$opaque;
			}
		}
	}
	imagedestroy( $gd );
	if ( $total < 1 ) {
		return true;
	}
	return ( $opaque / $total ) < 0.995;
}

/**
 * Otimiza um arquivo; faz backup antes. Retorna info da mudança ou null.
 *
 * @param string $rel Relative path.
 * @return array<string,mixed>|\WP_Error|null
 */
function ccd_uo_optimize_one( $rel ) {
	$rel  = ltrim( str_replace( '\\', '/', (string) $rel ), '/' );
	$root = trailingslashit( ccd_uo_uploads_root() );
	$src  = $root . $rel;
	if ( ! is_readable( $src ) ) {
		return null;
	}

	$old_size = (int) filesize( $src );
	if ( $old_size < CCD_UO_MIN_BYTES ) {
		return null;
	}

	$backed = ccd_uo_backup_one( $rel );
	if ( is_wp_error( $backed ) ) {
		return $backed;
	}

	$editor = wp_get_image_editor( $src );
	if ( is_wp_error( $editor ) ) {
		return null; // skip ilegível
	}

	$size = $editor->get_size();
	if ( is_array( $size ) && ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
		$max = CCD_UO_MAX_EDGE;
		if ( (int) $size['width'] > $max || (int) $size['height'] > $max ) {
			$resized = $editor->resize( $max, $max, false );
			if ( is_wp_error( $resized ) ) {
				return null;
			}
		}
	}
	$editor->set_quality( CCD_UO_QUALITY );

	$ext      = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
	$dest_rel = $rel;
	$dest_abs = $src;
	$mime     = 'image/jpeg';
	$renamed  = false;

	if ( in_array( $ext, array( 'jpg', 'jpeg' ), true ) ) {
		$mime = 'image/jpeg';
	} elseif ( 'webp' === $ext ) {
		$mime = 'image/webp';
	} elseif ( 'png' === $ext ) {
		if ( ccd_uo_png_has_useful_alpha( $src ) ) {
			$mime = 'image/png';
		} else {
			$mime     = 'image/jpeg';
			$dest_rel = (string) preg_replace( '/\.png$/i', '.jpg', $rel );
			$dest_abs = $root . $dest_rel;
			$renamed  = ( $dest_rel !== $rel );
			if ( $renamed && file_exists( $dest_abs ) ) {
				return null;
			}
		}
	} else {
		return null;
	}

	$out_ext = 'jpg';
	if ( 'image/png' === $mime ) {
		$out_ext = 'png';
	} elseif ( 'image/webp' === $mime ) {
		$out_ext = 'webp';
	}
	$tmp   = $dest_abs . '.ccdopt.' . $out_ext;
	$saved = $editor->save( $tmp, $mime );
	if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
		if ( file_exists( $tmp ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $tmp );
		}
		return null;
	}

	$new_path = $saved['path'];
	$new_size = (int) filesize( $new_path );
	if ( $new_size < 1 || $new_size >= (int) ( $old_size * 0.98 ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@unlink( $new_path );
		return null;
	}

	if ( $renamed ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rename
		if ( ! rename( $new_path, $dest_abs ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			if ( ! copy( $new_path, $dest_abs ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $new_path );
				return new WP_Error( 'ccd_uo_rename', 'Falha ao renomear: ' . $rel );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $new_path );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@unlink( $src );
	} else {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rename
		if ( ! rename( $new_path, $src ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			if ( ! copy( $new_path, $src ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $new_path );
				return new WP_Error( 'ccd_uo_replace', 'Falha ao substituir: ' . $rel );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $new_path );
		}
	}

	return array(
		'from'      => $rel,
		'to'        => $dest_rel,
		'old_bytes' => $old_size,
		'new_bytes' => $new_size,
		'renamed'   => $renamed,
	);
}

/**
 * Aplica renomes PNG→JPEG no banco (anexos + conteúdo).
 *
 * @param array<int,array<string,string>> $renames List of from/to.
 * @return array{attachments:int,posts:int}
 */
function ccd_uo_apply_renames( array $renames ) {
	global $wpdb;
	$attachments = 0;
	$posts       = 0;

	foreach ( $renames as $row ) {
		$from = isset( $row['from'] ) ? (string) $row['from'] : '';
		$to   = isset( $row['to'] ) ? (string) $row['to'] : '';
		if ( $from === '' || $to === '' || $from === $to ) {
			continue;
		}

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
				if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
					$base = wp_basename( $from );
					$newb = wp_basename( $to );
					foreach ( $meta['sizes'] as $size => $info ) {
						if ( empty( $info['file'] ) || ! is_string( $info['file'] ) ) {
							continue;
						}
						if ( $info['file'] === $base ) {
							$meta['sizes'][ $size ]['file']      = $newb;
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
			}
			++$attachments;
		}

		$like  = '%' . $wpdb->esc_like( $from ) . '%';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s",
				$like
			)
		);
		foreach ( $rows as $post ) {
			$new_content = str_replace( $from, $to, $post->post_content );
			if ( $new_content !== $post->post_content ) {
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $new_content ),
					array( 'ID' => (int) $post->ID ),
					array( '%s' ),
					array( '%d' )
				);
				++$posts;
			}
		}

		$metas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
				$like
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
	}

	return array(
		'attachments' => $attachments,
		'posts'       => $posts,
	);
}

/**
 * Reverte renomes no banco (to → from).
 *
 * @param array<int,array<string,string>> $renames Renames.
 */
function ccd_uo_revert_renames( array $renames ) {
	$reversed = array();
	foreach ( $renames as $row ) {
		if ( empty( $row['from'] ) || empty( $row['to'] ) || $row['from'] === $row['to'] ) {
			continue;
		}
		$reversed[] = array(
			'from' => (string) $row['to'],
			'to'   => (string) $row['from'],
		);
	}
	if ( $reversed ) {
		// mime volta para png quando to termina em .png
		global $wpdb;
		foreach ( $reversed as $row ) {
			$from = $row['from'];
			$to   = $row['to'];
			$ids  = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
					$from
				)
			);
			foreach ( $ids as $post_id ) {
				$mime = str_ends_with( strtolower( $to ), '.png' ) ? 'image/png' : 'image/jpeg';
				wp_update_post(
					array(
						'ID'             => (int) $post_id,
						'post_mime_type' => $mime,
					)
				);
			}
		}
		ccd_uo_apply_renames( $reversed );
	}
}

/**
 * Restaura arquivos a partir do backup + reverte DB.
 *
 * @return true|\WP_Error
 */
function ccd_uo_restore_backup() {
	$manifest_file = ccd_uo_manifest_path();
	if ( ! is_readable( $manifest_file ) ) {
		return new WP_Error( 'ccd_uo_no_backup', 'Nenhum backup encontrado.' );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$manifest = json_decode( (string) file_get_contents( $manifest_file ), true );
	if ( ! is_array( $manifest ) || empty( $manifest['files'] ) || ! is_array( $manifest['files'] ) ) {
		return new WP_Error( 'ccd_uo_bad_manifest', 'Manifesto de backup inválido.' );
	}

	$uploads = trailingslashit( ccd_uo_uploads_root() );
	$bak     = trailingslashit( ccd_uo_backup_root() ) . 'files/';
	$renames = array();

	foreach ( $manifest['files'] as $row ) {
		if ( empty( $row['from'] ) ) {
			continue;
		}
		$from = (string) $row['from'];
		$to   = ! empty( $row['to'] ) ? (string) $row['to'] : $from;
		$src  = $bak . $from;
		if ( ! is_readable( $src ) ) {
			continue;
		}
		$dest = $uploads . $from;
		if ( ! wp_mkdir_p( dirname( $dest ) ) ) {
			return new WP_Error( 'ccd_uo_restore_mkdir', 'Falha ao restaurar pasta: ' . $from );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		if ( ! copy( $src, $dest ) ) {
			return new WP_Error( 'ccd_uo_restore_copy', 'Falha ao restaurar: ' . $from );
		}
		if ( $to !== $from && file_exists( $uploads . $to ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $uploads . $to );
		}
		if ( $to !== $from ) {
			$renames[] = array(
				'from' => $from,
				'to'   => $to,
			);
		}
	}

	if ( $renames ) {
		ccd_uo_revert_renames( $renames );
	}

	if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
		ccd_page_cache_purge_all();
	}

	return true;
}

/**
 * Remove o diretório de backup recursivamente.
 *
 * @param string $dir Directory.
 * @return bool
 */
function ccd_uo_rrmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return true;
	}
	$items = scandir( $dir );
	if ( ! is_array( $items ) ) {
		return false;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			ccd_uo_rrmdir( $path );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $path );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	return @rmdir( $dir );
}

/**
 * @return true|\WP_Error
 */
function ccd_uo_delete_backup() {
	$dir = ccd_uo_backup_root();
	if ( ! is_dir( $dir ) ) {
		delete_option( CCD_UO_OPTION );
		return true;
	}
	if ( ! ccd_uo_rrmdir( $dir ) ) {
		return new WP_Error( 'ccd_uo_delete', 'Não foi possível excluir o backup por completo.' );
	}
	delete_option( CCD_UO_OPTION );
	return true;
}

/**
 * Grava/atualiza manifesto.
 *
 * @param array<string,mixed> $manifest Manifest.
 * @return true|\WP_Error
 */
function ccd_uo_write_manifest( array $manifest ) {
	$ok = ccd_uo_ensure_backup_dir();
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $json ) ) {
		return new WP_Error( 'ccd_uo_json', 'Falha ao serializar manifesto.' );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	if ( false === file_put_contents( ccd_uo_manifest_path(), $json ) ) {
		return new WP_Error( 'ccd_uo_manifest_write', 'Falha ao gravar manifesto.' );
	}
	return true;
}

/**
 * @return array<string,mixed>
 */
function ccd_uo_read_manifest() {
	$path = ccd_uo_manifest_path();
	if ( ! is_readable( $path ) ) {
		return array();
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$data = json_decode( (string) file_get_contents( $path ), true );
	return is_array( $data ) ? $data : array();
}

/**
 * Um passo do job AJAX.
 *
 * @param string $action start|step|restore|delete.
 * @return array<string,mixed>|\WP_Error
 */
function ccd_uo_handle( $action ) {
	if ( 'restore' === $action ) {
		$result = ccd_uo_restore_backup();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'done'    => true,
			'message' => 'Backup restaurado com sucesso.',
		);
	}

	if ( 'delete' === $action ) {
		$result = ccd_uo_delete_backup();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'done'    => true,
			'message' => 'Backup excluído.',
		);
	}

	if ( 'start' === $action ) {
		if ( ccd_uo_backup_exists() ) {
			return new WP_Error(
				'ccd_uo_backup_exists',
				'Já existe um backup. Restaure ou exclua-o antes de otimizar de novo.'
			);
		}
		$ok = ccd_uo_ensure_backup_dir();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$candidates = ccd_uo_scan_candidates();
		if ( ! $candidates ) {
			return array(
				'done'    => true,
				'phase'   => 'done',
				'total'   => 0,
				'index'   => 0,
				'message' => 'Nenhum arquivo candidato (>= 200KB). Nada a fazer.',
			);
		}
		$state = array(
			'phase'      => 'optimize',
			'candidates' => $candidates,
			'index'      => 0,
			'changed'    => 0,
			'saved'      => 0,
			'skipped'    => 0,
			'errors'     => array(),
			'renames'    => array(),
			'files'      => array(),
			'started'    => time(),
		);
		ccd_uo_set_state( $state );
		ccd_uo_write_manifest(
			array(
				'version'   => CCD_UO_VERSION,
				'created'   => gmdate( 'c' ),
				'quality'   => CCD_UO_QUALITY,
				'max_edge'  => CCD_UO_MAX_EDGE,
				'min_bytes' => CCD_UO_MIN_BYTES,
				'files'     => array(),
				'stats'     => array(),
			)
		);
		return array(
			'done'    => false,
			'phase'   => 'optimize',
			'total'   => count( $candidates ),
			'index'   => 0,
			'message' => sprintf( 'Encontrados %d arquivos candidatos. Iniciando…', count( $candidates ) ),
		);
	}

	if ( 'step' !== $action ) {
		return new WP_Error( 'ccd_uo_action', 'Ação inválida.' );
	}

	$state = ccd_uo_get_state();
	if ( empty( $state['candidates'] ) || ! is_array( $state['candidates'] ) ) {
		return new WP_Error( 'ccd_uo_no_job', 'Nenhum job ativo. Clique em Backup + Otimizar.' );
	}

	$candidates = $state['candidates'];
	$total      = count( $candidates );
	$index      = isset( $state['index'] ) ? (int) $state['index'] : 0;
	$batch_end  = min( $total, $index + CCD_UO_BATCH );

	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	for ( $i = $index; $i < $batch_end; $i++ ) {
		$item = $candidates[ $i ];
		$rel  = is_array( $item ) ? (string) $item['rel'] : (string) $item;
		$result = ccd_uo_optimize_one( $rel );
		if ( is_wp_error( $result ) ) {
			$state['errors'][] = $rel . ': ' . $result->get_error_message();
			++$state['skipped'];
			continue;
		}
		if ( null === $result ) {
			++$state['skipped'];
			continue;
		}
		++$state['changed'];
		$state['saved']   += max( 0, (int) $result['old_bytes'] - (int) $result['new_bytes'] );
		$state['files'][]  = $result;
		if ( ! empty( $result['renamed'] ) ) {
			$state['renames'][] = array(
				'from' => $result['from'],
				'to'   => $result['to'],
			);
		}
	}

	$state['index'] = $batch_end;
	ccd_uo_set_state( $state );

	$manifest = ccd_uo_read_manifest();
	$manifest['files'] = isset( $state['files'] ) ? $state['files'] : array();
	$manifest['stats'] = array(
		'changed'     => (int) $state['changed'],
		'saved_bytes' => (int) $state['saved'],
		'skipped'     => (int) $state['skipped'],
		'total'       => $total,
	);
	ccd_uo_write_manifest( $manifest );

	if ( $batch_end < $total ) {
		return array(
			'done'    => false,
			'phase'   => 'optimize',
			'total'   => $total,
			'index'   => $batch_end,
			'changed' => (int) $state['changed'],
			'saved'   => ccd_uo_human_bytes( $state['saved'] ),
			'message' => sprintf( 'Otimizando %d / %d…', $batch_end, $total ),
		);
	}

	// Finalize: DB renames + cache.
	$db = array( 'attachments' => 0, 'posts' => 0 );
	if ( ! empty( $state['renames'] ) ) {
		$db = ccd_uo_apply_renames( $state['renames'] );
	}
	if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
		ccd_page_cache_purge_all();
	}

	$manifest['stats']['db']     = $db;
	$manifest['stats']['finished'] = gmdate( 'c' );
	ccd_uo_write_manifest( $manifest );

	$summary = sprintf(
		'Concluído: %d arquivos otimizados, %s economizados. Backup disponível para restaurar.',
		(int) $state['changed'],
		ccd_uo_human_bytes( $state['saved'] )
	);

	// Mantém manifesto; limpa job em andamento (candidates grandes).
	ccd_uo_set_state(
		array(
			'phase'   => 'done',
			'changed' => (int) $state['changed'],
			'saved'   => (int) $state['saved'],
			'finished'=> time(),
		)
	);

	return array(
		'done'    => true,
		'phase'   => 'done',
		'total'   => $total,
		'index'   => $total,
		'changed' => (int) $state['changed'],
		'saved'   => ccd_uo_human_bytes( $state['saved'] ),
		'db'      => $db,
		'message' => $summary,
	);
}

/**
 * Admin menu.
 */
add_action(
	'admin_menu',
	static function () {
		add_management_page(
			'Otimizar uploads',
			'Otimizar uploads',
			'manage_options',
			'ccd-uploads-optimize',
			'ccd_uo_render_admin_page'
		);
	}
);

/**
 * Página admin.
 */
function ccd_uo_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$backup   = ccd_uo_backup_exists();
	$manifest = $backup ? ccd_uo_read_manifest() : array();
	$stats    = isset( $manifest['stats'] ) && is_array( $manifest['stats'] ) ? $manifest['stats'] : array();
	$uploads  = ccd_uo_uploads_root();
	$nonce    = wp_create_nonce( 'ccd_uo' );

	$changed = isset( $stats['changed'] ) ? (int) $stats['changed'] : 0;
	$saved   = isset( $stats['saved_bytes'] ) ? ccd_uo_human_bytes( $stats['saved_bytes'] ) : '—';
	$created = isset( $manifest['created'] ) ? (string) $manifest['created'] : '';

	?>
	<div class="wrap" id="ccd-uo-wrap">
		<h1>Otimizar uploads</h1>
		<p>Comprime imagens grandes em <code>uploads/</code> (máx. <?php echo esc_html( (string) CCD_UO_MAX_EDGE ); ?>px, qualidade <?php echo esc_html( (string) CCD_UO_QUALITY ); ?>). Antes de alterar cada arquivo, copia para um backup local. PNG opaco vira JPEG (URLs atualizadas no banco).</p>

		<table class="widefat striped" style="max-width:720px;margin:1em 0;">
			<tbody>
				<tr><th>Uploads</th><td><code><?php echo esc_html( $uploads ); ?></code></td></tr>
				<tr><th>Backup</th><td>
					<?php if ( $backup ) : ?>
						<span style="color:#0a7a2f;">Disponível</span>
						<?php if ( $created ) : ?>
							— criado <?php echo esc_html( $created ); ?>
						<?php endif; ?>
						<?php if ( $changed ) : ?>
							— <?php echo esc_html( (string) $changed ); ?> arquivos, <?php echo esc_html( $saved ); ?> economizados
						<?php endif; ?>
					<?php else : ?>
						<span style="color:#666;">Nenhum</span>
					<?php endif; ?>
				</td></tr>
				<tr><th>Pasta backup</th><td><code><?php echo esc_html( ccd_uo_backup_root() ); ?></code></td></tr>
			</tbody>
		</table>

		<p class="ccd-uo-actions">
			<button type="button" class="button button-primary" id="ccd-uo-run" <?php disabled( $backup ); ?>>
				Backup + otimizar
			</button>
			<button type="button" class="button" id="ccd-uo-restore" <?php disabled( ! $backup ); ?>>
				Restaurar backup
			</button>
			<button type="button" class="button button-link-delete" id="ccd-uo-delete" <?php disabled( ! $backup ); ?>>
				Excluir backup
			</button>
		</p>

		<div id="ccd-uo-progress" style="display:none;max-width:720px;margin:1em 0;">
			<div style="background:#d7e2ea;border-radius:6px;overflow:hidden;height:18px;">
				<div id="ccd-uo-bar" style="background:#0277bd;height:18px;width:0%;transition:width .2s;"></div>
			</div>
			<p id="ccd-uo-status" style="margin:.5em 0 0;"></p>
		</div>

		<div id="ccd-uo-log" class="notice" style="display:none;max-width:720px;"></div>
	</div>
	<script>
	(function () {
		const nonce = <?php echo wp_json_encode( $nonce ); ?>;
		const ajaxurl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const $run = document.getElementById('ccd-uo-run');
		const $restore = document.getElementById('ccd-uo-restore');
		const $del = document.getElementById('ccd-uo-delete');
		const $prog = document.getElementById('ccd-uo-progress');
		const $bar = document.getElementById('ccd-uo-bar');
		const $status = document.getElementById('ccd-uo-status');
		const $log = document.getElementById('ccd-uo-log');

		function setBusy(b) {
			$run.disabled = b || <?php echo $backup ? 'true' : 'false'; ?>;
			$restore.disabled = b || <?php echo $backup ? 'false' : 'true'; ?>;
			$del.disabled = b || <?php echo $backup ? 'false' : 'true'; ?>;
			if (b) {
				// while running, keep restore/delete disabled
				$restore.disabled = true;
				$del.disabled = true;
				$run.disabled = true;
			}
		}

		function showLog(msg, ok) {
			$log.style.display = 'block';
			$log.className = 'notice notice-' + (ok ? 'success' : 'error');
			$log.innerHTML = '<p>' + msg + '</p>';
		}

		function post(action) {
			const body = new FormData();
			body.append('action', 'ccd_uo');
			body.append('nonce', nonce);
			body.append('ccd_action', action);
			return fetch(ajaxurl, { method: 'POST', body, credentials: 'same-origin' })
				.then(r => r.json());
		}

		async function runOptimize() {
			if (!confirm('Criar backup dos arquivos candidatos e otimizar a biblioteca? Pode demorar em produção.')) {
				return;
			}
			setBusy(true);
			$prog.style.display = 'block';
			$log.style.display = 'none';
			$status.textContent = 'Escaneando…';
			$bar.style.width = '2%';

			let res = await post('start');
			if (!res.success) {
				showLog((res.data && res.data.message) || 'Falha ao iniciar.', false);
				setBusy(false);
				location.reload();
				return;
			}
			$status.textContent = res.data.message || '';
			const total = res.data.total || 0;
			if (total === 0) {
				showLog('Nenhum arquivo candidato (>= 200KB).', true);
				setBusy(false);
				location.reload();
				return;
			}

			let done = false;
			while (!done) {
				res = await post('step');
				if (!res.success) {
					showLog((res.data && res.data.message) || 'Erro no processamento.', false);
					setBusy(false);
					return;
				}
				const d = res.data;
				done = !!d.done;
				const pct = d.total ? Math.min(100, Math.round(100 * (d.index || 0) / d.total)) : 100;
				$bar.style.width = pct + '%';
				$status.textContent = d.message || '';
			}
			showLog(res.data.message || 'Concluído.', true);
			setBusy(false);
			setTimeout(() => location.reload(), 800);
		}

		$run.addEventListener('click', () => { runOptimize().catch(e => showLog(String(e), false)); });

		$restore.addEventListener('click', async () => {
			if (!confirm('Restaurar todos os arquivos do backup e reverter renomes no banco?')) return;
			setBusy(true);
			const res = await post('restore');
			showLog((res.data && res.data.message) || (res.success ? 'OK' : 'Falha'), !!res.success);
			setBusy(false);
			if (res.success) setTimeout(() => location.reload(), 600);
		});

		$del.addEventListener('click', async () => {
			if (!confirm('Excluir o backup permanentemente? Não será possível restaurar.')) return;
			setBusy(true);
			const res = await post('delete');
			showLog((res.data && res.data.message) || (res.success ? 'OK' : 'Falha'), !!res.success);
			setBusy(false);
			if (res.success) setTimeout(() => location.reload(), 600);
		});
	})();
	</script>
	<?php
}

add_action(
	'wp_ajax_ccd_uo',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissão.' ), 403 );
		}
		check_ajax_referer( 'ccd_uo', 'nonce' );

		$action = isset( $_POST['ccd_action'] ) ? sanitize_key( wp_unslash( $_POST['ccd_action'] ) ) : '';
		$result = ccd_uo_handle( $action );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}
);

/**
 * Esconde backup de listagens de mídia acidentais (path fora de uploads já).
 * Bloqueia acesso HTTP direto via .htaccess; no nginx/Railway o index.php impede listagem.
 */
add_filter(
	'upload_dir',
	static function ( $dirs ) {
		// no-op: backup intencionalmente fora de basedir
		return $dirs;
	}
);
