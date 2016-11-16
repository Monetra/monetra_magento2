define(
	[
		'jquery',
		'Magento_Ui/js/modal/alert'
	],
	function($, alert) {

		var startReloadInterval = function() {
			var checkout_reload_message_content =
				'For your security, please complete the checkout process in the next ' + 
				'<span style="color:#FF0000;" id="checkout-reload-timer"></span>. ' +
				'If you do not, any information that has been entered on this page will be cleared.';
			
			/* Actual HMAC timeout is 15 minutes, setting this to 1 minute less */
			var hmac_timeout_seconds = 840000;
			
			/* Warning will appear 5 minutes before auto-reload will be triggered */
			var reload_warn_seconds = 540000;
			
			var now = new Date().getTime();
			var reload_expire_time = now + hmac_timeout_seconds;
			var reload_warn_time = now + reload_warn_seconds;
			var reload_warning_is_visible = false;
			var checkout_reload_timer;
			var reload_interval;

			reload_interval = setInterval(function() {
				var current_time = new Date().getTime();
				var total_seconds_left;
				var minutes_left;
				var seconds_left;
				if (current_time >= reload_expire_time) {
					clearInterval(reload_interval);
					window.location.reload();
					return;
				} else if (current_time < reload_expire_time && current_time >= reload_warn_time) {
					total_seconds_left = Math.ceil((reload_expire_time - current_time)/1000);
					minutes_left = String(Math.floor(total_seconds_left/60));
					seconds_left = String(total_seconds_left % 60);
					if (minutes_left.length === 1) {
						minutes_left = "0" + minutes_left;
					}
					if (seconds_left.length === 1) {
						seconds_left = "0" + seconds_left;
					}
					if (!reload_warning_is_visible) {
						$('#checkout-reload-message').html(checkout_reload_message_content);
						checkout_reload_timer = $('#checkout-reload-timer');
						reload_warning_is_visible = true;
					}
					if (reload_warning_is_visible) {
						checkout_reload_timer.html(minutes_left + ':' + seconds_left);
					}
				}
			}, 1000);
		};

		var relevant_response_tag_names = [
			'ticket',
			'monetra_resp_hmacsha256'
		];
		
		var error_alert_params = {
			title: 'Payment Error',
			content: 'An error occurred while attempting to verify payment information. '
		};
		
		startReloadInterval();

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

						var error_message;
						
						/* A status code of 400 means that the Monetra server 
						 * rejected the request. Depending on why this happened,
						 * it's possible that a page reload could resolve the 
						 * problem. The "Ok" button that dismisses the alert will 
						 * reload the page. Also, advise the user that if the 
						 * reload does not solve the problem, they should contact
						 * support.
						 */
						if (jqXHR.status === 400) {
							error_message = 'Please click "OK" to reload the page and try again. ';
							error_message += 'If issue is not resolved, please contact support for assistance.';
							error_alert_params.buttons = [{
								text: $.mage.__('OK'),
								click: function() {
									this.closeModal(true);
									window.location.reload();
								}
							}];
						} else {
							/* In all other cases (browser rejects Monetra server cert,
							 * erroneous Monetra server URL, etc.), advise the user
							 * to contact support. 
							 */
							error_message = 'Please contact support for assistance.';
						}
						
						error_alert_params.content += $.mage.__(error_message);
						
						alert(error_alert_params);
					}
				});
			},
		};
	}
);
