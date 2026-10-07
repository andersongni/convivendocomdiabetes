<?php
/**
 * Plugin Name: CCD Noptin Form
 * Description: Garante o shortcode [noptin-form], imagem local, layout e mensagens PT do formulario 2859.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Autocomplete no campo de e-mail do Noptin (aviso do navegador / Lighthouse).
 * O markup e impresso via noptin_field_type_frontend_optin_markup (nao via noptin_display_email_input).
 */
add_action(
	'noptin_field_type_frontend_optin_markup',
	static function ( $field ) {
		$type = '';
		if ( is_array( $field ) && isset( $field['type']['type'] ) ) {
			$type = (string) $field['type']['type'];
		}
		if ( $type !== 'email' ) {
			return;
		}
		ob_start(
			static function ( $html ) {
				if ( ! is_string( $html ) || $html === '' || stripos( $html, 'autocomplete=' ) !== false ) {
					return $html;
				}
				$out = preg_replace(
					'/(<input\b(?=[^>]*\btype=(["\'])email\2)[^>]*?)(\s*\/?>)/is',
					'$1 autocomplete="email"$3',
					$html,
					1
				);
				return is_string( $out ) ? $out : $html;
			}
		);
	},
	0
);
add_action(
	'noptin_field_type_frontend_optin_markup',
	static function ( $field ) {
		$type = '';
		if ( is_array( $field ) && isset( $field['type']['type'] ) ) {
			$type = (string) $field['type']['type'];
		}
		if ( $type !== 'email' ) {
			return;
		}
		if ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
	},
	1000
);

/**
 * Mensagens do formulario em portugues (com causa quando aplicavel).
 */
add_filter('default_noptin_form_messages', static function ($messages) {
	if (!is_array($messages)) {
		return $messages;
	}

	$pt = array(
		'success'                => 'Obrigado por se inscrever na newsletter!',
		'invalid_email'          => 'Informe um endereco de e-mail valido.',
		'required_field_missing' => 'Preencha todos os campos obrigatorios.',
		'accept_terms'           => 'Aceite os termos e condicoes para continuar.',
		'already_subscribed'     => 'Este e-mail ja esta inscrito na newsletter. Obrigado!',
		'error'                  => 'Nao foi possivel concluir o cadastro. Tente novamente em instantes.',
		'unsubscribed'           => 'Voce foi descadastrado com sucesso.',
		'not_subscribed'         => 'Este e-mail nao esta inscrito na newsletter.',
		'updated'                => 'Obrigado! Seus dados foram atualizados.',
	);

	foreach ($pt as $key => $text) {
		if (isset($messages[$key]) && is_array($messages[$key])) {
			$messages[$key]['default'] = $text;
		}
	}

	return $messages;
});

/**
 * Traduz erros genericos do Noptin e anexa a causa do banco quando existir.
 */
add_action('noptin_form_error', static function ($listener) {
	if (!is_object($listener) || !isset($listener->error) || !is_wp_error($listener->error)) {
		return;
	}

	global $wpdb;

	$map = array(
		'An error occurred. Try again.'                       => 'Nao foi possivel concluir o cadastro.',
		'An error occurred'                                   => 'Nao foi possivel concluir o cadastro.',
		'Please provide a valid email address'                => 'Informe um endereco de e-mail valido.',
		'Please provide a valid email address.'               => 'Informe um endereco de e-mail valido.',
		'Oops. Something went wrong. Please try again later.' => 'Nao foi possivel concluir o cadastro. Tente novamente em instantes.',
	);

	$rewritten = new WP_Error();

	foreach ($listener->error->get_error_codes() as $code) {
		foreach ($listener->error->get_error_messages($code) as $message) {
			$translated = $message;
			foreach ($map as $en => $pt) {
				if (strcasecmp(trim((string) $message), $en) === 0) {
					$translated = $pt;
					break;
				}
			}

			$has_cause = (bool) preg_match('/conclus[aã]o do cadastro:/iu', $translated)
				|| (bool) preg_match('/concluir o cadastro:/iu', $translated);

			if (
				!$has_cause
				&& !empty($wpdb->last_error)
				&& stripos($translated, $wpdb->last_error) === false
				&& preg_match('/cadastro|erro|error/i', $translated)
			) {
				$translated = rtrim($translated, '.') . '. Causa: ' . $wpdb->last_error;
			}

			$rewritten->add($code, $translated);
		}
	}

	$listener->error = $rewritten;
});

/**
 * Processa shortcodes em widgets de texto/HTML (alguns contextos nao passam por the_content).
 */
add_filter('widget_text', 'do_shortcode', 11);
add_filter('widget_custom_html_content', 'do_shortcode', 11);

/**
 * Form 2859 e popup (auto no load), mas a home embute [noptin-form id=2859].
 * O Noptin bloqueia shortcode em popup/slide-in — forca inpost so no shortcode.
 */
const CCD_NOPTIN_FORM_ID = 2859;

add_filter(
	'noptin_form_optinType',
	static function ( $type, $form ) {
		if ( empty( $GLOBALS['ccd_noptin_shortcode_inpost'] ) ) {
			return $type;
		}
		if ( ! is_object( $form ) || (int) $form->id !== CCD_NOPTIN_FORM_ID ) {
			return $type;
		}
		return 'inpost';
	},
	10,
	2
);

add_action(
	'init',
	static function () {
		if ( ! shortcode_exists( 'noptin-form' ) ) {
			return;
		}
		remove_shortcode( 'noptin-form' );
		add_shortcode(
			'noptin-form',
			static function ( $atts ) {
				$atts = is_array( $atts ) ? $atts : array();
				$id   = isset( $atts['id'] ) ? (int) $atts['id'] : 0;
				if ( $id === CCD_NOPTIN_FORM_ID ) {
					$GLOBALS['ccd_noptin_shortcode_inpost'] = true;
				}
				$html = '';
				if ( class_exists( '\Hizzle\Noptin\Forms\Renderer' ) ) {
					$html = (string) \Hizzle\Noptin\Forms\Renderer::legacy_shortcode( $atts );
				}
				unset( $GLOBALS['ccd_noptin_shortcode_inpost'] );
				return $html;
			}
		);
	},
	20
);

/**
 * reCAPTCHA v2 no formulario de newsletter (popup + shortcode).
 */
add_action(
	'before_output_noptin_form_submit_button',
	static function ( $form ) {
		if ( ! is_object( $form ) || (int) $form->id !== CCD_NOPTIN_FORM_ID ) {
			return;
		}
		if ( ! function_exists( 'ccd_recaptcha_is_configured' ) || ! ccd_recaptcha_is_configured() ) {
			return;
		}

		static $n = 0;
		++$n;
		$keys = ccd_recaptcha_keys();
		echo '<div class="ccd-recaptcha-field ccd-noptin-recaptcha-wrap">';
		echo '<div class="g-recaptcha" id="ccd-noptin-recaptcha-' . esc_attr( (string) $n ) . '"'
			. ' data-sitekey="' . esc_attr( $keys['site'] ) . '"></div>';
		echo '</div>';
	},
	10
);

add_action(
	'noptin_form_errors',
	static function ( $listener ) {
		if ( ! is_object( $listener ) || empty( $listener->error ) || ! is_wp_error( $listener->error ) ) {
			return;
		}
		if ( ! function_exists( 'ccd_recaptcha_is_configured' ) || ! ccd_recaptcha_is_configured() ) {
			return;
		}

		$submitted = isset( $listener->submitted ) && is_array( $listener->submitted )
			? $listener->submitted
			: array();
		$source    = isset( $submitted['source'] ) ? (int) $submitted['source'] : 0;
		$form_id   = isset( $submitted['noptin_form_id'] ) ? (int) $submitted['noptin_form_id'] : 0;
		if ( $source !== CCD_NOPTIN_FORM_ID && $form_id !== CCD_NOPTIN_FORM_ID ) {
			return;
		}

		$token = isset( $submitted['g-recaptcha-response'] )
			? sanitize_text_field( (string) $submitted['g-recaptcha-response'] )
			: '';

		if ( $token === '' || ! ccd_recaptcha_verify( $token ) ) {
			$listener->error->add(
				'recaptcha',
				'Confirme o captcha "Nao sou um robo" e tente novamente.',
				array(
					'selector' => '.ccd-noptin-recaptcha-wrap',
				)
			);
		}
	},
	5
);

/**
 * Avatar do form: usa arquivo local em vez do dominio de producao.
 */
add_filter('noptin_form_image', static function ($image) {
	if (!is_string($image) || $image === '') {
		return $image;
	}

	if ( strpos( $image, 'uploads/2022/07/Bia-2-2.' ) !== false ) {
		return content_url( 'uploads/2022/07/Bia-2-2.jpg' );
	}

	return $image;
});

/** Altura automatica para nao cortar titulo/avatar. */
add_filter('noptin_form_formHeight', static function ($height, $form) {
	if (is_object($form) && (int) $form->id === 2859) {
		return '0px';
	}
	return $height;
}, 10, 2);

add_filter('noptin_form_formRadius', static function ($radius, $form) {
	if (is_object($form) && (int) $form->id === 2859) {
		return '23px';
	}
	return $radius;
}, 10, 2);

/**
 * CSS para o formulario ficar igual ao original (sem cortar titulo/avatar).
 */
add_action('wp_enqueue_scripts', static function () {
	$css = <<<'CSS'
:root {
	--ccd-blue: #03a9f4;
	--ccd-blue-deep: #0288d1;
	--ccd-blue-soft: #e8f7fd;
	--ccd-blue-line: #7ecff7;
	--ccd-ink: #2b3a42;
	--ccd-surface: #ffffff;
	--ccd-radius: 14px;
}

/* Mesma identidade visual do formulario de contato */
.noptin-form-id-2859 .noptin-optin-form-wrapper {
	overflow: visible !important;
	min-height: auto !important;
	height: auto !important;
	width: min(100%, 620px) !important;
	border-radius: 22px !important;
	border: 1px solid rgba(3, 169, 244, 0.18) !important;
	border-style: solid !important;
	border-color: rgba(3, 169, 244, 0.18) !important;
	border-width: 1px !important;
	padding: 1.75rem 1.5rem 1.5rem !important;
	box-sizing: border-box;
	background: linear-gradient(180deg, var(--ccd-blue-soft) 0%, var(--ccd-surface) 42%) !important;
	background-color: var(--ccd-surface) !important;
	color: var(--ccd-ink) !important;
	box-shadow: 0 18px 40px rgba(3, 169, 244, 0.12) !important;
}
.noptin-form-id-2859 .noptin-form-header {
	align-items: center;
	gap: 16px;
	margin-bottom: 16px;
}
.noptin-form-id-2859 .noptin-form-header-image {
	flex: 0 0 72px;
	width: 72px;
	max-width: 72px;
}
.noptin-form-id-2859 .noptin-form-header-image img {
	display: block !important;
	width: 72px !important;
	height: 72px !important;
	max-width: none !important;
	object-fit: cover !important;
	border-radius: 50% !important;
	border: 2px solid #fff !important;
	box-shadow: 0 4px 12px rgba(3, 169, 244, 0.2);
}
.noptin-form-id-2859 .noptin-form-heading {
	font-size: 1.25rem !important;
	line-height: 1.35 !important;
	font-weight: 600 !important;
	color: var(--ccd-ink) !important;
}
.noptin-form-id-2859 .noptin-form-field__email,
.noptin-form-id-2859 input.noptin-form-field,
.noptin-form-id-2859 .noptin-form-field {
	border-radius: 999px !important;
	min-height: 3rem !important;
	border: 1.5px solid rgba(3, 169, 244, 0.28) !important;
	background: var(--ccd-surface) !important;
	color: var(--ccd-ink) !important;
	padding: 0.85rem 1.05rem !important;
	font-size: 1rem !important;
	box-shadow: none !important;
}
.noptin-form-id-2859 .noptin-form-field__email:focus,
.noptin-form-id-2859 input.noptin-form-field:focus {
	outline: none !important;
	border-color: var(--ccd-blue) !important;
	box-shadow: 0 0 0 4px rgba(3, 169, 244, 0.16) !important;
}
.noptin-form-id-2859 .ccd-noptin-recaptcha-wrap,
.noptin-popup .ccd-noptin-recaptcha-wrap {
	display: flex;
	justify-content: center;
	margin: 0.85rem 0 0.35rem;
	width: 100%;
}
.noptin-form-id-2859 .ccd-noptin-recaptcha-wrap .g-recaptcha,
.noptin-popup .ccd-noptin-recaptcha-wrap .g-recaptcha {
	transform-origin: center center;
}
.noptin-form-id-2859 .noptin-form-submit,
.noptin-form-id-2859 input.noptin-form-submit,
.noptin-form-id-2859 input[type="submit"].noptin-form-submit {
	display: inline-flex !important;
	align-items: center;
	justify-content: center;
	width: 100% !important;
	min-height: 3rem !important;
	margin-top: 0.65rem !important;
	border: 0 !important;
	border-radius: 999px !important;
	background: linear-gradient(135deg, var(--ccd-blue) 0%, var(--ccd-blue-deep) 100%) !important;
	background-color: var(--ccd-blue) !important;
	color: #fff !important;
	font-size: 1rem !important;
	font-weight: 600 !important;
	letter-spacing: 0.02em;
	text-transform: none !important;
	box-shadow: 0 10px 22px rgba(3, 169, 244, 0.28) !important;
}
.noptin-form-id-2859 .noptin-form-submit:hover,
.noptin-form-id-2859 input.noptin-form-submit:hover {
	filter: brightness(1.03);
}
/*
 * Fechar (X):
 * - so no modal (.noptin-popup no body; clone do template-holder)
 * - oculto no shortcode embutido no final da home/conteudo
 */
.hentry .noptin-popup-close,
.entry-content .noptin-popup-close,
.page-content .noptin-popup-close,
.post-content .noptin-popup-close,
article .noptin-popup-close {
	display: none !important;
}
.noptin-popup .noptin-popup-close,
.noptin-popup-template-holder .noptin-popup-close {
	display: inline-flex !important;
	align-items: center !important;
	justify-content: center !important;
	opacity: 1 !important;
	background: linear-gradient(135deg, var(--ccd-blue) 0%, var(--ccd-blue-deep) 100%) !important;
	background-color: var(--ccd-blue) !important;
	color: #ffffff !important;
	border: 0 !important;
	border-radius: 999px !important;
	width: 2.5rem !important;
	height: 2.5rem !important;
	box-shadow: 0 10px 22px rgba(3, 169, 244, 0.28) !important;
	cursor: pointer !important;
	z-index: 100000000 !important;
	transition: filter 0.15s ease, box-shadow 0.2s ease;
}
.noptin-popup .noptin-popup-close svg,
.noptin-popup-template-holder .noptin-popup-close svg {
	display: none !important;
}
.noptin-popup .noptin-popup-close::before,
.noptin-popup-template-holder .noptin-popup-close::before {
	content: "\00d7";
	color: #ffffff !important;
	font-family: Arial, Helvetica, sans-serif !important;
	font-size: 2rem !important;
	font-weight: 900 !important;
	line-height: 1 !important;
	margin-top: -0.05em;
	speak: never;
}
.noptin-popup .noptin-popup-close:hover,
.noptin-popup-template-holder .noptin-popup-close:hover {
	filter: brightness(1.03);
	background: linear-gradient(135deg, var(--ccd-blue) 0%, var(--ccd-blue-deep) 100%) !important;
	background-color: var(--ccd-blue) !important;
	box-shadow: 0 12px 26px rgba(3, 169, 244, 0.34) !important;
}
@media (max-width: 600px) {
	.noptin-form-id-2859 .noptin-optin-form-wrapper {
		padding: 1.35rem 1.1rem 1.25rem !important;
		border-radius: 18px !important;
	}
	.noptin-form-id-2859 .noptin-form-heading {
		font-size: 1.1rem !important;
	}
	.noptin-form-id-2859 .noptin-form-header-image img {
		width: 56px !important;
		height: 56px !important;
	}
}
CSS;

	// Sem depender do handle do Noptin (pode nao existir em todas as paginas).
	wp_register_style('ccd-noptin-form', false, array(), '1.3.2');
	wp_enqueue_style('ccd-noptin-form');
	wp_add_inline_style('ccd-noptin-form', $css);
}, 20);

/**
 * Alinha o form 2859: popup no 1o acesso + imagem/altura locais.
 * Versao 6: nao forcar popup a cada pagina para admin logado.
 */
add_action( 'init', static function () {
	if ( get_option( 'ccd_noptin_form_2859_fixed' ) === '6' ) {
		return;
	}

	$state = get_post_meta( 2859, '_noptin_state', true );
	if ( ! is_array( $state ) ) {
		return;
	}

	$state['image']      = content_url( 'uploads/2022/07/Bia-2-2.jpg' );
	$state['formHeight'] = '0px';
	$state['formRadius'] = '23px';
	$state['formWidth']  = isset( $state['formWidth'] ) && $state['formWidth'] !== ''
		? $state['formWidth']
		: '620px';
	$state['optinType']         = 'popup';
	$state['optinStatus']       = 'true';
	$state['descriptionColor']  = '#01579b';
	$state['triggerPopup']      = 'immeadiate';
	$state['timeDelayDuration'] = ! empty( $state['timeDelayDuration'] ) ? $state['timeDelayDuration'] : '4';
	// No editor Noptin: checked = 1x/semana; unchecked = 1x/sessao.
	$state['DisplayOncePerSession'] = true;
	$state['hideSeconds']           = defined( 'WEEK_IN_SECONDS' ) ? WEEK_IN_SECONDS : ( 7 * DAY_IN_SECONDS );
	if ( empty( $state['slideDirection'] ) ) {
		$state['slideDirection'] = 'bottom_right';
	}

	update_post_meta( 2859, '_noptin_state', $state );
	update_post_meta( 2859, '_noptin_optin_type', 'popup' );

	// Padrao do Noptin e true — admin logado via o cookie e o popup volta a cada clique/pagina.
	$noptin_opts = get_option( 'noptin_options', array() );
	if ( ! is_array( $noptin_opts ) ) {
		$noptin_opts = array();
	}
	$noptin_opts['always_show_to_admin'] = false;
	update_option( 'noptin_options', $noptin_opts, false );

	update_option( 'ccd_noptin_form_2859_fixed', '6', false );

	wp_cache_delete('noptin_popup_forms', 'noptin');

	$dir = WP_CONTENT_DIR . '/cache/ccd-page';
	if (is_dir($dir)) {
		foreach (glob($dir . '/*.html') ?: array() as $file) {
			@unlink($file);
		}
	}
}, 30);
