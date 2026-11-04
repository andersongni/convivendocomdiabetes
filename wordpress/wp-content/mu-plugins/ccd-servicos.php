<?php
/**
 * Plugin Name: CCD Serviços
 * Description: Layout redesenhado da página Serviços (slug "servicos"). Desligar com CCD_SERVICOS_DISABLE.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug da página Serviços.
 */
function ccd_servicos_slug() {
	return 'servicos';
}

/**
 * Layout ativo (kill-switch: CCD_SERVICOS_DISABLE).
 *
 * @return bool
 */
function ccd_servicos_enabled() {
	return ! ( defined( 'CCD_SERVICOS_DISABLE' ) && CCD_SERVICOS_DISABLE );
}

/**
 * Estamos na página Serviços (consulta principal)?
 *
 * @return bool
 */
function ccd_servicos_is_page() {
	return ccd_servicos_enabled() && is_page( ccd_servicos_slug() );
}

/**
 * Invalida cache HTML quando a versão do layout muda.
 */
add_action(
	'init',
	static function () {
		if ( ! ccd_servicos_enabled() || ! function_exists( 'ccd_page_cache_purge_all' ) ) {
			return;
		}
		$ver = '1.0.2';
		if ( get_option( 'ccd_servicos_cache_bust' ) === $ver ) {
			return;
		}
		ccd_page_cache_purge_all();
		update_option( 'ccd_servicos_cache_bust', $ver, false );
	},
	2
);

add_filter(
	'body_class',
	static function ( $classes ) {
		if ( ccd_servicos_is_page() ) {
			$classes[] = 'ccd-servicos';
		}
		return $classes;
	}
);

/**
 * Ícones SVG (inline, sem dependência externa).
 *
 * @param string $name upload|chat|list|clipboard|calendar|layout.
 * @return string
 */
function ccd_servicos_icon( $name ) {
	$icons = array(
		'upload'    => '<path d="M12 16V6"/><path d="M8 10l4-4 4 4"/><path d="M5 18h14"/>',
		'chat'      => '<path d="M6 18.5V15a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1.5a1.5 1.5 0 0 1-1.5 1.5H11l-5 3z"/><circle cx="11" cy="13.5" r=".8"/><circle cx="14.5" cy="13.5" r=".8"/><circle cx="18" cy="13.5" r=".8"/>',
		'list'      => '<path d="M9 7h10M9 12h10M9 17h10"/><circle cx="5.5" cy="7" r="1.2"/><circle cx="5.5" cy="12" r="1.2"/><circle cx="5.5" cy="17" r="1.2"/>',
		'clipboard' => '<rect x="7" y="5" width="10" height="14" rx="2"/><path d="M10 5V4.5a1.5 1.5 0 0 1 1.5-1.5h1A1.5 1.5 0 0 1 14 4.5V5"/><path d="M10 11h4M10 14h4"/>',
		'calendar'  => '<rect x="5" y="6" width="14" height="13" rx="2"/><path d="M5 10h14M9 4v4M15 4v4"/><circle cx="9" cy="14" r="1"/><circle cx="12" cy="14" r="1"/><circle cx="15" cy="14" r="1"/>',
		'layout'    => '<rect x="4.5" y="5" width="15" height="14" rx="2"/><path d="M4.5 9.5h15M11 9.5v9.5"/>',
	);
	$path = isset( $icons[ $name ] ) ? $icons[ $name ] : $icons['list'];
	return '<svg class="ccd-sv__svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}

/**
 * Marcação da página.
 *
 * @return string
 */
function ccd_servicos_markup() {
	$services = array(
		array(
			'tone'  => 'blue',
			'icon'  => 'upload',
			'label' => 'Consultoria',
			'title' => 'Jornada do Paciente',
			'text'  => 'Olhar estratégico para a experiência e as necessidades de quem convive.',
		),
		array(
			'tone'  => 'pink',
			'icon'  => 'chat',
			'label' => 'Palestras',
			'title' => 'Convivendo com Diabetes',
			'text'  => 'Conversas e encontros para informar, acolher e ampliar a conscientização.',
		),
		array(
			'tone'  => 'blue',
			'icon'  => 'list',
			'label' => 'Estratégia',
			'title' => 'Planejamento editorial',
			'text'  => 'Pautas e calendários de conteúdo sobre diabetes e saúde.',
		),
		array(
			'tone'  => 'pink',
			'icon'  => 'clipboard',
			'label' => 'Conteúdo',
			'title' => 'Produção especializada',
			'text'  => 'Textos e materiais sobre saúde, diabetes e obesidade.',
		),
		array(
			'tone'  => 'blue',
			'icon'  => 'calendar',
			'label' => 'Eventos',
			'title' => 'Curadoria e planejamento',
			'text'  => 'Eventos educativos na área da saúde, do conceito à programação.',
		),
		array(
			'tone'  => 'pink',
			'icon'  => 'layout',
			'label' => 'Parcerias',
			'title' => 'Espaço publicitário',
			'text'  => 'Divulgação de marcas nas redes do Convivendo com Diabetes.',
		),
	);

	$kit_url = 'https://drive.google.com/file/d/1vb8Ow4F0QaF53pxkS-YiBj98_VnRCwWM/view?usp=drive_link';

	ob_start();
	?>
<div class="ccd-sv">
	<header class="ccd-sv__intro">
		<p class="ccd-sv__eyebrow">Como posso contribuir</p>
		<h2 class="ccd-sv__headline">Conteúdo, estratégia e conexão</h2>
		<p class="ccd-sv__lead">Como paciente e jornalista de saúde especializada em diabetes e obesidade, produzo conteúdos e projetos que aproximam informação de qualidade das pessoas. Também atuo como consultora para empresas e para a indústria farmacêutica.</p>
	</header>

	<ul class="ccd-sv__grid">
		<?php foreach ( $services as $item ) : ?>
			<li class="ccd-sv__card ccd-sv__card--<?php echo esc_attr( $item['tone'] ); ?>">
				<span class="ccd-sv__icon" aria-hidden="true"><?php echo ccd_servicos_icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<p class="ccd-sv__label"><?php echo esc_html( $item['label'] ); ?></p>
				<h3 class="ccd-sv__title"><?php echo esc_html( $item['title'] ); ?></h3>
				<p class="ccd-sv__text"><?php echo esc_html( $item['text'] ); ?></p>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="ccd-sv__cta">
		<p class="ccd-sv__cta-lead">Quer conhecer formatos, audiência e possibilidades de parceria?</p>
		<a class="ccd-sv__btn" href="<?php echo esc_url( $kit_url ); ?>" target="_blank" rel="noopener noreferrer">
			<span class="ccd-sv__btn-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v10"/><path d="M8 10l4 4 4-4"/><path d="M5 18h14"/></svg>
			</span>
			<span class="ccd-sv__btn-text">Baixe aqui o meu Mídia Kit</span>
			<span class="ccd-sv__sr">(abre em nova aba)</span>
		</a>
	</div>
</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Substitui o corpo da página Serviços pelo novo layout.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! ccd_servicos_is_page() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		return ccd_servicos_markup();
	},
	11
);

/**
 * CSS (somente na página).
 */
add_action(
	'wp_head',
	static function () {
		if ( ! ccd_servicos_is_page() ) {
			return;
		}
		?>
<style id="ccd-servicos-css">
body.ccd-servicos{overflow-x:clip}
body.ccd-servicos .page-content{position:relative;overflow-x:clip}
.ccd-sv{--sv-ink:#12303f;--sv-muted:#5d7280;--sv-blue:#3bb4e8;--sv-blue-soft:#e8f6fc;--sv-pink:#c81e5b;--sv-pink-soft:#f8e6ee;--sv-cta:#a91355;--sv-radius:18px;position:relative;padding:2.5rem 0 3.25rem;color:var(--sv-ink)}
.ccd-sv *,.ccd-sv *::before,.ccd-sv *::after{box-sizing:border-box}
.ccd-sv__sr{position:absolute!important;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap;border:0}
.ccd-sv .ccd-sv__eyebrow{margin:0 0 .7rem;font-size:.75rem!important;font-weight:700!important;letter-spacing:.18em;text-transform:uppercase;color:var(--sv-blue)!important;text-align:left}
.ccd-sv__headline{margin:0;font-size:clamp(1.85rem,4.2vw,2.65rem);font-weight:800;line-height:1.15;color:var(--sv-ink);text-align:left}
.ccd-sv .ccd-sv__lead{margin:1rem 0 0;max-width:46rem;font-size:1.05rem;line-height:1.6;color:var(--sv-muted)!important;text-align:left}
.ccd-sv__intro{margin-bottom:2.5rem}
.ccd-sv__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.35rem;margin:0;padding:0;list-style:none}
.ccd-sv__grid::before,.ccd-sv__grid::after{content:none!important;display:none!important}
.ccd-sv__card{display:flex;flex-direction:column;align-items:flex-start;margin:0;padding:1.35rem 1.35rem 1.45rem;list-style:none;border-radius:var(--sv-radius);background:#fff;box-shadow:0 10px 28px rgba(18,48,63,.08);border-top:4px solid var(--sv-blue)}
.ccd-sv__card--pink{border-top-color:var(--sv-pink)}
.ccd-sv__icon{display:inline-flex;align-items:center;justify-content:center;width:2.65rem;height:2.65rem;margin-bottom:.95rem;border-radius:999px;background:var(--sv-blue-soft);color:var(--sv-blue)}
.ccd-sv__card--pink .ccd-sv__icon{background:var(--sv-pink-soft);color:var(--sv-pink)}
.ccd-sv .ccd-sv__label{margin:0 0 .45rem;font-size:.72rem!important;font-weight:700!important;letter-spacing:.14em;text-transform:uppercase;color:var(--sv-blue)!important;text-align:left}
.ccd-sv__title{margin:0;font-size:1.15rem;font-weight:800;line-height:1.25;color:var(--sv-ink);text-align:left}
.ccd-sv .ccd-sv__text{margin:.55rem 0 0;font-size:.95rem;line-height:1.5;color:var(--sv-muted)!important;text-align:left}
.ccd-sv__cta{margin-top:3rem;text-align:center}
.ccd-sv .ccd-sv__cta-lead{margin:0 0 1.1rem;font-size:.98rem;color:var(--sv-muted)!important;text-align:center}
.ccd-sv a.ccd-sv__btn,.ccd-sv a.ccd-sv__btn:visited{display:inline-flex;align-items:center;justify-content:center;gap:.85rem;min-height:4.1rem;padding:1.05rem 2.35rem;border-radius:999px;background:var(--sv-cta)!important;color:#fff!important;text-decoration:none!important;box-shadow:0 14px 32px rgba(169,19,85,.3);transition:transform .2s ease,box-shadow .2s ease,background .2s ease}
.ccd-sv a.ccd-sv__btn:hover,.ccd-sv a.ccd-sv__btn:focus,.ccd-sv a.ccd-sv__btn:focus-visible{background:#931149!important;color:#fff!important;transform:translateY(-2px);box-shadow:0 18px 36px rgba(169,19,85,.36)}
.ccd-sv a.ccd-sv__btn:focus-visible{outline:3px solid var(--sv-blue);outline-offset:3px}
.ccd-sv__btn-icon,.ccd-sv__btn-icon svg{display:inline-flex;flex-shrink:0;width:24px;height:24px;color:#fff!important;stroke:#fff}
.ccd-sv a.ccd-sv__btn .ccd-sv__btn-text{font-family:Georgia,"Times New Roman",serif;font-style:italic;font-weight:700;font-size:clamp(1.2rem,2.4vw,1.45rem);line-height:1.2;color:#fff!important}
@media (max-width:900px){.ccd-sv__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:600px){
.ccd-sv{padding-top:1.5rem}
.ccd-sv__grid{grid-template-columns:minmax(0,1fr);max-width:26rem;margin-left:auto;margin-right:auto}
.ccd-sv a.ccd-sv__btn{width:100%;max-width:26rem;padding:1.05rem 1.5rem}
}
@media (prefers-reduced-motion:reduce){.ccd-sv__btn{transition:none}.ccd-sv__btn:hover,.ccd-sv__btn:focus-visible{transform:none}}
</style>
		<?php
	},
	30
);
