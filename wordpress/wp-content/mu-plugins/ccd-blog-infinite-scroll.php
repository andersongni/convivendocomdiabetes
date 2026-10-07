<?php
/**
 * Plugin Name: CCD Blog Infinite Scroll
 * Description: No /blog/ e arquivos de categoria, carrega posts no scroll em vez de paginacao numerada.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blog index (pagina de posts) ou arquivo de categoria — nao a home estatica.
 */
function ccd_blog_infinite_is_target() {
	if ( is_home() && ! is_front_page() ) {
		return true;
	}
	return is_category();
}

/**
 * Texto de fim conforme o contexto da listagem.
 *
 * @return string
 */
function ccd_blog_infinite_end_message() {
	if ( is_category() ) {
		return 'Você chegou ao fim desta categoria.';
	}
	return 'Você chegou ao fim do blog.';
}

/**
 * Estado serializado para boot (soft-nav le do documento fetchado).
 */
function ccd_blog_infinite_state() {
	if ( ! ccd_blog_infinite_is_target() ) {
		return array(
			'active'  => false,
			'nextUrl' => '',
			'hasMore' => false,
			'loading' => 'Carregando mais posts…',
			'end'     => 'Você chegou ao fim do blog.',
			'error'   => 'Não foi possível carregar mais posts. Tente novamente.',
		);
	}

	global $wp_query;
	$max_pages = (int) $wp_query->max_num_pages;
	$current   = max( 1, (int) get_query_var( 'paged' ) );
	$next_link = '';
	if ( $current < $max_pages ) {
		$next_link = (string) get_next_posts_page_link( $max_pages );
		$next_link = strtok( $next_link, '?' ) ?: $next_link;
	}

	return array(
		'active'  => true,
		'nextUrl' => $next_link ? esc_url_raw( $next_link ) : '',
		'hasMore' => (bool) $next_link,
		'loading' => 'Carregando mais posts…',
		'end'     => ccd_blog_infinite_end_message(),
		'error'   => 'Não foi possível carregar mais posts. Tente novamente.',
	);
}

/**
 * Classe no body no servidor: esconde paginacao sem esperar JS
 * (e sobrevive a page cache antigo sem body.category no CSS).
 */
add_filter(
	'body_class',
	static function ( $classes ) {
		if ( ccd_blog_infinite_is_target() ) {
			$classes[] = 'ccd-blog-infinite';
		}
		return $classes;
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_admin() ) {
			return;
		}

		$handle = 'ccd-blog-infinite-scroll';
		wp_register_style( $handle, false, array(), '1.2.1' );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			<<<'CSS'
/*
 * Paginacao continua no HTML (crawl/rel=next), so sai do fluxo visual.
 * Infinite scroll segue lendo os links dessas paginas.
 */
body.blog .navigation.pagination,
body.category .navigation.pagination,
.ccd-blog-infinite .navigation.pagination {
	position: absolute !important;
	width: 1px !important;
	height: 1px !important;
	padding: 0 !important;
	margin: -1px !important;
	overflow: hidden !important;
	clip: rect(0, 0, 0, 0) !important;
	white-space: nowrap !important;
	border: 0 !important;
}
.ccd-blog-infinite-status {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 0.65rem;
	margin: 1.25rem 0 2rem;
	color: #3d4f5c;
	font-size: 0.95rem;
}
.ccd-blog-infinite-status[hidden] {
	display: none !important;
}
.ccd-blog-infinite-spinner {
	width: 1.1rem;
	height: 1.1rem;
	border: 2px solid #c5d3dc;
	border-top-color: #0277bd;
	border-radius: 50%;
	animation: ccd-blog-spin 0.7s linear infinite;
}
@keyframes ccd-blog-spin {
	to { transform: rotate(360deg); }
}
@media (prefers-reduced-motion: reduce) {
	.ccd-blog-infinite-spinner {
		animation: none;
	}
}
CSS
		);

		wp_register_script( $handle, false, array( 'jquery', 'ccd-soft-nav' ), '1.2.1', true );
		wp_enqueue_script( $handle );
		wp_add_inline_script(
			$handle,
			'window.ccdBlogInfinite = ' . wp_json_encode( ccd_blog_infinite_state() ) . ';',
			'before'
		);
		wp_add_inline_script(
			$handle,
			<<<'JS'
(function ($) {
	var teardown = null;

	function readCfg(doc) {
		var root = doc || document;
		var el = root.querySelector('#ccd-blog-infinite-state');
		if (el && el.textContent) {
			try {
				return JSON.parse(el.textContent);
			} catch (e) {}
		}
		return window.ccdBlogInfinite || {};
	}

	function stop() {
		if (typeof teardown === 'function') {
			teardown();
			teardown = null;
		}
		$('.ccd-blog-infinite-status, .ccd-blog-infinite-sentinel').remove();
		$(window).off('scroll.ccdBlogInfinite');
	}

	function boot(cfg) {
		stop();
		cfg = cfg || {};
		if (!cfg.active) {
			$('body').removeClass('ccd-blog-infinite');
			return;
		}

		var $list = $('.post-list.row').first();
		if (!$list.length) {
			$('body').removeClass('ccd-blog-infinite');
			return;
		}

		$('body').addClass('ccd-blog-infinite');
		if (!cfg.hasMore || !cfg.nextUrl) return;

		var $status = $(
			'<div class="ccd-blog-infinite-status" hidden role="status" aria-live="polite">' +
				'<span class="ccd-blog-infinite-spinner" aria-hidden="true"></span>' +
				'<span class="ccd-blog-infinite-label"></span>' +
			'</div>'
		);
		$list.after($status);

		var $sentinel = $('<div class="ccd-blog-infinite-sentinel" aria-hidden="true"></div>');
		$status.after($sentinel);

		var nextUrl = cfg.nextUrl;
		var loading = false;
		var done = false;
		var observer = null;

		function setStatus(text, spinning) {
			$status.prop('hidden', !text);
			$status.find('.ccd-blog-infinite-label').text(text || '');
			$status.find('.ccd-blog-infinite-spinner').toggle(!!spinning);
		}

		function appendItems($items) {
			if (!$items.length) return;

			$items.find('img').each(function () {
				var $img = $(this);
				// Sem loading=lazy: evita Intervention do Edge sobre placeholders.
				if ($img.attr('loading') === 'lazy') {
					$img.removeAttr('loading');
				}
				if ($img.attr('decoding') === undefined) {
					$img.attr('decoding', 'async');
				}
			});

			var masonry = $list.data('masonry');
			if (masonry && window.innerWidth >= 768) {
				$items.css('width', function () {
					return $(this).css('max-width');
				});
				$list.append($items);
				$list.masonry('appended', $items);
				$items.find('img').on('load', function () {
					$list.masonry('layout');
				});
				setTimeout(function () {
					if ($list.data('masonry')) {
						$list.masonry('layout');
					}
				}, 400);
			} else {
				$list.append($items);
			}
		}

		function loadNext() {
			if (loading || done || !nextUrl) return;
			loading = true;
			setStatus(cfg.loading || 'Carregando…', true);

			fetch(nextUrl, {
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'CCD-Infinite-Scroll' }
			})
				.then(function (res) {
					if (!res.ok) throw new Error('HTTP ' + res.status);
					return res.text();
				})
				.then(function (html) {
					var doc = new DOMParser().parseFromString(html, 'text/html');
					var items = doc.querySelectorAll('.post-list.row .post-list-item');
					var $items = $(Array.prototype.slice.call(items));
					appendItems($items);

					var next = doc.querySelector('a.next.page-numbers, .nav-links a.next, a[rel="next"]');
					if (!next) {
						var nums = doc.querySelectorAll('.navigation.pagination a.page-numbers:not(.prev):not(.next)');
						var current = doc.querySelector('.navigation.pagination .page-numbers.current');
						var curNum = current ? parseInt((current.textContent || '').replace(/\D/g, ''), 10) : NaN;
						next = null;
						if (!isNaN(curNum)) {
							nums.forEach(function (a) {
								var n = parseInt((a.textContent || '').replace(/\D/g, ''), 10);
								if (n === curNum + 1) next = a;
							});
						}
					}

					if (next && next.href && $items.length) {
						nextUrl = next.href;
					} else {
						done = true;
						nextUrl = '';
						setStatus(cfg.end || '', false);
						if (observer) observer.disconnect();
						return;
					}

					setStatus('', false);
				})
				.catch(function () {
					setStatus(cfg.error || 'Erro ao carregar.', false);
					setTimeout(function () {
						if (!done) setStatus('', false);
					}, 2500);
				})
				.finally(function () {
					loading = false;
				});
		}

		if ('IntersectionObserver' in window) {
			observer = new IntersectionObserver(
				function (entries) {
					entries.forEach(function (entry) {
						if (entry.isIntersecting) loadNext();
					});
				},
				{ rootMargin: '320px 0px', threshold: 0 }
			);
			observer.observe($sentinel.get(0));
		} else {
			$(window).on('scroll.ccdBlogInfinite', function () {
				var rect = $sentinel.get(0).getBoundingClientRect();
				if (rect.top < window.innerHeight + 320) loadNext();
			});
		}

		teardown = function () {
			if (observer) observer.disconnect();
			$(window).off('scroll.ccdBlogInfinite');
			$status.remove();
			$sentinel.remove();
		};
	}

	window.ccdBlogInfiniteBoot = boot;
	window.ccdBlogInfiniteStop = stop;

	function onReady() {
		boot(readCfg(document));
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady);
	} else {
		onReady();
	}

	document.addEventListener('ccd:softnav', function (event) {
		var doc = event.detail && event.detail.doc ? event.detail.doc : document;
		var cfg = readCfg(doc);
		window.ccdBlogInfinite = cfg;
		boot(cfg);
	});
})(jQuery);
JS
		);
	},
	30
);

add_action(
	'wp_footer',
	static function () {
		if ( is_admin() ) {
			return;
		}
		$state = ccd_blog_infinite_state();
		echo '<script type="application/json" id="ccd-blog-infinite-state">' .
			wp_json_encode( $state ) .
			'</script>' . "\n";
	},
	1
);
