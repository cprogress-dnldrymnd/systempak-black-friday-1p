(function () {
	'use strict';

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function qsa(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function isVariableProduct() {
		var typeSelect = qs('#product-type');
		if (typeSelect) {
			return typeSelect.value === 'variable';
		}
		var root = qs('#spbf-variation-offer');
		return root && root.getAttribute('data-is-variable') === '1';
	}

	function isBfCategoryChecked(termId) {
		if (!termId) {
			return false;
		}
		var inputs = qsa('#product_catchecklist input[type="checkbox"], #product_cat-all input[type="checkbox"], #product_cat-pop input[type="checkbox"]');
		return inputs.some(function (input) {
			return String(input.value) === String(termId) && input.checked;
		});
	}

	function syncMetaBox() {
		var root = qs('#spbf-variation-offer');
		if (!root) {
			return;
		}

		var termId = root.getAttribute('data-category-term-id');
		var isVariable = isVariableProduct();
		var inCategory = isBfCategoryChecked(termId);
		var gateType = qs('.spbf-variation-offer__gate--type', root);
		var gateCat = qs('.spbf-variation-offer__gate--category', root);
		var body = qs('.spbf-variation-offer__body', root);
		var listWrap = qs('.spbf-variation-offer__list-wrap', root);
		var modeSpecific = qs('input[name="spbf_variation_mode"][value="specific"]', root);

		if (gateType) {
			gateType.hidden = isVariable;
		}
		if (gateCat) {
			gateCat.hidden = !isVariable || inCategory;
		}
		if (body) {
			body.hidden = !(isVariable && inCategory);
		}
		if (listWrap && modeSpecific) {
			listWrap.hidden = !modeSpecific.checked;
		}

		var metabox = qs('#spbf_variation_offer');
		if (metabox) {
			metabox.classList.toggle('spbf-metabox--inactive', !(isVariable && inCategory));
		}
	}

	function onReady(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	onReady(function () {
		if (!qs('#spbf-variation-offer')) {
			return;
		}

		document.addEventListener('change', function (event) {
			var t = event.target;
			if (!t) {
				return;
			}
			if (t.id === 'product-type' || t.name === 'spbf_variation_mode' || (t.type === 'checkbox' && t.closest && (t.closest('#product_catchecklist') || t.closest('#product_cat-all') || t.closest('#product_cat-pop')))) {
				syncMetaBox();
			}
		});

		// Category checklist can load asynchronously / via popular tab.
		var observerTarget = qs('#product_catdiv') || qs('#taxonomy-product_cat');
		if (observerTarget && typeof MutationObserver !== 'undefined') {
			var timer = null;
			var observer = new MutationObserver(function () {
				window.clearTimeout(timer);
				timer = window.setTimeout(syncMetaBox, 50);
			});
			observer.observe(observerTarget, { childList: true, subtree: true, attributes: true });
		}

		syncMetaBox();
	});
})();
