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
		if (!$container || !$container.length) {
			if (message && isError) {
				window.alert(message);
			}
			return;
		}

		$container.text(message || '').css('color', isError ? '#b32d2e' : '#2271b1');
	}

	function getAutoCouponUi($button) {
		var $currentButton = $button && $button.length ? $button : $('.wsvd-apply-customer-coupons').first();
		var $wrapper = $currentButton.closest('.wc-order-data-row, .wc-order-totals-items, .inside, .woocommerce_order_items_wrapper');

		if (!$wrapper.length) {
			$wrapper = $('#woocommerce-order-items .inside').first();
		}

		return {
			button: $currentButton.length ? $currentButton : $('.wsvd-apply-customer-coupons').first(),
			spinner: $wrapper.find('.wsvd-apply-customer-coupons-spinner').first(),
			feedback: $wrapper.find('.wsvd-apply-customer-coupons-feedback').first()
		};
	}

	function getFieldValue(selectors) {
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

	function getTaxableAddress() {
		var country = getFieldValue(['#_billing_country', '#_shipping_country']);
		var state = getFieldValue(['#_billing_state', '#_shipping_state']);
		var postcode = getFieldValue(['#_billing_postcode', '#_shipping_postcode']);
		var city = getFieldValue(['#_billing_city', '#_shipping_city']);

		return {
			country: String(country || '').toUpperCase(),
			state: String(state || '').toUpperCase(),
			postcode: String(postcode || '').toUpperCase(),
			city: String(city || '').toUpperCase()
		};
	}

	function refreshOrderItemsHtml(html, notesHtml) {
		if (!html) {
			return;
		}

		$('#woocommerce-order-items').find('.inside').empty().append(html);

		if (notesHtml) {
			$('#woocommerce-order-notes').find('.inside').html(notesHtml);
		}

		$('#woocommerce-order-items').trigger('wc_order_items_reloaded');
	}

	$(document).on('click', '.wsvd-apply-customer-coupons', function (event) {
		var settings = window.wsvdAdminAutoCoupons || {};
		var $button = $(this);
		var ui = getAutoCouponUi($button);
		var $spinner = ui.spinner;
		var $feedback = ui.feedback;
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
			email: email,
			country: getTaxableAddress().country,
			state: getTaxableAddress().state,
			postcode: getTaxableAddress().postcode,
			city: getTaxableAddress().city
		})
			.done(function (response) {
				if (!response || !response.success) {
					var errorMessage = response && response.data && response.data.message ? response.data.message : settings.genericError;
					setFeedback($feedback, errorMessage, true);
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
					return;
				}

				refreshOrderItemsHtml(
					response.data && response.data.html ? response.data.html : '',
					response.data && response.data.notes_html ? response.data.notes_html : ''
				);

				var currentUi = getAutoCouponUi($button);
				currentUi.button.prop('disabled', false);
				currentUi.spinner.removeClass('is-active');
			})
			.fail(function (xhr) {
				var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;
				var errorMessage = response && response.data && response.data.message ? response.data.message : settings.genericError;
				setFeedback($feedback, errorMessage || 'Errore inatteso.', true);
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			});
	});
})(jQuery);
