<?php
/**
 * Plugin Name: CCD WPForms REST Compat
 * Description: Polyfill para wpforms_is_wpforms_rest() chamado sem \ dentro de namespaces do WPForms Lite.
 */

namespace WPForms\Integrations\Gutenberg;

if (!function_exists(__NAMESPACE__ . '\\wpforms_is_wpforms_rest')) {
	/**
	 * @return bool
	 */
	function wpforms_is_wpforms_rest()
	{
		return \function_exists('wpforms_is_wpforms_rest') && \wpforms_is_wpforms_rest();
	}
}

namespace WPForms\Integrations\Elementor;

if (!function_exists(__NAMESPACE__ . '\\wpforms_is_wpforms_rest')) {
	/**
	 * @return bool
	 */
	function wpforms_is_wpforms_rest()
	{
		return \function_exists('wpforms_is_wpforms_rest') && \wpforms_is_wpforms_rest();
	}
}
