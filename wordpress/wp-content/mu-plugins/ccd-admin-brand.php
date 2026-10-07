<?php
/**
 * Plugin Name: CCD Admin Brand
 * Description: Identidade visual CCD no wp-admin (leve, tokens + CSS externo).
 * Version: 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Pasta de assets deste mu-plugin. */
const CCD_ADMIN_BRAND_ASSETS = 'mu-plugins/assets/admin';

/** Bump ao alterar CSS/comportamento (cache bust fallback). */
const CCD_ADMIN_BRAND_VER = '2.0.0';

/**
 * URL de um asset em assets/admin/.
 *
 * @param string $file Nome do arquivo (ex.: ccd-admin.css).
 * @return string
 */
function ccd_admin_brand_asset_url( $file ) {
	return content_url( CCD_ADMIN_BRAND_ASSETS . '/' . ltrim( (string) $file, '/' ) );
}

/**
 * Versao baseada em mtime do arquivo (fallback: constante).
 *
 * @param string $file Nome do arquivo em assets/admin/.
 * @return string
 */
function ccd_admin_brand_asset_ver( $file ) {
	$path = WP_CONTENT_DIR . '/' . CCD_ADMIN_BRAND_ASSETS . '/' . ltrim( (string) $file, '/' );
	if ( is_readable( $path ) ) {
		return (string) filemtime( $path );
	}
	return CCD_ADMIN_BRAND_VER;
}

/**
 * Registra handles de estilo (tokens → brand / front / colors).
 */
function ccd_admin_brand_register_styles() {
	$tokens = 'ccd-admin-tokens';
	wp_register_style(
		$tokens,
		ccd_admin_brand_asset_url( 'ccd-admin-tokens.css' ),
		array(),
		ccd_admin_brand_asset_ver( 'ccd-admin-tokens.css' )
	);
	wp_register_style(
		'ccd-admin-brand',
		ccd_admin_brand_asset_url( 'ccd-admin.css' ),
		array( 'colors', $tokens, 'ccd-admin-nunito' ),
		ccd_admin_brand_asset_ver( 'ccd-admin.css' )
	);
	wp_register_style(
		'ccd-admin-brand-front',
		ccd_admin_brand_asset_url( 'ccd-admin-bar-front.css' ),
		array( $tokens ),
		ccd_admin_brand_asset_ver( 'ccd-admin-bar-front.css' )
	);
}

/**
 * Classe de escopo no body do admin (semântica + CSS scoped).
 *
 * @param string $classes Classes atuais.
 * @return string
 */
function ccd_admin_brand_body_class( $classes ) {
	$classes = preg_replace( '/\b(folded|auto-fold)\b/', '', (string) $classes );
	$classes = trim( preg_replace( '/\s+/', ' ', (string) $classes ) );
	if ( false === strpos( $classes, 'ccd-admin' ) ) {
		$classes .= ' ccd-admin';
	}
	return trim( $classes );
}

/**
 * Esquema de cores nativo WP (swatches no perfil).
 */
add_action(
	'admin_init',
	static function () {
		wp_admin_css_color(
			'ccd',
			'Convivendo com Diabetes',
			ccd_admin_brand_asset_url( 'ccd-admin-colors.css' ),
			array( '#013555', '#014a73', '#015f92', '#9b1048' ),
			array(
				'base'    => '#ffffff',
				'focus'   => '#ffffff',
				'current' => '#ffffff',
			)
		);

		if ( function_exists( 'get_user_setting' ) && get_user_setting( 'mfold' ) === 'f' && function_exists( 'delete_user_setting' ) ) {
			delete_user_setting( 'mfold' );
		}
	}
);

/** Força esquema CCD. */
add_filter(
	'get_user_option_admin_color',
	static function () {
		return 'ccd';
	}
);

/** Esconde seletor de cores (marca fixa). */
add_action(
	'admin_head-profile.php',
	static function () {
		echo '<style>.user-admin-color-wrap{display:none!important}</style>';
	}
);
add_action(
	'admin_head-user-edit.php',
	static function () {
		echo '<style>.user-admin-color-wrap{display:none!important}</style>';
	}
);

/** Tipografia + CSS do admin. */
add_action(
	'admin_enqueue_scripts',
	static function () {
		wp_enqueue_style(
			'ccd-admin-nunito',
			'https://fonts.googleapis.com/css2?family=Nunito:wght@600;700;800&family=Open+Sans:wght@400;600;700&display=swap',
			array(),
			null
		);
		ccd_admin_brand_register_styles();
		wp_enqueue_style( 'ccd-admin-tokens' );
		wp_enqueue_style( 'ccd-admin-brand' );
	},
	100
);

/** Barra admin no front. */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_admin_bar_showing() ) {
			return;
		}
		ccd_admin_brand_register_styles();
		wp_enqueue_style( 'ccd-admin-tokens' );
		wp_enqueue_style( 'ccd-admin-brand-front' );
	},
	20
);

/**
 * Remove "Sobre o WordPress"; marca o nome do site.
 *
 * @param WP_Admin_Bar $bar Admin bar.
 */
add_action(
	'admin_bar_menu',
	static function ( $bar ) {
		if ( ! $bar instanceof WP_Admin_Bar ) {
			return;
		}
		$bar->remove_node( 'wp-logo' );
		$node = $bar->get_node( 'site-name' );
		if ( $node ) {
			$node->meta['class'] = trim( ( isset( $node->meta['class'] ) ? (string) $node->meta['class'] : '' ) . ' ccd-admin-brand-site' );
			$bar->add_node( (array) $node );
		}
	},
	999
);

/** Body class: escopo CSS + menu sempre expandido. */
add_filter( 'admin_body_class', 'ccd_admin_brand_body_class', 99 );

/** Nao persistir menu recolhido. */
add_filter(
	'set_user_setting_mfold',
	static function () {
		return '';
	}
);
