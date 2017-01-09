define(
	[
		"jquery",
		"Monetra_Monetra/js/client-ticket-request",
		"Magento_Ui/js/modal/alert",
		"Magento_Sales/order/create/scripts",
		"Magento_Sales/order/create/form"
	],
	function($, clientTicketRequest, alert) {

		var submit_order = order.submit;
		var response_field_container = $('#monetra_client_ticket_response_fields');

		var append_ticket_field = function(key, value) {
			$('<input>')
				.attr('type', 'hidden')
				.attr('name', 'payment[' + key + ']')
				.attr('value', value)
				.appendTo(response_field_container);
		};

		order.submit = function() {

			var request_field_container = $('#monetra_client_ticket_request_fields');
			var values_to_post = {};
			var cc_exp_month_input = $('#' + clientTicketRequest.method_code + '_expiration');
			var cc_exp_year_input = $('#' + clientTicketRequest.method_code + '_expiration_yr');
			var exp_date = clientTicketRequest.formatExpDate(cc_exp_month_input, cc_exp_year_input);
			var cvv_input = $('#' + clientTicketRequest.method_code + '_cc_cid');
			var zipcode = $('#order-billing_address_postcode').val();
			var street = $('#order-billing_address_street0').val();

			if (typeof zipcode === 'undefined' || zipcode == '') {
				alert({
					content: 'Please enter a billing zip code.'
				});
				return;
			}
			if (typeof street === 'undefined' || street == '') {
				alert({
					content: 'Please enter a billing street address.'
				});
				return;
			}

			request_field_container.children('input').each(function() {
				values_to_post[$(this).attr('name').replace('ticket_', '')] = $(this).val();
			});
			values_to_post.account = $('#' + clientTicketRequest.method_code + '_cc_number').val();
			values_to_post.expdate = exp_date;

			values_to_post.zip = zipcode;
			values_to_post.street = street;

			if (cvv_input.length > 0) {
				values_to_post.cvv2 = $('#' + clientTicketRequest.method_code + '_cc_cid').val();
			}

			clientTicketRequest.sendRequest(
				request_field_container.data('url'),
				values_to_post,
				append_ticket_field,
				submit_order
			);
		};
	}
);
