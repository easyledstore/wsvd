(function ($) {
	'use strict';

	function getCustomerEmail() {
		var selectors = [
			'#_billing_email',
			'input[name="_billing_email"]',
			'input[name="billing_email"]',
			'#billing_email'
		];
		var value = '';

		$.each(selectors, function (_, selector) {
			var $field = $(selector).first();

			if ($field.length && $.trim($field.val())) {
				value = $.trim($field.val());
				return false;
			}
		});

		return value;
	}

	function setFeedback($container, message, isError) {
		$container
			.text(message || '')
			.css('color', isError ? '#b32d2e' : '#2271b1');
	}

	$(document).on('click', '.wsvd-apply-customer-coupons', function (event) {
		var settings = window.wsvdAdminAutoCoupons || {};
		var $button = $(this);
		var $wrapper = $button.closest('.wc-order-data-row, .wc-order-totals-items, .inside, .woocommerce_order_items_wrapper');
		var $spinner = $wrapper.find('.wsvd-apply-customer-coupons-spinner').first();
		var $feedback = $wrapper.find('.wsvd-apply-customer-coupons-feedback').first();
		var email = getCustomerEmail();
		var orderId = $button.data('order-id');

		event.preventDefault();
		setFeedback($feedback, '', false);

		if (!email) {
			setFeedback($feedback, settings.missingEmail || 'Inserisci prima l\'email del cliente.', true);
			return;
		}

		$button.prop('disabled', true);
		$spinner.addClass('is-active');

		$.post(settings.ajaxUrl || window.ajaxurl, {
			action: 'wsvd_apply_customer_auto_coupons',
			nonce: settings.nonce,
			order_id: orderId,
			email: email
		})
			.done(function (response) {
				if (!response || !response.success) {
					var errorMessage = response && response.data && response.data.message ? response.data.message : settings.genericError;
					setFeedback($feedback, errorMessage, true);
					return;
				}

				setFeedback($feedback, response.data.message || settings.refreshFallback, false);

				if (response.data.shouldRefresh && window.wc_meta_boxes_order_items && typeof window.wc_meta_boxes_order_items.reload_items === 'function') {
					window.wc_meta_boxes_order_items.reload_items();
					return;
				}

				if (response.data.shouldRefresh) {
					window.location.reload();
				}
			})
			.fail(function (xhr) {
				var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;
				var errorMessage = response && response.data && response.data.message ? response.data.message : settings.genericError;
				setFeedback($feedback, errorMessage || 'Errore inatteso.', true);
			})
			.always(function () {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			});
	});
})(jQuery);
