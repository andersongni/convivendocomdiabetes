<?php
/**
 * Plugin Name: CCD HTTP Errors
 * Description: Mensagens de erro HTTP amigaveis em portugues (403/404) dentro do visual do site.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Mensagens padrao.
 *
 * @param int $code HTTP status.
 * @return array{title:string,lead:string,detail:string}
 */
function ccd_http_error_messages($code) {
	$map = array(
		403 => array(
			'title'  => 'Acesso não permitido',
			'lead'   => 'Você não tem permissão para ver este conteúdo.',
			'detail' => 'Pastas de arquivos do site não podem ser listadas. Se você chegou aqui por um link, ele pode estar incompleto ou desatualizado.',
		),
		404 => array(
			'title'  => 'Página não encontrada',
			'lead'   => 'Não encontramos o que você procura.',
			'detail' => 'O endereço pode ter sido digitado errado, ou o conteúdo foi movido ou removido.',
		),
		500 => array(
			'title'  => 'Erro no servidor',
			'lead'   => 'Algo inesperado aconteceu do nosso lado.',
			'detail' => 'Tente novamente em instantes. Se o problema continuar, fale conosco pela página de contato.',
		),
	);

	return isset($map[$code])
		? $map[$code]
		: array(
			'title'  => 'Não foi possível abrir esta página',
			'lead'   => 'Ocorreu um erro ao processar o seu pedido.',
			'detail' => 'Código do erro: ' . (int) $code . '.',
		);
}

/**
 * Renderiza o card de erro (reutilizado no 404 do WP).
 *
 * @param int $code HTTP status.
 */
function ccd_http_error_render_card($code) {
	$msg     = ccd_http_error_messages((int) $code);
	$home    = home_url('/');
	$contato = home_url('/contato/');
	?>
	<style id="ccd-http-error-wp404">
		.ccd-http-error-wrap {
			min-height: 50vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 2.5rem 1.25rem 3rem;
			box-sizing: border-box;
		}
		.ccd-http-error-card {
			width: min(100%, 560px);
			padding: 2rem 1.75rem 1.75rem;
			border-radius: 22px;
			background: linear-gradient(180deg, #e8f7fd 0%, #ffffff 42%);
			border: 1px solid rgba(3, 169, 244, 0.18);
			box-shadow: 0 18px 40px rgba(3, 169, 244, 0.1);
			text-align: center;
		}
		.ccd-http-error-code {
			display: inline-block;
			margin: 0 0 0.75rem;
			padding: 0.25rem 0.75rem;
			border-radius: 999px;
			background: rgba(3, 169, 244, 0.12);
			color: #0288d1;
			font-size: 0.875rem;
			font-weight: 700;
			letter-spacing: 0.04em;
		}
		.ccd-http-error-card h1 {
			margin: 0 0 0.75rem !important;
			font-size: clamp(1.5rem, 3vw, 1.85rem) !important;
			line-height: 1.25 !important;
			color: #2b3a42 !important;
		}
		.ccd-http-error-card .lead {
			margin: 0 0 0.75rem;
			font-size: 1.1rem;
			font-weight: 600;
			color: #01579b;
		}
		.ccd-http-error-card .detail {
			margin: 0 0 1.5rem;
			font-size: 1rem;
			line-height: 1.55;
			color: #5f7380;
		}
		.ccd-http-error-actions {
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem;
			justify-content: center;
		}
		.ccd-http-error-actions a {
			display: inline-flex !important;
			align-items: center;
			justify-content: center;
			min-height: 2.75rem;
			padding: 0 1.35rem !important;
			border-radius: 999px !important;
			font-weight: 600 !important;
			text-decoration: none !important;
		}
		.ccd-http-error-actions a.primary {
			background: linear-gradient(135deg, #03a9f4 0%, #0288d1 100%) !important;
			color: #fff !important;
			border: 0 !important;
			box-shadow: 0 10px 22px rgba(3, 169, 244, 0.28) !important;
		}
		.ccd-http-error-actions a.secondary {
			background: #fff !important;
			color: #0288d1 !important;
			border: 1.5px solid rgba(3, 169, 244, 0.4) !important;
		}
	</style>
	<div class="ccd-http-error-wrap">
		<div class="ccd-http-error-card">
			<p class="ccd-http-error-code">Erro <?php echo (int) $code; ?></p>
			<h1><?php echo esc_html($msg['title']); ?></h1>
			<p class="lead"><?php echo esc_html($msg['lead']); ?></p>
			<p class="detail"><?php echo esc_html($msg['detail']); ?></p>
			<div class="ccd-http-error-actions">
				<a class="primary" href="<?php echo esc_url($home); ?>">Voltar ao início</a>
				<a class="secondary" href="<?php echo esc_url($contato); ?>">Falar conosco</a>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Substitui o conteudo da 404 do WordPress pela mensagem amigavel.
 */
add_filter(
	'template_include',
	static function ($template) {
		if (!is_404()) {
			return $template;
		}

		$custom = WP_CONTENT_DIR . '/ccd-http-error-404-template.php';
		if (is_readable($custom)) {
			return $custom;
		}

		return $template;
	},
	99
);
