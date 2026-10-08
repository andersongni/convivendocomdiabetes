<?php
/**
 * Plugin Name: CCD Page Cache
 * Description: Gera cache HTML de paginas para visitantes (par com advanced-cache.php).
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('CCD_PAGE_CACHE_TTL')) {
	define('CCD_PAGE_CACHE_TTL', 3600);
}

/**
 * @return string
 */
function ccd_page_cache_dir() {
	return WP_CONTENT_DIR . '/cache/ccd-page';
}

/**
 * @return string|null
 */
function ccd_page_cache_file() {
	if (is_admin() || is_user_logged_in() || is_feed() || is_search() || is_404()) {
		return null;
	}
	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
		return null;
	}
	if (!empty($_GET)) {
		return null;
	}
	$host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : 'localhost';
	$uri  = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
	$path = (string) wp_parse_url($uri, PHP_URL_PATH);
	$path = untrailingslashit($path);
	if ($path === '/login' || $path === '/wp-login.php') {
		return null;
	}
	return ccd_page_cache_dir() . '/' . md5($host . '|' . $uri) . '.html';
}

/**
 * @return void
 */
function ccd_page_cache_purge_all() {
	$dir = ccd_page_cache_dir();
	if (!is_dir($dir)) {
		return;
	}
	foreach (glob($dir . '/*.html') ?: array() as $file) {
		@unlink($file);
	}
}

add_action(
	'template_redirect',
	static function () {
		if (!defined('WP_CACHE') || !WP_CACHE) {
			return;
		}
		$file = ccd_page_cache_file();
		if (!$file) {
			return;
		}

		ob_start(
			static function ($html) use ($file) {
				if (!is_string($html) || strlen($html) < 512) {
					return $html;
				}
				if (stripos($html, '</html>') === false) {
					return $html;
				}
				// Nunca gravar HTML de sessão que vê posts private/draft (badge "Privado:" / "Protegido:").
				if (preg_match('/\b(?:Privado|Protegido|Private|Protected):\s/u', $html)) {
					return $html;
				}
				$lib = WP_CONTENT_DIR . '/mu-plugins/ccd-env-urls-lib.php';
				if (is_readable($lib)) {
					require_once $lib;
					if (function_exists('ccd_env_url_rewrite')) {
						$html = ccd_env_url_rewrite($html);
					}
				}
				$dir = dirname($file);
				if (!is_dir($dir)) {
					wp_mkdir_p($dir);
				}
				$tmp = $file . '.' . getmypid() . '.tmp';
				if (file_put_contents($tmp, $html) !== false) {
					@rename($tmp, $file);
					@unlink($tmp);
				}
				return $html;
			}
		);
	},
	0
);

add_action('save_post', 'ccd_page_cache_purge_all');
add_action('deleted_post', 'ccd_page_cache_purge_all');
add_action('switch_theme', 'ccd_page_cache_purge_all');
add_action('activated_plugin', 'ccd_page_cache_purge_all');
add_action('deactivated_plugin', 'ccd_page_cache_purge_all');
