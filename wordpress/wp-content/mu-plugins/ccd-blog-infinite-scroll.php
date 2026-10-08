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
 *
 * @return array<string, mixed>
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
		'page'    => $current,
		'pages'   => $max_pages,
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
		// Sem dependencia de jQuery: ccd-perf defere jquery-core e o inline
		// rodava antes do jQuery existir (ReferenceError → scroll morto).
		wp_register_style( $handle, false, array(), '1.3.2' );
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

		wp_register_script( $handle, false, array(), '1.3.2', true );
		wp_enqueue_script( $handle );

		wp_add_inline_script(
			$handle,
			'window.ccdBlogInfinite = ' . wp_json_encode( ccd_blog_infinite_state() ) . ';',
			'before'
		);
		wp_add_inline_script(
			$handle,
			<<<'JS'
(function () {
	var teardown = null;
	var onScrollFallback = function () {};

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

	function listRoot() {
		return document.querySelector('.post-list.row');
	}

	function topLevelItems(scope) {
		var root = scope || document;
		var list = root.querySelector('.post-list.row');
		if (!list) return [];
		return Array.prototype.filter.call(list.children, function (el) {
			return el.nodeType === 1 && el.classList && el.classList.contains('post-list-item');
		});
	}

	function stop() {
		if (typeof teardown === 'function') {
			teardown();
			teardown = null;
		}
		document.querySelectorAll('.ccd-blog-infinite-status, .ccd-blog-infinite-sentinel').forEach(function (el) {
			el.remove();
		});
		window.removeEventListener('scroll', onScrollFallback);
	}

	function resolveNext(doc) {
		var cfg = readCfg(doc);
		if (cfg && cfg.nextUrl) {
			return String(cfg.nextUrl);
		}
		var next = doc.querySelector('a.next.page-numbers, .nav-links a.next, a[rel="next"]');
		if (next && next.href) {
			return next.href;
		}
		var nums = doc.querySelectorAll('.navigation.pagination a.page-numbers:not(.prev):not(.next)');
		var current = doc.querySelector('.navigation.pagination .page-numbers.current');
		var curNum = current ? parseInt((current.textContent || '').replace(/\D/g, ''), 10) : NaN;
		if (!isNaN(curNum)) {
			for (var i = 0; i < nums.length; i++) {
				var n = parseInt((nums[i].textContent || '').replace(/\D/g, ''), 10);
				if (n === curNum + 1 && nums[i].href) {
					return nums[i].href;
				}
			}
		}
		return '';
	}

	function boot(cfg) {
		stop();
		cfg = cfg || {};
		if (!cfg.active) {
			document.body.classList.remove('ccd-blog-infinite');
			return;
		}

		var list = listRoot();
		if (!list) {
			document.body.classList.remove('ccd-blog-infinite');
			return;
		}

		document.body.classList.add('ccd-blog-infinite');
		if (!cfg.hasMore || !cfg.nextUrl) {
			return;
		}

		var status = document.createElement('div');
		status.className = 'ccd-blog-infinite-status';
		status.hidden = true;
		status.setAttribute('role', 'status');
		status.setAttribute('aria-live', 'polite');
		status.innerHTML =
			'<span class="ccd-blog-infinite-spinner" aria-hidden="true"></span>' +
			'<span class="ccd-blog-infinite-label"></span>';
		list.insertAdjacentElement('afterend', status);

		var sentinel = document.createElement('div');
		sentinel.className = 'ccd-blog-infinite-sentinel';
		sentinel.setAttribute('aria-hidden', 'true');
		status.insertAdjacentElement('afterend', sentinel);

		var nextUrl = cfg.nextUrl;
		var loading = false;
		var done = false;
		var observer = null;
		var label = status.querySelector('.ccd-blog-infinite-label');
		var spinner = status.querySelector('.ccd-blog-infinite-spinner');

		function setStatus(text, spinning) {
			status.hidden = !text;
			if (label) label.textContent = text || '';
			if (spinner) spinner.style.display = spinning ? '' : 'none';
		}

		function appendItems(nodes) {
			if (!nodes.length) return;
			var imported = nodes.map(function (node) {
				var el = document.importNode(node, true);
				el.querySelectorAll('img').forEach(function (img) {
					if (img.getAttribute('loading') === 'lazy') {
						img.removeAttribute('loading');
					}
					if (!img.hasAttribute('decoding')) {
						img.setAttribute('decoding', 'async');
					}
				});
				return el;
			});

			var $ = window.jQuery;
			var masonry = $ && $(list).data('masonry');
			if (masonry && window.innerWidth >= 768) {
				var $items = $(imported);
				$items.css('width', function () {
					return $(this).css('max-width');
				});
				$(list).append($items);
				$(list).masonry('appended', $items);
				$items.find('img').on('load', function () {
					$(list).masonry('layout');
				});
				setTimeout(function () {
					if ($(list).data('masonry')) {
						$(list).masonry('layout');
					}
				}, 400);
			} else {
				imported.forEach(function (el) {
					list.appendChild(el);
				});
			}
		}

		function finish() {
			done = true;
			nextUrl = '';
			setStatus(cfg.end || '', false);
			if (observer) observer.disconnect();
		}

		function loadNext() {
			if (loading || done || !nextUrl) return;
			loading = true;
			setStatus(cfg.loading || 'Carregando…', true);

			var requested = nextUrl;
			var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
			var timer = null;
			if (controller) {
				// HTML frio no CF/Railway pode passar de 20s; aborta para nao travar o loader.
				timer = setTimeout(function () {
					try { controller.abort(); } catch (e) {}
				}, 45000);
			}

			fetch(requested, {
				credentials: 'same-origin',
				cache: 'default',
				signal: controller ? controller.signal : undefined
			})
				.then(function (res) {
					if (!res.ok) throw new Error('HTTP ' + res.status);
					return res.text();
				})
				.then(function (html) {
					var doc = new DOMParser().parseFromString(html, 'text/html');
					var items = topLevelItems(doc);
					appendItems(items);

					var resolved = resolveNext(doc);
					// Evita loop se o cache devolver a mesma pagina.
					if (resolved && resolved === requested) {
						resolved = '';
					}

					if (resolved) {
						nextUrl = resolved;
						if (document.head && !document.querySelector('link[data-ccd-inf="' + resolved + '"]')) {
							var warm = document.createElement('link');
							warm.rel = 'prefetch';
							warm.href = resolved;
							warm.setAttribute('data-ccd-inf', resolved);
							document.head.appendChild(warm);
						}
						setStatus('', false);
						return;
					}

					finish();
				})
				.catch(function () {
					setStatus(cfg.error || 'Erro ao carregar.', false);
					setTimeout(function () {
						if (!done) setStatus('', false);
					}, 2500);
				})
				.finally(function () {
					if (timer) clearTimeout(timer);
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
			observer.observe(sentinel);
		} else {
			onScrollFallback = function () {
				var rect = sentinel.getBoundingClientRect();
				if (rect.top < window.innerHeight + 320) loadNext();
			};
			window.addEventListener('scroll', onScrollFallback, { passive: true });
		}

		teardown = function () {
			if (observer) observer.disconnect();
			window.removeEventListener('scroll', onScrollFallback);
			status.remove();
			sentinel.remove();
		};

		// Exposto para testes / re-boot.
		window.ccdBlogInfiniteLoadNext = loadNext;
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
})();
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
