<?php
/**
 * Plugin Name: CCD Performance
 * Description: Fontes, third-parties, WebP LCP e trim de assets no front.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preconnect para Google Fonts (quando ainda usadas).
 */
add_action(
	'wp_head',
	static function () {
		if ( is_admin() ) {
			return;
		}
		echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	},
	1
);

/**
 * Mesmerize: menos pesos de fonte (Open Sans + Muli enxutos).
 */
add_filter(
	'style_loader_src',
	static function ( $src, $handle ) {
		if ( $handle !== 'mesmerize-fonts' || ! is_string( $src ) || $src === '' ) {
			return $src;
		}
		// Menos pesos: Open Sans + Muli (API css legado do Mesmerize).
		return 'https://fonts.googleapis.com/css?family=Open+Sans:400,600,700|Muli:400,600,700&display=swap&subset=latin';
	},
	30,
	2
);

/**
 * WPForms: so carrega assets se o shortcode/bloco estiver na pagina.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		$need = false;
		if ( function_exists( 'wpforms_has_form' ) || defined( 'CCD_CONTACT_FORM_ID' ) ) {
			$post = get_post();
			if ( $post instanceof WP_Post ) {
				$content = (string) $post->post_content;
				if (
					has_shortcode( $content, 'wpforms' )
					|| str_contains( $content, 'wpforms' )
					|| str_contains( $content, 'ccd-contact' )
				) {
					$need = true;
				}
			}
			if ( is_page( 'contato' ) || is_page( 'fale-comigo' ) || is_page( 'contact' ) ) {
				$need = true;
			}
		}
		if ( $need ) {
			return;
		}
		foreach ( array( 'wpforms', 'wpforms-full', 'wpforms-base', 'wpforms-modern' ) as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
		// Handles comuns do Lite.
		wp_dequeue_style( 'wpforms-gutenberg-form-selector' );
		wp_dequeue_script( 'wpforms-generic' );
		wp_dequeue_script( 'wpforms-confirmation' );
	},
	1000
);

/**
 * Garante sibling .webp ao lado de JPEG/PNG (GD/Imagick) e reescreve HTML.
 *
 * @param string $abs Absolute path to image.
 * @return string|null Absolute webp path if usable.
 */
function ccd_perf_ensure_webp( $abs ) {
	$abs = (string) $abs;
	if ( $abs === '' || ! is_readable( $abs ) ) {
		return null;
	}
	if ( ! preg_match( '/\.(jpe?g|png)$/i', $abs ) ) {
		return null;
	}
	$webp = (string) preg_replace( '/\.(jpe?g|png)$/i', '.webp', $abs );
	if ( is_readable( $webp ) && filesize( $webp ) > 0 ) {
		return $webp;
	}
	if ( ! function_exists( 'wp_get_image_editor' ) ) {
		return null;
	}
	$editor = wp_get_image_editor( $abs );
	if ( is_wp_error( $editor ) ) {
		return null;
	}
	if ( ! $editor->supports_mime_type( 'image/webp' ) ) {
		return null;
	}
	$saved = $editor->save( $webp, 'image/webp' );
	if ( is_wp_error( $saved ) || ! is_readable( $webp ) ) {
		return null;
	}
	return $webp;
}

/**
 * Converte URL de upload jpg/png → webp quando o arquivo existe (ou gera).
 *
 * @param string $url Image URL.
 * @return string
 */
function ccd_perf_url_to_webp( $url ) {
	if ( ! is_string( $url ) || $url === '' || ! preg_match( '/\.(jpe?g|png)(\?|$)/i', $url ) ) {
		return $url;
	}
	$upload = wp_upload_dir( null, false );
	if ( empty( $upload['baseurl'] ) || empty( $upload['basedir'] ) ) {
		return $url;
	}
	$baseurl = (string) $upload['baseurl'];
	$basedir = (string) $upload['basedir'];
	if ( ! str_starts_with( $url, $baseurl ) ) {
		return $url;
	}
	$rel = substr( $url, strlen( $baseurl ) );
	$rel = (string) preg_replace( '/\?.*$/', '', $rel );
	$rel = ltrim( str_replace( '\\', '/', $rel ), '/' );
	if ( $rel === '' || str_contains( $rel, '..' ) ) {
		return $url;
	}
	$abs  = trailingslashit( $basedir ) . $rel;
	$webp = ccd_perf_ensure_webp( $abs );
	if ( ! $webp ) {
		return $url;
	}
	$webp_rel = ltrim( str_replace( '\\', '/', substr( $webp, strlen( $basedir ) ) ), '/' );
	return trailingslashit( $baseurl ) . $webp_rel;
}

/**
 * Rewrita src/srcset para WebP + dims em imagens sem width/height.
 *
 * @param string $html HTML fragment.
 * @return string
 */
function ccd_perf_rewrite_img_html( $html ) {
	if ( ! is_string( $html ) || $html === '' || stripos( $html, '<img' ) === false ) {
		return $html;
	}
	return (string) preg_replace_callback(
		'/<img\b[^>]*>/i',
		static function ( $m ) {
			$tag = $m[0];
			if ( preg_match( '/\ssrc=(["\'])([^"\']+)\1/i', $tag, $sm ) ) {
				$new = ccd_perf_url_to_webp( $sm[2] );
				if ( $new !== $sm[2] ) {
					$tag = str_replace( $sm[0], ' src=' . $sm[1] . esc_url( $new ) . $sm[1], $tag );
				}
			}
			if ( preg_match( '/\ssrcset=(["\'])([^"\']+)\1/i', $tag, $ss ) ) {
				$parts = array_map( 'trim', explode( ',', $ss[2] ) );
				$out   = array();
				foreach ( $parts as $part ) {
					if ( $part === '' ) {
						continue;
					}
					$bits = preg_split( '/\s+/', $part, 2 );
					$url  = ccd_perf_url_to_webp( $bits[0] );
					$out[] = $url . ( isset( $bits[1] ) ? ' ' . $bits[1] : '' );
				}
				if ( $out ) {
					$tag = str_replace( $ss[0], ' srcset=' . $ss[1] . esc_attr( implode( ', ', $out ) ) . $ss[1], $tag );
				}
			}
			// CLS: dims faltando — tenta getimagesize no arquivo local.
			if ( ! preg_match( '/\swidth=/i', $tag ) || ! preg_match( '/\sheight=/i', $tag ) ) {
				if ( preg_match( '/\ssrc=(["\'])([^"\']+)\1/i', $tag, $sm2 ) ) {
					$upload = wp_upload_dir( null, false );
					$url    = $sm2[2];
					if ( ! empty( $upload['baseurl'] ) && str_starts_with( $url, (string) $upload['baseurl'] ) ) {
						$rel = ltrim( str_replace( '\\', '/', substr( $url, strlen( (string) $upload['baseurl'] ) ) ), '/' );
						$rel = (string) preg_replace( '/\?.*$/', '', $rel );
						$abs = trailingslashit( (string) $upload['basedir'] ) . $rel;
						if ( is_readable( $abs ) ) {
							$size = @getimagesize( $abs );
							if ( is_array( $size ) && ! empty( $size[0] ) && ! empty( $size[1] ) ) {
								if ( ! preg_match( '/\swidth=/i', $tag ) ) {
									$tag = preg_replace( '/<img\b/i', '<img width="' . (int) $size[0] . '"', $tag, 1 );
								}
								if ( ! preg_match( '/\sheight=/i', $tag ) ) {
									$tag = preg_replace( '/<img\b/i', '<img height="' . (int) $size[1] . '"', $tag, 1 );
								}
							}
						}
					}
				}
			}
			return $tag;
		},
		$html
	);
}

add_filter( 'the_content', 'ccd_perf_rewrite_img_html', 20 );
add_filter( 'post_thumbnail_html', 'ccd_perf_rewrite_img_html', 20 );
add_filter( 'get_custom_logo', 'ccd_perf_rewrite_img_html', 20 );

/**
 * One-shot: gera WebP das imagens pesadas da home (LCP) no primeiro hit logado/admin
 * ou via cron leve no front (rate-limited).
 */
add_action(
	'init',
	static function () {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		if ( get_option( 'ccd_perf_webp_home_v1' ) === '1' ) {
			return;
		}
		// Evita trabalho em todo request anonimo: so 1/50 ou admin.
		$roll = is_user_logged_in() || ( wp_rand( 1, 50 ) === 1 );
		if ( ! $roll ) {
			return;
		}
		$upload = wp_upload_dir( null, false );
		if ( empty( $upload['basedir'] ) ) {
			return;
		}
		$base = trailingslashit( (string) $upload['basedir'] );
		$targets = array(
			'2022/07/INICIAL.jpg',
			'2022/07/INICIAL-240x300.jpg',
			'2022/07/Bia-2-2.jpg',
		);
		$ok = 0;
		foreach ( $targets as $rel ) {
			if ( ccd_perf_ensure_webp( $base . $rel ) ) {
				++$ok;
			}
		}
		if ( $ok > 0 ) {
			update_option( 'ccd_perf_webp_home_v1', '1', false );
			if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
				ccd_page_cache_purge_all();
			}
		}
	},
	20
);
