(function($) {
	'use strict';

	if (typeof paypal === 'undefined' || typeof paypal_button_config === 'undefined') {
		return;
	}

	var $success = $('#paypal-success');
	var $error = $('#paypal-error');
	var $loading = $('#paypal-loading');

	var buttonConfig = {
		style: {
			label: 'pay',
			height: 35,
			shape: 'rect',
			layout: 'vertical'
		},
		onApprove: function(data, actions) {
			if ($loading.length) {
				$loading.show();
			}

			var isRecurring = paypal_button_config.is_recurring;
			var url = isRecurring ? paypal_button_config.u_subscribe : paypal_button_config.u_capture;
			var postBody = isRecurring
				? 'subscription_id=' + encodeURIComponent(data.subscriptionID) + '&term_id=' + encodeURIComponent(paypal_button_config.term_id)
				: 'order_id=' + encodeURIComponent(data.orderID);

			return fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: postBody
			}).then(function(res) {
				if (!res.ok) {
					return res.json().then(function(json) {
						throw new Error(json.error || paypal_button_config.lang_error);
					}).catch(function() {
						throw new Error(paypal_button_config.lang_error);
					});
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
				console.error('GroupSub onApprove Error:', err);
				if ($error.length) {
					$error.text(err.message || paypal_button_config.lang_error).show();
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
			console.error('PayPal Buttons Error:', err);
			if ($error.length) {
				$error.text(paypal_button_config.lang_error).show();
			}
		}
	};

	if (paypal_button_config.is_recurring && paypal_button_config.plan_id) {
		buttonConfig.createSubscription = function(data, actions) {
			if ($error.length) {
				$error.hide().text('');
			}
			if ($loading.length) {
				$loading.show();
			}
			return actions.subscription.create({
				plan_id: paypal_button_config.plan_id
			});
		};
	} else {
		buttonConfig.createOrder = function() {
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
					return res.json().then(function(json) {
						throw new Error(json.error || paypal_button_config.lang_error);
					}).catch(function() {
						throw new Error(paypal_button_config.lang_error);
					});
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
				console.error('GroupSub createOrder Error:', err);
				if ($error.length) {
					$error.text(err.message || paypal_button_config.lang_error).show();
				}
				throw err;
			});
		};
	}

	paypal.Buttons(buttonConfig).render('#paypal-button-container');

}(jQuery));
