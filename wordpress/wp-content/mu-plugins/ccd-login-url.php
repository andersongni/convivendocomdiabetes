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

/**
 * Path da requisição sem query string / barra final (ex.: /login).
 *
 * @return string
 */
function ccd_login_request_path()
{
	$uri  = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
	$path = (string) wp_parse_url($uri, PHP_URL_PATH);
	return untrailingslashit($path);
}

/**
 * Serve wp-login.php em /login sem depender de pretty permalinks
 * (install fresco no CI tem permalink_structure vazio).
 */
function ccd_serve_login_screen()
{
	nocache_headers();
	require ABSPATH . 'wp-login.php';
	exit;
}

add_action(
	'init',
	static function () {
		if (ccd_login_request_path() === '/' . CCD_LOGIN_SLUG) {
			ccd_serve_login_screen();
		}

		add_rewrite_rule('^' . CCD_LOGIN_SLUG . '/?$', 'index.php?ccd_login=1', 'top');

		if (get_option('ccd_login_rewrite_version') !== '4') {
			flush_rewrite_rules(false);
			update_option('ccd_login_rewrite_version', '4', false);
		}
	},
	0
);

add_filter('query_vars', static function ($vars) {
	$vars[] = 'ccd_login';
	return $vars;
});

add_action('parse_request', static function ($wp) {
	if (empty($wp->query_vars['ccd_login'])) {
		return;
	}

	ccd_serve_login_screen();
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
/* Idioma | Limpar cache — cada um na metade, centralizado */
body.login .ccd-login-tools-row {
	display: flex;
	flex-wrap: wrap;
	align-items: stretch;
	justify-content: center;
	gap: 0.85rem;
	width: min(720px, 94vw);
	margin: 1.35rem auto 1.5rem;
	box-sizing: border-box;
}
body.login .ccd-login-tools-row > .language-switcher,
body.login .ccd-login-tools-row > .ccd-clear-browser {
	flex: 1 1 calc(50% - 0.45rem);
	min-width: min(100%, 280px);
	max-width: 100%;
	margin: 0;
	box-sizing: border-box;
	background: #ffffff;
	border: 1px solid #d7e2ea;
	border-radius: 14px;
	box-shadow: 0 8px 22px rgba(15, 52, 78, 0.06);
	padding: 14px 16px 16px;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
}
body.login .language-switcher form#language-switcher {
	display: flex;
	flex-wrap: wrap;
	align-items: stretch;
	justify-content: center;
	gap: 0.65rem;
	margin: 0;
	width: 100%;
}
body.login .language-switcher label {
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
body.login .language-switcher label .dashicons {
	width: 18px;
	height: 18px;
	font-size: 18px;
	color: #0277bd;
}
body.login .language-switcher select {
	flex: 1 1 140px;
	min-height: 40px;
	border: 1px solid #c5d3dc;
	border-radius: 10px;
	background: #fbfdff;
	color: #1f2a33;
	padding: 0.45rem 0.75rem;
	font-size: 14px;
	box-shadow: none;
}
body.login .language-switcher select:focus {
	border-color: #0277bd;
	box-shadow: 0 0 0 2px rgba(2, 119, 189, 0.2);
	outline: none;
}
body.login .language-switcher .button {
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
body.login .language-switcher .button:hover,
body.login .language-switcher .button:focus {
	background: #015f92 !important;
	border-color: #015f92 !important;
	color: #fff !important;
}
body.login .ccd-clear-browser .ccd-clear-browser-label {
	display: block;
	width: 100%;
	margin: 0 0 0.55rem;
	color: #2b3a42;
	font-weight: 700;
	font-size: 13px;
}
body.login .ccd-clear-browser .button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	max-width: 260px;
	min-height: 40px;
	margin: 0;
	padding: 0 1.1rem;
	border: 1px solid #0277bd !important;
	border-radius: 999px !important;
	background: #0277bd !important;
	color: #fff !important;
	font-weight: 700 !important;
	text-decoration: none !important;
	box-shadow: none !important;
	line-height: 1.3;
}
body.login .ccd-clear-browser .button:hover,
body.login .ccd-clear-browser .button:focus {
	background: #015f92 !important;
	border-color: #015f92 !important;
	color: #fff !important;
}
@media (max-width: 640px) {
	body.login .ccd-login-tools-row > .language-switcher,
	body.login .ccd-login-tools-row > .ccd-clear-browser {
		flex: 1 1 100%;
	}
}
CSS;
		wp_register_style('ccd-login-brand', false, array(), '1.3.0');
		wp_enqueue_style('ccd-login-brand');
		wp_add_inline_style('ccd-login-brand', $css);
	},
	20
);

/**
 * Host HTTP atual (pode incluir porta, ex.: localhost:8080).
 *
 * @return string
 */
function ccd_login_request_http_host()
{
	$host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
	return trim($host);
}

/**
 * Hosts do site cujo cache HTTP pode ser limpo (só domínios CCD conhecidos).
 * Em localhost usa o Host da requisição (com porta).
 *
 * @return string[]
 */
function ccd_login_clear_cache_hosts()
{
	$current = ccd_login_request_http_host();
	$current_name = preg_replace('/:\d+$/', '', $current);

	// Local: um único hop no host:porta atual.
	if ($current_name === 'localhost' || $current_name === '127.0.0.1' || str_ends_with((string) $current_name, '.local')) {
		return $current !== '' ? array($current) : array();
	}

	$hosts = array();

	if (function_exists('ccd_env_url_legacy_hosts')) {
		$hosts = array_merge($hosts, ccd_env_url_legacy_hosts());
	} else {
		$hosts = array(
			'www.convivendocomdiabetes.com',
			'convivendocomdiabetes.com',
		);
	}

	foreach (array('home', 'siteurl') as $opt) {
		$url = (string) get_option($opt);
		$host = $url !== '' ? (string) wp_parse_url($url, PHP_URL_HOST) : '';
		if ($host !== '') {
			$hosts[] = strtolower($host);
		}
	}

	if (defined('WP_HOME') && is_string(WP_HOME)) {
		$host = (string) wp_parse_url(WP_HOME, PHP_URL_HOST);
		if ($host !== '') {
			$hosts[] = strtolower($host);
		}
	}

	return array_values(array_unique(array_filter(array_map(
		static function ($h) {
			$h = strtolower(trim((string) $h));
			return preg_match('/^[a-z0-9.-]+$/', $h) ? $h : '';
		},
		$hosts
	))));
}

/**
 * Token HMAC para hops entre domínios (nonce de cookie não serve cross-host).
 *
 * @param string[] $queue Hosts ainda a limpar.
 * @param int      $exp   Expiração unix.
 * @return string
 */
function ccd_login_clear_cache_token(array $queue, $exp)
{
	$payload = implode(',', $queue) . '|' . (int) $exp;
	return hash_hmac('sha256', $payload, wp_salt('nonce'));
}

/**
 * @param string[] $queue Hosts restantes (inclui o atual se ainda não limpou).
 * @return string
 */
function ccd_login_clear_cache_url(array $queue)
{
	$exp   = time() + 10 * MINUTE_IN_SECONDS;
	$token = ccd_login_clear_cache_token($queue, $exp);
	$host  = $queue[0];
	$path  = '/' . CCD_LOGIN_SLUG;
	$scheme = is_ssl() ? 'https' : 'http';
	if (defined('WP_HOME') && is_string(WP_HOME) && str_starts_with(WP_HOME, 'http://')) {
		$scheme = 'http';
	} elseif (!in_array($host, array('localhost', '127.0.0.1'), true)) {
		$scheme = 'https';
	}

	return add_query_arg(
		array(
			'action'    => 'ccd_clear_cache',
			'ccd_hosts' => implode(',', $queue),
			'ccd_exp'   => (string) $exp,
			'ccd_sig'   => $token,
		),
		$scheme . '://' . $host . $path
	);
}

/**
 * Limpa só o cache HTTP deste origin; em seguida o próximo domínio CCD da fila.
 */
function ccd_login_clear_site_cache()
{
	$action = isset($_REQUEST['action']) ? (string) wp_unslash($_REQUEST['action']) : '';
	if ($action !== 'ccd_clear_cache') {
		return;
	}

	$finish = static function ($ok) {
		$q = $ok ? 'ccd_cleared=1' : 'ccd_clear_error=1';
		wp_safe_redirect(home_url('/' . CCD_LOGIN_SLUG . '?' . $q));
		exit;
	};

	$hosts_raw = isset($_REQUEST['ccd_hosts']) ? (string) wp_unslash($_REQUEST['ccd_hosts']) : '';
	$exp       = isset($_REQUEST['ccd_exp']) ? (int) $_REQUEST['ccd_exp'] : 0;
	$sig       = isset($_REQUEST['ccd_sig']) ? (string) wp_unslash($_REQUEST['ccd_sig']) : '';

	$allowed = ccd_login_clear_cache_hosts();
	$queue   = array_values(array_filter(array_map('trim', explode(',', $hosts_raw))));
	$queue   = array_values(array_intersect($queue, $allowed));

	if ($exp < time() || $sig === '' || $queue === []) {
		$finish(false);
	}

	$expected = ccd_login_clear_cache_token($queue, $exp);
	if (!hash_equals($expected, $sig)) {
		$finish(false);
	}

	$current = ccd_login_request_http_host();
	if ($current === '' || strtolower($queue[0]) !== $current) {
		// Garante Clear-Site-Data no host certo (hop cross-host validado na whitelist).
		wp_redirect(ccd_login_clear_cache_url($queue), 302);
		exit;
	}

	nocache_headers();
	// Apenas cache deste origin (não cookies / storage).
	header('Clear-Site-Data: "cache"', true);
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);

	$rest = array_slice($queue, 1);
	if ($rest !== []) {
		wp_redirect(ccd_login_clear_cache_url($rest), 302);
		exit;
	}

	$finish(true);
}

add_action('login_init', 'ccd_login_clear_site_cache', 1);

add_filter(
	'login_message',
	static function ($message) {
		if (!empty($_GET['ccd_cleared'])) {
			$message .= '<p class="message">' . esc_html__(
				'Cache deste site foi limpo em todos os domínios conhecidos. Cookies e senhas salvas não foram alterados.',
				'default'
			) . '</p>';
		}
		if (!empty($_GET['ccd_clear_error'])) {
			$message .= '<p id="login_error">' . esc_html__(
				'Não foi possível limpar o cache. Recarregue a página e tente outra vez.',
				'default'
			) . '</p>';
		}
		return $message;
	}
);

add_action(
	'login_footer',
	static function () {
		$hosts = ccd_login_clear_cache_hosts();
		if ($hosts === []) {
			return;
		}
		$url  = ccd_login_clear_cache_url($hosts);
		$hint = __(
			'Apaga só o cache HTTP deste site (apex, www e outros domínios CCD). Não remove cookies nem o cache global do navegador.',
			'default'
		);
		echo '<div class="ccd-clear-browser">';
		echo '<span class="ccd-clear-browser-label">' . esc_html__('Cache do navegador', 'default') . '</span>';
		echo '<a class="button" href="' . esc_url($url) . '" title="' . esc_attr($hint) . '">';
		echo esc_html__('Limpar cache do site', 'default');
		echo '</a>';
		echo '</div>';

		$js = <<<'JS'
(function () {
	var lang = document.querySelector("body.login > .language-switcher");
	var clear = document.querySelector("body.login > .ccd-clear-browser");
	if (!clear) {
		return;
	}
	var row = document.createElement("div");
	row.className = "ccd-login-tools-row";
	if (lang && lang.parentNode) {
		lang.parentNode.insertBefore(row, lang);
		row.appendChild(lang);
		row.appendChild(clear);
	} else if (clear.parentNode) {
		clear.parentNode.insertBefore(row, clear);
		row.appendChild(clear);
	}
})();
JS;
		wp_print_inline_script_tag($js);
	},
	5
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
		// Captcha obrigatorio nestas tentativas: sem chaves ou token invalido = bloqueia.
		if (
			! function_exists( 'ccd_recaptcha_is_configured' )
			|| ! function_exists( 'ccd_recaptcha_require' )
			|| ! ccd_recaptcha_is_configured()
			|| ! ccd_recaptcha_require( null )
		) {
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
