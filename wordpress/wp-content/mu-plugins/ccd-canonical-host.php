<?php
/**
 * Plugin Name: CCD Canonical Host
 * Description: 301 de www.convivendocomdiabetes.com para o apex (WP_HOME).
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Host canônico a partir de WP_HOME (sem porta).
 *
 * @return string
 */
function ccd_canonical_host()
{
	$home = '';
	if (defined('WP_HOME') && is_string(WP_HOME) && WP_HOME !== '') {
		$home = WP_HOME;
	} else {
		$env = getenv('WP_HOME');
		if (is_string($env) && $env !== '') {
			$home = $env;
		}
	}

	$host = is_string($home) ? (string) (function_exists('wp_parse_url')
		? wp_parse_url($home, PHP_URL_HOST)
		: parse_url($home, PHP_URL_HOST)) : '';
	return strtolower((string) $host);
}

/**
 * Host da requisição atual (sem porta).
 *
 * @return string
 */
function ccd_request_host()
{
	$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
	$host = strtolower($host);
	if (str_contains($host, ':')) {
		$host = explode(':', $host, 2)[0];
	}
	return $host;
}

/**
 * Redireciona www → host canônico (apex) com 301.
 */
function ccd_canonical_host_redirect()
{
	if (PHP_SAPI === 'cli' || (defined('WP_CLI') && WP_CLI)) {
		return;
	}

	$canonical = ccd_canonical_host();
	if ($canonical === '' || $canonical === 'localhost') {
		return;
	}

	$request_host = ccd_request_host();
	if ($request_host === '' || $request_host === $canonical) {
		return;
	}

	// Só www do mesmo domínio (não redireciona railway.app nem outros hosts).
	if ($request_host !== 'www.' . $canonical) {
		return;
	}

	$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
	if ($uri === '/ccdhealth' || str_starts_with($uri, '/ccdhealth?')) {
		return;
	}

	$scheme = 'https';
	if (defined('WP_HOME') && is_string(WP_HOME) && str_starts_with(WP_HOME, 'http://')) {
		$scheme = 'http';
	}

	$target = $scheme . '://' . $canonical . $uri;
	nocache_headers();
	header('Location: ' . $target, true, 301);
	exit;
}

ccd_canonical_host_redirect();
