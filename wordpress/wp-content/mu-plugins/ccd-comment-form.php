<?php
/**
 * Plugin Name: CCD Comment Form
 * Description: Formulario de comentarios com validacao em tempo real, sem campo de site e botao liberado so com tudo valido.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove o campo Website/URL do formulario.
 *
 * @param array<string, string> $fields Fields.
 * @return array<string, string>
 */
function ccd_comment_form_remove_url_field( $fields ) {
	unset( $fields['url'] );
	return $fields;
}
add_filter( 'comment_form_default_fields', 'ccd_comment_form_remove_url_field' );
add_filter( 'comment_form_fields', 'ccd_comment_form_remove_url_field' );

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
		$cookies   = isset( $defaults['fields']['cookies'] ) ? $defaults['fields']['cookies'] : '';

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

		if ( $cookies !== '' ) {
			$defaults['fields']['cookies'] = $cookies;
		}

		$defaults['comment_field'] = '<p class="comment-form-comment ccd-comment-field"><label for="comment">Comentário <span class="required">*</span></label> <textarea id="comment" name="comment" cols="45" rows="6" maxlength="65525" required></textarea><span class="ccd-comment-error" data-for="comment" hidden></span></p>';

		$defaults['submit_button'] = '<input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s" disabled aria-disabled="true" />';
		$defaults['submit_field']  = '<p class="form-submit ccd-comment-submit">%1$s %2$s<span class="ccd-comment-hint">Preencha os campos e o captcha para publicar.</span></p>';

		return $defaults;
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_singular() || ! comments_open() ) {
			return;
		}

		$handle = 'ccd-comment-form';
		wp_register_style( $handle, false, array(), '1.0.0' );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			<<<'CSS'
.ccd-comment-field { position: relative; margin-bottom: 1rem; }
.ccd-comment-field label { display: block; margin-bottom: 0.35rem; font-weight: 600; color: #2b3a42; }
.ccd-comment-field .required { color: #d32f2f; }
.ccd-comment-field input[type="text"],
.ccd-comment-field input[type="email"],
.ccd-comment-field textarea {
	width: 100%;
	max-width: 100%;
	box-sizing: border-box;
	border: 1px solid #c5d3da;
	border-radius: 10px;
	padding: 0.7rem 0.85rem;
	font-size: 1rem;
	line-height: 1.45;
	color: #2b3a42;
	background: #fff;
	transition: border-color .15s ease, box-shadow .15s ease;
}
.ccd-comment-field textarea { min-height: 140px; resize: vertical; }
.ccd-comment-field input:focus,
.ccd-comment-field textarea:focus {
	outline: none;
	border-color: #03a9f4;
	box-shadow: 0 0 0 3px rgba(3, 169, 244, 0.18);
}
.ccd-comment-field.is-invalid input,
.ccd-comment-field.is-invalid textarea {
	border-color: #d32f2f;
	box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.12);
}
.ccd-comment-field.is-valid input,
.ccd-comment-field.is-valid textarea {
	border-color: #2e7d32;
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
.ccd-comment-submit #submit[disabled],
.ccd-comment-submit input[type="submit"][disabled],
.ccd-comment-submit button[type="submit"][disabled] {
	opacity: 0.45;
	cursor: not-allowed;
	filter: grayscale(0.2);
}
.ccd-comment-hint {
	display: block;
	margin-top: 0.5rem;
	font-size: 0.85rem;
	color: #5f7380;
}
.ccd-comment-submit.is-ready .ccd-comment-hint { display: none; }
.ccd-recaptcha-field.is-invalid .g-recaptcha {
	outline: 2px solid #d32f2f;
	outline-offset: 4px;
	border-radius: 4px;
}
CSS
		);

		wp_register_script( $handle, false, array(), '1.0.0', true );
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
