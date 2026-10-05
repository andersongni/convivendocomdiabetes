<?php
/**
 * Plugin Name: CCD Português (Brasil)
 * Description: Traduz termos em ingles restantes do tema/plugins no front e na tela de login.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mapa de traduções pontuais (texto original => pt_BR).
 *
 * @return array<string, string>
 */
function ccd_pt_br_map() {
	return array(
		'Skip to content'     => 'Ir para o conteúdo',
		'on'                  => 'em',
		'by'                  => 'por',
		'in'                  => 'em',
		'Next:'               => 'Próximo:',
		'Previous:'           => 'Anterior:',
		'Next post:'          => 'Próximo post:',
		'Previous post:'      => 'Post anterior:',
		'Read more'           => 'Leia mais',
		'Nothing Found'       => 'Nada encontrado',
		'Pages:'              => 'Páginas:',
		'Page'                => 'Página',
		'Close Panel'         => 'Fechar painel',
		'No Responses'        => 'Nenhum comentário',
		'One Response'        => 'Um comentário',
		'% Responses'         => '% comentários',
		'About the author'    => 'Sobre o autor',
		'Search &hellip;'     => 'Pesquisar &hellip;',
		'Search for:'         => 'Pesquisar por:',
		'Posts'               => 'Publicações',
		'Post navigation'     => 'Navegação de posts',
	);
}

/** Remove o texto/logo "Powered by WordPress" da tela de login. */
add_filter( 'login_headertext', '__return_empty_string' );
add_action(
	'login_enqueue_scripts',
	static function () {
		echo '<style>.login h1.wp-login-logo,.login h1 a{display:none!important;}</style>';
	}
);

/**
 * @param string $translation Translated text.
 * @param string $text        Original text.
 * @return string
 */
function ccd_pt_br_translate( $translation, $text ) {
	// Evita alterar strings do painel administrativo.
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $translation;
	}

	$map = ccd_pt_br_map();
	if ( isset( $map[ $text ] ) ) {
		return $map[ $text ];
	}
	return $translation;
}

add_filter( 'gettext', 'ccd_pt_br_translate', 20, 2 );
add_filter( 'gettext_with_context', 'ccd_pt_br_translate', 20, 2 );

/**
 * Alguns temas passam HTML em gettext com sprintf; cobre padrões comuns.
 *
 * @param string $translation Translated text.
 * @param string $single      Singular.
 * @param string $plural      Plural.
 * @param int    $number      Number.
 * @return string
 */
add_filter(
	'ngettext',
	static function ( $translation, $single, $plural, $number ) {
		if ( $single === 'One Response' && (int) $number === 1 ) {
			return 'Um comentário';
		}
		if ( $plural === '% Responses' && (int) $number !== 1 ) {
			return '% comentários';
		}
		return $translation;
	},
	20,
	4
);
