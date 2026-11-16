(function ($) {
	'use strict';

	function badgeHtml() {
		var label = (window.spbfFrontend && spbfFrontend.badgeLabel) ? spbfFrontend.badgeLabel : 'Black Friday';
		return $('<span/>', {
			'class': 'spbf-variation-badge',
			text: label
		});
	}

	function syncSkuBadge($variation) {
		var $meta = $('.sku-meta').first();
		if (!$meta.length) {
			return;
		}
		$meta.find('.spbf-variation-badge').remove();
		if ($variation && $variation.length && $variation.attr('data-spbf') === 'yes') {
			$meta.append(' ').append(badgeHtml());
		}
	}

	function syncFromActive() {
		var $active = $('.woovr-variation.woovr-variation-active, .woovr-variation-radio.woovr-variation-active').first();
		if (!$active.length) {
			$active = $('.woovr-variation-radio input:checked').closest('.woovr-variation');
		}
		syncSkuBadge($active);
	}

	$(function () {
		syncFromActive();

		$(document).on('click', '.woovr-variation-radio', function () {
			syncSkuBadge($(this));
		});

		$(document).on('found_variation', 'form.variations_form', function (event, variation) {
			var $meta = $('.sku-meta').first();
			if (!$meta.length) {
				return;
			}
			$meta.find('.spbf-variation-badge').remove();
			if (variation && variation.spbf_1p_offer) {
				$meta.append(' ').append(badgeHtml());
			}
		});

		$(document).on('reset_data', 'form.variations_form', function () {
			$('.sku-meta .spbf-variation-badge').remove();
		});
	});
})(jQuery);
