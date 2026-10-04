<?php
/**
 * Plugin Name: CCD Force Password Change
 * Description: Obriga troca de senha no primeiro acesso apos bootstrap.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Redireciona admin para o perfil ate a senha ser trocada.
 */
add_action('admin_init', static function () {
	if (!is_user_logged_in()) {
		return;
	}

	$user_id = get_current_user_id();
	if (!get_user_meta($user_id, 'ccd_force_password_change', true)) {
		return;
	}

	global $pagenow;
	$allowed = array('profile.php', 'user-edit.php', 'admin-ajax.php', 'async-upload.php');
	if (in_array($pagenow, $allowed, true)) {
		return;
	}

	if (defined('DOING_AJAX') && DOING_AJAX) {
		return;
	}

	wp_safe_redirect(admin_url('profile.php?ccd_force_pw=1#password'));
	exit;
});

add_action('admin_notices', static function () {
	if (!is_user_logged_in()) {
		return;
	}
	if (!get_user_meta(get_current_user_id(), 'ccd_force_password_change', true)) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>Troca de senha obrigatoria:</strong> defina uma nova senha abaixo antes de continuar usando o painel.</p></div>';
});

add_action('profile_update', static function ($user_id) {
	if (empty($_POST['pass1'])) {
		return;
	}
	delete_user_meta($user_id, 'ccd_force_password_change');
}, 20);

add_action('after_password_reset', static function ($user) {
	if ($user instanceof WP_User) {
		delete_user_meta($user->ID, 'ccd_force_password_change');
	}
});
