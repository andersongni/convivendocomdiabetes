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

	// Healthcheck do Railway usa /wp-login.php (config atual do serviço).
	$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	if ($ua !== '' && stripos($ua, 'RailwayHealthCheck') !== false) {
		status_header(200);
		header('Content-Type: text/plain; charset=UTF-8');
		header('Cache-Control: no-store');
		echo 'ok';
		exit;
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

/**
 * URL do logo do site para a tela de login.
 *
 * @return string
 */
function ccd_login_logo_url()
{
	$logo_id = (int) get_theme_mod('custom_logo');
	if ($logo_id) {
		$src = wp_get_attachment_image_src($logo_id, 'medium');
		if (!is_array($src) || empty($src[0])) {
			$src = wp_get_attachment_image_src($logo_id, 'full');
		}
		if (is_array($src) && !empty($src[0])) {
			return (string) $src[0];
		}
	}

	return content_url('mu-plugins/assets/brand/logo-convivendo-com-diabetes-300.png');
}

add_filter('login_headerurl', static function () {
	return home_url('/');
});

add_filter('login_headertext', static function () {
	return get_bloginfo('name', 'display');
});

/**
 * Identidade visual CCD na tela de login (/login).
 */
add_action(
	'login_enqueue_scripts',
	static function () {
		$logo = esc_url(ccd_login_logo_url());
		$css  = <<<CSS
body.login {
	background:
		radial-gradient(1200px 500px at 50% -10%, rgba(2, 119, 189, 0.12), transparent 60%),
		linear-gradient(180deg, #f4f8fb 0%, #eef3f7 100%);
	font-family: Nunito, "Open Sans", "Segoe UI", sans-serif;
	color: #1f2a33;
}
body.login #login {
	width: min(360px, 92vw);
	padding-top: 6vh;
}
.login h1.wp-login-logo,
.login h1 {
	display: block !important;
	margin: 0 0 1.25rem;
}
.login h1 a {
	display: block !important;
	background-image: url("{$logo}") !important;
	background-size: contain !important;
	background-position: center center !important;
	background-repeat: no-repeat !important;
	width: 100% !important;
	height: 84px !important;
	margin: 0 auto !important;
	padding: 0 !important;
	text-indent: -9999px;
	overflow: hidden;
	outline: none !important;
	box-shadow: none !important;
}
.login #loginform,
.login #registerform,
.login #lostpasswordform {
	background: #ffffff;
	border: 1px solid #d7e2ea;
	border-radius: 14px;
	box-shadow: 0 10px 30px rgba(15, 52, 78, 0.08);
	padding: 26px 24px 22px;
}
.login label {
	color: #2b3a42;
	font-weight: 600;
	font-size: 13px;
}
.login form .input,
.login input[type="text"],
.login input[type="password"] {
	border: 1px solid #c5d3dc;
	border-radius: 10px;
	background: #fbfdff;
	box-shadow: none;
	padding: 0.55rem 0.75rem;
	font-size: 15px;
	color: #1f2a33;
}
.login form .input:focus,
.login input[type="text"]:focus,
.login input[type="password"]:focus {
	border-color: #0277bd;
	box-shadow: 0 0 0 2px rgba(2, 119, 189, 0.2);
}
.login .button-primary,
.login .button.button-large {
	background: #0277bd !important;
	border-color: #0277bd !important;
	border-radius: 999px !important;
	color: #fff !important;
	font-weight: 700 !important;
	text-shadow: none !important;
	box-shadow: none !important;
	padding: 0 1.35rem !important;
	min-height: 40px !important;
	line-height: 2.2 !important;
}
.login .button-primary:hover,
.login .button-primary:focus,
.login .button.button-large:hover,
.login .button.button-large:focus {
	background: #015f92 !important;
	border-color: #015f92 !important;
}
.login #nav,
.login #backtoblog {
	padding: 0 4px;
	text-align: center;
}
.login #nav a,
.login #backtoblog a {
	color: #015f92;
	font-weight: 600;
}
.login #nav a:hover,
.login #backtoblog a:hover {
	color: #014a73;
}
.login .language-switcher {
	margin: 1.35rem auto 0;
	max-width: 100%;
	background: #ffffff;
	border: 1px solid #d7e2ea;
	border-radius: 14px;
	box-shadow: 0 8px 22px rgba(15, 52, 78, 0.06);
	padding: 14px 16px 16px;
}
.login .language-switcher form#language-switcher {
	display: flex;
	flex-wrap: wrap;
	align-items: stretch;
	justify-content: center;
	gap: 0.65rem;
	margin: 0;
}
.login .language-switcher label {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 0.4rem;
	width: 100%;
	margin: 0 0 0.15rem;
	color: #2b3a42;
	font-weight: 700;
	font-size: 13px;
}
.login .language-switcher label .dashicons {
	width: 18px;
	height: 18px;
	font-size: 18px;
	color: #0277bd;
}
.login .language-switcher select {
	flex: 1 1 170px;
	min-height: 40px;
	border: 1px solid #c5d3dc;
	border-radius: 10px;
	background: #fbfdff;
	color: #1f2a33;
	padding: 0.45rem 0.75rem;
	font-size: 14px;
	box-shadow: none;
}
.login .language-switcher select:focus {
	border-color: #0277bd;
	box-shadow: 0 0 0 2px rgba(2, 119, 189, 0.2);
	outline: none;
}
.login .language-switcher .button {
	flex: 0 0 auto;
	min-height: 40px !important;
	border-radius: 999px !important;
	border: 1px solid #0277bd !important;
	background: #0277bd !important;
	color: #fff !important;
	font-weight: 700 !important;
	padding: 0 1.2rem !important;
	box-shadow: none !important;
	text-shadow: none !important;
}
.login .language-switcher .button:hover,
.login .language-switcher .button:focus {
	background: #015f92 !important;
	border-color: #015f92 !important;
	color: #fff !important;
}
.login .dashicons-visibility::before,
.login .dashicons-hidden::before {
	color: #0277bd;
}
.login .message,
.login #login_error,
.login .success {
	border-radius: 10px;
	border-left-width: 4px;
}
.login .ccd-login-recaptcha {
	margin: 0.85rem 0 0.25rem;
}
.login .ccd-login-recaptcha .g-recaptcha {
	transform-origin: left top;
}
CSS;
		wp_register_style('ccd-login-brand', false, array(), '1.1.0');
		wp_enqueue_style('ccd-login-brand');
		wp_add_inline_style('ccd-login-brand', $css);
	},
	20
);

const CCD_LOGIN_FAIL_LIMIT = 3;
const CCD_LOGIN_FAIL_TTL   = HOUR_IN_SECONDS;

/**
 * Chave de transient por IP para falhas de login.
 *
 * @return string
 */
function ccd_login_fail_key()
{
	$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : '0';
	return 'ccd_login_fails_' . md5($ip);
}

/**
 * @return int
 */
function ccd_login_fail_count()
{
	return (int) get_transient(ccd_login_fail_key());
}

/**
 * Chave de “captcha obrigatório” (persiste até login ok).
 *
 * @return string
 */
function ccd_login_captcha_required_key()
{
	return 'ccd_login_captcha_req_' . md5(ccd_login_fail_key());
}

/**
 * Captcha obrigatório após N falhas (se reCAPTCHA estiver configurado).
 *
 * @return bool
 */
function ccd_login_needs_captcha()
{
	if (!function_exists('ccd_recaptcha_is_configured') || !ccd_recaptcha_is_configured()) {
		return false;
	}
	if (get_transient(ccd_login_captcha_required_key())) {
		return true;
	}
	return ccd_login_fail_count() >= CCD_LOGIN_FAIL_LIMIT;
}

/**
 * Marca captcha como obrigatório para este cliente.
 */
function ccd_login_mark_captcha_required()
{
	set_transient(ccd_login_captcha_required_key(), 1, CCD_LOGIN_FAIL_TTL);
}

add_action(
	'wp_login_failed',
	static function () {
		$key   = ccd_login_fail_key();
		$count = ccd_login_fail_count() + 1;
		set_transient($key, $count, CCD_LOGIN_FAIL_TTL);
		if ($count >= CCD_LOGIN_FAIL_LIMIT) {
			ccd_login_mark_captcha_required();
		}
	},
	10
);

add_action(
	'wp_login',
	static function () {
		delete_transient(ccd_login_fail_key());
		delete_transient(ccd_login_captcha_required_key());
	},
	10
);

add_action(
	'login_enqueue_scripts',
	static function () {
		if (!ccd_login_needs_captcha() || !function_exists('ccd_recaptcha_keys')) {
			return;
		}
		wp_enqueue_script(
			'google-recaptcha',
			'https://www.google.com/recaptcha/api.js',
			array(),
			null,
			true
		);
	},
	25
);

add_action(
	'login_form',
	static function () {
		if (!ccd_login_needs_captcha() || !function_exists('ccd_recaptcha_keys')) {
			return;
		}
		$keys = ccd_recaptcha_keys();
		if ($keys['site'] === '') {
			return;
		}
		echo '<p class="ccd-login-recaptcha">';
		echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($keys['site']) . '"></div>';
		echo '</p>';
	},
	20
);

/**
 * Exige reCAPTCHA após o limite de falhas.
 * Prioridade alta: filtros padrão do WP (prio 20) sobrescrevem WP_Error anterior
 * quando a senha está correta — por isso validamos depois da autenticação.
 *
 * @param WP_User|WP_Error|null $user     User.
 * @param string                $username Username.
 * @param string                $password Password.
 * @return WP_User|WP_Error|null
 */
add_filter(
	'authenticate',
	static function ($user, $username, $password) {
		// Só bloqueia tentativas reais de login (não a carga vazia da tela).
		if ((string) $username === '' && (string) $password === '') {
			return $user;
		}
		if (!ccd_login_needs_captcha()) {
			return $user;
		}
		if (!function_exists('ccd_recaptcha_verify')) {
			return new WP_Error(
				'ccd_login_captcha',
				__('<strong>Erro:</strong> confirme o captcha para continuar.', 'default')
			);
		}

		$token = isset($_POST['g-recaptcha-response'])
			? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response']))
			: '';

		if ($token === '' || !ccd_recaptcha_verify($token)) {
			ccd_login_mark_captcha_required();
			return new WP_Error(
				'ccd_login_captcha',
				__('<strong>Erro:</strong> confirme o captcha para continuar.', 'default')
			);
		}

		return $user;
	},
	100,
	3
);

/**
 * Mensagem genérica de falha de autenticação (não revela se o usuário existe).
 *
 * @param WP_User|WP_Error|null $user User or error.
 * @return WP_User|WP_Error|null
 */
add_filter(
	'authenticate',
	static function ($user) {
		if (!is_wp_error($user)) {
			return $user;
		}

		$auth_codes = array(
			'invalid_username',
			'invalid_email',
			'incorrect_password',
			'invalid_password',
			'authentication_failed',
		);

		if (!array_intersect($user->get_error_codes(), $auth_codes)) {
			return $user;
		}

		return new WP_Error(
			'ccd_invalid_login',
			__('<strong>Erro:</strong> Usuário ou senha inválidos.', 'default')
		);
	},
	99
);
