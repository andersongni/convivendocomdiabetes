<?php
/**
 * Plugin Name: CCD Acessibilidade (localhost)
 * Description: Tipografia e contraste mais acessiveis. Ativo apenas em localhost — nao aplica no Railway.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gate: so localhost / ambiente local.
 */
function ccd_a11y_is_local() {
	if ( defined( 'CCD_A11Y_FORCE' ) && CCD_A11Y_FORCE ) {
		return true;
	}
	if ( defined( 'CCD_A11Y_DISABLE' ) && CCD_A11Y_DISABLE ) {
		return false;
	}

	$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
	if ( $env === 'local' || $env === 'development' ) {
		return true;
	}

	$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$host = is_string( $host ) ? strtolower( $host ) : '';
	return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
		|| str_ends_with( $host, '.local' )
		|| str_ends_with( $host, '.test' );
}

/**
 * Internas (posts/paginas): sem subtitulo "Convivendo com Diabetes" no hero.
 */
add_filter(
	'inner_header_show_subtitle',
	static function ( $show ) {
		return ccd_a11y_is_local() ? false : $show;
	}
);

/**
 * Invalida cache HTML local quando a versao do a11y muda.
 */
add_action(
	'init',
	static function () {
		if ( ! ccd_a11y_is_local() || ! function_exists( 'ccd_page_cache_purge_all' ) ) {
			return;
		}
		$ver = '1.8.2';
		if ( get_option( 'ccd_a11y_cache_bust' ) === $ver ) {
			return;
		}
		ccd_page_cache_purge_all();
		update_option( 'ccd_a11y_cache_bust', $ver, false );
	},
	1
);

/**
 * Sobe a arvore ate a categoria raiz (sem pai).
 *
 * @param WP_Term $term Categoria de partida.
 * @return WP_Term
 */
function ccd_a11y_category_root( WP_Term $term ) {
	while ( (int) $term->parent > 0 ) {
		$parent = get_category( (int) $term->parent );
		if ( ! $parent instanceof WP_Term || is_wp_error( $parent ) ) {
			break;
		}
		$term = $parent;
	}
	return $term;
}

/**
 * Categoria principal do post (raiz / top-level — nunca subcategoria).
 *
 * @return WP_Term|null
 */
function ccd_a11y_primary_category() {
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return null;
	}

	$cats = get_the_category( $post_id );
	if ( ! is_array( $cats ) || $cats === array() ) {
		return null;
	}

	$top = array();
	foreach ( $cats as $cat ) {
		if ( $cat instanceof WP_Term && (int) $cat->parent === 0 ) {
			$top[] = $cat;
		}
	}

	$primary_id = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
	if ( $primary_id > 0 ) {
		foreach ( $top as $cat ) {
			if ( (int) $cat->term_id === $primary_id ) {
				return $cat;
			}
		}
		$primary = get_category( $primary_id );
		if ( $primary instanceof WP_Term && ! is_wp_error( $primary ) ) {
			return ccd_a11y_category_root( $primary );
		}
	}

	if ( $top !== array() ) {
		return $top[0];
	}

	return ccd_a11y_category_root( $cats[0] );
}

/**
 * Primeira letra maiuscula, demais minusculas (UTF-8).
 *
 * @param string $text Texto.
 * @return string
 */
function ccd_a11y_sentence_case( $text ) {
	$text = trim( (string) $text );
	if ( $text === '' ) {
		return '';
	}
	$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	$first = function_exists( 'mb_substr' ) ? mb_substr( $lower, 0, 1, 'UTF-8' ) : substr( $lower, 0, 1 );
	$rest  = function_exists( 'mb_substr' ) ? mb_substr( $lower, 1, null, 'UTF-8' ) : substr( $lower, 1 );
	$first = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first, 'UTF-8' ) : strtoupper( $first );
	return $first . $rest;
}

/**
 * Archives: hero sem prefixo "Categoria:" / "Tag:".
 * Posts: hero mostra so a categoria principal; titulo vai para o conteudo.
 */
add_filter(
	'mesmerize_header_title',
	static function ( $title ) {
		if ( ! ccd_a11y_is_local() ) {
			return $title;
		}
		if ( is_category() ) {
			$name = single_cat_title( '', false );
			return is_string( $name ) && $name !== '' ? esc_html( ccd_a11y_sentence_case( $name ) ) : $title;
		}
		if ( is_tag() ) {
			$name = single_tag_title( '', false );
			return is_string( $name ) && $name !== '' ? esc_html( ccd_a11y_sentence_case( $name ) ) : $title;
		}
		if ( ! is_singular( 'post' ) ) {
			return $title;
		}
		$cat = ccd_a11y_primary_category();
		if ( ! $cat instanceof WP_Term ) {
			return $title;
		}
		return sprintf(
			'<a href="%s" rel="category tag">%s</a>',
			esc_url( get_category_link( $cat->term_id ) ),
			esc_html( ccd_a11y_sentence_case( $cat->name ) )
		);
	},
	20
);

add_action(
	'mesmerize_before_inner_page_header_content',
	static function () {
		if ( ! ccd_a11y_is_local() || ! is_singular( 'post' ) ) {
			return;
		}
		$GLOBALS['ccd_a11y_hero_ob'] = true;
		ob_start(
			static function ( $html ) {
				if ( ! is_string( $html ) ) {
					return $html;
				}
				$html = preg_replace(
					'/<h1(\s+class="hero-title")>/',
					'<p$1 data-ccd="hero-category">',
					$html,
					1
				);
				$html = preg_replace( '/<\/h1>/', '</p>', $html, 1 );
				return $html;
			}
		);
	}
);

add_action(
	'mesmerize_after_inner_page_header_content',
	static function () {
		if ( empty( $GLOBALS['ccd_a11y_hero_ob'] ) ) {
			return;
		}
		unset( $GLOBALS['ccd_a11y_hero_ob'] );
		if ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
	},
	0
);

/**
 * Posts: sem meta (autor/data) no rodape do artigo.
 */
add_filter(
	'mesmerize_show_post_meta',
	static function ( $show ) {
		if ( ccd_a11y_is_local() && is_singular( 'post' ) ) {
			return false;
		}
		return $show;
	}
);

/**
 * Posts: titulo + byline no topo; remove navegacao anterior/proximo.
 */
add_action(
	'template_redirect',
	static function () {
		if ( ! ccd_a11y_is_local() || ! is_singular( 'post' ) ) {
			return;
		}
		ob_start(
			static function ( $html ) {
				if ( ! is_string( $html ) || $html === '' ) {
					return $html;
				}
				$title = single_post_title( '', false );
				if ( ! is_string( $title ) || $title === '' ) {
					return $html;
				}

				$post = get_queried_object();
				$author_id = ( $post instanceof WP_Post ) ? (int) $post->post_author : 0;
				$author    = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
				$author_url = home_url( '/sobre/' );
				$date      = get_the_date( '', $post instanceof WP_Post ? $post : null );

				$byline = '';
				if ( $author !== '' && $date !== '' ) {
					$byline = sprintf(
						'<p class="ccd-post-byline">por <a href="%s">%s</a> em %s</p>',
						esc_url( $author_url ),
						esc_html( $author ),
						esc_html( $date )
					);
				}

				$header = '<h1 class="ccd-post-entry-title">' . esc_html( $title ) . '</h1>' . $byline;

				$replaced = preg_replace(
					'#<header class="entry-header">\s*<div class="meta">\s*.*?\s*</div>\s*<h1[^>]*>\s*</h1>\s*</header>#s',
					'<header class="entry-header">' . $header . '</header>',
					$html,
					1
				);
				if ( ! is_string( $replaced ) || $replaced === $html ) {
					$replaced = preg_replace(
						'#<div class="meta">\s*.*?\s*</div>\s*<h1[^>]*>\s*</h1>#s',
						$header,
						$html,
						1
					);
				}
				if ( ! is_string( $replaced ) || $replaced === $html ) {
					$replaced = preg_replace( '#<h1[^>]*>\s*</h1>#', $header, $html, 1 );
				}
				if ( ! is_string( $replaced ) ) {
					$replaced = $html;
				}

				// Se o titulo ja existia sem byline, injeta apos o h1.
				if ( $byline && strpos( $replaced, 'ccd-post-byline' ) === false ) {
					$with_byline = preg_replace(
						'#(<h1 class="ccd-post-entry-title">.*?</h1>)#s',
						'$1' . $byline,
						$replaced,
						1
					);
					if ( is_string( $with_byline ) ) {
						$replaced = $with_byline;
					}
				}

				$replaced = preg_replace(
					'#<nav class="navigation post-navigation"[^>]*>.*?</nav>#s',
					'',
					$replaced,
					1
				);

				return is_string( $replaced ) ? $replaced : $html;
			}
		);
	},
	1
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! ccd_a11y_is_local() || is_admin() ) {
			return;
		}

		$css = <<<'CSS'
/* =========================================================
 * CCD A11y — contraste (localhost only)
 * Titulos: Nunito (arredondada) + negrito + contorno
 * ========================================================= */
:root {
	--ccd-a11y-ink: #1f2a33;
	--ccd-a11y-ink-soft: #2b3a42;
	--ccd-a11y-muted: #3d4f5c;
	--ccd-a11y-link: #015f92;
	--ccd-a11y-link-hover: #014a73;
	--ccd-a11y-accent: #c2185b; /* magenta acessivel no branco */
	--ccd-a11y-blue: #0277bd;
	--ccd-a11y-focus: #0b57d0;
	--ccd-a11y-surface: #ffffff;
	--ccd-a11y-title: Nunito, "Open Sans", sans-serif;
}

html {
	font-size: 100%; /* 16px base do browser */
}

body,
body.mesmerize-front-page,
#page,
#page-content,
.page-content,
.content,
.entry-content,
.blog-post,
.post-content {
	color: var(--ccd-a11y-ink-soft) !important;
	font-size: 1.0625rem !important; /* 17px */
	font-weight: 400 !important;
	line-height: 1.65 !important;
}

/* Titulos (Nunito) — negrito para leitura */
h1, .h1, .h1-h,
h2, .h2,
h3, .h3,
h4, .h4,
h5, .h5,
.hero-title,
.header-subtitle,
.entry-title,
.post-title,
.blog-post h3,
.blog-post .post-title,
.section-title,
#reply-title {
	font-family: var(--ccd-a11y-title) !important;
	font-weight: 700 !important;
}

h2, .h2,
h3, .h3,
h4, .h4,
.entry-title,
.post-title,
.blog-post h3,
.blog-post .post-title,
#page-content h1:not(.hero-title),
.page-content h1:not(.hero-title),
.entry-content h1:not(.hero-title) {
	color: var(--ccd-a11y-ink) !important;
}

/*
 * Cores inline claras (rosa #ff99cc, magenta #ff00ff, azul #3366ff)
 * falham contraste AA no branco — escurece para leitura.
 */
.page-content [style*="#ff99cc"],
.page-content [style*="#FF99CC"],
.page-content [style*="color: #ff99cc"],
.page-content [style*="color:#ff99cc"],
.content [style*="#ff99cc"],
.content [style*="#FF99CC"],
.entry-content [style*="#ff99cc"],
.entry-content [style*="#FF99CC"],
span[style*="color: #ff00ff"],
span[style*="color:#ff00ff"],
span[style*="color: #FF00FF"],
span[style*="color:#FF00FF"],
.entry-content [style*="#ff00ff"],
.page-content [style*="#ff00ff"],
.page-content [style*="#ff66cc"],
.page-content [style*="#FF66CC"],
.page-content [style*="#ff66b3"],
.page-content [style*="#f48fb1"],
.page-content [style*="#f8bbd0"] {
	color: #ad1457 !important; /* rosa escuro AA no branco */
	font-weight: 700 !important;
}
.page-content [style*="#3366ff"],
.page-content [style*="#3366FF"],
.page-content [style*="color: #3366ff"],
.page-content [style*="color:#3366ff"],
.content [style*="#3366ff"],
.entry-content [style*="#3366ff"],
.page-content [style*="#3399ff"],
.page-content [style*="#66ccff"],
.page-content [style*="#03a9f4"] {
	color: #0b57d0 !important; /* azul AA no branco */
	font-weight: 700 !important;
}
/* Sobre a Bia / titulos coloridos em h4 */
.page-content h4,
.content h4,
.entry-content h4 {
	font-weight: 700 !important;
	line-height: 1.35 !important;
}
/* Sobre: nome "Beatriz Libonati" */
.page-id-2447 .page-content h4 > span[style*="#ff99cc"],
.page-id-2447 .content h4 > span[style*="#ff99cc"],
.page-id-2447 .entry-content h4 > span[style*="#ff99cc"],
.page-id-2447 .page-content h4 > span[style*="#ff99cc"] strong,
.page-id-2447 .content h4 > span[style*="#ff99cc"] strong,
.page-id-2447 .entry-content h4 > span[style*="#ff99cc"] strong {
	font-size: 25px !important;
	line-height: 1.3 !important;
}
.page-content p,
.content p,
.entry-content p {
	color: var(--ccd-a11y-ink) !important;
	font-weight: 400 !important;
}

/* Links de conteudo */
.entry-content a,
.page-content a,
.content a:not(.button):not(.noptin-form-submit):not(.ccd-btn-sobre-mim):not(.ccd-btn-midia-kit),
.blog-post a:not(.button):not(.ccd-btn-sobre-mim):not(.ccd-btn-midia-kit) {
	color: var(--ccd-a11y-link) !important;
	text-decoration-thickness: 0.08em;
	text-underline-offset: 0.15em;
}
.entry-content a:hover,
.page-content a:hover,
.content a:not(.button):not(.ccd-btn-sobre-mim):not(.ccd-btn-midia-kit):hover {
	color: var(--ccd-a11y-link-hover) !important;
}

/* Menu: tamanho minimo + contraste (hover igual ao tema) */
#main_menu a,
ul.main-menu > li > a,
.navigation-bar .main-menu > li > a,
#menu-menu-principal > li > a {
	font-size: 0.9375rem !important; /* 15px */
	font-weight: 600 !important;
	letter-spacing: 0.02em;
	color: var(--ccd-a11y-ink) !important;
	line-height: 1.4 !important;
}
#main_menu > li:not(.current-menu-item):not(.current_page_item):hover > a,
#main_menu > li:not(.current-menu-item):not(.current_page_item).hover > a,
ul.main-menu > li:not(.current-menu-item):not(.current_page_item):hover > a,
ul.main-menu > li:not(.current-menu-item):not(.current_page_item).hover > a,
.navigation-bar .main-menu > li:not(.current-menu-item):not(.current_page_item):hover > a,
.navigation-bar .main-menu > li:not(.current-menu-item):not(.current_page_item).hover > a,
#menu-menu-principal > li:not(.current-menu-item):not(.current_page_item):hover > a,
#menu-menu-principal > li:not(.current-menu-item):not(.current_page_item).hover > a {
	color: #03a9f4 !important;
}
#main_menu .current-menu-item > a,
#main_menu .current_page_item > a,
ul.main-menu > li.current-menu-item > a,
#menu-menu-principal > li.current-menu-item > a {
	color: #03a9f4 !important;
}

/*
 * Hero — paginas internas: empilhar titulo/subtitulo + contorno nos glifos.
 * Home (.header-homepage): preservar tamanho/posicao originais do tema.
 */
.header[style*="background-image"]:not(.header-homepage),
.header.custom-mobile-image:not(.header-homepage) {
	position: relative;
	isolation: isolate;
}
.header[style*="background-image"]:not(.header-homepage)::before,
.header.custom-mobile-image:not(.header-homepage)::before {
	content: "";
	position: absolute;
	inset: 0;
	z-index: 0;
	pointer-events: none;
	/* Escurece faixas claras (ex.: Sobre a Bia) para o titulo branco ler bem */
	background:
		linear-gradient(180deg, rgba(10, 40, 60, 0.45) 0%, rgba(8, 28, 42, 0.58) 100%);
}
/*
 * Hero interno padronizado pela altura do /blog/ (exceto home).
 * Onda (.header-separator) deve ficar absolute — relative nos posts
 * inflava a barra azul ~46px.
 */
body:not(.home):not(.mesmerize-front-page) .header:not(.header-homepage),
.blog .header:not(.header-homepage),
.single .header:not(.header-homepage),
.page .header:not(.header-homepage),
.archive .header:not(.header-homepage),
.category .header:not(.header-homepage),
.tag .header:not(.header-homepage),
.search .header:not(.header-homepage),
.error404 .header:not(.header-homepage) {
	position: relative !important;
	isolation: isolate;
	overflow: hidden;
}
body:not(.mesmerize-front-page) .header:not(.header-homepage) .header-separator,
body:not(.mesmerize-front-page) .header:not(.header-homepage) .header-separator-bottom {
	position: absolute !important;
	left: 0 !important;
	right: 0 !important;
	bottom: -1px !important;
	width: 100% !important;
	z-index: 3 !important;
	margin: 0 !important;
}

/* Posts: overlay azul translucido (mesmo tom do hero /blog/) */
body.single-post .header:not(.header-homepage)::before {
	content: "" !important;
	display: block !important;
	position: absolute !important;
	top: 0 !important;
	right: 0 !important;
	bottom: 0 !important;
	left: 0 !important;
	width: 100% !important;
	height: 100% !important;
	z-index: 1 !important;
	pointer-events: none !important;
	background:
		linear-gradient(
			180deg,
			rgba(3, 169, 244, 0.72) 0%,
			rgba(2, 136, 209, 0.8) 100%
		) !important;
}
body.single-post .header:not(.header-homepage) .inner-header-description,
body.single-post .header:not(.header-homepage) .header-description-row {
	position: relative;
	z-index: 2;
}
.header:not(.header-homepage) .inner-header-description,
.header:not(.header-homepage) .header-description-row {
	position: relative;
	z-index: 1;
	text-align: center;
}
.header:not(.header-homepage) .inner-header-description {
	display: flex !important;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	box-sizing: border-box;
	/* Altura util padronizada (base /receitas/ ~184px). */
	height: 11.5rem !important;
	min-height: 11.5rem !important;
	max-height: 11.5rem !important;
	margin-top: 0 !important;
	padding-top: 0.35rem !important;
	padding-bottom: 3rem !important;
}
.header:not(.header-homepage) .header-description-row {
	width: 100%;
	margin: 0 !important;
}
.header:not(.header-homepage) .header-description-row > [class*="col-"],
.header:not(.header-homepage) .inner-header-description > [class*="col-"] {
	display: flex !important;
	flex-direction: column !important;
	flex-wrap: nowrap !important;
	align-items: center !important;
	justify-content: center;
	gap: 0.45rem;
}

/* Contorno + negrito nos titulos do hero (internas + home) */
.header:not(.header-homepage) .hero-title,
.header:not(.header-homepage) .header-subtitle,
.header-homepage .hero-title,
.header-homepage .header-subtitle,
.header-homepage h1.h1-h,
.header-homepage .h1-h {
	background: none !important;
	border-radius: 0 !important;
	box-shadow: none !important;
	color: #ffffff !important;
	font-family: var(--ccd-a11y-title) !important;
	paint-order: stroke fill;
}

/*
 * Hero interno unico (base /receitas/): mesmo tamanho, tipografia e centro.
 * Inclui posts (categoria no hero) — sem overrides menores.
 */
.header:not(.header-homepage) .hero-title,
.header:not(.header-homepage) .hero-title a,
body.single-post .header:not(.header-homepage) .hero-title,
body.single-post .header:not(.header-homepage) .hero-title a,
.archive .header:not(.header-homepage) .hero-title,
.page .header:not(.header-homepage) .hero-title,
.blog .header:not(.header-homepage) .hero-title,
.search .header:not(.header-homepage) .hero-title,
.error404 .header:not(.header-homepage) .hero-title {
	display: block !important;
	float: none !important;
	clear: both !important;
	width: 100% !important;
	max-width: none !important;
	margin: 0 auto !important;
	padding: 0 1rem !important;
	box-sizing: border-box;
	color: #ffffff !important;
	font-family: var(--ccd-a11y-title) !important;
	font-size: 2.75rem !important;
	font-weight: 800 !important;
	line-height: 1.18 !important;
	letter-spacing: 0.9px !important;
	text-align: center !important;
	text-transform: none !important;
	text-decoration: none !important;
	-webkit-text-stroke: 1.45px #061018;
	text-shadow:
		1px 0 0 #061018,
		-1px 0 0 #061018,
		0 1px 0 #061018,
		0 -1px 0 #061018,
		1px 1px 0 #061018,
		-1px -1px 0 #061018,
		0 2px 8px rgba(0, 0, 0, 0.45);
}

.header:not(.header-homepage) .header-subtitle {
	display: none !important;
}

body.single-post .header:not(.header-homepage) .hero-title a:hover,
body.single-post .header:not(.header-homepage) .hero-title a:focus,
.header:not(.header-homepage) .hero-title a:hover,
.header:not(.header-homepage) .hero-title a:focus {
	color: #ffffff !important;
	text-decoration: none !important;
}
body.single-post .post-content-single > .meta {
	display: none !important;
}
body.single-post .post-content-single > h1,
body.single-post .post-content-single > h1.ccd-post-entry-title {
	display: block !important;
	margin: 0 0 0.35rem !important;
	padding: 0 !important;
	color: var(--ccd-a11y-ink) !important;
	font-family: var(--ccd-a11y-title) !important;
	font-size: clamp(1.55rem, 2.6vw, 2.05rem) !important;
	font-weight: 800 !important;
	line-height: 1.25 !important;
	letter-spacing: 0.01em;
}
body.single-post .post-content-single > .ccd-post-byline {
	display: block !important;
	margin: 0 0 1.25rem !important;
	padding: 0 !important;
	color: var(--ccd-a11y-muted) !important;
	font-size: 0.95rem !important;
	font-weight: 400 !important;
	line-height: 1.45 !important;
}
body.single-post .post-content-single > .ccd-post-byline a {
	color: var(--ccd-a11y-link) !important;
	text-decoration: none !important;
	font-weight: 600 !important;
}
body.single-post .post-content-single > .ccd-post-byline a:hover,
body.single-post .post-content-single > .ccd-post-byline a:focus {
	color: var(--ccd-a11y-link-hover) !important;
	text-decoration: underline !important;
}
body.single-post .post-content-single > .post-meta,
body.single-post .row.post-meta.small,
body.single-post .post-navigation,
body.single-post nav.navigation.post-navigation {
	display: none !important;
}

@media (max-width: 767px) {
	.header:not(.header-homepage) .hero-title,
	.header:not(.header-homepage) .hero-title a,
	body.single-post .header:not(.header-homepage) .hero-title,
	body.single-post .header:not(.header-homepage) .hero-title a {
		font-size: 1.85rem !important;
		line-height: 1.18 !important;
		letter-spacing: 0.9px !important;
		padding: 0 0.75rem !important;
	}
}

/* Home: tamanho/posicao do tema + negrito e borda */
.header-homepage {
	position: relative;
	isolation: isolate;
}
.header-homepage::before {
	content: none !important;
	display: none !important;
}
.header-homepage .header-description {
	display: block !important;
	min-height: 0 !important;
	padding: 0 !important;
	align-items: stretch !important;
}
.header-homepage .header-description-row {
	display: flex !important;
	width: 100%;
	padding-top: 1.25rem !important;
	padding-bottom: 25% !important;
	margin: 0 !important;
	text-align: inherit !important;
}
.header-homepage .header-content,
.header-homepage .align-holder {
	display: block !important;
	/* Uma linha abaixo, sem aumentar a altura do hero. */
	transform: translateY(4rem);
}
.header-homepage .align-holder.right {
	text-align: right !important;
	margin-left: auto;
}
.header-homepage .hero-title,
.header-homepage h1.h1-h,
.header-homepage .h1-h {
	display: block !important;
	width: auto !important;
	max-width: none !important;
	margin: 0 0 1.25rem !important;
	padding: 0 !important;
	font-size: 3.5rem !important;
	font-weight: 700 !important;
	line-height: 1.14 !important;
	letter-spacing: 0.9px;
	-webkit-text-stroke: 1.35px #0a141c;
	text-shadow:
		1px 0 0 #0a141c,
		-1px 0 0 #0a141c,
		0 1px 0 #0a141c,
		0 -1px 0 #0a141c,
		1px 1px 0 #0a141c,
		-1px -1px 0 #0a141c,
		0 2px 8px rgba(0, 0, 0, 0.4);
}
.header-homepage .header-subtitle {
	display: block !important;
	width: auto !important;
	max-width: 36rem !important;
	margin: 0 0 1.25rem auto !important;
	padding: 0 !important;
	font-size: 1.3rem !important;
	font-weight: 700 !important;
	line-height: 1.3 !important;
	-webkit-text-stroke: 1px #0a141c;
	text-shadow:
		1px 0 0 #0a141c,
		-1px 0 0 #0a141c,
		0 1px 0 #0a141c,
		0 -1px 0 #0a141c,
		0 2px 6px rgba(0, 0, 0, 0.35);
}
.header-homepage .align-holder:not(.right) .header-subtitle {
	margin-left: 0 !important;
	margin-right: auto !important;
}
@media (max-width: 767px) {
	.header-homepage .hero-title,
	.header-homepage h1.h1-h {
		font-size: 2.25rem !important;
	}
}

/* Meta / “Leia mais” / paginacao — eram ~80% e cinza claro */
.blog-post .post-content,
.blog-post p,
.post-list-item p,
.card .content,
.read-more,
.more-link,
.pagination a,
.navigation.pagination a,
.prev-posts a,
.next-posts a {
	font-size: 1rem !important;
	font-weight: 400 !important;
	color: var(--ccd-a11y-muted) !important;
}
.read-more,
.more-link {
	font-weight: 600 !important;
	color: var(--ccd-a11y-link) !important;
}
/*
 * Overscroll (Chromium/Edge): a cor do root pinta o topo;
 * a sombra do rodape estende a cor escura para baixo da pagina.
 */
html {
	background: #ffffff !important;
	background-color: #ffffff !important;
}
body {
	background-color: #ffffff !important;
	overflow-x: clip;
}
.footer,
.footer-simple,
div.footer,
footer.footer {
	background-color: #0f2a38 !important;
	background-image: none !important;
}
.footer.footer-simple {
	box-shadow: 0 50vh 0 50vh #0f2a38;
}
.footer,
.footer a,
.footer p,
.footer .copyright,
.footer-simple,
.footer-simple a,
.footer-simple p {
	color: #e8f1f6 !important;
}
.footer a:hover,
.footer-simple a:hover {
	color: #7ecff7 !important;
}

/* Formularios / placeholders com contraste */
input::placeholder,
textarea::placeholder {
	color: #5a6b76 !important;
	opacity: 1 !important;
}
input[type="text"],
input[type="email"],
input[type="search"],
input[type="url"],
input[type="tel"],
textarea,
select {
	font-size: 1rem !important;
	font-weight: 400 !important;
	color: var(--ccd-a11y-ink) !important;
	min-height: 2.75rem;
}

/* Noptin: tipografia/contraste sem quebrar a identidade clara do card */
.noptin-form-id-2859 .noptin-form-heading {
	color: var(--ccd-a11y-ink) !important;
	font-size: clamp(1.15rem, 2vw, 1.35rem) !important;
	font-weight: 600 !important;
	line-height: 1.35 !important;
}
.noptin-form-id-2859 .noptin-form-field,
.noptin-form-id-2859 input.noptin-form-field,
.noptin-form-id-2859 .noptin-text {
	font-size: 1rem !important;
	font-weight: 400 !important;
	color: var(--ccd-a11y-ink) !important;
	min-height: 3rem !important;
}
.noptin-form-id-2859 .noptin-form-field::placeholder {
	color: #4a5c66 !important;
}
.noptin-form-id-2859 .noptin-form-submit,
.noptin-form-id-2859 input.noptin-form-submit {
	font-size: 1.05rem !important;
	font-weight: 600 !important;
	color: #ffffff !important;
	min-height: 3rem !important;
}

/* Comentarios */
.ccd-comment-field label,
.comment-form label {
	font-size: 1rem !important;
	font-weight: 600 !important;
	color: var(--ccd-a11y-ink) !important;
}
.ccd-comment-field input,
.ccd-comment-field textarea,
.comment-form input,
.comment-form textarea {
	font-size: 1rem !important;
	font-weight: 400 !important;
}
.ccd-comment-submit #submit,
.ccd-comment-submit button[type="submit"] {
	font-weight: 600 !important;
}
.ccd-comment-error {
	font-size: 0.95rem !important;
	font-weight: 500 !important;
}

/* Contato WPForms */
div.wpforms-container-full#wpforms-2765 .wpforms-field input,
div.wpforms-container-full#wpforms-2765 .wpforms-field textarea {
	font-size: 1.05rem !important;
	font-weight: 400 !important;
	color: var(--ccd-a11y-ink) !important;
}
div.wpforms-container-full#wpforms-2765 .wpforms-field input::placeholder,
div.wpforms-container-full#wpforms-2765 .wpforms-field textarea::placeholder {
	color: #4a5c66 !important;
}
div.wpforms-container-full#wpforms-2765 button.wpforms-submit {
	font-size: 1.05rem !important;
	font-weight: 600 !important;
}

/* Foco visivel (teclado) */
:focus-visible {
	outline: 3px solid var(--ccd-a11y-focus) !important;
	outline-offset: 3px !important;
}
a:focus-visible,
button:focus-visible,
input:focus-visible,
textarea:focus-visible,
select:focus-visible,
.noptin-form-submit:focus-visible,
.wpforms-submit:focus-visible {
	outline: 3px solid var(--ccd-a11y-focus) !important;
	outline-offset: 3px !important;
}

/* Skip link util */
.skip-link.screen-reader-text:focus {
	position: fixed !important;
	top: 0.75rem !important;
	left: 0.75rem !important;
	z-index: 100000 !important;
	width: auto !important;
	height: auto !important;
	padding: 0.75rem 1rem !important;
	clip: auto !important;
	background: #0b57d0 !important;
	color: #fff !important;
	font-size: 1rem !important;
	font-weight: 700 !important;
	border-radius: 8px !important;
	text-decoration: none !important;
}

/* Home: @convivendocomdiabetes no titulo Instagram mantem cor e vira link. */
.page-id-2453 h2 a[href*="instagram.com/convivendocomdiabetes"],
.home h2 a[href*="instagram.com/convivendocomdiabetes"] {
	color: inherit !important;
	text-decoration: underline !important;
	text-underline-offset: 0.15em;
}
.page-id-2453 h2 a[href*="instagram.com/convivendocomdiabetes"]:hover,
.home h2 a[href*="instagram.com/convivendocomdiabetes"]:hover {
	filter: brightness(0.9);
}

/*
 * Botao "Sobre mim":
 * - 200x80 (PNG original)
 * - cor = "Acompanhe" do Instagram (#ad1457 apos remap a11y)
 * - alinhado ao icone LinkedIn ao lado
 */
p:has(> a.ccd-btn-sobre-mim) {
	display: flex !important;
	flex-wrap: wrap;
	align-items: center !important;
	gap: 0.75rem;
}
p:has(> a.ccd-btn-sobre-mim) a[href*="linkedin"],
p:has(> a.ccd-btn-sobre-mim) a[href*="linked.in"] {
	display: inline-flex !important;
	align-items: center !important;
	justify-content: center !important;
	flex: 0 0 74px;
	width: 74px !important;
	height: 74px !important;
	margin: 0 !important;
	line-height: 0 !important;
	border-radius: 50% !important;
	box-shadow: 0 6px 14px rgba(10, 102, 194, 0.28) !important;
	transition: filter 0.15s ease, box-shadow 0.2s ease, transform 0.15s ease;
}
p:has(> a.ccd-btn-sobre-mim) a[href*="linkedin"] img,
p:has(> a.ccd-btn-sobre-mim) a[href*="linked.in"] img {
	display: block !important;
	width: 74px !important;
	height: 74px !important;
	max-width: 74px !important;
	margin: 0 !important;
	border-radius: 50%;
	transition: filter 0.15s ease;
}
p:has(> a.ccd-btn-sobre-mim) a[href*="linkedin"]:hover,
p:has(> a.ccd-btn-sobre-mim) a[href*="linkedin"]:focus,
p:has(> a.ccd-btn-sobre-mim) a[href*="linked.in"]:hover,
p:has(> a.ccd-btn-sobre-mim) a[href*="linked.in"]:focus {
	transform: translateY(-1px);
	box-shadow: 0 10px 20px rgba(10, 102, 194, 0.42) !important;
	filter: brightness(0.92);
	text-decoration: none !important;
}
p:has(> a.ccd-btn-sobre-mim) a[href*="linkedin"]:focus-visible,
p:has(> a.ccd-btn-sobre-mim) a[href*="linked.in"]:focus-visible {
	outline: 3px solid #0b57d0 !important;
	outline-offset: 3px !important;
}
a.ccd-btn-sobre-mim,
.content a.ccd-btn-sobre-mim,
.page-content a.ccd-btn-sobre-mim,
.entry-content a.ccd-btn-sobre-mim {
	display: inline-flex !important;
	align-items: center !important;
	justify-content: center !important;
	box-sizing: border-box !important;
	width: 200px !important;
	height: 80px !important;
	min-width: 200px !important;
	max-width: 200px !important;
	min-height: 80px !important;
	max-height: 80px !important;
	margin: 0 !important;
	padding: 0 1.25rem !important;
	border: 0 !important;
	border-radius: 999px !important;
	background: #ad1457 !important; /* mesma cor de "Acompanhe" */
	background-image: none !important;
	color: #ffffff !important;
	font-family: "Pacifico", "Segoe Script", "Comic Sans MS", cursive !important;
	font-size: 1.65rem !important;
	font-weight: 400 !important;
	letter-spacing: 0.01em;
	line-height: 1 !important;
	text-decoration: none !important;
	text-transform: none !important;
	vertical-align: middle;
	box-shadow: 0 6px 14px rgba(173, 20, 87, 0.35) !important;
	transition: filter 0.15s ease, box-shadow 0.2s ease, transform 0.15s ease;
}
a.ccd-btn-sobre-mim:hover,
a.ccd-btn-sobre-mim:focus,
.content a.ccd-btn-sobre-mim:hover,
.page-content a.ccd-btn-sobre-mim:hover {
	color: #ffffff !important;
	background: #8e1047 !important;
	filter: none;
	transform: translateY(-1px);
	box-shadow: 0 10px 20px rgba(142, 16, 71, 0.4) !important;
	text-decoration: none !important;
}
a.ccd-btn-sobre-mim:focus-visible,
a.ccd-btn-midia-kit:focus-visible {
	outline: 3px solid #0b57d0 !important;
	outline-offset: 3px !important;
}

/* Midia Kit (Servicos): posicao/tamanho originais (800x100, centro) */
p:has(> a.ccd-btn-midia-kit),
.ccd-btn-midia-kit-wrap {
	display: block !important;
	width: 100% !important;
	margin: 1.75rem 0 2rem !important;
	padding: 0 !important;
	text-align: center !important;
}
a.ccd-btn-midia-kit,
.content a.ccd-btn-midia-kit,
.page-content a.ccd-btn-midia-kit,
.entry-content a.ccd-btn-midia-kit {
	display: flex !important;
	align-items: center !important;
	justify-content: center !important;
	box-sizing: border-box !important;
	width: min(800px, 100%) !important;
	max-width: 800px !important;
	height: 100px !important;
	min-height: 100px !important;
	max-height: 100px !important;
	margin: 1.75rem auto 2rem !important;
	padding: 0 2rem !important;
	border: 0 !important;
	border-radius: 999px !important;
	background: #ad1457 !important;
	background-image: none !important;
	color: #ffffff !important;
	font-family: "Pacifico", "Segoe Script", "Comic Sans MS", cursive !important;
	font-size: clamp(1.35rem, 2.4vw, 1.85rem) !important;
	font-weight: 400 !important;
	letter-spacing: 0.01em;
	line-height: 1.15 !important;
	text-align: center !important;
	text-decoration: none !important;
	text-transform: none !important;
	box-shadow: 0 6px 14px rgba(173, 20, 87, 0.35) !important;
	transition: filter 0.15s ease, box-shadow 0.2s ease, transform 0.15s ease;
}
a.ccd-btn-midia-kit:hover,
a.ccd-btn-midia-kit:focus,
.content a.ccd-btn-midia-kit:hover {
	color: #ffffff !important;
	background: #8e1047 !important;
	transform: translateY(-1px);
	box-shadow: 0 10px 20px rgba(142, 16, 71, 0.4) !important;
	text-decoration: none !important;
}
@media (max-width: 767px) {
	a.ccd-btn-midia-kit,
	.content a.ccd-btn-midia-kit {
		height: auto !important;
		min-height: 72px !important;
		max-height: none !important;
		padding: 0.85rem 1.25rem !important;
		font-size: 1.25rem !important;
	}
}

/* Fallback visual enquanto o JS troca o PNG (Sobre mim) */
a:has(> img[src*="Sobre-mim"]:not([src*="Sobre-mim-4"])) img[src*="Sobre-mim"] {
	position: absolute !important;
	width: 1px !important;
	height: 1px !important;
	padding: 0 !important;
	margin: -1px !important;
	overflow: hidden !important;
	clip: rect(0, 0, 0, 0) !important;
	border: 0 !important;
}
a:has(> img[src*="Sobre-mim"]:not([src*="Sobre-mim-4"])) {
	display: inline-flex !important;
	align-items: center !important;
	justify-content: center !important;
	width: 200px !important;
	height: 80px !important;
	padding: 0 1.25rem !important;
	border-radius: 999px !important;
	background: #ad1457 !important;
	color: #ffffff !important;
	font-family: "Pacifico", "Segoe Script", cursive !important;
	font-size: 1.65rem !important;
	text-decoration: none !important;
	box-shadow: 0 6px 14px rgba(173, 20, 87, 0.35) !important;
}
a:has(> img[src*="Sobre-mim"]:not([src*="Sobre-mim-4"]))::after {
	content: "Sobre mim";
}
a:has(> img[src*="Sobre-mim-4"]) img[src*="Sobre-mim-4"] {
	position: absolute !important;
	width: 1px !important;
	height: 1px !important;
	padding: 0 !important;
	margin: -1px !important;
	overflow: hidden !important;
	clip: rect(0, 0, 0, 0) !important;
	border: 0 !important;
}
a:has(> img[src*="Sobre-mim-4"]) {
	display: inline-flex !important;
	align-items: center !important;
	justify-content: center !important;
	width: min(800px, 100%) !important;
	height: 100px !important;
	padding: 0 2rem !important;
	border-radius: 999px !important;
	background: #ad1457 !important;
	color: #ffffff !important;
	font-family: "Pacifico", "Segoe Script", cursive !important;
	font-size: clamp(1.35rem, 2.4vw, 1.85rem) !important;
	text-decoration: none !important;
	box-shadow: 0 6px 14px rgba(173, 20, 87, 0.35) !important;
}
a:has(> img[src*="Sobre-mim-4"])::after {
	content: "Baixe aqui o meu Mídia Kit";
}

/* Reduz movimento se o usuario pedir */
@media (prefers-reduced-motion: reduce) {
	*,
	*::before,
	*::after {
		animation-duration: 0.01ms !important;
		animation-iteration-count: 1 !important;
		transition-duration: 0.01ms !important;
		scroll-behavior: auto !important;
	}
}
CSS;

		wp_enqueue_style(
			'ccd-a11y-nunito',
			'https://fonts.googleapis.com/css2?family=Nunito:wght@600;700;800&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'ccd-a11y-pacifico',
			'https://fonts.googleapis.com/css2?family=Pacifico&display=swap',
			array( 'ccd-a11y-nunito' ),
			null
		);
		wp_register_style( 'ccd-a11y', false, array( 'ccd-a11y-pacifico' ), '1.8.2' );
		wp_enqueue_style( 'ccd-a11y' );
		wp_add_inline_style( 'ccd-a11y', $css );

		wp_register_script( 'ccd-a11y', false, array(), '1.8.2', true );
		wp_enqueue_script( 'ccd-a11y' );
		wp_add_inline_script(
			'ccd-a11y',
			'window.ccdA11ySobreUrl = ' . wp_json_encode( home_url( '/sobre/' ) ) . ';'
			. 'window.ccdA11yMidiaKitUrl = ' . wp_json_encode( 'https://drive.google.com/uc?export=download&id=1vb8Ow4F0QaF53pxkS-YiBj98_VnRCwWM' ) . ';'
		);
		wp_add_inline_script(
			'ccd-a11y',
			<<<'JS'
(function () {
	var sobreUrl = window.ccdA11ySobreUrl || '/sobre/';
	var midiaUrl = window.ccdA11yMidiaKitUrl || 'https://drive.google.com/uc?export=download&id=1vb8Ow4F0QaF53pxkS-YiBj98_VnRCwWM';

	function isMidiaKit(img, a) {
		var src = (img && img.getAttribute('src')) || '';
		var href = (a && a.getAttribute('href')) || '';
		return /Sobre-mim-4/i.test(src) || /drive\.google\.com/i.test(href);
	}

	document.querySelectorAll('a > img[src*="Sobre-mim"]').forEach(function (img) {
		var a = img.parentElement;
		if (!a) return;
		if (a.classList.contains('ccd-btn-sobre-mim') || a.classList.contains('ccd-btn-midia-kit')) return;

		if (isMidiaKit(img, a)) {
			a.classList.add('ccd-btn-midia-kit');
			a.setAttribute('href', midiaUrl);
			a.setAttribute('download', 'Convivendo-com-Diabetes-Midia-Kit.pdf');
			a.setAttribute('target', '_blank');
			a.setAttribute('rel', 'noopener noreferrer');
			a.textContent = 'Baixe aqui o meu Mídia Kit';
			if (a.parentElement && a.parentElement.tagName === 'P') {
				a.parentElement.classList.add('ccd-btn-midia-kit-wrap');
			}
			return;
		}

		a.classList.add('ccd-btn-sobre-mim');
		a.setAttribute('href', sobreUrl);
		a.textContent = 'Sobre mim';
	});
})();
JS
		);
	},
	50
);

/**
 * Home: @convivendocomdiabetes vira link do Instagram; remove o botao redundante abaixo.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_string( $content ) || $content === '' ) {
			return $content;
		}

		if ( ! is_front_page() && ! is_home() ) {
			return $content;
		}

		$ig = 'https://www.instagram.com/convivendocomdiabetes/';

		// @handle ainda sem link (evita linkar de novo se ja houver <a>).
		$linked = preg_replace(
			'~(<span[^>]*style="[^"]*color:\s*#00b3ff[^"]*"[^>]*>\s*<strong>)(?!<a\b)@convivendocomdiabetes(</strong>\s*</span>)~i',
			'$1<a href="' . esc_url( $ig ) . '" target="_blank" rel="noopener noreferrer">@convivendocomdiabetes</a>$2',
			$content,
			1
		);
		if ( is_string( $linked ) ) {
			$content = $linked;
		}

		// Remove "@convivendocomdiabetes no Instagram".
		$cleaned = preg_replace(
			'~\s*<p[^>]*>\s*<a[^>]*href="https?://(?:www\.)?instagram\.com/convivendocomdiabetes/?[^"]*"[^>]*>\s*@convivendocomdiabetes\s+no\s+Instagram\s*</a>\s*</p>\s*~iu',
			"\n",
			$content,
			1
		);
		if ( is_string( $cleaned ) ) {
			$content = $cleaned;
		}

		return $content;
	},
	15
);

/**
 * Substitui PNGs de CTA por botoes HTML acessiveis no conteudo.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! ccd_a11y_is_local() || ! is_string( $content ) || $content === '' ) {
			return $content;
		}

		$midia_url = esc_url( 'https://drive.google.com/uc?export=download&id=1vb8Ow4F0QaF53pxkS-YiBj98_VnRCwWM' );
		$midia_btn = '<a class="ccd-btn-midia-kit aligncenter" href="' . $midia_url . '" download="Convivendo-com-Diabetes-Midia-Kit.pdf" target="_blank" rel="noopener noreferrer">Baixe aqui o meu Mídia Kit</a>';

		$replaced = preg_replace(
			'#<a[^>]*href="[^"]*drive\.google\.com[^"]*"[^>]*>\s*<img[^>]*Sobre-mim[^>]*>\s*</a>#i',
			$midia_btn,
			$content
		);
		if ( ! is_string( $replaced ) ) {
			$replaced = $content;
		}

		$replaced = preg_replace(
			'#<a[^>]*>\s*<img[^>]*Sobre-mim-4[^>]*>\s*</a>#i',
			$midia_btn,
			$replaced
		);
		if ( ! is_string( $replaced ) ) {
			$replaced = $content;
		}

		$sobre_url = esc_url( home_url( '/sobre/' ) );
		$sobre_btn = '<a class="ccd-btn-sobre-mim" href="' . $sobre_url . '">Sobre mim</a>';

		$replaced = preg_replace(
			'#<a[^>]*>\s*<img[^>]*Sobre-mim(?!-4)[^>]*>\s*</a>#i',
			$sobre_btn,
			$replaced
		);

		return is_string( $replaced ) ? $replaced : $content;
	},
	20
);
