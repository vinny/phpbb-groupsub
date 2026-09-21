(function($) {
	'use strict';

	$(document).ready(function() {
		// Handle gateway switching
		$('.groupsub-gateway-radio').on('change', function() {
			var selectedId = $(this).val();

			$('.groupsub-gateway-option').removeClass('is-active');
			$(this).closest('.groupsub-gateway-option').addClass('is-active');

			$('.groupsub-pane').removeClass('is-active');
			$('#groupsub-pane-' + selectedId).addClass('is-active');
		});

		// Pix payment generation and polling logic
		var pixPollInterval = null;

		$('#groupsub-pix-btn-generate').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var urlCreate = $btn.data('url-create');
			var urlStatusTpl = $btn.data('url-status');

			$btn.prop('disabled', true).addClass('disabled');
			$('#groupsub-pix-loading').show();
			$('#groupsub-pix-error').hide();

			$.ajax({
				url: urlCreate,
				type: 'POST',
				dataType: 'json',
				success: function(response) {
					$('#groupsub-pix-loading').hide();

					if (!response.success) {
						var errMsg = response.error || $btn.data('lang-error');
						$('#groupsub-pix-error').text(errMsg).show();
						$btn.prop('disabled', false).removeClass('disabled');
						return;
					}

					$btn.hide();
					$('#groupsub-pix-display').show();

					if (response.qr_code_base64) {
						$('#groupsub-pix-img').attr('src', 'data:image/png;base64,' + response.qr_code_base64);
					}

					$('#groupsub-pix-code').val(response.qr_code);

					// Start polling status
					var paymentId = response.payment_id;
					var checkUrl = urlStatusTpl.replace('__PAYMENT_ID__', paymentId);

					if (pixPollInterval) {
						clearInterval(pixPollInterval);
					}

					pixPollInterval = setInterval(function() {
						$.ajax({
							url: checkUrl,
							type: 'GET',
							dataType: 'json',
							cache: false,
							success: function(statusRes) {
								if (statusRes.status === 'approved' && statusRes.redirect_url) {
									clearInterval(pixPollInterval);
									pixPollInterval = null;
									window.location.replace(statusRes.redirect_url);
								} else if (statusRes.status === 'cancelled' || statusRes.status === 'rejected') {
									clearInterval(pixPollInterval);
									pixPollInterval = null;
									$('#groupsub-pix-display').hide();
									$('#groupsub-pix-error').text($btn.data('lang-cancelled')).show();
									$btn.show().prop('disabled', false).removeClass('disabled');
								}
							}
						});
					}, 3000);
				},
				error: function(xhr) {
					$('#groupsub-pix-loading').hide();
					var err = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : $btn.data('lang-error');
					$('#groupsub-pix-error').text(err).show();
					$btn.prop('disabled', false).removeClass('disabled');
				}
			});
		});

		// Copy Pix Code Button
		$('#groupsub-pix-btn-copy').on('click', function(e) {
			e.preventDefault();
			var $code = $('#groupsub-pix-code');
			$code.select();
			navigator.clipboard.writeText($code.val()).then(function() {
				var $btn = $('#groupsub-pix-btn-copy');
				var origText = $btn.text();
				var copiedText = $btn.data('lang-copied') || 'Copied!';
				$btn.text(copiedText);
				setTimeout(function() {
					$btn.text(origText);
				}, 2000);
			});
		});
	});
})(jQuery);
