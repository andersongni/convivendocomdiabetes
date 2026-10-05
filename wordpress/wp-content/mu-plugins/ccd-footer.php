<?php
/**
 * Plugin Name: CCD Footer
 * Description: Remove credito do tema no rodape.
 */

if (!defined('ABSPATH')) {
	exit;
}

add_filter('mesmerize_footer_content_copyright_text_default', static function () {
	return '&copy; {year} {blogname}.';
});

add_filter('theme_mod_footer_content_copyright_text', static function ($value) {
	if (!is_string($value) || $value === '') {
		return '&copy; {year} {blogname}.';
	}

	$value = preg_replace(
		'/\s*Built using WordPress and\s*<a[^>]*>EmpowerWP Theme<\/a>\.?\s*/i',
		'',
		$value
	);

	$value = preg_replace('/\s*Built using WordPress and the\s*<a[^>]*>Mesmerize Theme<\/a>\.?\s*/i', '', $value);

	return $value !== '' ? $value : '&copy; {year} {blogname}.';
});
