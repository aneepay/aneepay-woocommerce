/**
 * AneePay Crypto Gateway - checkout status polling.
 */
(function () {
	'use strict';

	var MAX_ATTEMPTS = 40;
	var INTERVAL_MS = 5000;

	function poll() {
		var block = document.querySelector('.aneepay-payment[data-order-id]');

		if (!block) {
			return;
		}

		var orderId = block.getAttribute('data-order-id');
		var attempts = 0;

		var timer = setInterval(function () {
			attempts += 1;

			if (attempts > MAX_ATTEMPTS) {
				clearInterval(timer);
				return;
			}

			var body = new URLSearchParams();
			body.append('action', 'aneepay_check_status');
			body.append('nonce', window.aneepayParams.nonce);
			body.append('orderId', orderId);

			fetch(window.aneepayParams.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (data) {
					if (data && data.success && data.data && data.data.status) {
						var status = data.data.status;

						if (status === 'success' || status === 'failed' || status === 'cancelled') {
							clearInterval(timer);
							window.location.reload();
						}
					}
				})
				.catch(function () {});
		}, INTERVAL_MS);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', poll);
	} else {
		poll();
	}
})();
