<?php
/**
 * Plugin Name: CCD Performance
 * Description: Fontes, CSS/JS async, Noptin lazy, WebP LCP e trim de assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Cache-Control HTML amigo de CDN (s-maxage) sem cachear no browser. */
const CCD_PERF_HTML_CACHE = 'public, max-age=0, s-maxage=3600, must-revalidate';

/**
 * Base URL dos assets locais (fontes self-host).
 *
 * @return string
 */
function ccd_perf_assets_url() {
	return trailingslashit( content_url( 'mu-plugins/ccd-assets' ) );
}

/**
 * Self-host Open Sans / Muli(Mulish) / Nunito / Pacifico — sem Google Fonts.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() ) {
			return;
		}
		foreach ( array( 'mesmerize-fonts', 'ccd-a11y-fonts', 'ccd-a11y-nunito', 'ccd-a11y-pacifico' ) as $h ) {
			wp_dequeue_style( $h );
			wp_deregister_style( $h );
		}

		$css_path = WPMU_PLUGIN_DIR . '/ccd-assets/ccd-fonts.css';
		if ( ! is_readable( $css_path ) ) {
			return;
		}
		$css = (string) file_get_contents( $css_path );
		$css = str_replace( 'CCD_FONTS_BASE', untrailingslashit( ccd_perf_assets_url() ) . '/fonts', $css );
		wp_register_style( 'ccd-fonts', false, array(), '1.2.0' );
		wp_enqueue_style( 'ccd-fonts' );
		wp_add_inline_style( 'ccd-fonts', $css );
	},
	5
);

/**
 * Preload LCP (foto da home) + hint de fonte critica.
 */
add_action(
	'wp_head',
	static function () {
		if ( is_admin() ) {
			return;
		}
		$font = ccd_perf_assets_url() . 'fonts/opensans-w400-normal.woff2';
		echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( $font ) . '" crossorigin>' . "\n";
		if ( ! is_front_page() ) {
			return;
		}
		$upload = wp_upload_dir( null, false );
		if ( empty( $upload['baseurl'] ) || empty( $upload['basedir'] ) ) {
			return;
		}
		$rel  = '2022/07/INICIAL.webp';
		$abs  = trailingslashit( (string) $upload['basedir'] ) . $rel;
		if ( ! is_readable( $abs ) ) {
			$rel = '2022/07/INICIAL.jpg';
			$abs = trailingslashit( (string) $upload['basedir'] ) . $rel;
		}
		if ( ! is_readable( $abs ) ) {
			return;
		}
		$url = trailingslashit( (string) $upload['baseurl'] ) . $rel;
		echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '" fetchpriority="high">' . "\n";
	},
	2
);

/**
 * CSS pesado do tema: nao bloqueia first paint (media=print → all).
 * Sem <noscript> duplicado (evita baixar o CSS 2x).
 *
 * @param string $html   Link tag.
 * @param string $handle Style handle.
 * @return string
 */
function ccd_perf_async_style_tag( $html, $handle ) {
	if ( is_admin() || ! is_string( $html ) ) {
		return $html;
	}
	if ( stripos( $html, 'onload=' ) !== false ) {
		return $html;
	}
	$async_handles = array(
		'mesmerize-parent',
		'mesmerize-style',
		'mesmerize-style-bundle',
		'empowerwp-style',
		'empowerwp-style-bundle',
		'noptin_front',
		'noptin-form',
		'noptin_form_styles',
		'ccd-noptin-form',
	);
	$by_handle = in_array( $handle, $async_handles, true )
		|| str_ends_with( (string) $handle, '-parent' )
		|| str_ends_with( (string) $handle, '-style-bundle' );
	$by_href   = (bool) preg_match( '#/(style\.min\.css|theme\.bundle\.min\.css|frontend\.css)#', $html );
	if ( ! $by_handle && ! $by_href ) {
		return $html;
	}
	$html = str_replace( "media='all'", "media='print' onload=\"this.media='all'\"", $html );
	$html = str_replace( 'media="all"', 'media="print" onload="this.media=\'all\'"', $html );
	if ( stripos( $html, 'media=' ) === false ) {
		$html = str_replace( '<link ', '<link media="print" onload="this.media=\'all\'" ', $html );
	}
	return $html;
}
add_filter( 'style_loader_tag', 'ccd_perf_async_style_tag', 20, 2 );

/**
 * Defer jQuery + masonry/imagesloaded (tema ja defere theme.bundle).
 *
 * @param string $tag    Script tag.
 * @param string $handle Script handle.
 * @param string $src    Script src.
 * @return string
 */
function ccd_perf_defer_script_tag( $tag, $handle, $src ) {
	if ( is_admin() || ! is_string( $tag ) ) {
		return $tag;
	}
	$defer = array( 'jquery', 'jquery-core', 'jquery-migrate', 'masonry', 'imagesloaded' );
	if ( ! in_array( $handle, $defer, true ) ) {
		return $tag;
	}
	if ( stripos( $tag, ' defer' ) !== false || stripos( $tag, ' async' ) !== false ) {
		return $tag;
	}
	return str_replace( ' src', ' defer src', $tag );
}
add_filter( 'script_loader_tag', 'ccd_perf_defer_script_tag', 20, 3 );

/**
 * Pagina embute shortcode/bloco Noptin (precisa CSS/JS cedo)?
 *
 * @return bool
 */
function ccd_perf_needs_noptin_now() {
	if ( is_admin() ) {
		return false;
	}
	$post = get_post();
	if ( ! ( $post instanceof WP_Post ) ) {
		return false;
	}
	$content = (string) $post->post_content;
	return has_shortcode( $content, 'noptin' )
		|| has_shortcode( $content, 'noptin-form' )
		|| str_contains( $content, '[noptin' )
		|| str_contains( $content, 'wp:noptin' );
}

/**
 * Coleta e remove assets Noptin do queue (qualquer handle/path).
 *
 * @param bool $scripts Collect scripts (true) or styles (false).
 * @return string[] URLs.
 */
function ccd_perf_strip_noptin_assets( $scripts = true ) {
	$out  = array();
	$q    = $scripts ? wp_scripts() : wp_styles();
	$mark = array( 'noptin', 'newsletter-optin' );
	foreach ( (array) $q->registered as $handle => $obj ) {
		$handle = (string) $handle;
		// Nao remover o nosso lazy loader.
		if ( $handle === 'ccd-noptin-lazy' ) {
			continue;
		}
		$src = isset( $obj->src ) ? (string) $obj->src : '';
		$hit = false;
		foreach ( $mark as $m ) {
			if ( stripos( $handle, $m ) !== false || stripos( $src, $m ) !== false ) {
				$hit = true;
				break;
			}
		}
		if ( ! $hit ) {
			continue;
		}
		if ( $src !== '' && $src !== false ) {
			$url = $src;
			if ( ! empty( $obj->ver ) ) {
				$url = add_query_arg( 'ver', $obj->ver, $url );
			}
			$out[] = $url;
		}
		if ( $scripts ) {
			wp_dequeue_script( $handle );
		} else {
			wp_dequeue_style( $handle );
		}
	}
	return $out;
}

/**
 * Lazy-load Noptin apos idle/interacao (home tem shortcode abaixo da dobra).
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}

		$styles     = ccd_perf_strip_noptin_assets( false );
		$scripts    = ccd_perf_strip_noptin_assets( true );
		$timeout_ms = ccd_perf_needs_noptin_now() ? 1800 : 5000;

		if ( ! $styles ) {
			$styles[] = content_url( 'plugins/newsletter-optin-box/includes/assets/css/frontend.css' );
		}
		if ( ! $scripts ) {
			$scripts[] = content_url( 'plugins/newsletter-optin-box/includes/assets/js/dist/frontend.js' );
		}

		wp_register_script( 'ccd-noptin-lazy', false, array(), '1.2.0', true );
		wp_enqueue_script( 'ccd-noptin-lazy' );
		wp_add_inline_script(
			'ccd-noptin-lazy',
			'window.ccdNoptinLazy=' . wp_json_encode(
				array(
					'styles'  => array_values( array_unique( array_filter( $styles ) ) ),
					'scripts' => array_values( array_unique( array_filter( $scripts ) ) ),
					'timeout' => (int) $timeout_ms,
				)
			) . ';'
			. '(function(){var d=window.ccdNoptinLazy||{},done=false,t=d.timeout||4000;function load(){if(done)return;done=true;'
			. '(d.styles||[]).forEach(function(href){var l=document.createElement("link");l.rel="stylesheet";l.href=href;document.head.appendChild(l);});'
			. '(d.scripts||[]).forEach(function(src){var s=document.createElement("script");s.src=src;s.defer=true;document.body.appendChild(s);});'
			. '}'
			. '["pointerdown","keydown","touchstart","scroll"].forEach(function(ev){window.addEventListener(ev,load,{once:true,passive:true});});'
			. 'if("requestIdleCallback" in window){requestIdleCallback(load,{timeout:t});}else{setTimeout(load,t);}'
			. '})();',
			'after'
		);
	},
	PHP_INT_MAX
);

// Noptin pode re-enfileirar no print — strip de novo.
add_action(
	'wp_print_scripts',
	static function () {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		ccd_perf_strip_noptin_assets( true );
	},
	0
);
add_action(
	'wp_print_styles',
	static function () {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		ccd_perf_strip_noptin_assets( false );
	},
	0
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
		if ( is_page( array( 'contato', 'fale-comigo', 'contact' ) ) ) {
			$need = true;
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
		wp_dequeue_style( 'wpforms-gutenberg-form-selector' );
		wp_dequeue_script( 'wpforms-generic' );
		wp_dequeue_script( 'wpforms-confirmation' );
	},
	1000
);

/**
 * Garante sibling .webp ao lado de JPEG/PNG (GD/Imagick).
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
 * Redimensiona avatar Bia para max edge (popup Noptin).
 *
 * @param int $max_edge Max width/height.
 * @return string|null Absolute path to -288.webp (or similar).
 */
function ccd_perf_ensure_bia_avatar_small( $max_edge = 288 ) {
	$max_edge = max( 96, (int) $max_edge );
	$upload   = wp_upload_dir( null, false );
	if ( empty( $upload['basedir'] ) ) {
		return null;
	}
	$base   = trailingslashit( (string) $upload['basedir'] ) . '2022/07/';
	$dest   = $base . 'Bia-2-2-' . $max_edge . '.webp';
	if ( is_readable( $dest ) && filesize( $dest ) > 0 ) {
		return $dest;
	}
	$sources = array( $base . 'Bia-2-2.webp', $base . 'Bia-2-2.jpg', $base . 'Bia-2-2.png' );
	$src     = null;
	foreach ( $sources as $candidate ) {
		if ( is_readable( $candidate ) ) {
			$src = $candidate;
			break;
		}
	}
	if ( ! $src || ! function_exists( 'wp_get_image_editor' ) ) {
		return null;
	}
	$editor = wp_get_image_editor( $src );
	if ( is_wp_error( $editor ) ) {
		return null;
	}
	$editor->resize( $max_edge, $max_edge, false );
	if ( method_exists( $editor, 'set_quality' ) ) {
		$editor->set_quality( 78 );
	}
	if ( $editor->supports_mime_type( 'image/webp' ) ) {
		$saved = $editor->save( $dest, 'image/webp' );
	} else {
		$dest  = $base . 'Bia-2-2-' . $max_edge . '.jpg';
		$saved = $editor->save( $dest, 'image/jpeg' );
	}
	if ( is_wp_error( $saved ) || ! is_readable( $dest ) ) {
		return null;
	}
	return $dest;
}

/**
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
			// Avatar Noptin / Bia full-res → versao 288.
			if ( preg_match( '#/uploads/2022/07/Bia-2-2\.(jpe?g|png|webp)#i', $tag ) ) {
				$small = ccd_perf_ensure_bia_avatar_small( 288 );
				if ( $small ) {
					$upload = wp_upload_dir( null, false );
					$rel    = ltrim( str_replace( '\\', '/', substr( $small, strlen( (string) $upload['basedir'] ) ) ), '/' );
					$url    = trailingslashit( (string) $upload['baseurl'] ) . $rel;
					$tag    = preg_replace( '/\ssrc=(["\'])[^"\']+\1/i', ' src="' . esc_url( $url ) . '"', $tag, 1 );
				}
			}
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
					$bits  = preg_split( '/\s+/', $part, 2 );
					$url   = ccd_perf_url_to_webp( $bits[0] );
					$out[] = $url . ( isset( $bits[1] ) ? ' ' . $bits[1] : '' );
				}
				if ( $out ) {
					$tag = str_replace( $ss[0], ' srcset=' . $ss[1] . esc_attr( implode( ', ', $out ) ) . $ss[1], $tag );
				}
			}
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
 * One-shot: WebP LCP + avatar Bia 288px.
 */
add_action(
	'init',
	static function () {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		$flag = 'ccd_perf_assets_v2';
		if ( get_option( $flag ) === '1' ) {
			return;
		}
		$roll = is_user_logged_in() || ( wp_rand( 1, 40 ) === 1 );
		if ( ! $roll ) {
			return;
		}
		$upload = wp_upload_dir( null, false );
		if ( empty( $upload['basedir'] ) ) {
			return;
		}
		$base = trailingslashit( (string) $upload['basedir'] );
		$ok   = 0;
		foreach ( array( '2022/07/INICIAL.jpg', '2022/07/INICIAL-240x300.jpg', '2022/07/Bia-2-2.jpg' ) as $rel ) {
			if ( ccd_perf_ensure_webp( $base . $rel ) ) {
				++$ok;
			}
		}
		if ( ccd_perf_ensure_bia_avatar_small( 288 ) ) {
			++$ok;
		}
		if ( $ok > 0 ) {
			update_option( $flag, '1', false );
			if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
				ccd_page_cache_purge_all();
			}
		}
	},
	20
);
