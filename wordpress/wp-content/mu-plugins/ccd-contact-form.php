<?php
/**
 * Plugin Name: CCD Contact Form
 * Description: Layout "Fale comigo" + identidade visual do WPForms de contato.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCD_CONTACT_PAGE_ID = 1442;
const CCD_CONTACT_FORM_ID = 2765;
const CCD_CONTACT_EMAIL   = 'beatrizlibonati@gmail.com';
const CCD_CONTACT_MSG_MAX = 1500;

/**
 * @return bool
 */
function ccd_is_contact_page() {
	return is_page( 'contato' ) || is_page( CCD_CONTACT_PAGE_ID );
}

/**
 * @return array<string, array{label:string,value:string,image:string}>
 */
function ccd_contact_subject_choices() {
	$labels = array( 'Dúvidas', 'Sugestões', 'Parcerias', 'Outro' );
	$out    = array();
	$i      = 1;
	foreach ( $labels as $label ) {
		$out[ (string) $i ] = array(
			'label' => $label,
			'value' => $label,
			'image' => '',
		);
		++$i;
	}
	return $out;
}

add_filter(
	'wpforms_frontend_form_data',
	static function ( $form_data ) {
		if ( (int) ( $form_data['id'] ?? 0 ) !== CCD_CONTACT_FORM_ID ) {
			return $form_data;
		}

		$fields      = &$form_data['fields'];
		$assunto_key = isset( $fields[4] ) ? 4 : ( isset( $fields['4'] ) ? '4' : null );
		$msg_key     = isset( $fields[2] ) ? 2 : ( isset( $fields['2'] ) ? '2' : null );
		$name_key    = isset( $fields[0] ) ? 0 : ( isset( $fields['0'] ) ? '0' : null );
		$email_key   = isset( $fields[3] ) ? 3 : ( isset( $fields['3'] ) ? '3' : null );

		if ( $assunto_key !== null ) {
			$fields[ $assunto_key ]['type']        = 'select';
			$fields[ $assunto_key ]['required']    = '1';
			$fields[ $assunto_key ]['size']        = 'large';
			$fields[ $assunto_key ]['placeholder'] = 'Assunto *';
			$fields[ $assunto_key ]['choices']     = ccd_contact_subject_choices();
		}
		if ( $msg_key !== null ) {
			$fields[ $msg_key ]['limit_enabled'] = '1';
			$fields[ $msg_key ]['limit_count']   = (string) CCD_CONTACT_MSG_MAX;
			$fields[ $msg_key ]['limit_mode']    = 'characters';
			$fields[ $msg_key ]['placeholder']   = 'Sua mensagem *';
		}
		if ( $name_key !== null ) {
			$fields[ $name_key ]['placeholder'] = 'Seu nome *';
			if ( isset( $fields[ $name_key ]['simple_placeholder'] ) ) {
				$fields[ $name_key ]['simple_placeholder'] = 'Seu nome *';
			}
		}
		if ( $email_key !== null ) {
			$fields[ $email_key ]['placeholder'] = 'Seu e-mail *';
		}

		return $form_data;
	},
	20
);

/**
 * @return string
 */
function ccd_contact_render_layout() {
	$email = CCD_CONTACT_EMAIL;
	$form  = do_shortcode( '[wpforms id="' . CCD_CONTACT_FORM_ID . '" title="false" description="false"]' );

	ob_start();
	?>
	<div class="ccd-contact">
		<div class="ccd-contact__grid">
			<aside class="ccd-contact__info" aria-labelledby="ccd-contact-info-title">
				<p class="ccd-contact__eyebrow"><span>Fale comigo</span></p>
				<h2 id="ccd-contact-info-title" class="ccd-contact__title">Vamos conversar?</h2>
				<p class="ccd-contact__lead">
					Você pode preencher o formulário ao lado ou entrar em contato diretamente pelo e-mail.
					Será um prazer receber sua mensagem!
				</p>

				<a class="ccd-contact__email" href="<?php echo esc_url( 'mailto:' . $email ); ?>">
					<span class="ccd-contact__email-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
					</span>
					<span class="ccd-contact__email-copy">
						<strong>E-mail</strong>
						<span class="ccd-contact__email-address"><?php echo esc_html( $email ); ?></span>
					</span>
				</a>

				<ul class="ccd-contact__topics">
					<li class="ccd-contact__topic ccd-contact__topic--duvidas">
						<span class="ccd-contact__topic-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
						</span>
						<strong>Dúvidas</strong>
						<span>Perguntas sobre conteúdo, diabetes e estilo de vida.</span>
					</li>
					<li class="ccd-contact__topic ccd-contact__topic--sugestoes">
						<span class="ccd-contact__topic-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12c.5.5 1 1.5 1 2h6c0-.5.5-1.5 1-2a7 7 0 0 0-4-12z"/></svg>
						</span>
						<strong>Sugestões</strong>
						<span>Ideias e temas que você gostaria de ver por aqui.</span>
					</li>
					<li class="ccd-contact__topic ccd-contact__topic--parcerias">
						<span class="ccd-contact__topic-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M8 14c-1.5 0-4-.5-4-3a3 3 0 0 1 3-3c2 0 3 2 5 2s3-2 5-2a3 3 0 0 1 3 3c0 2.5-2.5 3-4 3"/><path d="M12 14v3"/><path d="M9 21h6"/></svg>
						</span>
						<strong>Parcerias</strong>
						<span>Propostas de colaboração e projetos.</span>
					</li>
				</ul>
			</aside>

			<section class="ccd-contact__card" aria-labelledby="ccd-contact-form-title">
				<header class="ccd-contact__card-head">
					<span class="ccd-contact__card-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>
					</span>
					<div class="ccd-contact__card-head-text">
						<h2 id="ccd-contact-form-title" class="ccd-contact__card-title">Envie sua mensagem</h2>
						<p class="ccd-contact__card-sub">Preencha o formulário abaixo e eu entro em contato em breve.</p>
					</div>
				</header>

				<?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<p class="ccd-contact__privacy">
					<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
					<span>Seus dados estão seguros e serão usados apenas para responder sua mensagem.</span>
				</p>
			</section>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

add_shortcode(
	'ccd_contact',
	static function () {
		return ccd_contact_render_layout();
	}
);

add_action(
	'wp',
	static function () {
		if ( ! ccd_is_contact_page() ) {
			return;
		}
		remove_filter( 'the_content', 'wpautop' );
		remove_filter( 'the_content', 'shortcode_unautop' );
	},
	20
);

add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! ccd_is_contact_page() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		static $done = false;
		if ( $done ) {
			return '';
		}
		$done = true;
		return ccd_contact_render_layout();
	},
	12
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! ccd_is_contact_page() ) {
			return;
		}

		$msg_max = (string) CCD_CONTACT_MSG_MAX;

		$css = <<<CSS
.page-id-1442 {
	--ccd-c-blue: #2196f3;
	--ccd-c-blue-deep: #1565c0;
	--ccd-c-blue-soft: #e3f2fd;
	--ccd-c-blue-wash: #f3f9fd;
	--ccd-c-ink: #1a2a40;
	--ccd-c-muted: #5f7380;
	--ccd-c-line: #d7e4ee;
	--ccd-c-surface: #ffffff;
	--ccd-c-green: #66bb6a;
	--ccd-c-green-soft: #e8f5e9;
	--ccd-c-pink: #ec407a;
	--ccd-c-pink-soft: #fce4ec;
}

.page-id-1442 #page-content,
.page-id-1442 .page-content {
	background: var(--ccd-c-blue-wash) !important;
	padding-bottom: 4rem !important;
}
.page-id-1442 .gridContainer.content,
.page-id-1442 .content.gridContainer {
	max-width: 1140px !important;
	width: 100% !important;
	margin: 0 auto !important;
	padding: 1.75rem 1.25rem 2.5rem !important;
	background: transparent !important;
	box-shadow: none !important;
	border: 0 !important;
}
.page-id-1442 .post-1442,
.page-id-1442 .post-1442 > div,
.page-id-1442 article,
.page-id-1442 .entry-content {
	background: transparent !important;
	box-shadow: none !important;
	border: 0 !important;
	padding: 0 !important;
	margin: 0 !important;
	max-width: none !important;
}

.ccd-contact {
	position: relative;
	margin: 0 auto;
}
.ccd-contact svg {
	display: block;
	flex: 0 0 auto;
}

.ccd-contact__grid {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	grid-template-areas: "info form";
	gap: 2.5rem 3rem;
	align-items: start;
}

/* ---- Info (esquerda) ---- */
.ccd-contact__info {
	grid-area: info;
	min-width: 0;
	padding-top: 0.35rem;
}

.ccd-contact__eyebrow {
	display: flex;
	align-items: center;
	gap: 0.65rem;
	margin: 0 0 0.85rem;
	color: var(--ccd-c-blue);
	font-size: 0.78rem;
	font-weight: 700;
	letter-spacing: 0.14em;
	text-transform: uppercase;
	line-height: 1;
}
.ccd-contact__eyebrow::after {
	content: "";
	display: block;
	width: 40px;
	height: 2px;
	border-radius: 999px;
	background: var(--ccd-c-blue);
}

.ccd-contact__title {
	margin: 0 0 0.85rem;
	color: var(--ccd-c-ink);
	font-size: clamp(1.9rem, 3.2vw, 2.5rem);
	font-weight: 800;
	line-height: 1.15;
	letter-spacing: -0.02em;
}

.ccd-contact__lead {
	margin: 0 0 1.35rem;
	max-width: 32rem;
	color: var(--ccd-c-muted);
	font-size: 1.05rem;
	line-height: 1.65;
}

.ccd-contact__email {
	display: flex;
	align-items: center;
	gap: 0.9rem;
	margin: 0 0 1.75rem;
	padding: 0.95rem 1.1rem;
	background: #eaf5fc;
	border: 1px solid rgba(33, 150, 243, 0.14);
	border-radius: 16px;
	text-decoration: none !important;
	transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.ccd-contact__email:hover,
.ccd-contact__email:focus {
	border-color: rgba(33, 150, 243, 0.35);
	box-shadow: 0 8px 20px rgba(33, 150, 243, 0.1);
}
.ccd-contact__email-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 46px;
	width: 46px;
	height: 46px;
	border-radius: 50%;
	background: var(--ccd-c-blue);
	color: #fff;
}
.ccd-contact__email-copy {
	display: flex;
	flex-direction: column;
	justify-content: center;
	gap: 0.12rem;
	min-width: 0;
	line-height: 1.25;
}
.ccd-contact__email-copy strong {
	color: var(--ccd-c-ink);
	font-size: 0.95rem;
	font-weight: 700;
}
.ccd-contact__email-address {
	color: var(--ccd-c-blue);
	font-weight: 600;
	font-size: 1rem;
	text-decoration: underline;
	text-underline-offset: 2px;
	word-break: break-word;
}

/* 3 tópicos em linha: ícone acima do texto */
.ccd-contact__topics {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 1.25rem 1.1rem;
	margin: 0;
	padding: 0;
	list-style: none;
}
/* Clearfix do tema vira item fantasma no CSS grid */
.ccd-contact__topics::before,
.ccd-contact__topics::after,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field-container::before,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field-container::after {
	content: none !important;
	display: none !important;
}
.ccd-contact__topic {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 0.55rem;
	margin: 0;
	padding: 0;
}
.ccd-contact__topic-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 48px;
	height: 48px;
	border-radius: 50%;
}
.ccd-contact__topic--duvidas .ccd-contact__topic-icon {
	background: var(--ccd-c-blue-soft);
	color: var(--ccd-c-blue);
}
.ccd-contact__topic--sugestoes .ccd-contact__topic-icon {
	background: var(--ccd-c-green-soft);
	color: var(--ccd-c-green);
}
.ccd-contact__topic--parcerias .ccd-contact__topic-icon {
	background: var(--ccd-c-pink-soft);
	color: var(--ccd-c-pink);
}
.ccd-contact__topic strong {
	display: block;
	margin: 0;
	color: var(--ccd-c-ink);
	font-size: 1rem;
	font-weight: 700;
	line-height: 1.25;
}
.ccd-contact__topic > span:last-child {
	display: block;
	margin: 0;
	color: var(--ccd-c-muted);
	font-size: 0.88rem;
	line-height: 1.45;
}

/* ---- Card formulário (direita) ---- */
.ccd-contact__card {
	grid-area: form;
	min-width: 0;
	background: var(--ccd-c-surface);
	border: 1px solid rgba(26, 42, 64, 0.05);
	border-radius: 24px;
	box-shadow: 0 18px 44px rgba(26, 42, 64, 0.08);
	padding: 1.6rem 1.5rem 1.35rem;
	-webkit-user-select: none;
	user-select: none;
	caret-color: transparent;
}

.ccd-contact__card-head {
	display: flex;
	align-items: center;
	gap: 0.9rem;
	margin: 0 0 1.25rem;
}
.ccd-contact__card-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 48px;
	width: 48px;
	height: 48px;
	border-radius: 50%;
	background: var(--ccd-c-blue-soft);
	color: var(--ccd-c-blue);
}
.ccd-contact__card-head-text {
	min-width: 0;
}
.ccd-contact__card-title {
	margin: 0 0 0.2rem;
	color: var(--ccd-c-ink);
	font-size: 1.28rem;
	font-weight: 800;
	line-height: 1.25;
}
.ccd-contact__card-sub {
	margin: 0;
	color: var(--ccd-c-muted);
	font-size: 0.92rem;
	line-height: 1.45;
}

.ccd-contact__privacy {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 0.4rem;
	margin: 1rem 0 0;
	color: #7a8d9c;
	font-size: 0.78rem;
	line-height: 1.4;
	text-align: center;
}

/* WPForms */
.ccd-contact div.wpforms-container-full#wpforms-2765 {
	max-width: none !important;
	margin: 0 !important;
	padding: 0 !important;
	background: transparent !important;
	border: 0 !important;
	box-shadow: none !important;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field-container {
	display: flex !important;
	flex-wrap: wrap;
	gap: 0.85rem;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field {
	padding: 0 !important;
	margin: 0 !important;
	float: none !important;
	clear: none !important;
	width: 100% !important;
	max-width: none !important;
	min-width: 0 !important;
	position: relative;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field.wpforms-field-name,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field.wpforms-field-email {
	flex: 1 1 200px !important;
	width: auto !important;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field-select,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field-text,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field-textarea,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field-hp {
	flex: 1 1 100%;
}

.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-label-hide,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field-label {
	position: absolute !important;
	width: 1px !important;
	height: 1px !important;
	padding: 0 !important;
	margin: -1px !important;
	overflow: hidden !important;
	clip: rect(0, 0, 0, 0) !important;
	border: 0 !important;
	pointer-events: none !important;
}

.ccd-contact .ccd-field-wrap {
	position: relative !important;
	display: block !important;
	width: 100% !important;
}
.ccd-contact .ccd-field-icon {
	position: absolute !important;
	left: 0.9rem !important;
	top: 50% !important;
	transform: translateY(-50%) !important;
	width: 18px !important;
	height: 18px !important;
	margin: 0 !important;
	padding: 0 !important;
	z-index: 3 !important;
	pointer-events: none !important;
	color: #7a90a0 !important;
	display: flex !important;
	align-items: center !important;
	justify-content: center !important;
	line-height: 0 !important;
}
.ccd-contact .ccd-field-icon svg {
	width: 18px !important;
	height: 18px !important;
	display: block !important;
}
.ccd-contact .wpforms-field-textarea .ccd-field-icon {
	top: 1rem !important;
	transform: none !important;
}

.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field input[type="text"],
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field input[type="email"],
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field select,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field textarea,
.ccd-contact div.wpforms-container-full#wpforms-2765 input.wpforms-field-medium,
.ccd-contact div.wpforms-container-full#wpforms-2765 input.wpforms-field-large,
.ccd-contact div.wpforms-container-full#wpforms-2765 select.wpforms-field-medium,
.ccd-contact div.wpforms-container-full#wpforms-2765 select.wpforms-field-large,
.ccd-contact div.wpforms-container-full#wpforms-2765 textarea.wpforms-field-medium,
.ccd-contact div.wpforms-container-full#wpforms-2765 textarea.wpforms-field-large {
	width: 100% !important;
	max-width: 100% !important;
	min-height: 48px !important;
	background: #f7fafc !important;
	border: 1.5px solid var(--ccd-c-line) !important;
	border-radius: 14px !important;
	color: var(--ccd-c-ink) !important;
	font-size: 0.98rem !important;
	line-height: 1.45 !important;
	padding: 0.75rem 1rem 0.75rem 2.65rem !important;
	box-shadow: none !important;
	float: none !important;
	display: block !important;
	-webkit-user-select: text;
	user-select: text;
	caret-color: auto;
	appearance: none;
	box-sizing: border-box !important;
	scroll-margin-top: 130px;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field textarea {
	min-height: 150px !important;
	resize: vertical;
	padding-top: 0.85rem !important;
	padding-bottom: 1.75rem !important;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field select {
	background-color: #f7fafc !important;
	background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%235a6f82' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") !important;
	background-repeat: no-repeat !important;
	background-position: right 1rem center !important;
	padding-right: 2.4rem !important;
	cursor: pointer;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field input::placeholder,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field textarea::placeholder {
	color: #8aa0ad !important;
	opacity: 1;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field input:focus,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field select:focus,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field textarea:focus {
	outline: none !important;
	border-color: var(--ccd-c-blue) !important;
	background: #fff !important;
	box-shadow: 0 0 0 4px rgba(33, 150, 243, 0.14) !important;
}

.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field-limit-text,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-character-limit {
	display: none !important;
}
.ccd-contact .ccd-contact__charcount {
	position: absolute;
	right: 0.9rem;
	bottom: 0.65rem;
	margin: 0;
	color: #8aa0ad;
	font-size: 0.75rem;
	line-height: 1;
	z-index: 4;
	pointer-events: none;
}

.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-submit-container {
	flex: 1 1 100%;
	padding: 0.2rem 0 0 !important;
	margin: 0 !important;
	width: 100% !important;
	float: none !important;
	clear: none !important;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 button.wpforms-submit,
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-submit {
	display: inline-flex !important;
	align-items: center;
	justify-content: center;
	gap: 0.55rem;
	width: 100% !important;
	margin: 0 !important;
	padding: 0.95rem 1.2rem !important;
	border: 0 !important;
	border-radius: 14px !important;
	background: var(--ccd-c-blue) !important;
	color: #fff !important;
	font-size: 1rem !important;
	font-weight: 700 !important;
	line-height: 1.2 !important;
	box-shadow: 0 12px 24px rgba(33, 150, 243, 0.28) !important;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 button.wpforms-submit::before {
	content: "";
	width: 18px;
	height: 18px;
	background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m22 2-7 20-4-9-9-4z'/%3E%3Cpath d='M22 2 11 13'/%3E%3C/svg%3E") center / contain no-repeat;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 button.wpforms-submit:hover {
	background: var(--ccd-c-blue-deep) !important;
}
.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-error,
.ccd-contact div.wpforms-container-full#wpforms-2765 label.wpforms-error {
	color: #d32f2f !important;
	font-size: 0.8rem !important;
}

@media (max-width: 960px) {
	.ccd-contact__grid {
		grid-template-columns: 1fr;
		grid-template-areas:
			"info"
			"form";
		gap: 1.75rem;
	}
	.ccd-contact__topics {
		grid-template-columns: 1fr;
		gap: 1.1rem;
	}
	.ccd-contact__topic {
		flex-direction: row;
		align-items: flex-start;
		gap: 0.8rem;
	}
	.ccd-contact__topic-icon {
		flex: 0 0 48px;
	}
}

@media (max-width: 600px) {
	.page-id-1442 .gridContainer.content,
	.page-id-1442 .content.gridContainer {
		padding-left: 0.85rem !important;
		padding-right: 0.85rem !important;
	}
	.ccd-contact__card {
		padding: 1.2rem 1rem 1.1rem;
		border-radius: 20px;
	}
	.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field.wpforms-field-name,
	.ccd-contact div.wpforms-container-full#wpforms-2765 .wpforms-field.wpforms-field-email {
		flex: 0 0 100% !important;
		width: 100% !important;
	}
}
CSS;

		wp_register_style( 'ccd-contact-form', false, array(), '3.0.1' );
		wp_enqueue_style( 'ccd-contact-form' );
		wp_add_inline_style( 'ccd-contact-form', $css );

		wp_register_script( 'ccd-contact-form', false, array(), '3.0.1', true );
		wp_enqueue_script( 'ccd-contact-form' );
		wp_add_inline_script(
			'ccd-contact-form',
			'(function(){var root=document.querySelector(".ccd-contact");if(!root)return;var form=root.querySelector("#wpforms-2765");if(!form)return;var MAX=' . $msg_max . ';' .
			<<<'JS'
var ICONS={name:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>',email:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',select:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h6"/></svg>',textarea:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'};
function iconFor(field){if(field.classList.contains("wpforms-field-name"))return ICONS.name;if(field.classList.contains("wpforms-field-email"))return ICONS.email;if(field.classList.contains("wpforms-field-select")||field.classList.contains("wpforms-field-text"))return ICONS.select;if(field.classList.contains("wpforms-field-textarea"))return ICONS.textarea;return ICONS.select;}
form.querySelectorAll(".wpforms-field").forEach(function(field){var control=field.querySelector('input:not([type="hidden"]), select, textarea');if(!control||field.querySelector(".ccd-field-wrap"))return;var wrap=document.createElement("div");wrap.className="ccd-field-wrap";wrap.style.position="relative";wrap.style.display="block";wrap.style.width="100%";control.parentNode.insertBefore(wrap,control);wrap.appendChild(control);var icon=document.createElement("span");icon.className="ccd-field-icon";icon.setAttribute("aria-hidden","true");icon.innerHTML=iconFor(field);icon.style.position="absolute";icon.style.left="0.9rem";icon.style.width="18px";icon.style.height="18px";icon.style.zIndex="3";icon.style.pointerEvents="none";icon.style.display="flex";icon.style.alignItems="center";icon.style.justifyContent="center";icon.style.color="#7a90a0";if(field.classList.contains("wpforms-field-textarea")){icon.style.top="1rem";icon.style.transform="none";}else{icon.style.top="50%";icon.style.transform="translateY(-50%)";}wrap.insertBefore(icon,control);});
function navHeight(){var nav=document.querySelector(".navigation-bar.fixto-fixed")||document.querySelector(".navigation-bar");return nav?Math.ceil(nav.getBoundingClientRect().height):120;}
function keepFieldBelowNav(el){if(!el||!el.getBoundingClientRect)return;var top=el.getBoundingClientRect().top;var pad=navHeight()+16;if(top<pad)window.scrollBy(0,top-pad);}
form.querySelectorAll('input:not([type="hidden"]), textarea, select').forEach(function(el){el.addEventListener("focus",function(){keepFieldBelowNav(el);requestAnimationFrame(function(){keepFieldBelowNav(el);});});});
form.addEventListener("mousedown",function(event){var t=event.target;if(!t)return;if(t.matches("input, textarea, button, label, select, a"))return;if(t.closest("input, textarea, button, label, select, a"))return;event.preventDefault();if(document.activeElement&&form.contains(document.activeElement))document.activeElement.blur();},true);
var ta=form.querySelector("textarea");if(ta){var wrap=ta.closest(".wpforms-field");if(wrap&&!wrap.querySelector(".ccd-contact__charcount")){var counter=document.createElement("span");counter.className="ccd-contact__charcount";counter.setAttribute("aria-live","polite");wrap.appendChild(counter);function sync(){var n=(ta.value||"").length;if(n>MAX){ta.value=ta.value.slice(0,MAX);n=MAX;}counter.textContent=n+" / "+MAX;}ta.setAttribute("maxlength",String(MAX));ta.addEventListener("input",sync);sync();}}
form.querySelectorAll(".wpforms-one-half, .wpforms-first").forEach(function(el){el.classList.remove("wpforms-one-half","wpforms-first");});
})();
JS
		);
	},
	30
);
