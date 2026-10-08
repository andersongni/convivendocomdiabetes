<?php
/**
 * Template 404 amigavel (carregado via ccd-http-errors.php).
 */

if (!defined('ABSPATH')) {
	exit;
}

status_header(404);
nocache_headers();

get_header();
?>
<div class="content page-content">
	<div class="gridContainer">
		<?php ccd_http_error_render_card(404); ?>
	</div>
</div>
<?php
get_footer();
