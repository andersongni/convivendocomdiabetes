<?php
/**
 * Plugin Name: CCD Frontend Fixes
 * Description: Runtime fixes synced from production (header offcanvas + Simple CSS content).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
CSS;

		echo "<style id=\"ccd-frontend-fixes\">\n{$css}\n</style>\n";
	},
	100
);

/**
 * Theme mods set on Railway to keep desktop/tablet using the normal menu.
 */
add_action(
	'after_setup_theme',
	static function () {
		if ( get_option( 'ccd_frontend_fixes_theme_mods_applied' ) ) {
			return;
		}

		set_theme_mod( 'header_offscreen_nav_on_desktop', '0' );
		set_theme_mod( 'header_offscreen_nav_on_tablet', '0' );
		update_option( 'ccd_frontend_fixes_theme_mods_applied', 1, true );
	},
	20
);
