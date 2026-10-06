<?php
/**
 * Plugin Name: CCD Frontend Fixes
 * Description: Runtime fixes synced from production (header offcanvas + Simple CSS content).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evita o aviso do Edge/Chromium:
 * "[Intervention] Images loaded lazily and replaced with placeholders…"
 */
add_filter( 'wp_lazy_loading_enabled', '__return_false' );
add_filter(
	'wp_img_tag_add_loading_attr',
	static function () {
		return false;
	},
	99
);
add_filter(
	'wp_iframe_tag_add_loading_attr',
	static function () {
		return false;
	},
	99
);

/**
 * Forca loading=eager (remove lazy) em HTML de conteudo.
 *
 * @param string $content Conteudo.
 * @return string
 */
function ccd_force_img_loading_eager( $content ) {
	if ( ! is_string( $content ) || $content === '' || stripos( $content, '<img' ) === false ) {
		return $content;
	}
	$content = (string) preg_replace( '/\sloading=(["\'])lazy\1/i', ' loading="eager"', $content );
	$content = (string) preg_replace( '/<img(?![^>]*\bloading=)/i', '<img loading="eager"', $content );
	return $content;
}

foreach ( array( 'the_content', 'the_excerpt', 'widget_text', 'widget_custom_html_content', 'post_thumbnail_html', 'get_custom_logo' ) as $ccd_lazy_filter ) {
	add_filter( $ccd_lazy_filter, 'ccd_force_img_loading_eager', 99 );
}

add_filter(
	'wp_get_attachment_image_attributes',
	static function ( $attr ) {
		if ( ! is_array( $attr ) ) {
			return $attr;
		}
		$attr['loading'] = 'eager';
		return $attr;
	},
	99
);

/**
 * Passada final no HTML completo (shortcodes/page builders).
 */
add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
			return;
		}
		ob_start(
			static function ( $html ) {
				if ( ! is_string( $html ) || $html === '' ) {
					return $html;
				}
				$html = (string) preg_replace( '/\sloading=(["\'])lazy\1/i', ' loading="eager"', $html );
				$html = (string) preg_replace( '/<img(?![^>]*\bloading=)/i', '<img loading="eager"', $html );
				return $html;
			}
		);
	},
	0
);

/**
 * CSS previously stored in the Simple CSS plugin option on Railway.
 */
add_action(
	'wp_head',
	static function () {
		$css = <<<'CSS'
/* Legacy tagDiv leftovers */
.td-logo .td-main-logo img{
	width:800px;
	height:96px;
}
#td-header-menu .sub-menu .menu-item a{
	color:transparent;
	display:none;
}

/* Hide offcanvas hamburger on tablet/desktop */
@media (min-width: 768px) {
	.main_menu_col [data-component="offcanvas"],
	.navigation-bar [data-component="offcanvas"] {
		display: none !important;
		visibility: hidden !important;
		pointer-events: none !important;
	}
}

/*
 * Yoast SEO admin-bar badge ("1") was rendering outside the admin bar
 * and appearing as a broken red icon over the site header (left of INÍCIO).
 * Hide the Yoast admin-bar entry on the frontend; SEO remains available in wp-admin.
 */
#wpadminbar #wp-admin-bar-wpseo-menu {
	display: none !important;
}

/* Menu superior: sem selecao de texto / cursor de insercao */
.navigation-bar,
.navigation-bar a,
.main_menu_col,
.main_menu_col a,
ul.main-menu,
ul.main-menu a,
ul.dropdown-menu,
ul.dropdown-menu a,
#menu-menu-principal,
#menu-menu-principal a {
	-webkit-user-select: none !important;
	user-select: none !important;
	cursor: pointer !important;
	caret-color: transparent !important;
}

/*
 * Menu sticky (fixto-fixed) cobre o topo da viewport.
 * scroll-padding evita que focus/caret de inputs fiquem sob o header.
 */
html {
	scroll-padding-top: 130px;
}
.navigation-bar.fixto-fixed {
	pointer-events: auto !important;
	isolation: isolate;
}

/*
 * Sem @view-transition { navigation: auto }:
 * o sticky (fixto) clona .navigation-bar e gerava
 * "Unexpected duplicate view-transition-name" + InvalidStateError.
 */
a[href],
button,
input,
textarea,
select,
[tabindex]:not([tabindex="-1"]) {
	scroll-margin-top: 130px;
}
CSS;

		echo "<style id=\"ccd-frontend-fixes\">\n{$css}\n</style>\n";
	},
	100
);

/**
 * Logo do header: usa variante media no src (evita PNG 1000px a cada navegacao).
 *
 * @param array|false  $image         Dados da imagem.
 * @param int          $attachment_id ID.
 * @param string|int[] $size          Tamanho pedido.
 * @param bool         $icon          Icone.
 * @return array|false
 */
add_filter(
	'wp_get_attachment_image_src',
	static function ( $image, $attachment_id, $size, $icon ) {
		unset( $icon );
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( ! $logo_id || (int) $attachment_id !== $logo_id ) {
			return $image;
		}
		if ( $size !== 'full' && $size !== 'post-thumbnail' ) {
			return $image;
		}
		$medium = wp_get_attachment_image_src( $attachment_id, 'medium' );
		return is_array( $medium ) ? $medium : $image;
	},
	20,
	4
);

/**
 * Logo do header: sizes alinhado ao max-height do menu (~70px).
 *
 * @param array        $attr       Atributos.
 * @param WP_Post      $attachment Anexo.
 * @param string|int[] $size       Tamanho.
 * @return array
 */
add_filter(
	'wp_get_attachment_image_attributes',
	static function ( $attr, $attachment, $size ) {
		unset( $size );
		if ( ! is_array( $attr ) || ! $attachment instanceof WP_Post ) {
			return $attr;
		}
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( ! $logo_id || (int) $attachment->ID !== $logo_id ) {
			return $attr;
		}
		$attr['decoding']      = 'async';
		$attr['fetchpriority'] = 'high';
		$attr['sizes']         = '(max-width: 767px) 160px, 220px';
		return $attr;
	},
	20,
	3
);

/**
 * Theme mods: menu padrao igual em home e paginas internas.
 */
add_action(
	'after_setup_theme',
	static function () {
		if ( get_option( 'ccd_frontend_fixes_theme_mods_applied' ) === '2' ) {
			return;
		}

		set_theme_mod( 'header_offscreen_nav_on_desktop', '0' );
		set_theme_mod( 'header_offscreen_nav_on_tablet', '0' );

		// Home estava sem "boxed"; internas usam boxed — unifica no padrao EmpowerWP.
		set_theme_mod( 'header_nav_boxed', true );
		set_theme_mod( 'inner_header_nav_boxed', true );
		set_theme_mod( 'header_nav_sticked', true );
		set_theme_mod( 'inner_header_nav_sticked', true );
		set_theme_mod( 'header_nav_transparent', false );
		set_theme_mod( 'inner_header_nav_transparent', false );

		update_option( 'ccd_frontend_fixes_theme_mods_applied', '2', true );
	},
	20
);

/**
 * Garante as mesmas classes de nav na home e nas internas.
 *
 * @param array  $classes Classes.
 * @param string $prefix  header|inner_header.
 * @return array
 */
add_filter(
	'mesmerize_header_main_class',
	static function ( $classes, $prefix ) {
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}
		if ( ! in_array( 'boxed', $classes, true ) ) {
			$classes[] = 'boxed';
		}
		if ( ! in_array( 'coloured-nav', $classes, true ) ) {
			$classes[] = 'coloured-nav';
		}
		return $classes;
	},
	20,
	2
);

/**
 * Remove Playfair Display do pacote de fontes do Mesmerize (nao e usado no site).
 */
add_filter(
	'mesmerize_google_fonts',
	static function ( $fonts ) {
		if ( ! is_array( $fonts ) ) {
			return $fonts;
		}
		if ( isset( $fonts['Playfair Display'] ) ) {
			unset( $fonts['Playfair Display'] );
			return $fonts;
		}
		return array_values(
			array_filter(
				$fonts,
				static function ( $font ) {
					return ! is_array( $font )
						|| ! isset( $font['family'] )
						|| $font['family'] !== 'Playfair Display';
				}
			)
		);
	},
	20
);

add_action(
	'init',
	static function () {
		if ( get_option( 'ccd_removed_playfair_font_cache' ) === '2' ) {
			return;
		}
		$cached = get_option( '__mesmerize_cached_values__' );
		if ( is_array( $cached ) && isset( $cached['mesmerize_google_fonts'] ) ) {
			unset( $cached['mesmerize_google_fonts'] );
			update_option( '__mesmerize_cached_values__', $cached, false );
		}
		update_option( 'ccd_removed_playfair_font_cache', '2', true );
	},
	1
);

add_filter(
	'style_loader_src',
	static function ( $src, $handle ) {
		if ( $handle !== 'mesmerize-fonts' || ! is_string( $src ) ) {
			return $src;
		}
		// Remove familia Playfair e pesos orfaos deixados por cache antigo.
		$src = preg_replace( '/\|?Playfair\+Display(?::[^|&]*)?/', '', $src );
		$src = preg_replace( '/\|:[^|&]+/', '', $src );
		$src = preg_replace( '/\|+/', '|', $src );
		$src = preg_replace( '/family=(\|)/', 'family=', $src );
		return $src;
	},
	20,
	2
);

/**
 * Mesmerize theme.bundle usa `if (!wp || !wp.customize)` — isso lanca
 * ReferenceError quando o global `wp` nao existe no front (so no Customizer).
 * Stub minimo evita o erro sem carregar o stack inteiro do wp-*.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() ) {
			return;
		}

		wp_register_script( 'ccd-wp-stub', false, array(), '1.0.0', false );
		wp_enqueue_script( 'ccd-wp-stub' );
		wp_add_inline_script( 'ccd-wp-stub', 'window.wp = window.wp || {};', 'after' );

		$scripts = wp_scripts();
		foreach ( array( 'mesmerize-theme', 'empowerwp-theme' ) as $handle ) {
			if ( isset( $scripts->registered[ $handle ] ) ) {
				$deps = $scripts->registered[ $handle ]->deps;
				if ( ! in_array( 'ccd-wp-stub', $deps, true ) ) {
					$scripts->registered[ $handle ]->deps[] = 'ccd-wp-stub';
				}
			}
		}
	},
	20
);

/**
 * Links externos: sempre abrir em nova guia (target=_blank + noopener).
 */
function ccd_is_external_url( $url ) {
	if ( ! is_string( $url ) || $url === '' ) {
		return false;
	}

	$url = trim( $url );
	if ( $url === '' || $url[0] === '#' || $url[0] === '?' ) {
		return false;
	}

	$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	if ( in_array( $scheme, array( 'mailto', 'tel', 'sms', 'javascript', 'data' ), true ) ) {
		return false;
	}

	// Relativos / mesmo site.
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! is_string( $host ) || $host === '' ) {
		return false;
	}

	$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	if ( ! is_string( $site_host ) || $site_host === '' ) {
		return false;
	}

	return strcasecmp( $host, $site_host ) !== 0;
}

/**
 * @param string $html
 * @return string
 */
function ccd_force_external_links_blank( $html ) {
	if ( ! is_string( $html ) || $html === '' || stripos( $html, '<a' ) === false ) {
		return $html;
	}

	return preg_replace_callback(
		'/<a\b([^>]*?)>/i',
		static function ( $m ) {
			$attrs = $m[1];
			if ( ! preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/i', $attrs, $href_m ) ) {
				return $m[0];
			}

			if ( ! ccd_is_external_url( html_entity_decode( $href_m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) {
				return $m[0];
			}

			if ( preg_match( '/\btarget\s*=/i', $attrs ) ) {
				$attrs = preg_replace( '/\btarget\s*=\s*(["\'])(.*?)\1/i', 'target="_blank"', $attrs );
			} else {
				$attrs .= ' target="_blank"';
			}

			if ( preg_match( '/\brel\s*=\s*(["\'])(.*?)\1/i', $attrs, $rel_m ) ) {
				$parts = preg_split( '/\s+/', strtolower( trim( $rel_m[2] ) ) ) ?: array();
				foreach ( array( 'noopener', 'noreferrer' ) as $need ) {
					if ( ! in_array( $need, $parts, true ) ) {
						$parts[] = $need;
					}
				}
				$attrs = preg_replace(
					'/\brel\s*=\s*(["\'])(.*?)\1/i',
					'rel="' . esc_attr( implode( ' ', array_filter( $parts ) ) ) . '"',
					$attrs
				);
			} else {
				$attrs .= ' rel="noopener noreferrer"';
			}

			return '<a' . $attrs . '>';
		},
		$html
	) ?? $html;
}

foreach ( array( 'the_content', 'the_excerpt', 'widget_text', 'widget_custom_html_content', 'comment_text' ) as $ccd_ext_filter ) {
	add_filter( $ccd_ext_filter, 'ccd_force_external_links_blank', 99 );
}

add_filter(
	'nav_menu_link_attributes',
	static function ( $atts ) {
		if ( ! empty( $atts['href'] ) && ccd_is_external_url( $atts['href'] ) ) {
			$atts['target'] = '_blank';
			$rel            = isset( $atts['rel'] ) ? preg_split( '/\s+/', strtolower( (string) $atts['rel'] ) ) : array();
			$rel            = is_array( $rel ) ? $rel : array();
			foreach ( array( 'noopener', 'noreferrer' ) as $need ) {
				if ( ! in_array( $need, $rel, true ) ) {
					$rel[] = $need;
				}
			}
			$atts['rel'] = trim( implode( ' ', array_filter( $rel ) ) );
		}
		return $atts;
	},
	20
);

/**
 * Autocomplete em campos reconhecidos pelo autofill do navegador.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() ) {
			return;
		}

		wp_register_script( 'ccd-autocomplete', false, array(), '1.0.0', true );
		wp_enqueue_script( 'ccd-autocomplete' );
		wp_add_inline_script(
			'ccd-autocomplete',
			<<<'JS'
(function () {
	function apply(root) {
		var scope = root && root.querySelectorAll ? root : document;
		scope.querySelectorAll('input[type="email"]:not([autocomplete]), input[name="email"]:not([autocomplete]), input[name="noptin_fields[email]"]:not([autocomplete])').forEach(function (el) {
			el.setAttribute('autocomplete', 'email');
		});
		scope.querySelectorAll('input[name="author"]:not([autocomplete]), input[name="name"]:not([autocomplete]), input[autocomplete=""][name="author"]').forEach(function (el) {
			if (!el.getAttribute('autocomplete')) el.setAttribute('autocomplete', 'name');
		});
		scope.querySelectorAll('textarea[name="comment"]:not([autocomplete])').forEach(function (el) {
			el.setAttribute('autocomplete', 'off');
		});
	}
	function boot() {
		apply(document);
		if (typeof MutationObserver === 'undefined') return;
		new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var nodes = mutations[i].addedNodes;
				for (var j = 0; j < nodes.length; j++) {
					if (nodes[j].nodeType === 1) apply(nodes[j]);
				}
			}
		}).observe(document.documentElement, { childList: true, subtree: true });
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
JS
		);
	},
	25
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() ) {
			return;
		}

		wp_register_script( 'ccd-external-links', false, array(), '1.0.0', true );
		wp_enqueue_script( 'ccd-external-links' );
		wp_add_inline_script(
			'ccd-external-links',
			<<<'JS'
(function () {
	function isExternal(a) {
		var raw = a.getAttribute('href');
		if (!raw) return false;
		raw = raw.trim();
		if (!raw || raw.charAt(0) === '#' || raw.charAt(0) === '?') return false;
		var lower = raw.toLowerCase();
		if (
			lower.indexOf('mailto:') === 0 ||
			lower.indexOf('tel:') === 0 ||
			lower.indexOf('sms:') === 0 ||
			lower.indexOf('javascript:') === 0 ||
			lower.indexOf('data:') === 0
		) {
			return false;
		}
		try {
			return new URL(a.href, window.location.href).origin !== window.location.origin;
		} catch (e) {
			return false;
		}
	}

	function apply(root) {
		(root.querySelectorAll ? root : document).querySelectorAll('a[href]').forEach(function (a) {
			if (!isExternal(a)) return;
			a.setAttribute('target', '_blank');
			var rel = (a.getAttribute('rel') || '').toLowerCase().split(/\s+/).filter(Boolean);
			['noopener', 'noreferrer'].forEach(function (token) {
				if (rel.indexOf(token) === -1) rel.push(token);
			});
			a.setAttribute('rel', rel.join(' '));
		});
	}

	function boot() {
		apply(document);
		if (typeof MutationObserver === 'undefined') return;
		var obs = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var nodes = mutations[i].addedNodes;
				for (var j = 0; j < nodes.length; j++) {
					var n = nodes[j];
					if (n.nodeType !== 1) continue;
					if (n.matches && n.matches('a[href]') && isExternal(n)) {
						n.setAttribute('target', '_blank');
						var rel = (n.getAttribute('rel') || '').toLowerCase().split(/\s+/).filter(Boolean);
						['noopener', 'noreferrer'].forEach(function (token) {
							if (rel.indexOf(token) === -1) rel.push(token);
						});
						n.setAttribute('rel', rel.join(' '));
					}
					if (n.querySelectorAll) apply(n);
				}
			}
		});
		obs.observe(document.documentElement, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
JS
		);
	},
	30
);
