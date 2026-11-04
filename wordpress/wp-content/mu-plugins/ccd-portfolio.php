<?php
/**
 * Plugin Name: CCD Portfólio
 * Description: Layout redesenhado da página Portfólio (slug "clipping"). Reaproveita as imagens/links já usados na página. Desligar com CCD_PORTFOLIO_DISABLE.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug da página Portfólio.
 */
function ccd_portfolio_slug() {
	return 'clipping';
}

/**
 * Layout ativo (kill-switch: CCD_PORTFOLIO_DISABLE).
 *
 * @return bool
 */
function ccd_portfolio_enabled() {
	return ! ( defined( 'CCD_PORTFOLIO_DISABLE' ) && CCD_PORTFOLIO_DISABLE );
}

/**
 * Estamos na página Portfólio (consulta principal)?
 *
 * @return bool
 */
function ccd_portfolio_is_page() {
	return ccd_portfolio_enabled() && is_page( ccd_portfolio_slug() );
}

/**
 * Invalida cache HTML quando a versão do layout muda.
 */
add_action(
	'init',
	static function () {
		if ( ! ccd_portfolio_enabled() || ! function_exists( 'ccd_page_cache_purge_all' ) ) {
			return;
		}
		$ver = '1.0.4';
		if ( get_option( 'ccd_portfolio_cache_bust' ) === $ver ) {
			return;
		}
		ccd_page_cache_purge_all();
		update_option( 'ccd_portfolio_cache_bust', $ver, false );
	},
	2
);

add_filter(
	'body_class',
	static function ( $classes ) {
		if ( ccd_portfolio_is_page() ) {
			$classes[] = 'ccd-portfolio';
		}
		return $classes;
	}
);

/**
 * <img> de um anexo (por ID) com fallback para o arquivo em uploads.
 *
 * @param int    $id       ID do anexo.
 * @param string $fallback Caminho em uploads (ex.: 2022/08/arquivo.png).
 * @param string $alt      Texto alternativo.
 * @param string $size     Tamanho registrado.
 * @param array  $attr     Atributos extras.
 * @return string
 */
function ccd_portfolio_image( $id, $fallback, $alt, $size = 'large', array $attr = array() ) {
	$attr['alt'] = $alt;
	$html        = wp_get_attachment_image( (int) $id, $size, false, $attr );
	if ( '' !== $html ) {
		return $html;
	}
	return sprintf(
		'<img src="%s" alt="%s" loading="lazy" decoding="async" />',
		esc_url( content_url( '/uploads/' . ltrim( $fallback, '/' ) ) ),
		esc_attr( $alt )
	);
}

/**
 * Marcação da página.
 *
 * @return string
 */
function ccd_portfolio_markup() {
	$campaigns = array(
		array(
			'id'       => 2879,
			'file'     => '2022/08/Na-Winsocial-sou-integrante-do-Conselho-W.jpg',
			'url'      => 'https://www.instagram.com/p/CXw74jjM4LV/?igshid=YmMyMTA2M2Y=',
			'title'    => 'Lilly',
			'subtitle' => 'Campanha de conscientização',
		),
		array(
			'id'       => 2880,
			'file'     => '2022/08/Na-Winsocial-sou-integrante-do-Conselho-W-1.jpg',
			'url'      => 'https://www.instagram.com/p/CW6f0lWlzhS/?igshid=YmMyMTA2M2Y=',
			'title'    => 'Boehringer',
			'subtitle' => 'Conteúdo em parceria',
		),
		array(
			'id'       => 2883,
			'file'     => '2022/08/Na-Winsocial-sou-integrante-do-Conselho-W-2.jpg',
			'url'      => 'https://www.instagram.com/p/CaQbrCCuA9X/?igshid=YmMyMTA2M2Y=',
			'title'    => 'Conselho W',
			'subtitle' => 'W na Winsocial',
		),
	);

	ob_start();
	?>
<div class="ccd-pf">
	<span class="ccd-pf__deco ccd-pf__deco--pink" aria-hidden="true"></span>
	<span class="ccd-pf__deco ccd-pf__deco--teal" aria-hidden="true"></span>

	<header class="ccd-pf__intro">
		<p class="ccd-pf__eyebrow">Convivendo com Diabetes</p>
		<h2 class="ccd-pf__headline">Histórias que inspiram.<br>Conteúdos que transformam.</h2>
		<p class="ccd-pf__lead">Campanhas, projetos e participações que ampliam a conversa sobre diabetes.</p>
		<span class="ccd-pf__bar" aria-hidden="true"></span>
	</header>

	<section class="ccd-pf__section" aria-labelledby="ccd-pf-campanhas">
		<p class="ccd-pf__eyebrow">01 / Campanhas</p>
		<h2 class="ccd-pf__title" id="ccd-pf-campanhas">Campanhas publicitárias</h2>
		<p class="ccd-pf__lead">Projetos e parcerias com marcas e iniciativas de saúde.</p>

		<ul class="ccd-pf__cards">
			<?php foreach ( $campaigns as $i => $item ) : ?>
				<li class="ccd-pf__card">
					<a class="ccd-pf__thumb" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
						<?php
						echo ccd_portfolio_image( // phpcs:ignore WordPress.Security.EscapeOutput
							$item['id'],
							$item['file'],
							'Campanha ' . $item['title'],
							'medium_large',
							array(
								'loading'  => 0 === $i ? 'eager' : 'lazy',
								'sizes'    => '(max-width: 640px) 100vw, 330px',
								'decoding' => 'async',
							)
						);
						?>
						<span class="ccd-pf__sr">(abre em nova aba)</span>
					</a>
					<p class="ccd-pf__card-title"><?php echo esc_html( $item['title'] ); ?></p>
					<p class="ccd-pf__card-sub"><?php echo esc_html( $item['subtitle'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="ccd-pf__section" aria-labelledby="ccd-pf-galeria">
		<p class="ccd-pf__eyebrow">Galeria</p>
		<h2 class="ccd-pf__title" id="ccd-pf-galeria">Outros conteúdos</h2>
		<p class="ccd-pf__lead">Registros de vídeos, depoimentos e materiais das campanhas.</p>

		<div class="ccd-pf__panel"><div class="ccd-pf__crop">
			<?php
			echo ccd_portfolio_image( // phpcs:ignore WordPress.Security.EscapeOutput
				2948,
				'2022/08/Convivendo-com-Diabetes-Midia-Kit-2021.pdf-7.png',
				'Galeria com vídeos, depoimentos e materiais das campanhas',
				'large',
				array(
					'loading'  => 'lazy',
					'sizes'    => '(max-width: 1000px) 100vw, 960px',
					'decoding' => 'async',
				)
			);
			?>
			</div>
		</div>
	</section>

	<section class="ccd-pf__section" aria-labelledby="ccd-pf-midia">
		<p class="ccd-pf__eyebrow">02 / Imprensa</p>
		<h2 class="ccd-pf__title" id="ccd-pf-midia">Na mídia</h2>
		<p class="ccd-pf__lead">Entrevistas e participações em veículos de comunicação.</p>

		<div class="ccd-pf__media">
			<div class="ccd-pf__card">
				<a class="ccd-pf__thumb" href="https://youtu.be/b9miM-JoIw4" target="_blank" rel="noopener noreferrer">
					<?php
					echo ccd_portfolio_image( // phpcs:ignore WordPress.Security.EscapeOutput
						2882,
						'2022/08/Na-Winsocial-sou-integrante-do-Conselho-W-3.jpg',
						'Entrevista na Band',
						'medium_large',
						array(
							'loading'  => 'lazy',
							'sizes'    => '(max-width: 782px) 100vw, 380px',
							'decoding' => 'async',
						)
					);
					?>
					<span class="ccd-pf__sr">(abre em nova aba)</span>
				</a>
				<p class="ccd-pf__card-title">Entrevista na Band</p>
				<p class="ccd-pf__card-sub">Participação na imprensa</p>
			</div>

			<div class="ccd-pf__panel"><div class="ccd-pf__crop">
				<?php
				echo ccd_portfolio_image( // phpcs:ignore WordPress.Security.EscapeOutput
					2949,
					'2022/08/Convivendo-com-Diabetes-Midia-Kit-2021.pdf-8.png',
					'Entrevistas e participações em veículos de comunicação',
					'large',
					array(
						'loading'  => 'lazy',
						'sizes'    => '(max-width: 782px) 100vw, 600px',
						'decoding' => 'async',
					)
				);
				?>
				</div>
			</div>
		</div>
	</section>
</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Substitui o corpo da página Portfólio pelo novo layout.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! ccd_portfolio_is_page() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		return ccd_portfolio_markup();
	},
	11
);

/**
 * CSS (somente na página).
 */
add_action(
	'wp_head',
	static function () {
		if ( ! ccd_portfolio_is_page() ) {
			return;
		}
		?>
<style id="ccd-portfolio-css">
body.ccd-portfolio{overflow-x:clip}
body.ccd-portfolio .page-content{position:relative;overflow-x:clip}
.ccd-pf{--pf-ink:#12303f;--pf-muted:#566a76;--pf-pink:#c81e5b;--pf-pink-soft:#ec6a9b;--pf-radius:18px;position:relative;isolation:isolate;padding:2.5rem 0 3rem;color:var(--pf-ink)}
.ccd-pf *,.ccd-pf *::before,.ccd-pf *::after{box-sizing:border-box}
.ccd-pf__deco{position:absolute;z-index:-1;border-radius:50%;pointer-events:none}
.ccd-pf__deco--pink{width:clamp(220px,34vw,380px);aspect-ratio:1;top:-5rem;right:-9rem;background:rgba(236,106,155,.16)}
.ccd-pf__deco--teal{width:clamp(200px,30vw,340px);aspect-ratio:1;top:46%;left:-12rem;background:rgba(45,190,178,.12)}
.ccd-pf .ccd-pf__eyebrow{margin:0 0 .6rem;font-size:.75rem!important;font-weight:700!important;letter-spacing:.2em;text-transform:uppercase;color:var(--pf-pink)!important;text-align:left}
.ccd-pf__headline,.ccd-pf__title{margin:0;font-weight:800;line-height:1.15;color:var(--pf-ink);text-align:left}
.ccd-pf__headline{font-size:clamp(1.9rem,4.4vw,2.75rem)}
.ccd-pf__title{font-size:clamp(1.55rem,3.2vw,2.1rem)}
.ccd-pf .ccd-pf__lead{margin:.75rem 0 0;font-size:1rem;line-height:1.55;color:var(--pf-muted)!important;text-align:left}
.ccd-pf__bar{display:block;width:3.25rem;height:4px;margin-top:1.5rem;border-radius:4px;background:var(--pf-pink-soft)}
.ccd-pf__intro{margin-bottom:3.25rem}
.ccd-pf__section{margin-top:3.5rem}
.ccd-pf__cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.75rem;margin:1.75rem 0 0;padding:0;list-style:none}
.ccd-pf__cards::before,.ccd-pf__cards::after,.ccd-pf__media::before,.ccd-pf__media::after{content:none!important;display:none!important}
.ccd-pf__card{margin:0;padding:0;list-style:none}
.ccd-pf__card::marker{content:none}
.ccd-pf .ccd-pf__sr{position:absolute!important;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap;border:0}
.ccd-pf__thumb{display:block;overflow:hidden;border:2px solid rgba(236,106,155,.45);border-radius:var(--pf-radius);box-shadow:0 10px 26px rgba(18,48,63,.12);background:#fff;transition:transform .2s ease,box-shadow .2s ease}
.ccd-pf__thumb:hover,.ccd-pf__thumb:focus-visible{transform:translateY(-3px);box-shadow:0 16px 32px rgba(18,48,63,.18)}
.ccd-pf__thumb:focus-visible{outline:3px solid var(--pf-pink);outline-offset:3px}
.ccd-pf__thumb img{display:block;width:100%;height:auto;aspect-ratio:8/7;object-fit:cover}
.ccd-pf .ccd-pf__card-title{margin:.85rem 0 0;font-size:1rem;font-weight:700!important;color:var(--pf-ink)!important;text-align:left}
.ccd-pf .ccd-pf__card-sub{margin:.15rem 0 0;font-size:.875rem;color:var(--pf-muted)!important;text-align:left}
.ccd-pf__panel{margin-top:1.75rem;padding:clamp(.75rem,2vw,1.5rem);border-radius:var(--pf-radius);background:#fff;box-shadow:0 10px 30px rgba(18,48,63,.08)}
.ccd-pf__panel img{display:block;width:100%;height:auto;border-radius:10px}
.ccd-pf__crop{overflow:hidden;border-radius:10px;aspect-ratio:2.2/1}
.ccd-pf__panel .ccd-pf__crop img{width:111%;max-width:none;margin:-9.4% 0 0 -5.5%;border-radius:0}
.ccd-pf__media{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,8fr);gap:1.75rem;align-items:start;margin-top:1.75rem}
.ccd-pf__media .ccd-pf__panel{margin-top:0}
@media (max-width:782px){
.ccd-pf{padding-top:1.5rem}
.ccd-pf__cards{grid-template-columns:minmax(0,1fr);max-width:26rem}
.ccd-pf__media{grid-template-columns:minmax(0,1fr)}
.ccd-pf__media .ccd-pf__card{max-width:26rem}
.ccd-pf__deco--teal{top:38%}
}
@media (prefers-reduced-motion:reduce){.ccd-pf__thumb{transition:none}.ccd-pf__thumb:hover,.ccd-pf__thumb:focus-visible{transform:none}}
</style>
		<?php
	},
	30
);
