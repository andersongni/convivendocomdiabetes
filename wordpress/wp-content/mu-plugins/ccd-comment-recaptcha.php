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

/**
 * Carrega o JS do reCAPTCHA no front (comentarios e/ou newsletter).
 *
 * @return bool
 */
function ccd_recaptcha_should_enqueue() {
	if ( is_admin() || ! ccd_recaptcha_is_configured() ) {
		return false;
	}
	// Newsletter: sempre (mesmo admin logado — captcha e obrigatorio no cadastro).
	if ( defined( 'CCD_NOPTIN_FORM_ID' ) || shortcode_exists( 'noptin-form' ) || class_exists( '\Hizzle\Noptin\Forms\Main' ) ) {
		return true;
	}
	// Comentarios: so se nao houver bypass de moderador.
	if ( ccd_recaptcha_user_bypasses() ) {
		return false;
	}
	return is_singular() && comments_open();
}

/**
 * Loader lazy: api.js so sobe apos idle/interacao (nao bloqueia LCP).
 */
function ccd_recaptcha_lazy_loader_js() {
	return 'window.ccdOnRecaptchaSuccess=window.ccdOnRecaptchaSuccess||function(){};'
		. 'window.ccdOnRecaptchaExpired=window.ccdOnRecaptchaExpired||function(){};'
		. 'window.ccdOnRecaptchaError=window.ccdOnRecaptchaError||function(){};'
		. 'window.ccdRecaptchaRenderAll=window.ccdRecaptchaRenderAll||function(){'
		. 'if(!window.grecaptcha||!grecaptcha.render)return;'
		. 'document.querySelectorAll(".g-recaptcha:not([data-ccd-rendered])").forEach(function(el){'
		. 'var k=el.getAttribute("data-sitekey");if(!k)return;'
		. 'try{grecaptcha.render(el,{sitekey:k,callback:window.ccdOnRecaptchaSuccess,'
		. '"expired-callback":window.ccdOnRecaptchaExpired,"error-callback":window.ccdOnRecaptchaError});'
		. 'el.setAttribute("data-ccd-rendered","1");}catch(e){}});};'
		. '(function(){var loaded=false;function load(){if(loaded)return;loaded=true;'
		. 'var s=document.createElement("script");s.src="https://www.google.com/recaptcha/api.js?onload=ccdRecaptchaRenderAll&render=explicit&hl=pt-BR";'
		. 's.async=true;document.head.appendChild(s);}'
		. '["pointerdown","keydown","touchstart","scroll"].forEach(function(ev){'
		. 'window.addEventListener(ev,load,{once:true,passive:true});});'
		. 'if("requestIdleCallback" in window){requestIdleCallback(load,{timeout:4000});}'
		. 'else{setTimeout(load,3500);}'
		. '})();';
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! ccd_recaptcha_should_enqueue() ) {
			return;
		}

		wp_register_script( 'ccd-recaptcha-lazy', false, array(), '1.0.0', true );
		wp_enqueue_script( 'ccd-recaptcha-lazy' );
		wp_add_inline_script( 'ccd-recaptcha-lazy', ccd_recaptcha_lazy_loader_js(), 'after' );
	}
);

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
	echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($keys['site']) . '"'
		. ' data-callback="ccdOnRecaptchaSuccess"'
		. ' data-expired-callback="ccdOnRecaptchaExpired"'
		. ' data-error-callback="ccdOnRecaptchaError"></div>';
	echo '</p>';
}

add_filter('preprocess_comment', static function ($commentdata) {
	if ( ccd_recaptcha_require( null, true ) ) {
		return $commentdata;
	}

	wp_die(
		esc_html__('Falha na verificacao reCAPTCHA. Marque "Nao sou um robo" e tente novamente.', 'default'),
		esc_html__('Erro no comentario', 'default'),
		array(
			'response'  => 403,
			'back_link' => true,
		)
	);
});

/**
 * Le um campo de request de forma uniforme (POST, array, ArrayAccess, WP_REST_Request).
 *
 * Nao use `is_array( $submitted )` para decidir se ha dados: plugins (ex.: Noptin)
 * passam WP_REST_Request no mesmo fluxo e o captcha seria ignorado.
 *
 * @param mixed  $source Objeto com get_submitted(), array/ArrayAccess, ou null (= $_POST).
 * @param string $key    Nome do campo.
 * @param mixed  $default Valor padrao.
 * @return mixed
 */
function ccd_request_value( $source, $key, $default = '' ) {
	if ( is_object( $source ) && method_exists( $source, 'get_submitted' ) ) {
		return $source->get_submitted( $key, $default );
	}

	if ( is_array( $source ) || $source instanceof ArrayAccess ) {
		return isset( $source[ $key ] ) ? $source[ $key ] : $default;
	}

	if ( null === $source || false === $source ) {
		return isset( $_POST[ $key ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? wp_unslash( $_POST[ $key ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
			: $default;
	}

	return $default;
}

/**
 * Token reCAPTCHA v2 do request atual (ou de um bag de submitted).
 *
 * @param mixed $source null = $_POST; senao listener/array/REST request.
 * @return string
 */
function ccd_recaptcha_token_from( $source = null ) {
	$token = ccd_request_value( $source, 'g-recaptcha-response', '' );
	if ( is_array( $token ) ) {
		$token = reset( $token );
	}
	return sanitize_text_field( (string) $token );
}

/**
 * Exige captcha valido. Retorna true se OK; false se falhou (e nao ha bypass).
 *
 * @param mixed $source null = $_POST; senao listener/array/REST request.
 * @param bool  $allow_moderator_bypass Se moderadores de comentario podem pular.
 * @return bool
 */
function ccd_recaptcha_require( $source = null, $allow_moderator_bypass = false ) {
	if ( $allow_moderator_bypass && ccd_recaptcha_user_bypasses() ) {
		return true;
	}
	if ( ! ccd_recaptcha_is_configured() ) {
		return true;
	}
	$token = ccd_recaptcha_token_from( $source );
	return $token !== '' && ccd_recaptcha_verify( $token );
}

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
