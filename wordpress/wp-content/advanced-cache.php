<?php
/**
 * Cache HTML cedo (antes dos plugins) para visitantes deslogados.
 * Ativado com define('WP_CACHE', true) no wp-config.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (PHP_SAPI === 'cli' || (defined('WP_CLI') && WP_CLI)) {
	return;
}

if (
	(defined('DOING_CRON') && DOING_CRON)
	|| (defined('REST_REQUEST') && REST_REQUEST)
	|| (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
) {
	return;
}

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
	return;
}

if (!empty($_GET)) {
	return;
}

foreach ($_COOKIE as $name => $value) {
	if (
		strpos($name, 'wordpress_logged_in_') === 0
		|| strpos($name, 'comment_author_') === 0
		|| strpos($name, 'woocommerce_') === 0
	) {
		return;
	}
}

$host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : 'localhost';
$uri  = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
$path = (string) parse_url($uri, PHP_URL_PATH);
$path = rtrim($path, '/');
if ($path === '/login' || $path === '/wp-login.php') {
	return;
}
$key  = md5($host . '|' . $uri);
$file = WP_CONTENT_DIR . '/cache/ccd-page/' . $key . '.html';

if (!is_readable($file)) {
	return;
}

$mtime = filemtime($file);
$ttl   = defined('CCD_PAGE_CACHE_TTL') ? (int) CCD_PAGE_CACHE_TTL : 3600;
if ($mtime === false || (time() - $mtime) > $ttl) {
	return;
}

$html = file_get_contents($file);
if ($html === false) {
	return;
}

// Não servir HTML gravado com visão de usuário logado (posts private/draft).
$private_lib = WP_CONTENT_DIR . '/mu-plugins/ccd-page-cache-lib.php';
if (is_readable($private_lib)) {
	require_once $private_lib;
}
if (function_exists('ccd_page_cache_html_looks_private') && ccd_page_cache_html_looks_private($html)) {
	@unlink($file);
	return;
}

$lib = WP_CONTENT_DIR . '/mu-plugins/ccd-env-urls-lib.php';
if (is_readable($lib)) {
	require_once $lib;
	if (function_exists('ccd_env_url_rewrite')) {
		$html = ccd_env_url_rewrite($html);
	}
}

header('Content-Type: text/html; charset=UTF-8');
header('X-CCD-Cache: HIT');
// Browser revalida; Cloudflare/edge pode honrar s-maxage (ver docs/PERFORMANCE.md).
header('Cache-Control: public, max-age=0, s-maxage=3600, must-revalidate');
echo $html;
exit;
