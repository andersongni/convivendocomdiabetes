<?php
/**
 * Pagina amigavel de erro HTTP (403/404/500) — Apache ErrorDocument + fallback.
 *
 * Ex.: listagem de /wp-content/uploads/2022/08/ (403 Forbidden do Apache).
 */

declare(strict_types=1);

$code = 0;
if (isset($_GET['code'])) {
	$code = (int) $_GET['code'];
} elseif (!empty($_SERVER['REDIRECT_STATUS'])) {
	$code = (int) $_SERVER['REDIRECT_STATUS'];
} elseif (!empty($_SERVER['REDIRECT_REDIRECT_STATUS'])) {
	$code = (int) $_SERVER['REDIRECT_REDIRECT_STATUS'];
}

if (!in_array($code, array(400, 401, 403, 404, 405, 408, 410, 429, 500, 502, 503, 504), true)) {
	$code = 403;
}

$wp_load = dirname(__DIR__) . '/wp-load.php';
if (!is_readable($wp_load)) {
	http_response_code($code);
	header('Content-Type: text/html; charset=UTF-8');
	echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Erro '
		. (int) $code
		. '</title></head><body style="font-family:Nunito,Segoe UI,sans-serif;background:#f4f8fb;color:#2b3a42;padding:2rem;text-align:center">'
		. '<h1>Algo deu errado</h1><p>Nao foi possivel abrir este endereco.</p>'
		. '<p><a href="/" style="color:#0288d1">Voltar ao inicio</a></p></body></html>';
	exit;
}

require_once $wp_load;

// Garante funcoes do mu-plugin mesmo se a ordem de carga falhar.
if (!function_exists('ccd_http_error_render_card')) {
	$mu = WP_CONTENT_DIR . '/mu-plugins/ccd-http-errors.php';
	if (is_readable($mu)) {
		require_once $mu;
	}
}

status_header($code);
nocache_headers();

get_header();
?>
<div class="content page-content">
	<div class="gridContainer">
		<?php
		if (function_exists('ccd_http_error_render_card')) {
			ccd_http_error_render_card($code);
		} else {
			echo '<h1>Erro ' . (int) $code . '</h1>';
			echo '<p><a href="' . esc_url(home_url('/')) . '">Voltar ao inicio</a></p>';
		}
		?>
	</div>
</div>
<?php
get_footer();
