<?php
/**
 * Stubs minimos para exercitar helpers CCD sem bootstrap completo do WP.
 */

declare(strict_types=1);

/**
 * Estado mutável para testes de contrato (migrações SEO, etc.).
 *
 * @var array{
 *   options: array<string, mixed>,
 *   transients: array<string, mixed>,
 *   post_meta: array<string, mixed>,
 *   is_admin: bool,
 *   is_user_logged_in: bool,
 *   is_feed: bool,
 *   is_search: bool,
 *   is_404: bool,
 *   doing_cron: bool,
 *   doing_ajax: bool,
 *   update_post_calls: int,
 *   update_option_log: list<string>,
 *   update_term_calls: int
 * }
 */
$GLOBALS['ccd_test'] = array(
	'options'            => array(),
	'transients'         => array(),
	'post_meta'          => array(),
	'is_admin'           => false,
	'is_user_logged_in'  => false,
	'is_feed'            => false,
	'is_search'          => false,
	'is_404'             => false,
	'doing_cron'         => false,
	'doing_ajax'         => false,
	'update_post_calls'  => 0,
	'update_option_log'  => array(),
	'update_term_calls'  => 0,
);

/**
 * @return void
 */
function ccd_test_reset_state(): void {
	$GLOBALS['ccd_test'] = array(
		'options'           => array(),
		'transients'        => array(),
		'post_meta'         => array(),
		'is_admin'          => false,
		'is_user_logged_in' => false,
		'is_feed'           => false,
		'is_search'         => false,
		'is_404'            => false,
		'doing_cron'        => false,
		'doing_ajax'        => false,
		'update_post_calls' => 0,
		'update_option_log' => array(),
		'update_term_calls' => 0,
	);
	if (isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb']) && property_exists($GLOBALS['wpdb'], 'db_options')) {
		$GLOBALS['wpdb']->db_options = array();
	}
	if (defined('WP_CLI') && WP_CLI) {
		// Constante nao pode ser unset; testes usam is_admin/cron.
	}
}

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
		$option = (string) $option;
		if (array_key_exists($option, $GLOBALS['ccd_test']['options'])) {
			return $GLOBALS['ccd_test']['options'][$option];
		}
		return $default;
	}
}
if (!function_exists('wp_cache_delete')) {
	function wp_cache_delete($key, $group = '') {
		unset($key, $group);
		return true;
	}
}
if (!function_exists('wp_cache_set')) {
	function wp_cache_set($key, $data, $group = '', $expire = 0) {
		unset($group, $expire);
		// Em testes, prime_cache alinha o “object cache” com get_option.
		if (isset($GLOBALS['ccd_test']['options'])) {
			$GLOBALS['ccd_test']['options'][(string) $key] = $data;
		}
		return true;
	}
}
if (!function_exists('update_option')) {
	function update_option($option, $value, $autoload = null) {
		unset($autoload);
		$option = (string) $option;
		$GLOBALS['ccd_test']['options'][$option] = $value;
		$GLOBALS['ccd_test']['update_option_log'][] = $option;
		return true;
	}
}
if (!function_exists('get_transient')) {
	function get_transient($transient) {
		$key = (string) $transient;
		return array_key_exists($key, $GLOBALS['ccd_test']['transients'])
			? $GLOBALS['ccd_test']['transients'][$key]
			: false;
	}
}
if (!function_exists('set_transient')) {
	function set_transient($transient, $value, $expiration = 0) {
		unset($expiration);
		$GLOBALS['ccd_test']['transients'][(string) $transient] = $value;
		return true;
	}
}
if (!function_exists('delete_transient')) {
	function delete_transient($transient) {
		unset($GLOBALS['ccd_test']['transients'][(string) $transient]);
		return true;
	}
}
if (!function_exists('is_admin')) {
	function is_admin() {
		return !empty($GLOBALS['ccd_test']['is_admin']);
	}
}
if (!function_exists('is_user_logged_in')) {
	function is_user_logged_in() {
		return !empty($GLOBALS['ccd_test']['is_user_logged_in']);
	}
}
if (!function_exists('is_feed')) {
	function is_feed() {
		return !empty($GLOBALS['ccd_test']['is_feed']);
	}
}
if (!function_exists('is_search')) {
	function is_search() {
		return !empty($GLOBALS['ccd_test']['is_search']);
	}
}
if (!function_exists('is_404')) {
	function is_404() {
		return !empty($GLOBALS['ccd_test']['is_404']);
	}
}
if (!function_exists('untrailingslashit')) {
	function untrailingslashit($value) {
		return rtrim((string) $value, '/\\');
	}
}
if (!function_exists('wp_doing_ajax')) {
	function wp_doing_ajax() {
		return !empty($GLOBALS['ccd_test']['doing_ajax']);
	}
}
if (!function_exists('wp_doing_cron')) {
	function wp_doing_cron() {
		return !empty($GLOBALS['ccd_test']['doing_cron']);
	}
}
if (!function_exists('get_post_meta')) {
	function get_post_meta($post_id, $key = '', $single = false) {
		$k = (int) $post_id . '|' . (string) $key;
		if (!array_key_exists($k, $GLOBALS['ccd_test']['post_meta'])) {
			return $single ? '' : array();
		}
		$val = $GLOBALS['ccd_test']['post_meta'][$k];
		return $single ? $val : array($val);
	}
}
if (!function_exists('update_post_meta')) {
	function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') {
		unset($prev_value);
		$GLOBALS['ccd_test']['post_meta'][(int) $post_id . '|' . (string) $meta_key] = $meta_value;
		return true;
	}
}
if (!function_exists('wp_update_post')) {
	function wp_update_post($postarr, $wp_error = false) {
		unset($wp_error);
		$GLOBALS['ccd_test']['update_post_calls']++;
		if (is_array($postarr) && isset($postarr['ID'])) {
			return (int) $postarr['ID'];
		}
		return 1;
	}
}
if (!function_exists('wp_insert_post')) {
	function wp_insert_post($postarr, $wp_error = false) {
		unset($postarr, $wp_error);
		$GLOBALS['ccd_test']['update_post_calls']++;
		return 99;
	}
}
if (!function_exists('wp_set_post_categories')) {
	function wp_set_post_categories($post_id, $post_categories = array(), $append = false) {
		unset($post_id, $post_categories, $append);
		return true;
	}
}
if (!function_exists('wp_update_term')) {
	function wp_update_term($term_id, $taxonomy, $args = array()) {
		unset($term_id, $taxonomy, $args);
		$GLOBALS['ccd_test']['update_term_calls']++;
		return array('term_id' => 1);
	}
}
if (!function_exists('current_time')) {
	function current_time($type, $gmt = 0) {
		unset($type, $gmt);
		return '2026-01-01 00:00:00';
	}
}
if (!function_exists('get_post')) {
	function get_post($post = null, $output = 'OBJECT', $filter = 'raw') {
		unset($output, $filter);
		if ($post instanceof WP_Post) {
			return $post;
		}
		return null;
	}
}
if (!function_exists('get_post_field')) {
	function get_post_field($field, $post = null, $context = 'display') {
		unset($context);
		if ($field === 'post_author') {
			return '1';
		}
		return '';
	}
}
if (!function_exists('get_users')) {
	function get_users($args = array()) {
		unset($args);
		$user = new stdClass();
		$user->ID = 1;
		return array($user);
	}
}
if (!function_exists('is_wp_error')) {
	function is_wp_error($thing) {
		return $thing instanceof WP_Error;
	}
}
if (!class_exists('WP_Error', false)) {
	class WP_Error {
		/** @var string */
		public $message = '';
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
		public $description = '';
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
		$ids_only = isset($args['fields']) && $args['fields'] === 'ids';
		if ($name === 'hipoglicemia') {
			if ($ids_only) {
				return array(10);
			}
			$p = new WP_Post();
			$p->ID = 10;
			$p->post_name = 'hipoglicemia';
			$p->post_title = 'Hipoglicemia: sinais e o que fazer';
			return array($p);
		}
		if (isset($args['cat']) && (int) $args['cat'] === 1) {
			if ($ids_only) {
				return array(11);
			}
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
		public $posts = 'wp_posts';
		public $options = 'wp_options';
		public $prefix = 'wp_';
		/** @var list<string> */
		public $category_slugs = array('diabetes', 'receitas');
		/** @var array<string, string> option_name => value (simula MySQL para drift de cache). */
		public $db_options = array();

		public function get_col($query) {
			unset($query);
			return $this->category_slugs;
		}

		public function get_var($query) {
			$query = (string) $query;
			// prepare() stub nao interpola args; testes de drift usam uma única option.
			if (str_contains($query, 'wp_options') && count($this->db_options) === 1) {
				return (string) reset($this->db_options);
			}
			return null;
		}

		public function esc_like($text) {
			return addcslashes((string) $text, '_%\\');
		}

		public function prepare($query, ...$args) {
			unset($args);
			return (string) $query;
		}

		public function update($table, $data, $where, $format = null, $where_format = null) {
			unset($table, $data, $where, $format, $where_format);
			return false;
		}

		public function delete($table, $where, $format = null) {
			unset($table, $where, $format);
			return false;
		}
	};
}
if (!function_exists('get_terms')) {
	function get_terms($args = array()) {
		unset($args);
		return array();
	}
}
if (!function_exists('update_term_meta')) {
	function update_term_meta($term_id, $meta_key, $meta_value, $prev_value = '') {
		unset($term_id, $meta_key, $meta_value, $prev_value);
		return true;
	}
}
if (!function_exists('get_page_by_path')) {
	function get_page_by_path($page_path, $output = 'OBJECT', $post_type = 'page') {
		unset($page_path, $output, $post_type);
		return null;
	}
}
