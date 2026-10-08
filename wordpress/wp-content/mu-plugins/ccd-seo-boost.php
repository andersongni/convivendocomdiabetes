<?php
/**
 * Plugin Name: CCD SEO Boost
 * Description: Meta da home/páginas, titles de categorias, alts, breadcrumbs Yoast e posts relacionados.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCD_SEO_BOOST_VERSION = '4';

/**
 * @return string
 */
function ccd_seo_home_metadesc() {
	return 'Blog da Bia Libonati sobre diabetes tipo 2: rotina, saúde e convivência no dia a dia. Conteúdos práticos e histórias reais.';
}

/**
 * @return string
 */
function ccd_seo_home_title() {
	return 'Diabetes tipo 2: rotina, saúde e convivência | Convivendo com Diabetes';
}

/**
 * Metas manuais de páginas-chave (slug => description).
 *
 * @return array<string, string>
 */
function ccd_seo_page_metadescs() {
	return array(
		'blog'                 => 'Artigos, receitas e histórias reais sobre diabetes tipo 2, rotina e saúde no blog Convivendo com Diabetes.',
		'contato'              => 'Fale com a Bia Libonati: dúvidas, parcerias e imprensa sobre diabetes tipo 2 e convivência com a condição.',
		'clipping'             => 'Portfólio da Bia Libonati na mídia: campanhas, entrevistas e aparições sobre diabetes e saúde.',
		'resenha-de-livros'    => 'Resenhas de livros sobre diabetes, saúde e bem-estar recomendados pela Bia Libonati.',
		'eventos-e-campanhas'  => 'Eventos e campanhas de conscientização sobre diabetes com a participação da Bia Libonati.',
		'servicos'             => 'Serviços de conteúdo e consultoria em diabetes e obesidade com a jornalista Bia Libonati.',
		'sobre'                => 'Conheça a Bia Libonati: jornalista que convive com diabetes tipo 2 e cria o Convivendo com Diabetes.',
	);
}

/**
 * Metas manuais de posts estratégicos (slug => description).
 *
 * @return array<string, string>
 */
function ccd_seo_post_metadescs() {
	return array(
		'hipoglicemia' => 'O que é hipoglicemia no diabetes, sinais de alerta e como agir com segurança no dia a dia.',
		'diabetes-tipo-2-o-que-e-e-como-e-feito-diagnostico' => 'Diabetes tipo 2: o que é, diferença entre tipos e quais exames ajudam no diagnóstico.',
		'diabetes-tipo-2-tem-cura-saiba-mais-sobre-os-tratamentos-disponiveis' => 'Diabetes tipo 2 tem cura? Entenda tratamentos, insulina e o que a ciência diz hoje.',
		'quantas-vezes-por-dia-devo-medir-minha-glicemia' => 'Quantas vezes medir a glicemia por dia? Orientações práticas para o controle no cotidiano.',
		'jejum-intermitente-e-diabetes-e-permitido-ou-nao' => 'Jejum intermitente e diabetes: quando pode, riscos e por que falar com o médico antes.',
		'a-logica-do-cuidado-no-tratamento-de-diabetes' => 'A lógica do cuidado no tratamento do diabetes: rotina, adesão e convivência com a condição.',
		'novidades-no-tratamento-do-diabetes-falta-pouco-para-o-pancreas-artificial' => 'Tecnologias no tratamento do diabetes: sensores, bombas e o caminho do pâncreas artificial.',
		'brasileiros-mais-proximos-do-pancreas-artificial' => 'Como o Brasil avança em tecnologias próximas ao pâncreas artificial no tratamento do diabetes.',
		'entenda-como-o-diabetes-pode-afetar-a-visao' => 'Como o diabetes pode afetar a visão: riscos oculares e a importância do controle glicêmico.',
		'metade-dos-diabeticos-morre-devido-a-infarto-ou-avc-mas-apenas-3-temem-essa-complicacao' => 'Diabetes, coração e AVC: por que o risco cardiovascular merece atenção no dia a dia.',
		'viagens-e-diabetes-um-guia-para-se-dar-bem-quando-estiver-longe-de-casa' => 'Guia prático de viagens com diabetes: medicamentos, glicemia e organização longe de casa.',
		'cupcake-de-maca-diet-vai-no-microondas' => 'Receita de cupcake de maçã diet no microondas: opção prática e sem açúcar para o dia a dia.',
		'bolo-red-velvet-diet-com-recheio-de-creme-de-cream-cheese' => 'Bolo red velvet diet com creme de cream cheese: receita sem açúcar para celebrar sem culpa.',
		'bolo-de-fuba-diet-sem-farinha' => 'Bolo de fubá diet sem farinha de trigo: receita simples e adequada para quem evita açúcar.',
		'alimentacao-e-diabetes-tipo-2' => 'Alimentação e diabetes tipo 2: prato, carboidratos, horários e hábitos sustentáveis para o cotidiano.',
		'sensor-de-glicose-como-funciona' => 'Sensor de glicose (CGM/FGM): como funciona, o que mostra e como usar os dados no dia a dia.',
		'o-que-e-hba1c-hemoglobina-glicada' => 'HbA1c (hemoglobina glicada): o que mede, com que frequência repetir e como usar o resultado no cuidado.',
	);
}

/**
 * Descrições de categorias-hub (slug => texto).
 *
 * @return array<string, string>
 */
function ccd_seo_category_descriptions() {
	return array(
		'diabetes'              => 'Conteúdos sobre diabetes tipo 1 e tipo 2: diagnóstico, tratamento, rotina e convivência com a condição.',
		'diabetes-tipo-1-e-tipo-2' => 'Artigos sobre diabetes tipo 1 e tipo 2: diferenças, diagnóstico, cuidados e histórias reais.',
		'alimentacao'           => 'Alimentação e diabetes: dicas práticas, hábitos e escolhas do dia a dia para conviver melhor.',
		'receitas'              => 'Receitas diets e sem açúcar para quem convive com diabetes — doces e salgados do dia a dia.',
		'receitas-doces'        => 'Receitas doces sem açúcar e diets para diabéticos, com sabor e praticidade.',
		'receitas-salgadas'     => 'Receitas salgadas adequadas para quem monitora glicemia e busca refeições equilibradas.',
		'receitas-veganas'      => 'Receitas veganas e diets pensadas para o dia a dia com diabetes.',
		'leites-vegetais'       => 'Leites vegetais e alternativas sem açúcar no contexto da alimentação com diabetes.',
		'psicologia'            => 'Saúde emocional e diabetes: convivência, motivações e bem-estar no cotidiano.',
		'blog'                  => 'Textos do blog Convivendo com Diabetes sobre rotina, saúde e experiências reais.',
		'eventos-e-campanhas'   => 'Eventos e campanhas de conscientização sobre diabetes acompanhados pela Bia Libonati.',
		'resenha-de-livros'     => 'Resenhas de livros sobre diabetes, saúde e qualidade de vida.',
	);
}

/**
 * @param string $text Texto.
 * @param int    $max  Limite.
 * @return string
 */
function ccd_seo_boost_truncate( $text, $max = 155 ) {
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
	if ( $text === '' ) {
		return '';
	}
	$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	if ( $len <= $max ) {
		return $text;
	}
	$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	$cut = preg_replace( '/\s+\S*$/u', '', $cut );
	return rtrim( (string) $cut, " \t\n\r\0\x0B.,;:" ) . '…';
}

/**
 * Title estável para arquivo de termo (evita "- Sitename" ou title de post errado).
 *
 * @param WP_Term $term Termo.
 * @return string
 */
function ccd_seo_term_document_title( WP_Term $term ) {
	$name = trim( $term->name );
	if ( $name === '' ) {
		return '';
	}
	return $name . ' - ' . get_bloginfo( 'name' );
}

/**
 * Meta description para arquivo de termo.
 *
 * @param WP_Term $term Termo.
 * @return string
 */
function ccd_seo_term_metadesc( WP_Term $term ) {
	$map = ccd_seo_category_descriptions();
	if ( isset( $map[ $term->slug ] ) ) {
		return ccd_seo_boost_truncate( $map[ $term->slug ], 155 );
	}
	$from_term = trim( wp_strip_all_tags( term_description( $term->term_id, $term->taxonomy ) ) );
	if ( $from_term !== '' ) {
		return ccd_seo_boost_truncate( $from_term, 155 );
	}
	return ccd_seo_boost_truncate(
		sprintf(
			'Artigos e conteúdos sobre %s no Convivendo com Diabetes — educação e convivência com diabetes tipo 2.',
			$term->name
		),
		155
	);
}

/**
 * Sincroniza title Yoast (post/page).
 *
 * @param int    $post_id Post ID.
 * @param string $title   Title.
 * @return void
 */
function ccd_seo_sync_yoast_title( $post_id, $title ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || $title === '' ) {
		return;
	}
	update_post_meta( $post_id, '_yoast_wpseo_title', $title );

	global $wpdb;
	$table  = $wpdb->prefix . 'yoast_indexable';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists ) {
		$wpdb->update(
			$table,
			array(
				'title'      => $title,
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'object_type' => 'post',
				'object_id'   => $post_id,
			),
			array( '%s', '%s' ),
			array( '%s', '%d' )
		);
	}
}

/**
 * @param string $content  HTML do post.
 * @param int    $image_id Attachment ID (classe wp-image-N).
 * @param string $alt      Texto alternativo.
 * @return string
 */
function ccd_seo_set_content_img_alt( $content, $image_id, $alt ) {
	if ( ! is_string( $content ) || $content === '' ) {
		return $content;
	}
	$image_id = (int) $image_id;
	$alt_esc  = esc_attr( $alt );
	$out      = preg_replace_callback(
		'/<img\b[^>]*\bwp-image-' . $image_id . '\b[^>]*>/i',
		static function ( $m ) use ( $alt_esc ) {
			$tag = $m[0];
			if ( preg_match( '/\balt=/i', $tag ) ) {
				return (string) preg_replace( '/\balt=(["\'])(?:(?!\1).)*\1/i', 'alt="' . $alt_esc . '"', $tag, 1 );
			}
			return (string) preg_replace( '/<img\b/i', '<img alt="' . $alt_esc . '"', $tag, 1 );
		},
		$content,
		1
	);
	return is_string( $out ) ? $out : $content;
}

/**
 * Aplica meta da home + alts (idempotente via option ccd_seo_boost).
 *
 * @return void
 */
/**
 * Sincroniza description no indexable do Yoast 28+.
 *
 * @param int    $post_id Post ID.
 * @param string $desc    Meta description.
 * @return void
 */
function ccd_seo_sync_yoast_description( $post_id, $desc ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || $desc === '' ) {
		return;
	}

	update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );

	global $wpdb;
	$table = $wpdb->prefix . 'yoast_indexable';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists ) {
		$wpdb->update(
			$table,
			array(
				'description' => $desc,
				'updated_at'  => current_time( 'mysql' ),
			),
			array(
				'object_type' => 'post',
				'object_id'   => $post_id,
			),
			array( '%s', '%s' ),
			array( '%s', '%d' )
		);
	}

	if ( ! function_exists( 'YoastSEO' ) ) {
		return;
	}
	try {
		$container = YoastSEO()->classes;
		if ( $container && method_exists( $container, 'get' ) ) {
			$builder = $container->get( 'Yoast\WP\SEO\Builders\Indexable_Builder' );
			if ( $builder && method_exists( $builder, 'build_for_id_and_type' ) ) {
				$builder->build_for_id_and_type( $post_id, 'post' );
			}
		}
	} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// Meta + update direto ja cobrem o caso.
	}
}

/**
 * Garante template Yoast de categoria com %%term_title%%.
 *
 * @return void
 */
function ccd_seo_fix_category_title_template() {
	if ( ! class_exists( 'WPSEO_Options', false ) && ! get_option( 'wpseo_titles' ) ) {
		return;
	}

	$titles = get_option( 'wpseo_titles', array() );
	if ( ! is_array( $titles ) ) {
		$titles = array();
	}

	$desired = '%%term_title%% %%sep%% %%sitename%%';
	$changed = false;
	foreach ( array( 'title-tax-category', 'title-tax-post_tag' ) as $key ) {
		$current = isset( $titles[ $key ] ) ? (string) $titles[ $key ] : '';
		// Template sem term_title (ou vazio) gera titles "- Sitename".
		if ( $current === '' || stripos( $current, '%%term_title%%' ) === false ) {
			$titles[ $key ] = $desired;
			$changed        = true;
		}
	}

	if ( $changed ) {
		update_option( 'wpseo_titles', $titles );
	}
}

/**
 * Grava title/metadesc Yoast em cada termo de categoria.
 *
 * @return void
 */
function ccd_seo_sync_category_term_seo() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return;
	}

	global $wpdb;
	$table  = $wpdb->prefix . 'yoast_indexable';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$title = ccd_seo_term_document_title( $term );
		$desc  = ccd_seo_term_metadesc( $term );
		if ( $title === '' ) {
			continue;
		}

		update_term_meta( $term->term_id, '_yoast_wpseo_title', $title );
		update_term_meta( $term->term_id, '_yoast_wpseo_metadesc', $desc );

		if ( $exists ) {
			$wpdb->update(
				$table,
				array(
					'title'       => $title,
					'description' => $desc,
					'updated_at'  => current_time( 'mysql' ),
				),
				array(
					'object_type' => 'term',
					'object_id'   => (int) $term->term_id,
				),
				array( '%s', '%s', '%s' ),
				array( '%s', '%d' )
			);
		}
	}
}

/**
 * Aplica metas das páginas-chave.
 *
 * @return void
 */
function ccd_seo_sync_key_page_metadescs() {
	$map = ccd_seo_page_metadescs();

	$blog_id = (int) get_option( 'page_for_posts' );
	if ( $blog_id > 0 && isset( $map['blog'] ) ) {
		ccd_seo_sync_yoast_description( $blog_id, $map['blog'] );
	}

	foreach ( $map as $slug => $desc ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post ) {
			ccd_seo_sync_yoast_description( (int) $page->ID, $desc );
		}
	}
}

/**
 * Aplica metas dos posts estratégicos.
 *
 * @return void
 */
function ccd_seo_sync_key_post_metadescs() {
	foreach ( ccd_seo_post_metadescs() as $slug => $desc ) {
		$posts = get_posts(
			array(
				'name'           => $slug,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $posts[0] ) ) {
			ccd_seo_sync_yoast_description( (int) $posts[0], $desc );
			if ( function_exists( 'ccd_seo_focus_from_title' ) ) {
				$post = get_post( (int) $posts[0] );
				if ( $post instanceof WP_Post && function_exists( 'ccd_seo_sync_yoast_fields' ) ) {
					ccd_seo_sync_yoast_fields( (int) $post->ID, $desc, ccd_seo_focus_from_title( $post->post_title ) );
				}
			}
		}
	}
}

/**
 * Preenche description vazia das categorias-hub.
 *
 * @return void
 */
function ccd_seo_sync_category_term_descriptions() {
	foreach ( ccd_seo_category_descriptions() as $slug => $desc ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
			continue;
		}
		$current = trim( wp_strip_all_tags( (string) $term->description ) );
		if ( $current !== '' ) {
			continue;
		}
		wp_update_term(
			(int) $term->term_id,
			'category',
			array(
				'description' => $desc,
			)
		);
	}
}

function ccd_seo_boost_apply() {
	if ( function_exists( 'ccd_migration_option_matches' )
		? ccd_migration_option_matches( 'ccd_seo_boost', CCD_SEO_BOOST_VERSION )
		: get_option( 'ccd_seo_boost' ) === CCD_SEO_BOOST_VERSION ) {
		return;
	}
	if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	$allow = ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() || is_admin();
	if ( ! $allow ) {
		return;
	}
	if ( get_transient( 'ccd_seo_boost_migrating' ) ) {
		return;
	}
	set_transient( 'ccd_seo_boost_migrating', 1, 10 * MINUTE_IN_SECONDS );
	// Claim da versão primeiro: evita stampede se o trabalho for lento/falhar.
	if ( function_exists( 'ccd_migration_option_claim' ) ) {
		ccd_migration_option_claim( 'ccd_seo_boost', CCD_SEO_BOOST_VERSION );
	} else {
		update_option( 'ccd_seo_boost', CCD_SEO_BOOST_VERSION, false );
	}

	$home_id = (int) get_option( 'page_on_front' );
	if ( $home_id > 0 ) {
		ccd_seo_sync_yoast_description( $home_id, ccd_seo_home_metadesc() );
		ccd_seo_sync_yoast_title( $home_id, ccd_seo_home_title() );

		$post = get_post( $home_id );
		if ( $post instanceof WP_Post && is_string( $post->post_content ) ) {
			$content = ccd_seo_set_content_img_alt(
				$post->post_content,
				2726,
				'Beatriz Libonati, autora do blog Convivendo com Diabetes'
			);
			$content = ccd_seo_set_content_img_alt(
				$content,
				2937,
				'Bia Libonati no LinkedIn'
			);
			$content = preg_replace(
				'/<img\b(?![^>]*\balt=)([^>]*\bBia-2-2\.jpg[^>]*)>/i',
				'<img alt="Beatriz Libonati, autora do blog Convivendo com Diabetes"$1>',
				$content,
				1
			);
			if ( is_string( $content ) && $content !== $post->post_content ) {
				wp_update_post(
					array(
						'ID'           => $home_id,
						'post_content' => $content,
					)
				);
			}
		}
	}

	update_post_meta( 2726, '_wp_attachment_image_alt', 'Beatriz Libonati, autora do blog Convivendo com Diabetes' );
	update_post_meta( 2937, '_wp_attachment_image_alt', 'Bia Libonati no LinkedIn' );

	ccd_seo_fix_category_title_template();
	ccd_seo_sync_category_term_descriptions();
	ccd_seo_sync_category_term_seo();
	ccd_seo_sync_key_page_metadescs();
	ccd_seo_sync_key_post_metadescs();

	delete_transient( 'ccd_seo_boost_migrating' );

	if ( function_exists( 'ccd_page_cache_purge_all' ) ) {
		ccd_page_cache_purge_all();
	}
}

add_action( 'init', 'ccd_seo_boost_apply', 6 );

/**
 * Runtime: titles de categoria/tag nunca ficam vazios ou com title de post.
 *
 * @param string $title Title Yoast.
 * @return string
 */
function ccd_seo_filter_term_title( $title ) {
	if ( ! is_category() && ! is_tag() && ! is_tax() ) {
		return $title;
	}
	$term = get_queried_object();
	if ( ! $term instanceof WP_Term ) {
		return $title;
	}
	$fixed = ccd_seo_term_document_title( $term );
	return $fixed !== '' ? $fixed : $title;
}

add_filter( 'wpseo_title', 'ccd_seo_filter_term_title', 20 );
add_filter( 'wpseo_opengraph_title', 'ccd_seo_filter_term_title', 20 );

/**
 * Runtime: metadesc de arquivo de termo (evita snippet de post errado).
 *
 * @param string $desc Meta description.
 * @return string
 */
function ccd_seo_filter_term_metadesc( $desc ) {
	if ( ! is_category() && ! is_tag() && ! is_tax() ) {
		return $desc;
	}
	$term = get_queried_object();
	if ( ! $term instanceof WP_Term ) {
		return $desc;
	}
	return ccd_seo_term_metadesc( $term );
}

add_filter( 'wpseo_metadesc', 'ccd_seo_filter_term_metadesc', 20 );
add_filter( 'wpseo_opengraph_desc', 'ccd_seo_filter_term_metadesc', 20 );

/**
 * Runtime: metas de páginas-chave e posts estratégicos.
 *
 * @param string $desc Meta description.
 * @return string
 */
function ccd_seo_filter_key_page_metadesc( $desc ) {
	if ( is_front_page() ) {
		return ccd_seo_home_metadesc();
	}

	$map = ccd_seo_page_metadescs();

	if ( is_home() && ! is_front_page() && isset( $map['blog'] ) ) {
		return $map['blog'];
	}

	if ( is_page() ) {
		$page = get_queried_object();
		if ( $page instanceof WP_Post && isset( $map[ $page->post_name ] ) ) {
			return $map[ $page->post_name ];
		}
	}

	if ( is_singular( 'post' ) ) {
		$post = get_queried_object();
		$posts = ccd_seo_post_metadescs();
		if ( $post instanceof WP_Post && isset( $posts[ $post->post_name ] ) ) {
			return $posts[ $post->post_name ];
		}
	}

	return $desc;
}

add_filter( 'wpseo_metadesc', 'ccd_seo_filter_key_page_metadesc', 25 );
add_filter( 'wpseo_opengraph_desc', 'ccd_seo_filter_key_page_metadesc', 25 );

/**
 * Runtime: title da home otimizado para busca.
 *
 * @param string $title Title.
 * @return string
 */
function ccd_seo_filter_home_title( $title ) {
	if ( is_front_page() ) {
		return ccd_seo_home_title();
	}
	return $title;
}

add_filter( 'wpseo_title', 'ccd_seo_filter_home_title', 25 );
add_filter( 'wpseo_opengraph_title', 'ccd_seo_filter_home_title', 25 );

/**
 * Breadcrumbs Yoast abaixo do hero (exceto home).
 */
add_action(
	'mesmerize_after_inner_page_header_content',
	static function () {
		if ( is_front_page() || is_home() || ! function_exists( 'yoast_breadcrumb' ) ) {
			return;
		}
		yoast_breadcrumb(
			'<nav class="ccd-breadcrumbs" aria-label="Breadcrumb"><p id="breadcrumbs">',
			'</p></nav>'
		);
	},
	8
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() ) {
			return;
		}
		$handle = 'ccd-seo-boost';
		wp_register_style( $handle, false, array(), CCD_SEO_BOOST_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			<<<'CSS'
.ccd-breadcrumbs {
	max-width: 1100px;
	margin: 0.35rem auto 0.75rem;
	padding: 0 1.25rem;
	font-size: 0.9rem;
	line-height: 1.4;
	color: #4a5d68;
}
.ccd-breadcrumbs #breadcrumbs,
.ccd-breadcrumbs p {
	margin: 0;
}
.ccd-breadcrumbs a {
	color: #0277bd;
	text-decoration: underline;
	text-underline-offset: 2px;
}
.ccd-related-posts {
	max-width: 720px;
	margin: 2rem auto 1.5rem;
	padding: 1.25rem 0 0;
	border-top: 1px solid #d7e3ea;
}
.ccd-related-posts h2 {
	margin: 0 0 0.85rem;
	font-size: 1.25rem;
	color: #243944;
}
.ccd-related-posts ul {
	margin: 0;
	padding: 0;
	list-style: none;
}
.ccd-related-posts li {
	margin: 0 0 0.55rem;
}
.ccd-related-posts a {
	color: #0277bd;
	font-weight: 600;
	text-decoration: none;
}
.ccd-related-posts a:hover,
.ccd-related-posts a:focus {
	text-decoration: underline;
}
CSS
		);
	},
	30
);

/**
 * Posts relacionados no single (malha interna).
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
		$related = ccd_seo_related_posts_html( (int) get_the_ID() );
		return is_string( $content ) ? $content . $related : $content;
	},
	30
);

/**
 * @param int $post_id Post atual.
 * @return string
 */
function ccd_seo_related_posts_html( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return '';
	}

	$cats = wp_get_post_categories( $post_id );
	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'orderby'             => 'date',
	);
	if ( $cats ) {
		$args['category__in'] = $cats;
	}

	$q = new WP_Query( $args );
	if ( ! $q->have_posts() ) {
		wp_reset_postdata();
		return '';
	}

	$html = '<aside class="ccd-related-posts" aria-label="Posts relacionados"><h2>Leia também</h2><ul>';
	while ( $q->have_posts() ) {
		$q->the_post();
		$html .= sprintf(
			'<li><a href="%s">%s</a></li>',
			esc_url( get_permalink() ),
			esc_html( get_the_title() )
		);
	}
	$html .= '</ul></aside>';
	wp_reset_postdata();

	return $html;
}
