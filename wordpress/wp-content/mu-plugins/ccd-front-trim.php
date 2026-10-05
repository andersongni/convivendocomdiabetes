<?php
/**
 * Plugin Name: CCD Front Trim
 * Description: Remove scripts/estilos e hints que so pesam no front para visitantes.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @return bool
 */
function ccd_is_front_visitor() {
	return !is_admin() && !is_user_logged_in();
}

add_action(
	'init',
	static function () {
		if (!ccd_is_front_visitor()) {
			return;
		}

		// Emoji
		remove_action('wp_head', 'print_emoji_detection_script', 7);
		remove_action('wp_print_styles', 'print_emoji_styles');
		remove_action('admin_print_scripts', 'print_emoji_detection_script');
		remove_action('admin_print_styles', 'print_emoji_styles');
		remove_filter('the_content_feed', 'wp_staticize_emoji');
		remove_filter('comment_text_rss', 'wp_staticize_emoji');
		remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

		// Head noise
		remove_action('wp_head', 'rsd_link');
		remove_action('wp_head', 'wlwmanifest_link');
		remove_action('wp_head', 'wp_generator');
		remove_action('wp_head', 'wp_shortlink_wp_head', 10);
		remove_action('wp_head', 'rest_output_link_wp_head', 10);
		remove_action('wp_head', 'wp_oembed_add_discovery_links');
		remove_action('wp_head', 'wp_oembed_add_host_js');
		remove_action('template_redirect', 'rest_output_link_header', 11);

		// Heartbeat so no front
		add_action(
			'init',
			static function () {
				wp_deregister_script('heartbeat');
			},
			1
		);
	},
	1
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if (!ccd_is_front_visitor()) {
			return;
		}

		wp_deregister_script('wp-embed');
		wp_dequeue_script('wp-embed');
		wp_dequeue_style('dashicons');
		wp_dequeue_style('wp-block-library');
		wp_dequeue_style('wp-block-library-theme');
		wp_dequeue_style('classic-theme-styles');
		wp_dequeue_style('global-styles');
	},
	100
);

add_filter(
	'style_loader_tag',
	static function ($html, $handle) {
		if ($handle !== 'mesmerize-fonts' || !is_string($html)) {
			return $html;
		}
		// Se alguem reenfileirar a fonte, nao bloqueia render
		$html = str_replace("media='all'", "media='print' onload=\"this.media='all'\"", $html);
		$html = str_replace('media="all"', 'media="print" onload="this.media=\'all\'"', $html);
		return $html;
	},
	10,
	2
);

add_filter('wpseo_debug_markers', '__return_false');
