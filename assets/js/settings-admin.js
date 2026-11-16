(function () {
	'use strict';

	function onReady(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	onReady(function () {
		var list = document.getElementById('spbf-allowed-countries');
		if (!list) {
			return;
		}

		function setAll(checked) {
			var inputs = list.querySelectorAll('input[type="checkbox"]');
			Array.prototype.forEach.call(inputs, function (input) {
				input.checked = checked;
			});
		}

		function selectGbOnly() {
			var inputs = list.querySelectorAll('input[type="checkbox"]');
			Array.prototype.forEach.call(inputs, function (input) {
				input.checked = String(input.value).toUpperCase() === 'GB';
			});
		}

		document.addEventListener('click', function (event) {
			var btn = event.target.closest('[data-spbf-countries]');
			if (!btn) {
				return;
			}
			event.preventDefault();
			var mode = btn.getAttribute('data-spbf-countries');
			if (mode === 'all') {
				setAll(true);
			} else if (mode === 'none') {
				setAll(false);
			} else if (mode === 'gb') {
				selectGbOnly();
			}
		});
	});
})();
