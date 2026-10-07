/**
 * Posiciona submenus do wp-admin a direita (flyout), fora do clip do sidebar.
 */
(function ($) {
	'use strict';

	function menuWidth() {
		var raw = getComputedStyle(document.documentElement)
			.getPropertyValue('--ccd-admin-menu-width')
			.trim();
		var n = parseFloat(raw);
		return isFinite(n) && n > 0 ? n : 240;
	}

	function place($li) {
		var $sub = $li.children('.wp-submenu');
		if (!$sub.length) {
			return;
		}
		var el = $li.get(0);
		if (!el) {
			return;
		}
		var rect = el.getBoundingClientRect();
		var left = menuWidth();
		var top = Math.max(8, rect.top);
		$sub.css({ left: left + 'px', top: top + 'px' });

		var subH = $sub.outerHeight() || 0;
		var maxTop = Math.max(8, window.innerHeight - subH - 8);
		if (top > maxTop) {
			$sub.css('top', maxTop + 'px');
		}
	}

	function isDesktop() {
		return window.matchMedia('(min-width: 783px)').matches;
	}

	function bind() {
		var $menu = $('#adminmenu');
		if (!$menu.length) {
			return;
		}

		$menu.on('mouseenter.focusin', 'li.menu-top.wp-has-submenu', function () {
			if (!isDesktop()) {
				return;
			}
			place($(this));
		});

		$menu.on('mouseleave', 'li.menu-top.wp-has-submenu', function () {
			$(this).children('.wp-submenu').css({ top: '', left: '' });
		});

		function placeOpen() {
			if (!isDesktop()) {
				return;
			}
			$menu.find('li.menu-top.opensub').each(function () {
				place($(this));
			});
		}

		$(document).on('wp-menu-state-set wp-window-resized', placeOpen);
		$menu.on('scroll', placeOpen);
		$('#adminmenuwrap').on('scroll', placeOpen);

		// Mutation / WP hoverIntent adiciona .opensub depois do mouseenter.
		var obs = new MutationObserver(function (records) {
			if (!isDesktop()) {
				return;
			}
			records.forEach(function (r) {
				if (r.type !== 'attributes' || r.attributeName !== 'class') {
					return;
				}
				var $li = $(r.target);
				if ($li.hasClass('opensub')) {
					place($li);
				}
			});
		});
		$menu.find('li.menu-top.wp-has-submenu').each(function () {
			obs.observe(this, { attributes: true, attributeFilter: ['class'] });
		});
	}

	$(bind);
})(jQuery);
