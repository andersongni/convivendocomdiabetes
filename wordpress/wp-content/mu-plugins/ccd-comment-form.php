<?php
/**
 * Plugin Name: CCD Comment Form
 * Description: Formulario de comentarios com validacao em tempo real, sem campo de site e botao liberado so com tudo valido.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove Website/URL e o checkbox de cookies do formulario.
 *
 * @param array<string, string> $fields Fields.
 * @return array<string, string>
 */
function ccd_comment_form_prune_fields( $fields ) {
	unset( $fields['url'], $fields['cookies'] );
	return $fields;
}
add_filter( 'comment_form_default_fields', 'ccd_comment_form_prune_fields' );
add_filter( 'comment_form_fields', 'ccd_comment_form_prune_fields' );

add_filter(
	'wp_list_comments_args',
	static function ( $args ) {
		$args['avatar_size'] = 48;
		return $args;
	}
);

/** Nao exibe o opt-in de cookies (sempre salvamos os dados). */
add_filter( 'pre_option_show_comments_cookies_opt_in', static function () {
	return '0';
} );

/**
 * Sempre grava nome/e-mail do comentario no navegador para a proxima vez.
 */
add_action(
	'init',
	static function () {
		remove_action( 'set_comment_cookies', 'wp_set_comment_cookies', 10 );
		add_action(
			'set_comment_cookies',
			static function ( $comment, $user, $cookies_consent = true ) {
				wp_set_comment_cookies( $comment, $user, true );
			},
			10,
			3
		);
	},
	20
);

/** Ignora qualquer URL enviada no POST. */
add_filter( 'pre_comment_author_url', static function () {
	return '';
} );

/**
 * Rotulos e botao em portugues.
 *
 * @param array<string, mixed> $defaults Defaults.
 * @return array<string, mixed>
 */
add_filter(
	'comment_form_defaults',
	static function ( $defaults ) {
		$defaults['title_reply']          = 'Deixe um comentário';
		$defaults['title_reply_to']       = 'Responder a %s';
		$defaults['cancel_reply_link']    = 'Cancelar resposta';
		$defaults['label_submit']         = 'Publicar comentário';
		$defaults['comment_notes_before'] = '';
		$defaults['comment_notes_after']  = '';

		$req       = (bool) get_option( 'require_name_email' );
		$aria_req  = $req ? ' aria-required="true" required' : '';
		$html_req  = $req ? ' required' : '';
		$commenter = wp_get_current_commenter();

		$defaults['fields'] = array();

		$defaults['fields']['author'] = sprintf(
			'<p class="comment-form-author ccd-comment-field"><label for="author">Nome%s</label> <input id="author" name="author" type="text" value="%s" size="30" maxlength="245" autocomplete="name"%s /><span class="ccd-comment-error" data-for="author" hidden></span></p>',
			$req ? ' <span class="required">*</span>' : '',
			esc_attr( isset( $commenter['comment_author'] ) ? $commenter['comment_author'] : '' ),
			$aria_req . $html_req
		);

		$defaults['fields']['email'] = sprintf(
			'<p class="comment-form-email ccd-comment-field"><label for="email">E-mail%s</label> <input id="email" name="email" type="email" value="%s" size="30" maxlength="100" autocomplete="email"%s /><span class="ccd-comment-error" data-for="email" hidden></span></p>',
			$req ? ' <span class="required">*</span>' : '',
			esc_attr( isset( $commenter['comment_author_email'] ) ? $commenter['comment_author_email'] : '' ),
			$aria_req . $html_req
		);

		$defaults['comment_field'] = '<p class="comment-form-comment ccd-comment-field"><label for="comment">Comentário <span class="required">*</span></label> <textarea id="comment" name="comment" cols="45" rows="6" maxlength="65525" required></textarea><span class="ccd-comment-error" data-for="comment" hidden></span></p>';

		$defaults['submit_button'] = '<button name="%1$s" type="submit" id="%2$s" class="%3$s" disabled aria-disabled="true">%4$s</button>';
		$defaults['submit_field']  = '<p class="form-submit ccd-comment-submit">%1$s %2$s<span class="ccd-comment-hint">Preencha os campos e o captcha para publicar.</span></p>';

		return $defaults;
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_singular() || ( ! comments_open() && ! get_comments_number() ) ) {
			return;
		}

		$handle = 'ccd-comment-form';
		wp_register_style( $handle, false, array(), '1.1.1' );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			<<<'CSS'
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

/* Lista de comentarios — ritmo e identidade do site */
.post-comments {
	max-width: 36rem;
	width: 100%;
	margin: 2.25rem auto 0 !important;
	padding: 0 !important;
	box-sizing: border-box;
}
.comments-title {
	margin: 0 0 1.35rem !important;
	padding: 0 !important;
	color: var(--ccd-ink) !important;
	font-size: 1.35rem !important;
	font-weight: 700 !important;
	line-height: 1.3 !important;
	text-align: center;
}
.comment-list {
	list-style: none !important;
	margin: 0 !important;
	padding: 0 !important;
	display: flex;
	flex-direction: column;
	gap: 0.9rem;
}
.comment-list > li.comment,
.comment-list .children > li.comment {
	margin: 0 !important;
	padding: 0 !important;
	border: 0 !important;
	background: transparent !important;
	list-style: none !important;
}
.comment-list li.comment.even,
.comment-list li.comment.odd,
.comment-list li.comment.byuser,
.comment-list li.comment.bypostauthor {
	background: transparent !important;
	border: 0 !important;
}
.comment-body {
	margin: 0 !important;
	padding: 1.15rem 1.25rem 1.05rem !important;
	background: linear-gradient(180deg, #f7fcfe 0%, var(--ccd-surface) 55%);
	border: 1px solid rgba(3, 169, 244, 0.14);
	border-radius: 18px;
	box-shadow: 0 10px 28px rgba(3, 169, 244, 0.06);
}
.comment-list li.bypostauthor > .comment-body {
	background: linear-gradient(180deg, var(--ccd-blue-soft) 0%, var(--ccd-surface) 58%);
	border-color: rgba(3, 169, 244, 0.28);
	box-shadow: 0 12px 30px rgba(3, 169, 244, 0.1);
}
.comment-author {
	display: flex !important;
	align-items: center;
	gap: 0.75rem;
	margin: 0 0 0.2rem !important;
	color: var(--ccd-ink) !important;
	position: relative;
	z-index: 1;
}
.comment-author .avatar {
	position: static !important;
	left: auto !important;
	top: auto !important;
	width: 44px !important;
	height: 44px !important;
	margin: 0 !important;
	border-radius: 50% !important;
	object-fit: cover;
	flex-shrink: 0;
	box-shadow: 0 0 0 2px rgba(3, 169, 244, 0.18);
}
.comment-author .fn {
	font-family: Nunito, "Open Sans", sans-serif !important;
	font-size: 1.05rem !important;
	font-weight: 700 !important;
	font-style: normal !important;
	color: var(--ccd-ink) !important;
	line-height: 1.25 !important;
}
.comment-author .fn a {
	color: inherit !important;
	text-decoration: none !important;
}
.comment-author .says {
	display: none !important;
}
.comment-meta,
.comment-meta.commentmetadata {
	margin: 0 0 0.85rem 3.5rem !important;
	font-size: 0.875rem !important;
	line-height: 1.35 !important;
}
.comment-meta a,
.comment-meta.commentmetadata a {
	color: var(--ccd-muted) !important;
	text-decoration: none !important;
	font-weight: 500 !important;
	letter-spacing: 0 !important;
	text-transform: none !important;
}
.comment-meta a:hover,
.comment-meta a:focus {
	color: var(--ccd-blue-deep) !important;
}
.comment-body > p {
	margin: 0 0 0.75rem !important;
	color: var(--ccd-ink) !important;
	font-size: 1.0625rem !important;
	line-height: 1.65 !important;
}
.comment-body > p:last-of-type {
	margin-bottom: 0.85rem !important;
}
.comment-body .reply {
	margin: 0 !important;
	padding: 0 !important;
}
.comment-reply-link {
	display: inline-flex !important;
	align-items: center;
	gap: 0.35rem;
	margin: 0 !important;
	padding: 0.35rem 0.85rem !important;
	border-radius: 999px !important;
	background: rgba(3, 169, 244, 0.08);
	color: var(--ccd-blue-deep) !important;
	font-size: 0.875rem !important;
	font-weight: 600 !important;
	line-height: 1.2 !important;
	text-decoration: none !important;
	transition: background-color 0.15s ease, color 0.15s ease;
}
.comment-reply-link:hover,
.comment-reply-link:focus {
	background: rgba(3, 169, 244, 0.16);
	color: var(--ccd-blue-deep) !important;
}
.comment-reply-link:after {
	display: none !important;
}
.comment-list .children {
	list-style: none !important;
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	margin: 0.75rem 0 0 !important;
	padding: 0 0 0 1rem !important;
	border-left: 2px solid rgba(3, 169, 244, 0.22);
}
.comments-form {
	max-width: 36rem;
	width: 100%;
	margin: 1.75rem auto 2.5rem !important;
	padding: 0 !important;
	box-sizing: border-box;
}
.comments-form .comment-form {
	margin: 0 !important;
	padding: 0 !important;
}

/* Card alinhado ao formulario de contato */
#respond {
	max-width: 36rem;
	width: 100%;
	margin: 0 auto !important;
	padding: 1.75rem 1.5rem 1.5rem;
	background: linear-gradient(180deg, var(--ccd-blue-soft) 0%, var(--ccd-surface) 42%);
	border: 1px solid rgba(3, 169, 244, 0.18);
	border-radius: 22px;
	box-shadow: 0 18px 40px rgba(3, 169, 244, 0.08);
	scroll-margin-top: 130px;
	box-sizing: border-box;
}
#reply-title {
	margin: 0 0 1.25rem !important;
	color: var(--ccd-ink) !important;
	font-size: 1.35rem !important;
	font-weight: 600 !important;
	line-height: 1.3 !important;
	text-align: center;
}
#commentform { margin: 0; }

.ccd-comment-field { position: relative; margin-bottom: 1rem; }
.ccd-comment-field label {
	display: block;
	margin-bottom: 0.35rem;
	font-weight: 600;
	color: var(--ccd-ink);
}
.ccd-comment-field .required { color: #d32f2f; }
.ccd-comment-field input[type="text"],
.ccd-comment-field input[type="email"],
.ccd-comment-field textarea {
	width: 100%;
	max-width: 100%;
	box-sizing: border-box;
	background: var(--ccd-surface) !important;
	border: 1.5px solid rgba(3, 169, 244, 0.28) !important;
	border-radius: var(--ccd-radius) !important;
	padding: 0.9rem 1.05rem !important;
	font-size: 1rem !important;
	line-height: 1.45 !important;
	color: var(--ccd-ink) !important;
	box-shadow: none !important;
	transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
	scroll-margin-top: 130px;
}
.ccd-comment-field input[type="text"],
.ccd-comment-field input[type="email"] {
	border-radius: 999px !important;
}
.ccd-comment-field textarea {
	min-height: 160px;
	resize: vertical;
	border-radius: var(--ccd-radius) !important;
}
.ccd-comment-field input:hover,
.ccd-comment-field textarea:hover {
	border-color: var(--ccd-blue-line) !important;
}
.ccd-comment-field input:focus,
.ccd-comment-field textarea:focus {
	outline: none !important;
	border-color: var(--ccd-blue) !important;
	background: #fbfeff !important;
	box-shadow: 0 0 0 4px rgba(3, 169, 244, 0.16) !important;
}
.ccd-comment-field.is-invalid input,
.ccd-comment-field.is-invalid textarea {
	border-color: #d32f2f !important;
	box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.12) !important;
}
.ccd-comment-field.is-valid input,
.ccd-comment-field.is-valid textarea {
	border-color: #2e7d32 !important;
}
.ccd-comment-error {
	display: block;
	margin-top: 0.35rem;
	font-size: 0.875rem;
	color: #d32f2f;
}
.ccd-comment-error[hidden] { display: none !important; }
.comment-form-url,
#url { display: none !important; }
.comment-form-cookies-consent,
#wp-comment-cookies-consent {
	display: none !important;
}
.ccd-recaptcha-field { margin: 1rem 0; }

.ccd-comment-submit {
	padding: 0.35rem 0 0;
	text-align: center;
}
.ccd-comment-submit #submit,
.ccd-comment-submit input[type="submit"],
.ccd-comment-submit button[type="submit"] {
	display: inline-flex !important;
	align-items: center !important;
	justify-content: center !important;
	box-sizing: border-box !important;
	min-width: min(100%, 260px);
	min-height: 3.1rem !important;
	height: auto !important;
	width: auto !important;
	margin: 0.25rem auto 0 !important;
	padding: 0 1.75rem !important;
	border: 0 !important;
	border-radius: 999px !important;
	background: linear-gradient(135deg, var(--ccd-blue) 0%, var(--ccd-blue-deep) 100%) !important;
	color: #fff !important;
	font-family: inherit !important;
	font-size: 1rem !important;
	font-weight: 600 !important;
	letter-spacing: 0.02em;
	line-height: 1 !important;
	vertical-align: middle;
	appearance: none;
	-webkit-appearance: none;
	box-shadow: 0 10px 22px rgba(3, 169, 244, 0.28) !important;
	transition: transform 0.15s ease, box-shadow 0.2s ease, filter 0.2s ease, opacity 0.15s ease;
	cursor: pointer;
}
.ccd-comment-submit #submit:hover:not([disabled]),
.ccd-comment-submit input[type="submit"]:hover:not([disabled]),
.ccd-comment-submit button[type="submit"]:hover:not([disabled]) {
	filter: brightness(1.03);
	transform: translateY(-1px);
	box-shadow: 0 14px 28px rgba(3, 169, 244, 0.34) !important;
}
.ccd-comment-submit #submit[disabled],
.ccd-comment-submit input[type="submit"][disabled],
.ccd-comment-submit button[type="submit"][disabled] {
	opacity: 0.45;
	cursor: not-allowed;
	filter: grayscale(0.15);
	transform: none;
	box-shadow: 0 6px 14px rgba(3, 169, 244, 0.16) !important;
}
.ccd-comment-hint {
	display: block;
	margin-top: 0.65rem;
	font-size: 0.85rem;
	color: var(--ccd-muted);
	text-align: center;
}
.ccd-comment-submit.is-ready .ccd-comment-hint { display: none; }
.ccd-recaptcha-field.is-invalid .g-recaptcha {
	outline: 2px solid #d32f2f;
	outline-offset: 4px;
	border-radius: 4px;
}
@media (max-width: 600px) {
	.post-comments {
		margin-top: 1.75rem !important;
	}
	.comment-body {
		padding: 1rem 1.05rem 0.95rem !important;
		border-radius: 16px;
	}
	.comment-author .avatar {
		width: 40px !important;
		height: 40px !important;
	}
	.comment-meta,
	.comment-meta.commentmetadata {
		margin-left: 3.15rem !important;
	}
	.comment-list .children {
		padding-left: 0.75rem !important;
	}
	#respond {
		padding: 1.35rem 1.1rem 1.25rem;
		border-radius: 18px;
	}
	.ccd-comment-submit #submit,
	.ccd-comment-submit input[type="submit"],
	.ccd-comment-submit button[type="submit"] {
		width: 100% !important;
		min-width: 0;
	}
}
CSS
		);

		wp_register_script( $handle, false, array(), '1.1.1', true );
		wp_enqueue_script( $handle );

		$require_name_email = (bool) get_option( 'require_name_email' );
		$needs_captcha      = function_exists( 'ccd_recaptcha_is_configured' )
			&& ccd_recaptcha_is_configured()
			&& function_exists( 'ccd_recaptcha_user_bypasses' )
			&& ! ccd_recaptcha_user_bypasses();

		wp_add_inline_script(
			$handle,
			'window.ccdCommentForm = ' . wp_json_encode(
				array(
					'requireNameEmail' => $require_name_email,
					'needsCaptcha'     => $needs_captcha,
					'messages'         => array(
						'comment' => 'Escreva um comentário.',
						'author'  => 'Informe seu nome.',
						'email'   => 'Informe um e-mail válido.',
						'captcha' => 'Marque “Não sou um robô”.',
					),
				)
			) . ';',
			'before'
		);

		wp_add_inline_script(
			$handle,
			<<<'JS'
(function () {
	var cfg = window.ccdCommentForm || {};
	var form = document.getElementById('commentform');
	if (!form) return;

	var comment = form.querySelector('#comment');
	var author = form.querySelector('#author');
	var email = form.querySelector('#email');
	var url = form.querySelector('#url');
	var submit = form.querySelector('#submit') || form.querySelector('input[type="submit"], button[type="submit"]');
	var submitWrap = form.querySelector('.ccd-comment-submit') || (submit && submit.parentElement);
	var captchaWrap = form.querySelector('.ccd-recaptcha-field');
	var needsCaptcha = !!cfg.needsCaptcha && !!form.querySelector('.g-recaptcha');
	var requireNameEmail = !!cfg.requireNameEmail;
	var messages = cfg.messages || {};
	var captchaOk = !needsCaptcha;
	var touched = {};

	if (url) {
		var urlRow = url.closest('.comment-form-url') || url.parentElement;
		if (urlRow) urlRow.remove();
		else url.remove();
	}

	function fieldWrap(el) {
		return el ? el.closest('.ccd-comment-field') || el.parentElement : null;
	}

	function errorEl(name) {
		return form.querySelector('.ccd-comment-error[data-for="' + name + '"]');
	}

	function setError(name, el, message) {
		var wrap = fieldWrap(el);
		var err = errorEl(name);
		if (wrap) {
			wrap.classList.toggle('is-invalid', !!message);
			wrap.classList.toggle('is-valid', !message && el && String(el.value || '').trim() !== '');
		}
		if (err) {
			if (message) {
				err.textContent = message;
				err.hidden = false;
			} else {
				err.textContent = '';
				err.hidden = true;
			}
		}
	}

	function isEmail(value) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
	}

	function validateComment(show) {
		if (!comment) return true;
		var ok = String(comment.value || '').trim().length > 0;
		if (show || touched.comment) {
			setError('comment', comment, ok ? '' : (messages.comment || 'Escreva um comentário.'));
		}
		return ok;
	}

	function validateAuthor(show) {
		if (!author) return true;
		if (!requireNameEmail && String(author.value || '').trim() === '') {
			if (show || touched.author) setError('author', author, '');
			return true;
		}
		var ok = String(author.value || '').trim().length >= 2;
		if (show || touched.author) {
			setError('author', author, ok ? '' : (messages.author || 'Informe seu nome.'));
		}
		return ok;
	}

	function validateEmailField(show) {
		if (!email) return true;
		var value = String(email.value || '').trim();
		if (!requireNameEmail && value === '') {
			if (show || touched.email) setError('email', email, '');
			return true;
		}
		var ok = isEmail(value);
		if (show || touched.email) {
			setError('email', email, ok ? '' : (messages.email || 'Informe um e-mail válido.'));
		}
		return ok;
	}

	function validateCaptcha(show) {
		if (!needsCaptcha) return true;
		if (show && captchaWrap) {
			captchaWrap.classList.toggle('is-invalid', !captchaOk);
		}
		return captchaOk;
	}

	function formOk() {
		return validateComment(false) && validateAuthor(false) && validateEmailField(false) && validateCaptcha(false);
	}

	function updateSubmit() {
		var ok = formOk();
		if (submit) {
			submit.disabled = !ok;
			submit.setAttribute('aria-disabled', ok ? 'false' : 'true');
		}
		if (submitWrap) {
			submitWrap.classList.toggle('is-ready', ok);
		}
	}

	function onBlur(name, el, validator) {
		if (!el) return;
		el.addEventListener('blur', function () {
			touched[name] = true;
			validator(true);
			updateSubmit();
		});
		el.addEventListener('input', function () {
			if (touched[name]) validator(true);
			updateSubmit();
		});
	}

	onBlur('comment', comment, validateComment);
	onBlur('author', author, validateAuthor);
	onBlur('email', email, validateEmailField);

	window.ccdOnRecaptchaSuccess = function () {
		captchaOk = true;
		if (captchaWrap) captchaWrap.classList.remove('is-invalid');
		updateSubmit();
	};
	window.ccdOnRecaptchaExpired = function () {
		captchaOk = false;
		updateSubmit();
	};
	window.ccdOnRecaptchaError = function () {
		captchaOk = false;
		updateSubmit();
	};

	form.addEventListener('submit', function (event) {
		touched.comment = true;
		touched.author = true;
		touched.email = true;
		var ok =
			validateComment(true) &&
			validateAuthor(true) &&
			validateEmailField(true) &&
			validateCaptcha(true);
		if (!ok) {
			event.preventDefault();
			updateSubmit();
		}
	});

	updateSubmit();
})();
JS
		);
	},
	20
);
