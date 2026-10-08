/**
 * CCD Admin — flyout de submenu a direita no hover.
 *
 * Porque portal no body:
 * #adminmenuwrap tem overflow-y:auto; painel fixed/absolute filho e cortado.
 *
 * API interna (manutencao):
 * - openLi / closeLi / place  — estado + posicao
 * - bind                      — eventos hover/focus/scroll
 */
(function ($) {
	'use strict';

	var CLOSE_DELAY_MS = 200;
	var DESKTOP_MQ = '(min-width: 783px)';
	var OPEN_CLASS = 'ccd-flyout-open';
	var PANEL_CLASS = 'ccd-flyout-panel';
	var ROOT_ID = 'ccd-admin-flyout-root';
	var BODY_ACTIVE = 'ccd-flyout-active';

	var closeTimer = null;
	/** @type {JQuery|null} */
	var $openLi = null;
	/** @type {JQuery|null} submenu atualmente no portal */
	var $portedSub = null;

	function isDesktop() {
		return window.matchMedia(DESKTOP_MQ).matches;
	}

	function rootEl() {
		var el = document.getElementById(ROOT_ID);
		if (!el) {
			el = document.createElement('div');
			el.id = ROOT_ID;
			el.setAttribute('data-ccd-flyout-root', '1');
			document.body.appendChild(el);
		}
		return el;
	}

	/**
	 * Define left/top com !important (vence regras do stylesheet).
	 *
	 * @param {HTMLElement} el
	 * @param {number} left
	 * @param {number} top
	 */
	function setPos(el, left, top) {
		el.style.setProperty('left', Math.round(left) + 'px', 'important');
		el.style.setProperty('top', Math.round(top) + 'px', 'important');
	}

	function clearPos(el) {
		el.style.removeProperty('left');
		el.style.removeProperty('top');
	}

	function menuRight() {
		var wrap = document.getElementById('adminmenuwrap');
		if (wrap) {
			return wrap.getBoundingClientRect().right;
		}
		return 240;
	}

	/**
	 * @param {JQuery} $li
	 * @return {JQuery}
	 */
	function submenuOf($li) {
		if ($portedSub && $openLi && $openLi.get(0) === $li.get(0)) {
			return $portedSub;
		}
		return $li.children('.wp-submenu');
	}

	/**
	 * Move o .wp-submenu para o portal (fora do overflow da coluna).
	 *
	 * @param {JQuery} $li
	 * @return {JQuery}
	 */
	function portOut($li) {
		var $sub = $li.children('.wp-submenu');
		if (!$sub.length) {
			return $portedSub && $openLi && $openLi.get(0) === $li.get(0)
				? $portedSub
				: $();
		}
		$sub.addClass(PANEL_CLASS);
		rootEl().appendChild($sub.get(0));
		$portedSub = $sub;
		return $sub;
	}

	/**
	 * Devolve o submenu ao li original.
	 *
	 * @param {JQuery} $li
	 */
	function portBack($li) {
		if (!$portedSub || !$portedSub.length) {
			return;
		}
		var el = $portedSub.get(0);
		clearPos(el);
		$portedSub.removeClass(PANEL_CLASS);
		if ($li && $li.length) {
			$li.append(el);
		}
		$portedSub = null;
	}

	/**
	 * Alinha o painel ao item sob o cursor (e evita sair da viewport).
	 *
	 * @param {JQuery} $li
	 */
	function place($li) {
		var $sub = submenuOf($li);
		var anchor = $li.children('a.menu-top').get(0) || $li.get(0);
		if (!$sub.length || !anchor) {
			return;
		}

		var rect = anchor.getBoundingClientRect();
		var left = menuRight();
		var top = rect.top;
		var el = $sub.get(0);

		setPos(el, left, top);

		var subH = el.offsetHeight || 0;
		var maxTop = Math.max(8, window.innerHeight - subH - 8);
		if (top > maxTop) {
			top = maxTop;
		}
		if (top < 8) {
			top = 8;
		}
		setPos(el, left, top);
	}

	function clearCloseTimer() {
		if (closeTimer) {
			window.clearTimeout(closeTimer);
			closeTimer = null;
		}
	}

	/**
	 * @param {JQuery} $li
	 */
	function closeLi($li) {
		if (!$li || !$li.length) {
			return;
		}
		$li.removeClass(OPEN_CLASS + ' opensub');
		if ($openLi && $openLi.get(0) === $li.get(0)) {
			portBack($li);
			$openLi = null;
		}
		document.body.classList.remove(BODY_ACTIVE);
	}

	function closeAll() {
		clearCloseTimer();
		if ($openLi) {
			closeLi($openLi);
		}
		$('#adminmenu li.' + OPEN_CLASS).removeClass(OPEN_CLASS + ' opensub');
		document.body.classList.remove(BODY_ACTIVE);
	}

	/**
	 * @param {JQuery} $li
	 */
	function openLi($li) {
		if (!isDesktop() || !$li.length || !$li.hasClass('wp-has-submenu')) {
			return;
		}
		clearCloseTimer();

		if ($openLi && $openLi.get(0) !== $li.get(0)) {
			closeLi($openLi);
		}

		$li.addClass(OPEN_CLASS + ' opensub');
		$openLi = $li;
		document.body.classList.add(BODY_ACTIVE);
		portOut($li);
		place($li);
	}

	/**
	 * @param {JQuery} $li
	 */
	function scheduleClose($li) {
		clearCloseTimer();
		closeTimer = window.setTimeout(function () {
			closeLi($li);
		}, CLOSE_DELAY_MS);
	}

	/**
	 * relatedTarget ainda esta no item ou no painel portado?
	 *
	 * @param {JQuery} $li
	 * @param {EventTarget|null} to
	 * @return {boolean}
	 */
	function stillInside($li, to) {
		if (!to || !to.nodeType) {
			return false;
		}
		if ($li.get(0) === to || $.contains($li.get(0), to)) {
			return true;
		}
		if ($portedSub && $portedSub.length) {
			var panel = $portedSub.get(0);
			if (panel === to || $.contains(panel, to)) {
				return true;
			}
		}
		var root = document.getElementById(ROOT_ID);
		if (root && (root === to || $.contains(root, to))) {
			return true;
		}
		return false;
	}

	function bind() {
		var $menu = $('#adminmenu');
		if (!$menu.length) {
			return;
		}

		var itemSel = 'li.menu-top.wp-has-submenu';

		// mouseover/out (bubbling) — mais confiavel que mouseenter delegado.
		$menu.on('mouseover', itemSel, function (e) {
			var $li = $(this);
			if (!$li.is(e.target) && !$li.has(e.target).length) {
				return;
			}
			openLi($li);
		});

		$menu.on('mouseout', itemSel, function (e) {
			var $li = $(this);
			if (stillInside($li, e.relatedTarget)) {
				clearCloseTimer();
				return;
			}
			scheduleClose($li);
		});

		// Painel no portal (fora de #adminmenu).
		$(document).on('mouseover', '#' + ROOT_ID + ' .' + PANEL_CLASS, function () {
			if ($openLi) {
				clearCloseTimer();
			}
		});

		$(document).on('mouseout', '#' + ROOT_ID + ' .' + PANEL_CLASS, function (e) {
			if (!$openLi) {
				return;
			}
			if (stillInside($openLi, e.relatedTarget)) {
				clearCloseTimer();
				return;
			}
			scheduleClose($openLi);
		});

		$menu.on('focusin', itemSel, function () {
			openLi($(this));
		});

		$menu.on('focusout', itemSel, function (e) {
			var $li = $(this);
			if (stillInside($li, e.relatedTarget)) {
				return;
			}
			scheduleClose($li);
		});

		function refreshOpen() {
			if ($openLi && $openLi.hasClass(OPEN_CLASS)) {
				place($openLi);
			}
		}

		$('#adminmenuwrap').on('scroll', refreshOpen);
		$(window).on('resize', function () {
			if (!isDesktop()) {
				closeAll();
				return;
			}
			refreshOpen();
		});

		$(document).on('wp-menu-state-set', function () {
			if (!isDesktop()) {
				closeAll();
			}
		});
	}

	$(bind);
})(jQuery);
