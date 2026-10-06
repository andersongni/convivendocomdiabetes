<?php
/**
 * Plugin Name: CCD Contact Form
 * Description: Identidade visual do formulario de contato (WPForms) alinhada a marca.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_page( 'contato' ) && ! is_page( 1442 ) ) {
			return;
		}

		$css = <<<'CSS'
:root {
	--ccd-blue: #03a9f4;
	--ccd-blue-deep: #0288d1;
	--ccd-blue-soft: #e8f7fd;
	--ccd-blue-line: #7ecff7;
	--ccd-ink: #2b3a42;
	--ccd-muted: #5f7380;
	--ccd-surface: #ffffff;
	--ccd-radius: 14px;
}

/* Intro da pagina de contato */
.page-id-1442 .page-content > p:first-of-type,
.page-id-1442 .content > p:first-of-type,
.page-id-1442 article .entry-content > p:first-of-type {
	max-width: 36rem;
	margin-left: auto;
	margin-right: auto;
	margin-bottom: 1.75rem;
	color: var(--ccd-muted);
	font-size: 1.05rem;
	line-height: 1.65;
	text-align: center;
}

.page-id-1442 .page-content > p:first-of-type a,
.page-id-1442 .content > p:first-of-type a,
.page-id-1442 article .entry-content > p:first-of-type a {
	color: var(--ccd-blue);
	font-weight: 600;
	text-decoration: none;
	border-bottom: 1px solid var(--ccd-blue-line);
}

.page-id-1442 .page-content > p:first-of-type a:hover,
.page-id-1442 .content > p:first-of-type a:hover,
.page-id-1442 article .entry-content > p:first-of-type a:hover {
	color: var(--ccd-blue-deep);
	border-bottom-color: var(--ccd-blue-deep);
}

/* Card do formulario */
div.wpforms-container-full#wpforms-2765 {
	max-width: 640px;
	margin: 0 auto 2.5rem;
	padding: 1.75rem 1.5rem 1.5rem;
	background:
		linear-gradient(180deg, var(--ccd-blue-soft) 0%, var(--ccd-surface) 42%);
	border: 1px solid rgba(3, 169, 244, 0.18);
	border-radius: 22px;
	box-shadow: 0 18px 40px rgba(3, 169, 244, 0.08);
	position: relative;
	z-index: 1;
	/* Evita caret/selecao no padding do card (fora dos inputs) */
	-webkit-user-select: none;
	user-select: none;
	caret-color: transparent;
}

div.wpforms-container-full#wpforms-2765 .wpforms-form {
	margin: 0;
}

div.wpforms-container-full#wpforms-2765 .wpforms-field {
	padding: 0 0 1rem;
	position: relative;
	z-index: 1;
}

/* Labels escondidos do WPForms nao devem roubar clique/foco */
div.wpforms-container-full#wpforms-2765 .wpforms-label-hide {
	pointer-events: none !important;
}

div.wpforms-container-full#wpforms-2765 .wpforms-field input[type="text"],
div.wpforms-container-full#wpforms-2765 .wpforms-field input[type="email"],
div.wpforms-container-full#wpforms-2765 .wpforms-field textarea {
	width: 100% !important;
	max-width: 100% !important;
	background: var(--ccd-surface) !important;
	border: 1.5px solid rgba(3, 169, 244, 0.28) !important;
	border-radius: var(--ccd-radius) !important;
	color: var(--ccd-ink) !important;
	font-size: 1rem !important;
	line-height: 1.45 !important;
	padding: 0.9rem 1.05rem !important;
	box-shadow: none !important;
	transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
	position: relative;
	z-index: 2;
	scroll-margin-top: 130px;
	-webkit-user-select: text;
	user-select: text;
	caret-color: auto;
}

div.wpforms-container-full#wpforms-2765 .wpforms-field input[type="text"],
div.wpforms-container-full#wpforms-2765 .wpforms-field input[type="email"] {
	border-radius: 999px !important;
}

div.wpforms-container-full#wpforms-2765 .wpforms-field textarea {
	min-height: 160px !important;
	resize: vertical;
	border-radius: var(--ccd-radius) !important;
}

div.wpforms-container-full#wpforms-2765 .wpforms-field input::placeholder,
div.wpforms-container-full#wpforms-2765 .wpforms-field textarea::placeholder {
	color: #8aa0ad !important;
	opacity: 1;
}

div.wpforms-container-full#wpforms-2765 .wpforms-field input:hover,
div.wpforms-container-full#wpforms-2765 .wpforms-field textarea:hover {
	border-color: var(--ccd-blue-line) !important;
}

div.wpforms-container-full#wpforms-2765 .wpforms-field input:focus,
div.wpforms-container-full#wpforms-2765 .wpforms-field textarea:focus {
	outline: none !important;
	border-color: var(--ccd-blue) !important;
	background: #fbfeff !important;
	box-shadow: 0 0 0 4px rgba(3, 169, 244, 0.16) !important;
}

div.wpforms-container-full#wpforms-2765 .wpforms-submit-container {
	padding: 0.35rem 0 0;
	text-align: center;
}

div.wpforms-container-full#wpforms-2765 button.wpforms-submit,
div.wpforms-container-full#wpforms-2765 .wpforms-submit {
	display: inline-flex !important;
	align-items: center;
	justify-content: center;
	min-width: min(100%, 260px);
	width: auto !important;
	margin: 0.25rem auto 0 !important;
	padding: 0.95rem 1.75rem !important;
	border: 0 !important;
	border-radius: 999px !important;
	background: linear-gradient(135deg, var(--ccd-blue) 0%, var(--ccd-blue-deep) 100%) !important;
	color: #fff !important;
	font-size: 1rem !important;
	font-weight: 600 !important;
	letter-spacing: 0.02em;
	line-height: 1.2 !important;
	box-shadow: 0 10px 22px rgba(3, 169, 244, 0.28) !important;
	transition: transform 0.15s ease, box-shadow 0.2s ease, filter 0.2s ease;
}

div.wpforms-container-full#wpforms-2765 button.wpforms-submit:hover,
div.wpforms-container-full#wpforms-2765 .wpforms-submit:hover {
	filter: brightness(1.03);
	transform: translateY(-1px);
	box-shadow: 0 14px 28px rgba(3, 169, 244, 0.34) !important;
}

div.wpforms-container-full#wpforms-2765 button.wpforms-submit:focus,
div.wpforms-container-full#wpforms-2765 .wpforms-submit:focus {
	outline: none !important;
	box-shadow: 0 0 0 4px rgba(3, 169, 244, 0.22), 0 10px 22px rgba(3, 169, 244, 0.28) !important;
}

div.wpforms-container-full#wpforms-2765 .wpforms-error,
div.wpforms-container-full#wpforms-2765 label.wpforms-error {
	color: #d32f2f !important;
	font-size: 0.85rem !important;
}

@media (max-width: 600px) {
	div.wpforms-container-full#wpforms-2765 {
		padding: 1.35rem 1.1rem 1.25rem;
		border-radius: 18px;
	}

	div.wpforms-container-full#wpforms-2765 .wpforms-field.wpforms-one-half {
		width: 100% !important;
		margin-left: 0 !important;
	}

	div.wpforms-container-full#wpforms-2765 button.wpforms-submit,
	div.wpforms-container-full#wpforms-2765 .wpforms-submit {
		width: 100% !important;
		min-width: 0;
	}
}
CSS;

		wp_register_style( 'ccd-contact-form', false, array(), null );
		wp_enqueue_style( 'ccd-contact-form' );
		wp_add_inline_style( 'ccd-contact-form', $css );

		wp_register_script( 'ccd-contact-form', false, array(), null, true );
		wp_enqueue_script( 'ccd-contact-form' );
		wp_add_inline_script(
			'ccd-contact-form',
			<<<'JS'
(function () {
	var form = document.getElementById('wpforms-2765');
	if (!form) return;

	function navHeight() {
		var nav = document.querySelector('.navigation-bar.fixto-fixed') || document.querySelector('.navigation-bar');
		return nav ? Math.ceil(nav.getBoundingClientRect().height) : 120;
	}

	function keepFieldBelowNav(el) {
		if (!el || !el.getBoundingClientRect) return;
		var top = el.getBoundingClientRect().top;
		var pad = navHeight() + 16;
		if (top < pad) {
			window.scrollBy(0, top - pad);
			return true;
		}
		return false;
	}

	function correctFocus(el) {
		// Browser scroll-into-view pode colocar o campo sob o menu; corrige em etapas.
		keepFieldBelowNav(el);
		requestAnimationFrame(function () {
			keepFieldBelowNav(el);
			setTimeout(function () {
				keepFieldBelowNav(el);
			}, 50);
			setTimeout(function () {
				keepFieldBelowNav(el);
			}, 150);
		});
	}

	form.querySelectorAll('input:not([type="hidden"]), textarea').forEach(function (el) {
		el.addEventListener('focus', function () {
			correctFocus(el);
		});
	});

	// Clique no padding do card nao deve focar campo nem criar caret fantasma.
	form.addEventListener(
		'mousedown',
		function (event) {
			var t = event.target;
			if (!t) return;
			if (t.matches('input, textarea, button, label, select, a')) return;
			if (t.closest('input, textarea, button, label, select, a')) return;
			event.preventDefault();
			if (document.activeElement && form.contains(document.activeElement)) {
				document.activeElement.blur();
			}
		},
		true
	);
})();
JS
		);
	},
	30
);
