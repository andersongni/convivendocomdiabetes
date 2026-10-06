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
$ttl   = defined('CCD_PAGE_CACHE_TTL') ? (int) CCD_PAGE_CACHE_TTL : 600;
if ($mtime === false || (time() - $mtime) > $ttl) {
	return;
}

$html = file_get_contents($file);
if ($html === false) {
	return;
}

$lib = WP_CONTENT_DIR . '/ccd-env-urls-lib.php';
if (is_readable($lib)) {
	require_once $lib;
	if (function_exists('ccd_env_url_rewrite')) {
		$html = ccd_env_url_rewrite($html);
	}
}

header('Content-Type: text/html; charset=UTF-8');
header('X-CCD-Cache: HIT');
header('Cache-Control: public, max-age=60');
echo $html;
exit;
