<?php
/**
 * Plugin Name: CCD Login URL
 * Description: Login apenas em /login. Acesso a /wp-login.php retorna 404.
 */

if (!defined('ABSPATH')) {
	exit;
}

const CCD_LOGIN_SLUG = 'login';

/**
 * True quando a requisição HTTP aponta para wp-login.php.
 */
function ccd_is_direct_wp_login_request()
{
	$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
	if ($uri !== '' && stripos($uri, 'wp-login.php') !== false) {
		return true;
	}

	$script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
	if ($script !== '' && stripos($script, 'wp-login.php') !== false) {
		// Permite include interno via /login (REQUEST_URI = /login).
		$path = (string) wp_parse_url($uri, PHP_URL_PATH);
		$path = untrailingslashit($path);
		return $path !== '/' . CCD_LOGIN_SLUG;
	}

	return false;
}

/**
 * Responde 404 para /wp-login.php.
 */
function ccd_forbid_wp_login_php()
{
	if (!ccd_is_direct_wp_login_request()) {
		return;
	}

	global $wp_query;
	if ($wp_query instanceof WP_Query) {
		$wp_query->set_404();
	}

	status_header(404);
	nocache_headers();

	$template = get_query_template('404');
	if (is_string($template) && $template !== '' && file_exists($template)) {
		include $template;
		exit;
	}

	wp_die(esc_html__('Not Found', 'default'), esc_html__('Not Found', 'default'), array('response' => 404));
}

add_action('init', static function () {
	add_rewrite_rule('^' . CCD_LOGIN_SLUG . '/?$', 'index.php?ccd_login=1', 'top');

	if (get_option('ccd_login_rewrite_version') !== '3') {
		flush_rewrite_rules(false);
		update_option('ccd_login_rewrite_version', '3', false);
	}
}, 5);

add_filter('query_vars', static function ($vars) {
	$vars[] = 'ccd_login';
	return $vars;
});

add_action('parse_request', static function ($wp) {
	if (empty($wp->query_vars['ccd_login'])) {
		return;
	}

	nocache_headers();
	require ABSPATH . 'wp-login.php';
	exit;
});

// Bloqueia acesso HTTP direto a wp-login.php (o include via /login não cai aqui).
add_action('login_init', 'ccd_forbid_wp_login_php', 0);

add_filter('login_url', static function ($login_url, $redirect, $force_reauth) {
	$login_url = home_url('/' . CCD_LOGIN_SLUG);

	if (!empty($redirect)) {
		$login_url = add_query_arg('redirect_to', rawurlencode($redirect), $login_url);
	}
	if ($force_reauth) {
		$login_url = add_query_arg('reauth', '1', $login_url);
	}

	return $login_url;
}, 10, 3);

add_filter('lostpassword_url', static function ($url, $redirect) {
	$url = add_query_arg('action', 'lostpassword', home_url('/' . CCD_LOGIN_SLUG));
	if (!empty($redirect)) {
		$url = add_query_arg('redirect_to', rawurlencode($redirect), $url);
	}
	return $url;
}, 10, 2);

add_filter('register_url', static function () {
	return add_query_arg('action', 'register', home_url('/' . CCD_LOGIN_SLUG));
});

/**
 * Reescreve URLs geradas com wp-login.php para /login.
 *
 * @param string $url URL.
 */
function ccd_replace_login_php_url($url)
{
	if (!is_string($url) || strpos($url, 'wp-login.php') === false) {
		return $url;
	}

	return str_replace('wp-login.php', CCD_LOGIN_SLUG, $url);
}

add_filter('site_url', static function ($url) {
	return ccd_replace_login_php_url($url);
});

add_filter('network_site_url', static function ($url) {
	return ccd_replace_login_php_url($url);
});

add_filter('wp_redirect', static function ($location) {
	return ccd_replace_login_php_url($location);
});
