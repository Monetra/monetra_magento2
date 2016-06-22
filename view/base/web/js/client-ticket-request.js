define(
	[
		'jquery',
		'Magento_Ui/js/modal/alert',
	],
	function($, alert) {

		var alert_options = {
			title: 'Payment Error',
			content: 'An error occurred while attempting to verify payment information.'
		};

		var relevant_response_tag_names = [
			'ticket',
			'monetra_resp_hmacsha256'
		];

		return {

			method_code: 'monetra_client_ticket',

			formatExpDate: function(month_field, year_field) {
				var exp_month = month_field.val();
				var exp_year = year_field.val();

				if (exp_month.length === 1) {
					exp_month = '0'.concat(exp_month);
				}
				if (exp_year.length > 2) {
					exp_year = exp_year.substring(2);
				}
				return exp_month.concat(exp_year);
			},

			sendRequest: function(url, data_to_post, append_ticket_field, submit_order) {
				var self = this;
				$.ajax(url, {
					type: 'POST',
					dataType: 'xml',
					data: data_to_post,
					success: function(response) {

						var relevant_response_items = $(response).find('Resp').children().filter(function() {
							return relevant_response_tag_names.indexOf(this.tagName) !== -1;
						});

						$.each(relevant_response_items, function(_, node) {
							var key = 'ticket_response_' + node.tagName;
							var value = node.textContent;
							append_ticket_field(key, value);
						});

						append_ticket_field('ticket_request_sequence', data_to_post.monetra_req_sequence);
						append_ticket_field('ticket_request_timestamp', data_to_post.monetra_req_timestamp);

						$('#' + self.method_code + '_cc_number')
							.add('#' + self.method_code + '_expiration')
							.add('#' + self.method_code + '_expiration_yr')
							.removeAttr('name');

						submit_order();
					},
					error: function(jqXHR) {
						alert(alert_options);
					}
				});
			},
		};
	}
);
