(function($) {
	'use strict';

	if (typeof paypal === 'undefined' || typeof paypal_button_config === 'undefined') {
		return;
	}

	var $success = $('#paypal-success');
	var $error = $('#paypal-error');
	var $loading = $('#paypal-loading');

	paypal.Buttons({
		style: {
			label: 'pay',
			height: 35,
			shape: 'rect',
			layout: 'vertical'
		},
		createOrder: function() {
			if ($error.length) {
				$error.hide().text('');
			}
			if ($loading.length) {
				$loading.show();
			}

			return fetch(paypal_button_config.u_create, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: 'term_id=' + encodeURIComponent(paypal_button_config.term_id)
			}).then(function(res) {
				if (!res.ok) {
					throw new Error(paypal_button_config.lang_error);
				}
				return res.json();
			}).then(function(data) {
				if ($loading.length) {
					$loading.hide();
				}
				if (data && data.id) {
					return data.id;
				}
				throw new Error(paypal_button_config.lang_error);
			}).catch(function(err) {
				if ($loading.length) {
					$loading.hide();
				}
				if ($error.length) {
					$error.text(paypal_button_config.lang_error).show();
				}
				throw err;
			});
		},
		onApprove: function(data, actions) {
			if ($loading.length) {
				$loading.show();
			}

			return fetch(paypal_button_config.u_capture, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: 'order_id=' + encodeURIComponent(data.orderID)
			}).then(function(res) {
				if (!res.ok) {
					throw new Error(paypal_button_config.lang_error);
				}
				return res.json();
			}).then(function(result) {
				if ($loading.length) {
					$loading.hide();
				}
				if (result && result.success) {
					$('.submit-buttons').remove();
					if ($error.length) {
						$error.hide();
					}
					$success.show();
				} else {
					if ($error.length) {
						$error.text(paypal_button_config.lang_error).show();
					}
					return actions.restart();
				}
			}).catch(function(err) {
				if ($loading.length) {
					$loading.hide();
				}
				if ($error.length) {
					$error.text(paypal_button_config.lang_error).show();
				}
			});
		},
		onCancel: function() {
			if ($loading.length) {
				$loading.hide();
			}
			if ($error.length) {
				$error.text(paypal_button_config.lang_cancelled).show();
			}
		},
		onError: function(err) {
			if ($loading.length) {
				$loading.hide();
			}
			if ($error.length) {
				$error.text(paypal_button_config.lang_error).show();
			}
		}
	}).render('#paypal-button-container');

}(jQuery));
