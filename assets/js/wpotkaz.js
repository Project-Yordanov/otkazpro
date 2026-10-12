/**
 * WPOtkaz – progressive enhancement for the withdrawal form (no dependencies).
 * The form works fully without JavaScript; this only improves feedback.
 */
(function () {
	'use strict';

	var i18n = window.wpotkazL10n || {};

	function each(list, fn) {
		Array.prototype.forEach.call(list, fn);
	}

	function format(str, a, b) {
		return String(str || '').replace('%1$s', a).replace('%2$s', b);
	}

	function initSubmitForm(form) {
		var checks = form.querySelectorAll('[data-wpotkaz-check]');
		var button = form.querySelector('[data-wpotkaz-submit]');
		var status = form.querySelector('[data-wpotkaz-status]');
		var allWrap = form.querySelector('[data-wpotkaz-selectall-wrap]');
		var all = form.querySelector('[data-wpotkaz-selectall]');

		function update() {
			var count = 0;
			var pcs = 0;
			each(checks, function (cb) {
				var row = cb.closest('[data-wpotkaz-item]');
				var qty = row ? row.querySelector('[data-wpotkaz-qty]') : null;
				if (qty && qty.type === 'number') {
					qty.disabled = !cb.checked;
				}
				if (row) {
					row.classList.toggle('is-off', !cb.checked);
				}
				if (cb.checked) {
					count++;
					pcs += qty ? Math.max(1, parseInt(qty.value, 10) || 1) : 1;
				}
			});
			if (button) {
				button.disabled = count === 0;
			}
			if (status) {
				status.textContent = count === 0 ? (i18n.selectOne || '') : format(i18n.selected, count, pcs);
			}
			if (all) {
				all.checked = count === checks.length;
				all.indeterminate = count > 0 && count < checks.length;
			}
		}

		each(checks, function (cb) {
			cb.addEventListener('change', update);
		});
		each(form.querySelectorAll('[data-wpotkaz-qty]'), function (q) {
			q.addEventListener('input', update);
		});

		if (allWrap && all) {
			allWrap.hidden = false;
			all.addEventListener('change', function () {
				each(checks, function (cb) {
					cb.checked = all.checked;
				});
				update();
			});
		}

		form.addEventListener('submit', function (e) {
			if (form.getAttribute('data-wpotkaz-busy')) {
				e.preventDefault();
				return;
			}
			if (button && button.disabled) {
				e.preventDefault();
				return;
			}
			form.setAttribute('data-wpotkaz-busy', '1');
			if (button) {
				button.setAttribute('aria-busy', 'true');
				button.setAttribute('data-wpotkaz-label', button.textContent);
				button.textContent = i18n.sending || button.textContent;
			}
		});

		update();
	}

	function initLookupForm(form) {
		var button = form.querySelector('button[type=submit]');
		form.addEventListener('submit', function (e) {
			if (form.getAttribute('data-wpotkaz-busy')) {
				e.preventDefault();
				return;
			}
			form.setAttribute('data-wpotkaz-busy', '1');
			if (button) {
				button.setAttribute('aria-busy', 'true');
			}
		});
	}

	function init() {
		each(document.querySelectorAll('[data-wpotkaz-otkaz]'), function (root) {
			each(root.querySelectorAll('form[data-wpotkaz-form="submit"]'), initSubmitForm);
			each(root.querySelectorAll('form[data-wpotkaz-form="lookup"]'), initLookupForm);

			var focus = root.querySelector('[data-wpotkaz-focus]');
			if (focus) {
				// Page builders may keep the content hidden while an entrance animation runs.
				var tries = 0;
				var tryFocus = function () {
					focus.focus({ preventScroll: false });
					if (document.activeElement !== focus && tries++ < 10) {
						setTimeout(tryFocus, 300);
					}
				};
				tryFocus();
			}

			each(root.querySelectorAll('[data-wpotkaz-print]'), function (btn) {
				btn.hidden = false;
				btn.addEventListener('click', function () {
					window.print();
				});
			});
		});

		// Re-enable buttons when the page is restored from bfcache (back button).
		window.addEventListener('pageshow', function (e) {
			if (!e.persisted) {
				return;
			}
			each(document.querySelectorAll('[data-wpotkaz-otkaz] form[data-wpotkaz-busy]'), function (form) {
				form.removeAttribute('data-wpotkaz-busy');
				each(form.querySelectorAll('[aria-busy]'), function (b) {
					b.removeAttribute('aria-busy');
					if (b.getAttribute('data-wpotkaz-label')) {
						b.textContent = b.getAttribute('data-wpotkaz-label');
					}
				});
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
