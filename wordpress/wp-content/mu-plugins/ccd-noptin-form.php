<?php
/**
 * Plugin Name: CCD Noptin Form
 * Description: Garante o shortcode [noptin-form], imagem local e layout do formulario 2859.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Processa shortcodes em widgets de texto/HTML (alguns contextos nao passam por the_content).
 */
add_filter('widget_text', 'do_shortcode', 11);
add_filter('widget_custom_html_content', 'do_shortcode', 11);

/**
 * Avatar do form: usa arquivo local em vez do dominio de producao.
 */
add_filter('noptin_form_image', static function ($image) {
	if (!is_string($image) || $image === '') {
		return $image;
	}

	if (strpos($image, 'uploads/2022/07/Bia-2-2.png') !== false) {
		return content_url('uploads/2022/07/Bia-2-2.png');
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
.noptin-form-id-2859 .noptin-optin-form-wrapper {
	overflow: visible !important;
	min-height: auto !important;
	height: auto !important;
	border-radius: 23px !important;
	border-style: solid !important;
	padding: 24px !important;
	box-sizing: border-box;
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
}
.noptin-form-id-2859 .noptin-form-heading {
	font-size: 22px !important;
	line-height: 1.35 !important;
}
.noptin-form-id-2859 .noptin-form-submit {
	border-radius: 8px !important;
	border: 0 !important;
	min-height: 48px;
}
.noptin-form-id-2859 .noptin-form-field__email {
	border-radius: 8px !important;
	min-height: 48px;
}
@media (max-width: 600px) {
	.noptin-form-id-2859 .noptin-form-heading {
		font-size: 18px !important;
	}
	.noptin-form-id-2859 .noptin-form-header-image img {
		width: 56px !important;
		height: 56px !important;
	}
}
CSS;

	wp_register_style('ccd-noptin-form', false, array('noptin-form'), null);
	wp_enqueue_style('ccd-noptin-form');
	wp_add_inline_style('ccd-noptin-form', $css);
}, 20);

/**
 * Atualiza uma vez o estado do form 2859 (imagem local + altura).
 */
add_action('init', static function () {
	if (get_option('ccd_noptin_form_2859_fixed') === '2') {
		return;
	}

	$state = get_post_meta(2859, '_noptin_state', true);
	if (!is_array($state)) {
		return;
	}

	$state['image']      = content_url('uploads/2022/07/Bia-2-2.png');
	$state['formHeight'] = '0px';
	$state['formRadius'] = '23px';

	update_post_meta(2859, '_noptin_state', $state);
	update_option('ccd_noptin_form_2859_fixed', '2', false);

	$dir = WP_CONTENT_DIR . '/cache/ccd-page';
	if (is_dir($dir)) {
		foreach (glob($dir . '/*.html') ?: array() as $file) {
			@unlink($file);
		}
	}
}, 30);
