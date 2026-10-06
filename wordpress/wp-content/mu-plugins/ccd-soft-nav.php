<?php
/**
 * Plugin Name: CCD Soft Navigation
 * Description: Prefetch do menu + offset do hero. Sem interceptar cliques (navegacao nativa).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() || is_preview() || is_customize_preview() ) {
			return;
		}

		$handle = 'ccd-soft-nav';
		wp_register_script( $handle, false, array(), '1.1.1', true );
		wp_enqueue_script( $handle );
		wp_add_inline_script(
			$handle,
			<<<'JS'
(function () {
	if (window.ccdSoftNavBooted) return;
	window.ccdSoftNavBooted = true;

	function syncHeaderOffset() {
		var headerTop = document.querySelector('#page-top, .header-top');
		if (!headerTop) return;
		var height = Math.ceil(headerTop.getBoundingClientRect().height);
		if (!height) return;
		/* Folga para o titulo nao colar na borda do menu. */
		var offset = height + 8;
		document.documentElement.style.setProperty('--ccd-header-offset', offset + 'px');
		document.querySelectorAll('.header-wrapper .header-homepage, .header-wrapper .header').forEach(function (el) {
			el.style.setProperty('padding-top', offset + 'px', 'important');
		});
		if (typeof window.mesmerizeSetHeaderTopSpacing === 'function') {
			window.mesmerizeSetHeaderTopSpacing();
			document.querySelectorAll('.header-wrapper .header-homepage, .header-wrapper .header').forEach(function (el) {
				el.style.setProperty('padding-top', offset + 'px', 'important');
			});
		}
	}
	window.ccdSyncHeaderOffset = syncHeaderOffset;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', syncHeaderOffset);
	} else {
		syncHeaderOffset();
	}
	window.addEventListener('resize', syncHeaderOffset);
	window.addEventListener('load', syncHeaderOffset);
	setTimeout(syncHeaderOffset, 0);
	setTimeout(syncHeaderOffset, 150);
})();
JS
		);
	},
	5
);

/**
 * CSS do hero + prefetch nativo dos destinos do menu.
 */
add_action(
	'wp_head',
	static function () {
		if ( is_admin() ) {
			return;
		}

		$logo_url = '';
		$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo_src = wp_get_attachment_image_src( $custom_logo_id, 'medium' );
			if ( ! is_array( $logo_src ) || empty( $logo_src[0] ) ) {
				$logo_src = wp_get_attachment_image_src( $custom_logo_id, 'full' );
			}
			if ( is_array( $logo_src ) && ! empty( $logo_src[0] ) ) {
				$logo_url = (string) $logo_src[0];
			}
		}
		if ( $logo_url === '' ) {
			$logo_url = content_url( 'uploads/2022/07/LOGO-CONVIVENDO-COM-DIABETES-5-300x90.png' );
		}

		echo '<link rel="preload" as="image" href="' . esc_url( $logo_url ) . '" fetchpriority="high">' . "\n";

		echo <<<'CSS'
<style id="ccd-soft-nav">
/*
 * Menu e absolute sobre o hero — padding no first paint (sem esperar JS).
 * 11rem (~176px) cobre logo + menu em 2 linhas; JS afina com --ccd-header-offset.
 */
.header-wrapper .header-homepage,
.header-wrapper .header {
	padding-top: var(--ccd-header-offset, 11rem) !important;
	box-sizing: border-box;
}
#page-top,
#page-top .navigation-bar {
	pointer-events: auto !important;
	z-index: 10050 !important;
}
/*
 * Sem view-transition-name: o clone sticky do menu
 * duplicava ccd-site-nav e abortava a transicao no Edge.
 */
</style>
CSS;

		$locations = get_nav_menu_locations();
		$menu_id   = isset( $locations['primary'] ) ? (int) $locations['primary'] : 0;
		if ( ! $menu_id && is_array( $locations ) ) {
			foreach ( $locations as $id ) {
				$menu_id = (int) $id;
				if ( $menu_id ) {
					break;
				}
			}
		}

		$urls = array();
		if ( $menu_id ) {
			$items = wp_get_nav_menu_items( $menu_id );
			if ( is_array( $items ) ) {
				$home = home_url( '/' );
				foreach ( $items as $item ) {
					if ( empty( $item->url ) || (int) $item->menu_item_parent ) {
						continue;
					}
					$url = esc_url_raw( $item->url );
					if ( ! $url ) {
						continue;
					}
					if ( strpos( $url, $home ) !== 0 && strpos( $url, home_url() ) !== 0 ) {
						continue;
					}
					$urls[] = $url;
				}
			}
		}
		$urls = array_values( array_unique( $urls ) );

		if ( $urls ) {
			$specs = array(
				'prefetch' => array(
					array(
						'source'    => 'list',
						'urls'      => $urls,
						'eagerness' => 'moderate',
					),
				),
			);
			echo '<script type="speculationrules">' .
				wp_json_encode( $specs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) .
				'</script>' . "\n";
		}
	},
	101
);
