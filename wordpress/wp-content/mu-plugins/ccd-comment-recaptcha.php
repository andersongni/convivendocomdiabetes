<?php
/**
 * Plugin Name: CCD Comment reCAPTCHA
 * Description: Google reCAPTCHA v2 nos comentarios do blog.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @return array{site:string,secret:string}
 */
function ccd_recaptcha_keys()
{
	$site = '';
	$secret = '';

	if (defined('CCD_RECAPTCHA_SITE_KEY') && CCD_RECAPTCHA_SITE_KEY) {
		$site = (string) CCD_RECAPTCHA_SITE_KEY;
	} elseif (getenv('CCD_RECAPTCHA_SITE_KEY')) {
		$site = (string) getenv('CCD_RECAPTCHA_SITE_KEY');
	} else {
		$site = (string) get_option('ccd_recaptcha_site_key', '');
	}

	if (defined('CCD_RECAPTCHA_SECRET_KEY') && CCD_RECAPTCHA_SECRET_KEY) {
		$secret = (string) CCD_RECAPTCHA_SECRET_KEY;
	} elseif (getenv('CCD_RECAPTCHA_SECRET_KEY')) {
		$secret = (string) getenv('CCD_RECAPTCHA_SECRET_KEY');
	} else {
		$secret = (string) get_option('ccd_recaptcha_secret_key', '');
	}

	return array(
		'site'   => trim($site),
		'secret' => trim($secret),
	);
}

/**
 * @return bool
 */
function ccd_recaptcha_is_configured()
{
	$keys = ccd_recaptcha_keys();
	return $keys['site'] !== '' && $keys['secret'] !== '';
}

/**
 * Usuarios que moderam comentarios nao precisam do captcha.
 *
 * @return bool
 */
function ccd_recaptcha_user_bypasses()
{
	return is_user_logged_in() && current_user_can('moderate_comments');
}

add_action('wp_enqueue_scripts', static function () {
	if (!ccd_recaptcha_is_configured() || ccd_recaptcha_user_bypasses()) {
		return;
	}
	if (!is_singular() || !comments_open()) {
		return;
	}

	wp_enqueue_script(
		'google-recaptcha',
		'https://www.google.com/recaptcha/api.js',
		array(),
		null,
		true
	);
});

add_action('comment_form_after_fields', 'ccd_recaptcha_render_field');
add_action('comment_form_logged_in_after', 'ccd_recaptcha_render_field');

/**
 * Renderiza o widget reCAPTCHA no formulario.
 */
function ccd_recaptcha_render_field()
{
	if (!ccd_recaptcha_is_configured() || ccd_recaptcha_user_bypasses()) {
		return;
	}

	$keys = ccd_recaptcha_keys();
	echo '<p class="ccd-recaptcha-field" style="margin:1em 0;">';
	echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($keys['site']) . '"></div>';
	echo '</p>';
}

add_filter('preprocess_comment', static function ($commentdata) {
	if (ccd_recaptcha_user_bypasses()) {
		return $commentdata;
	}

	if (!ccd_recaptcha_is_configured()) {
		return $commentdata;
	}

	$token = isset($_POST['g-recaptcha-response'])
		? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response']))
		: '';

	if ($token === '' || !ccd_recaptcha_verify($token)) {
		wp_die(
			esc_html__('Falha na verificacao reCAPTCHA. Marque "Nao sou um robo" e tente novamente.', 'default'),
			esc_html__('Erro no comentario', 'default'),
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}

	return $commentdata;
});

/**
 * Valida o token no Google.
 *
 * @param string $token Token do cliente.
 * @return bool
 */
function ccd_recaptcha_verify($token)
{
	$keys = ccd_recaptcha_keys();
	if ($keys['secret'] === '' || $token === '') {
		return false;
	}

	$response = wp_remote_post(
		'https://www.google.com/recaptcha/api/siteverify',
		array(
			'timeout' => 8,
			'body'    => array(
				'secret'   => $keys['secret'],
				'response' => $token,
				'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
			),
		)
	);

	if (is_wp_error($response)) {
		return false;
	}

	$body = json_decode((string) wp_remote_retrieve_body($response), true);
	return is_array($body) && !empty($body['success']);
}

add_action('admin_init', static function () {
	register_setting(
		'discussion',
		'ccd_recaptcha_site_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);
	register_setting(
		'discussion',
		'ccd_recaptcha_secret_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	add_settings_section(
		'ccd_recaptcha_section',
		'Google reCAPTCHA (comentarios)',
		static function () {
			echo '<p>Chaves v2 ("Nao sou um robo"). Obtenha em <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">google.com/recaptcha/admin</a>. Dominios: <code>localhost</code> e o dominio de producao.</p>';
			if (defined('CCD_RECAPTCHA_SITE_KEY') || getenv('CCD_RECAPTCHA_SITE_KEY')) {
				echo '<p><em>Ha chaves definidas por variavel de ambiente/constante; elas tem prioridade sobre estes campos.</em></p>';
			}
		},
		'discussion'
	);

	add_settings_field(
		'ccd_recaptcha_site_key',
		'Site Key',
		static function () {
			$value = (string) get_option('ccd_recaptcha_site_key', '');
			printf(
				'<input type="text" class="regular-text" name="ccd_recaptcha_site_key" value="%s" autocomplete="off" />',
				esc_attr($value)
			);
		},
		'discussion',
		'ccd_recaptcha_section'
	);

	add_settings_field(
		'ccd_recaptcha_secret_key',
		'Secret Key',
		static function () {
			$value = (string) get_option('ccd_recaptcha_secret_key', '');
			printf(
				'<input type="password" class="regular-text" name="ccd_recaptcha_secret_key" value="%s" autocomplete="new-password" />',
				esc_attr($value)
			);
		},
		'discussion',
		'ccd_recaptcha_section'
	);
});

add_action('admin_notices', static function () {
	if (!current_user_can('manage_options') || ccd_recaptcha_is_configured()) {
		return;
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || !in_array($screen->id, array('dashboard', 'edit-comments', 'options-discussion'), true)) {
		return;
	}
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__('reCAPTCHA dos comentarios ainda nao esta configurado. Em Configuracoes → Discussao, informe Site Key e Secret Key.');
	echo ' <a href="' . esc_url(admin_url('options-discussion.php')) . '">Abrir Discussao</a>';
	echo '</p></div>';
});
