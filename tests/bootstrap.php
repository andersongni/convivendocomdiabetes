<?php
/**
 * Bootstrap PHPUnit: stubs minimos do WordPress + carrega helpers CCD.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/stubs/');
define('WPINC', 'wp-includes');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);

$root = dirname(__DIR__);
if (!defined('WP_CONTENT_DIR')) {
	define('WP_CONTENT_DIR', $root . '/wordpress/wp-content');
}

require_once __DIR__ . '/stubs/wp-stubs.php';

require_once $root . '/wordpress/wp-content/mu-plugins/ccd-a11y.php';
require_once $root . '/wordpress/wp-content/mu-plugins/ccd-migration-option.php';
require_once $root . '/wordpress/wp-content/mu-plugins/ccd-seo-boost.php';
require_once $root . '/wordpress/wp-content/mu-plugins/ccd-login-url.php';
require_once $root . '/wordpress/wp-content/mu-plugins/ccd-category-urls.php';
require_once $root . '/wordpress/wp-content/mu-plugins/seo-editorial/content.php';
require_once $root . '/wordpress/wp-content/mu-plugins/ccd-seo-editorial.php';
require_once $root . '/wordpress/wp-content/mu-plugins/ccd-page-cache-lib.php';
require_once $root . '/wordpress/wp-content/mu-plugins/ccd-page-cache.php';
