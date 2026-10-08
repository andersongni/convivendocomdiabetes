<?php
/**
 * Plugin Name: CCD Sitemap Fix
 * Description: Garante sitemap Yoast no localhost (rewrites + desliga sitemap do core que conflictava).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return bool
 */
function ccd_sitemap_yoast_enabled() {
	if ( ! class_exists( 'WPSEO_Options', false ) && ! function_exists( 'wpseo_init' ) ) {
		$wpseo = get_option( 'wpseo' );
		return is_array( $wpseo ) && ! empty( $wpseo['enable_xml_sitemap'] );
	}

	if ( class_exists( 'WPSEO_Options', false ) && method_exists( 'WPSEO_Options', 'get' ) ) {
		return WPSEO_Options::get( 'enable_xml_sitemap' ) === true;
	}

	$wpseo = get_option( 'wpseo' );
	return is_array( $wpseo ) && ! empty( $wpseo['enable_xml_sitemap'] );
}

/**
 * Yoast 13 registra rewrites no init, mas o disable-core moderno nao carrega
 * (Main ausente). O query var `sitemap` fica compartilhado: /wp-sitemap.xml
 * vira sitemap=index, o Yoast marca 404 e o core ainda imprime XML.
 */
add_filter(
	'wp_sitemaps_enabled',
	static function ( $enabled ) {
		if ( ccd_sitemap_yoast_enabled() ) {
			return false;
		}
		return $enabled;
	},
	0
);

add_action(
	'init',
	static function () {
		if ( ! ccd_sitemap_yoast_enabled() ) {
			return;
		}

		// Garante regras Yoast mesmo se o plugin ainda nao hookou (ordem).
		add_rewrite_rule( 'sitemap_index\.xml$', 'index.php?sitemap=1', 'top' );
		add_rewrite_rule( '([^/]+?)-sitemap([0-9]+)?\.xml$', 'index.php?sitemap=$matches[1]&sitemap_n=$matches[2]', 'top' );
		add_rewrite_rule( '([a-z]+)?-?sitemap\.xsl$', 'index.php?yoast-sitemap-xsl=$matches[1]', 'top' );

		if ( get_option( 'ccd_sitemap_rewrite_version' ) === '2' ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'ccd_sitemap_rewrite_version', '2', false );
	},
	20
);

add_action(
	'template_redirect',
	static function () {
		if ( ! ccd_sitemap_yoast_enabled() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		if ( $path === '' || strpos( $path, '/wp-sitemap' ) !== 0 ) {
			return;
		}

		if ( $path === '/wp-sitemap.xml' ) {
			wp_safe_redirect( home_url( '/sitemap_index.xml' ), 301 );
			exit;
		}

		if ( preg_match( '#^/wp-sitemap-(posts|taxonomies)-([a-z0-9_-]+)-(\d+)\.xml$#', $path, $m ) ) {
			$index = ( (int) $m[3] - 1 );
			$suffix = $index === 0 ? '' : (string) $index;
			wp_safe_redirect( home_url( '/' . $m[2] . '-sitemap' . $suffix . '.xml' ), 301 );
			exit;
		}

		if ( preg_match( '#^/wp-sitemap-users-(\d+)\.xml$#', $path, $m ) ) {
			$index = ( (int) $m[1] - 1 );
			$suffix = $index === 0 ? '' : (string) $index;
			wp_safe_redirect( home_url( '/author-sitemap' . $suffix . '.xml' ), 301 );
			exit;
		}
	},
	0
);

/**
 * XML mais limpo para o Google Search Console ("Não foi possível ler o sitemap"):
 * remove xml-stylesheet (só cosmética no browser) e reforça Content-Type.
 *
 * O índice pode ter o PI embutido em transient antigo; o Yoast emite o sitemap
 * em pre_get_posts — por isso o strip roda em buffer antes da saída.
 */
add_filter(
	'wpseo_stylesheet_url',
	static function () {
		return '';
	},
	999
);

add_action(
	'wpseo_sitemap_stylesheet_cache_1',
	static function ( $sitemaps ) {
		if ( is_object( $sitemaps ) && method_exists( $sitemaps, 'set_stylesheet' ) ) {
			$sitemaps->set_stylesheet( '' );
		}
	}
);

add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( ! ( $query instanceof WP_Query ) || ! $query->is_main_query() ) {
			return;
		}
		$sitemap = get_query_var( 'sitemap' );
		if ( $sitemap === '' || $sitemap === false || $sitemap === null ) {
			return;
		}
		ob_start(
			static function ( $html ) {
				return preg_replace( '/<\?xml-stylesheet\b[^?]*\?>\s*/i', '', (string) $html );
			}
		);
	},
	0
);

add_action(
	'init',
	static function () {
		if ( get_option( 'ccd_sitemap_gsc_clean' ) === '2' ) {
			return;
		}
		if ( ! class_exists( 'WPSEO_Sitemaps_Cache_Validator', false ) ) {
			return;
		}
		WPSEO_Sitemaps_Cache_Validator::invalidate_storage();
		if ( class_exists( 'WPSEO_Sitemaps_Cache', false ) ) {
			WPSEO_Sitemaps_Cache::clear( array( '1' ) );
		}
		update_option( 'ccd_sitemap_gsc_clean', '2', false );
	},
	99
);

add_filter(
	'wpseo_sitemap_http_headers',
	static function ( $headers ) {
		if ( ! is_array( $headers ) ) {
			return $headers;
		}
		$out = array();
		foreach ( $headers as $header => $status ) {
			if ( is_string( $header ) && stripos( $header, 'Content-Type:' ) === 0 ) {
				continue;
			}
			$out[ $header ] = $status;
		}
		// application/xml costuma ser mais previsível para o GSC do que text/xml.
		$out['Content-Type: application/xml; charset=UTF-8'] = '';
		return $out;
	}
);

add_filter(
	'robots_txt',
	static function ( $output, $public ) {
		if ( (string) $public === '0' || ! ccd_sitemap_yoast_enabled() ) {
			return $output;
		}

		// Roda DEPOIS do Yoast (99999): remove wp-sitemap do core e deduplica.
		$output = preg_replace( '/^Sitemap:\s*.*wp-sitemap\.xml\s*$/mi', '', (string) $output );
		$lines  = preg_split( '/\R/', (string) $output );
		$seen   = array();
		$out    = array();
		foreach ( $lines as $line ) {
			if ( preg_match( '/^Sitemap:\s*(.+)$/i', $line, $m ) ) {
				$key = strtolower( trim( $m[1] ) );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
			}
			$out[] = $line;
		}
		$output = implode( "\n", $out );

		if ( empty( $seen ) ) {
			$output = rtrim( $output ) . "\n\nSitemap: " . home_url( '/sitemap_index.xml' ) . "\n";
		}

		return $output;
	},
	100000,
	2
);
