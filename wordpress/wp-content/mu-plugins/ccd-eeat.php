<?php
/**
 * Plugin Name: CCD E-E-A-T
 * Description: Sinais de experiencia/autoridade: disclaimer de saude, autor, data de atualizacao, Organization e verificacao Search Console.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return string
 */
function ccd_eeat_author_name() {
	return 'Beatriz Libonati';
}

/**
 * @return string
 */
function ccd_eeat_about_url() {
	return home_url( '/sobre/' );
}

/**
 * Meta de verificacao Google (Search Console), via constante ou env.
 */
add_action(
	'wp_head',
	static function () {
		$code = '';
		if ( defined( 'CCD_GOOGLE_SITE_VERIFICATION' ) && CCD_GOOGLE_SITE_VERIFICATION ) {
			$code = (string) CCD_GOOGLE_SITE_VERIFICATION;
		} elseif ( getenv( 'CCD_GOOGLE_SITE_VERIFICATION' ) ) {
			$code = (string) getenv( 'CCD_GOOGLE_SITE_VERIFICATION' );
		}
		$code = trim( $code );
		if ( $code === '' ) {
			return;
		}
		printf(
			'<meta name="google-site-verification" content="%s" />' . "\n",
			esc_attr( $code )
		);
	},
	1
);

/**
 * Organization + Person (E-E-A-T) no JSON-LD.
 */
add_action(
	'wp_head',
	static function () {
		if ( is_admin() ) {
			return;
		}

		$logo_url = content_url( 'mu-plugins/assets/brand/logo-convivendo-com-diabetes.png' );
		$logo_id  = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$custom = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( is_string( $custom ) && $custom !== '' ) {
				$logo_url = $custom;
			}
		}

		$org = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type' => 'Organization',
					'@id'   => home_url( '/#organization' ),
					'name'  => 'Convivendo com Diabetes',
					'url'   => home_url( '/' ),
					'logo'  => array(
						'@type' => 'ImageObject',
						'url'   => $logo_url,
					),
					'sameAs' => array(
						'https://www.instagram.com/convivendocomdiabetes/',
						'https://www.linkedin.com/in/beatriz-libonati',
					),
					'founder' => array(
						'@id' => home_url( '/#person-bia' ),
					),
				),
				array(
					'@type'       => 'Person',
					'@id'         => home_url( '/#person-bia' ),
					'name'        => ccd_eeat_author_name(),
					'url'         => ccd_eeat_about_url(),
					'jobTitle'    => 'Jornalista e autora',
					'description' => 'Jornalista que convive com diabetes tipo 2 e compartilha rotina, saúde e experiências reais.',
					'sameAs'      => array(
						'https://www.instagram.com/convivendocomdiabetes/',
						'https://www.linkedin.com/in/beatriz-libonati',
					),
					'worksFor'    => array(
						'@id' => home_url( '/#organization' ),
					),
				),
			),
		);
		echo '<script type="application/ld+json" class="ccd-eeat-schema">'
			. wp_json_encode( $org, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			. '</script>' . "\n";
	},
	5
);

/**
 * Bloco E-E-A-T apos o conteudo do post.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		static $done = false;
		if ( $done ) {
			return $content;
		}
		$done = true;

		$modified = get_the_modified_date( 'd/m/Y' );
		$published = get_the_date( 'd/m/Y' );
		$about     = esc_url( ccd_eeat_about_url() );
		$author    = esc_html( ccd_eeat_author_name() );

		$box = '<aside class="ccd-eeat" aria-label="Sobre o conteúdo">';
		$box .= '<p class="ccd-eeat-meta"><strong>Autora:</strong> <a href="' . $about . '">' . $author . '</a>';
		$box .= ' · <strong>Publicado:</strong> ' . esc_html( $published );
		if ( $modified && $modified !== $published ) {
			$box .= ' · <strong>Atualizado:</strong> ' . esc_html( $modified );
		}
		$box .= '</p>';
		$box .= '<p class="ccd-eeat-disclaimer">Este conteúdo tem caráter informativo e de relato de experiência pessoal com diabetes. Não substitui orientação médica, nutricional ou de outro profissional de saúde. Em caso de dúvida, procure seu médico.</p>';
		$box .= '<p class="ccd-eeat-about"><a href="' . $about . '">Conheça a trajetória da Bia</a></p>';
		$box .= '</aside>';

		return is_string( $content ) ? $content . $box : $content;
	},
	40
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() ) {
			return;
		}
		$handle = 'ccd-eeat';
		wp_register_style( $handle, false, array(), '1.0.0' );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			<<<'CSS'
.ccd-eeat {
	max-width: 720px;
	margin: 1.75rem auto 1.25rem;
	padding: 1rem 1.15rem;
	border: 1px solid #d7e3ea;
	border-radius: 10px;
	background: #f7fbfd;
	color: #2b3a42;
	font-size: 0.95rem;
	line-height: 1.5;
}
.ccd-eeat p { margin: 0 0 0.65rem; }
.ccd-eeat p:last-child { margin-bottom: 0; }
.ccd-eeat a { color: #0277bd; font-weight: 600; }
.ccd-eeat-disclaimer { color: #3d4f5c; }
CSS
		);
	},
	30
);
