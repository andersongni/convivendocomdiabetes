<?php
/**
 * Plugin Name: CCD Console Cleanup
 * Description: Remove o aviso do jQuery Migrate no front e serve avatares Gravatar na mesma origem (evita Tracking Prevention).
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * WordPress registra jQuery com dependencia de jquery-migrate, que sempre
 * imprime "JQMIGRATE: Migrate is installed..." no console. No front isso
 * nao e necessario na maioria dos temas/plugins modernos.
 */
add_action('wp_default_scripts', static function ($scripts) {
	if (is_admin() || empty($scripts->registered['jquery'])) {
		return;
	}

	$scripts->registered['jquery']->deps = array_diff(
		$scripts->registered['jquery']->deps,
		array('jquery-migrate')
	);
});

/**
 * Reescreve URLs do Gravatar para um endpoint first-party.
 * O Tracking Prevention do browser bloqueia storage de secure.gravatar.com.
 */
add_filter('get_avatar_url', static function ($url, $id_or_email, $args) {
	if (!is_string($url) || strpos($url, 'gravatar.com') === false) {
		return $url;
	}

	$path = (string) wp_parse_url($url, PHP_URL_PATH);
	$hash = preg_replace('/[^a-f0-9]/', '', strtolower(basename($path)));
	if ($hash === '') {
		return $url;
	}

	$size    = isset($args['size']) ? (int) $args['size'] : 96;
	$default = isset($args['default']) ? (string) $args['default'] : 'mm';
	$rating  = isset($args['rating']) ? (string) $args['rating'] : 'g';

	return add_query_arg(
		array(
			's' => max(1, min(512, $size)),
			'd' => $default,
			'r' => $rating,
		),
		rest_url('ccd/v1/avatar/' . $hash)
	);
}, 10, 3);

add_action('rest_api_init', static function () {
	register_rest_route(
		'ccd/v1',
		'/avatar/(?P<hash>[a-f0-9]+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'ccd_serve_proxied_avatar',
			'permission_callback' => '__return_true',
			'args'                => array(
				'hash' => array(
					'required' => true,
					'type'     => 'string',
				),
				's'    => array(
					'default'           => 96,
					'sanitize_callback' => static function ($value) {
						return max(1, min(512, (int) $value));
					},
				),
				'd'    => array(
					'default'           => 'mm',
					'sanitize_callback' => static function ($value) {
						$value = (string) $value;
						if (preg_match('#^https?://#i', $value)) {
							return esc_url_raw($value);
						}
						return sanitize_key($value);
					},
				),
				'r'    => array(
					'default'           => 'g',
					'sanitize_callback' => static function ($value) {
						return sanitize_key((string) $value);
					},
				),
			),
		)
	);
});

/**
 * Proxy + cache em disco dos avatares Gravatar.
 *
 * @param WP_REST_Request $request Request.
 */
function ccd_serve_proxied_avatar(WP_REST_Request $request)
{
	$hash = strtolower((string) $request['hash']);
	if (strlen($hash) < 32) {
		return new WP_Error('ccd_avatar_invalid', 'Invalid avatar hash.', array('status' => 400));
	}

	$size    = (int) $request->get_param('s');
	$default = (string) $request->get_param('d');
	$rating  = (string) $request->get_param('r');

	if ($default === '') {
		$default = 'mm';
	}
	if ($rating === '') {
		$rating = 'g';
	}

	$uploads   = wp_upload_dir();
	$cache_dir = trailingslashit($uploads['basedir']) . 'ccd-avatars';
	if (!wp_mkdir_p($cache_dir)) {
		return new WP_Error('ccd_avatar_cache', 'Could not create avatar cache.', array('status' => 500));
	}

	$index_file = $cache_dir . '/index.php';
	if (!file_exists($index_file)) {
		file_put_contents($index_file, "<?php\n// Silence is golden.\n", LOCK_EX);
	}

	$cache_key  = $hash . '-' . $size . '-' . md5($default . '|' . $rating);
	$cache_file = $cache_dir . '/' . $cache_key . '.bin';
	$meta_file  = $cache_file . '.json';
	$ttl        = DAY_IN_SECONDS;

	if (is_readable($cache_file) && is_readable($meta_file) && (filemtime($cache_file) + $ttl) > time()) {
		$meta = json_decode((string) file_get_contents($meta_file), true);
		$type = is_array($meta) && !empty($meta['content_type']) ? $meta['content_type'] : 'image/jpeg';
		ccd_stream_avatar_file($cache_file, $type, $ttl);
	}

	$remote = sprintf(
		'https://secure.gravatar.com/avatar/%s?s=%d&d=%s&r=%s',
		rawurlencode($hash),
		$size,
		rawurlencode($default),
		rawurlencode($rating)
	);

	$response = wp_remote_get(
		$remote,
		array(
			'timeout'    => 8,
			'redirection' => 3,
			'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
		)
	);

	if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
		ccd_stream_fallback_avatar($size);
	}

	$body = wp_remote_retrieve_body($response);
	$type = wp_remote_retrieve_header($response, 'content-type');
	if (!is_string($type) || $type === '') {
		$type = 'image/jpeg';
	}
	$type = strtok($type, ';');

	file_put_contents($cache_file, $body, LOCK_EX);
	file_put_contents(
		$meta_file,
		wp_json_encode(array('content_type' => $type)),
		LOCK_EX
	);

	ccd_stream_avatar_file($cache_file, $type, $ttl);
}

/**
 * SVG local (mystery person) quando o fetch remoto falha.
 *
 * @param int $size Avatar size.
 */
function ccd_stream_fallback_avatar($size)
{
	$size = max(1, min(512, (int) $size));
	$svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 80 80" role="img" aria-label="Avatar">'
		. '<rect width="80" height="80" fill="#c2c2c2"/>'
		. '<circle cx="40" cy="30" r="16" fill="#eee"/>'
		. '<path d="M12 72c4-18 16-28 28-28s24 10 28 28" fill="#eee"/>'
		. '</svg>';

	header('Content-Type: image/svg+xml; charset=UTF-8');
	header('Content-Length: ' . (string) strlen($svg));
	header('Cache-Control: public, max-age=300');
	header('X-Content-Type-Options: nosniff');
	header('X-Robots-Tag: noindex');
	echo $svg;
	exit;
}

/**
 * Envia o arquivo de avatar com headers de cache.
 *
 * @param string $file Absolute path.
 * @param string $type Content-Type.
 * @param int    $ttl  Cache TTL in seconds.
 */
function ccd_stream_avatar_file($file, $type, $ttl)
{
	header('Content-Type: ' . $type);
	header('Content-Length: ' . (string) filesize($file));
	header('Cache-Control: public, max-age=' . (int) $ttl);
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + (int) $ttl) . ' GMT');
	header('X-Content-Type-Options: nosniff');
	header('X-Robots-Tag: noindex');
	readfile($file);
	exit;
}
