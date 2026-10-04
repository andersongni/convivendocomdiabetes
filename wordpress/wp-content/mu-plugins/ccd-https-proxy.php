<?php
/**
 * Plugin Name: CCD HTTPS behind proxy
 * Description: Detecta HTTPS no reverse proxy (Railway) para assets e admin.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (
	(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
	|| (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
) {
	$_SERVER['HTTPS'] = 'on';
}
