<?php
/**
 * Stubs minimos para exercitar helpers CCD sem bootstrap completo do WP.
 */

declare(strict_types=1);

if (!function_exists('add_action')) {
	function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
		unset($hook, $callback, $priority, $accepted_args);
	}
}
if (!function_exists('add_filter')) {
	function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
		unset($hook, $callback, $priority, $accepted_args);
	}
}
if (!function_exists('wp_strip_all_tags')) {
	function wp_strip_all_tags($string, $remove_breaks = false) {
		$string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $string);
		$string = strip_tags((string) $string);
		if ($remove_breaks) {
			$string = preg_replace('/[\\r\\n\\t ]+/', ' ', $string);
		}
		return trim((string) $string);
	}
}
if (!function_exists('esc_html')) {
	function esc_html($text) {
		return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
	}
}
if (!function_exists('esc_attr')) {
	function esc_attr($text) {
		return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
	}
}
if (!function_exists('esc_url')) {
	function esc_url($url) {
		return (string) $url;
	}
}
if (!function_exists('home_url')) {
	function home_url($path = '') {
		$path = (string) $path;
		if ($path !== '' && $path[0] !== '/') {
			$path = '/' . $path;
		}
		return 'https://convivendocomdiabetes.com' . $path;
	}
}
if (!function_exists('get_option')) {
	function get_option($option, $default = false) {
		unset($option);
		return $default;
	}
}
if (!function_exists('is_admin')) {
	function is_admin() {
		return false;
	}
}
if (!function_exists('wp_doing_ajax')) {
	function wp_doing_ajax() {
		return false;
	}
}
if (!function_exists('wp_doing_cron')) {
	function wp_doing_cron() {
		return false;
	}
}
if (!function_exists('sanitize_title')) {
	function sanitize_title($title) {
		$title = strtolower((string) $title);
		$title = preg_replace('/[^a-z0-9\\-]+/', '-', $title);
		return trim((string) $title, '-');
	}
}
if (!function_exists('wp_parse_url')) {
	function wp_parse_url($url, $component = -1) {
		return parse_url((string) $url, $component);
	}
}
if (!function_exists('get_bloginfo')) {
	function get_bloginfo($show = '') {
		return $show === 'name' ? 'Convivendo com Diabetes' : '';
	}
}
if (!function_exists('term_description')) {
	function term_description($term_id = 0, $taxonomy = null) {
		unset($term_id, $taxonomy);
		return '';
	}
}
if (!function_exists('wp_salt')) {
	function wp_salt($scheme = 'auth') {
		return 'ccd-ci-test-salt-' . (string) $scheme;
	}
}
if (!function_exists('is_ssl')) {
	function is_ssl() {
		return true;
	}
}
if (!function_exists('add_query_arg')) {
	function add_query_arg($args, $url = '') {
		if (!is_array($args)) {
			return (string) $url;
		}
		$sep = str_contains((string) $url, '?') ? '&' : '?';
		return (string) $url . $sep . http_build_query($args);
	}
}
if (!function_exists('wp_unslash')) {
	function wp_unslash($value) {
		return $value;
	}
}
if (!class_exists('WP_Term', false)) {
	class WP_Term {
		public $term_id = 0;
		public $name = '';
		public $slug = '';
		public $taxonomy = 'category';
		public $parent = 0;
	}
}
if (!class_exists('WP_Post', false)) {
	class WP_Post {
		public $ID = 0;
		public $post_name = '';
		public $post_type = 'post';
		public $post_title = '';
	}
}
if (!function_exists('get_term_by')) {
	function get_term_by($field, $value, $taxonomy = '') {
		if ($field === 'slug' && $taxonomy === 'category' && $value === 'diabetes') {
			$t = new WP_Term();
			$t->term_id = 1;
			$t->slug = 'diabetes';
			$t->name = 'Diabetes';
			return $t;
		}
		return false;
	}
}
if (!function_exists('get_posts')) {
	function get_posts($args = array()) {
		$name = isset($args['name']) ? (string) $args['name'] : '';
		if ($name === 'hipoglicemia') {
			$p = new WP_Post();
			$p->ID = 10;
			$p->post_name = 'hipoglicemia';
			$p->post_title = 'Hipoglicemia: sinais e o que fazer';
			return array($p);
		}
		if (isset($args['cat']) && (int) $args['cat'] === 1) {
			$p = new WP_Post();
			$p->ID = 11;
			$p->post_name = 'exemplo-categoria';
			$p->post_title = 'Post recente da categoria';
			return array($p);
		}
		return array();
	}
}
if (!function_exists('get_permalink')) {
	function get_permalink($post = 0) {
		if ($post instanceof WP_Post) {
			return 'https://example.test/' . $post->post_name . '/';
		}
		return 'https://example.test/';
	}
}
if (!function_exists('get_the_title')) {
	function get_the_title($post = 0) {
		if ($post instanceof WP_Post) {
			return (string) $post->post_title;
		}
		return '';
	}
}

/**
 * Stub minimo de $wpdb para ccd_category_urls_slugs().
 */
if (!isset($GLOBALS['wpdb'])) {
	$GLOBALS['wpdb'] = new class {
		public $terms = 'wp_terms';
		public $term_taxonomy = 'wp_term_taxonomy';
		/** @var list<string> */
		public $category_slugs = array('diabetes', 'receitas');

		public function get_col($query) {
			unset($query);
			return $this->category_slugs;
		}

		public function delete($table, $where, $format = null) {
			unset($table, $where, $format);
			return false;
		}
	};
}
