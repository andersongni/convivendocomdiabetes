<?php
/**
 * Plugin Name: CCD Update Policy
 * Description: Em producao (Railway) bloqueia updates in-place; promove via git/IaC. Localhost continua livre para atualizar.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return bool
 */
function ccd_updates_locked() {
	if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
		return true;
	}
	if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) {
		return true;
	}
	// Railway injeta estas vars mesmo sem as constantes ainda.
	if ( getenv( 'RAILWAY_ENVIRONMENT' ) || getenv( 'RAILWAY_ENVIRONMENT_ID' ) ) {
		return true;
	}
	return false;
}

if ( ! ccd_updates_locked() ) {
	return;
}

// Sem auto-update / instalacao pelo admin em producao.
add_filter( 'automatic_updater_disabled', '__return_true', 100 );
add_filter( 'auto_update_core', '__return_false', 100 );
add_filter( 'auto_update_plugin', '__return_false', 100 );
add_filter( 'auto_update_theme', '__return_false', 100 );
add_filter( 'file_mod_allowed', static function ( $allowed, $context ) {
	unset( $context );
	return false;
}, 100, 2 );

// Esconde nags e telas de "Atualizar agora" (o caminho e git + deploy).
add_action(
	'admin_menu',
	static function () {
		remove_submenu_page( 'index.php', 'update-core.php' );
	},
	999
);

add_action(
	'admin_bar_menu',
	static function ( $bar ) {
		if ( $bar instanceof WP_Admin_Bar ) {
			$bar->remove_node( 'updates' );
		}
	},
	999
);

add_action(
	'admin_init',
	static function () {
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'network_admin_notices', 'update_nag', 3 );
	},
	1
);

add_filter(
	'pre_site_transient_update_core',
	static function () {
		return (object) array(
			'updates'    => array(),
			'version_checked' => get_bloginfo( 'version' ),
			'last_checked'    => time(),
		);
	}
);

add_filter(
	'pre_site_transient_update_plugins',
	static function () {
		return (object) array(
			'response'     => array(),
			'translations' => array(),
			'no_update'    => array(),
			'last_checked' => time(),
		);
	}
);

add_filter(
	'pre_site_transient_update_themes',
	static function () {
		return (object) array(
			'response'     => array(),
			'translations' => array(),
			'last_checked' => time(),
		);
	}
);

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'themes', 'update-core' ), true ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p>';
		echo esc_html__(
			'Updates em producao estao desligados. Atualize no localhost, commit no git e promova via deploy (IaC/Railway). Ver docs/UPDATES.md.',
			'default'
		);
		echo '</p></div>';
	}
);
