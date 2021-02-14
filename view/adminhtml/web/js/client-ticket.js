var iframeElement = document.getElementById("monetra-payment-iframe");
var paymentFormHost = iframeElement.dataset.paymentFormHost;

define(
	[
		"jquery",
		"Magento_Ui/js/modal/alert",
		"Magento_Sales/order/create/scripts",
		"Magento_Sales/order/create/form",
		paymentFormHost + '/PaymentFrame/PaymentFrame.js'
	],
	function($, alert) {

		var submit_order = order.submit;

		function appendTicketField(key, value) {
			$('<input>')
				.attr('type', 'hidden')
				.attr('name', 'payment[' + key + ']')
				.attr('value', value)
				.appendTo($('#monetra_client_ticket_response_fields'));
		}

		function handlePaymentTicketResponse(response) {

			if (response.code !== 'AUTH') {
				alert({
					title: 'Payment Error',
					content: response.verbiage
				});
				return;
			}

			appendTicketField('ticket_response_ticket', response.ticket);
			appendTicketField('ticket_response_hmac', response.monetra_resp_hmacsha256);
			appendTicketField('ticket_request_sequence', iframeElement.dataset.hmacSequence);
			appendTicketField('ticket_request_timestamp', iframeElement.dataset.hmacTimestamp);
			appendTicketField('ticket_request_username', iframeElement.dataset.hmacUsername);

			submit_order();
		}

		var paymentFrame = new PaymentFrame(
			iframeElement.getAttribute('id'),
			paymentFormHost
		);

		paymentFrame.setPaymentSubmittedCallback(handlePaymentTicketResponse);

		paymentFrame.request();

		$('input[name="payment[method]"]').change(function() {
			if ($(this).val() === 'monetra_client_ticket') {
				iframeElement.contentWindow.postMessage(
					JSON.stringify({ type: "getHeight" }),
					paymentFormHost
				);
			}
		});

		order.submit = function() {
			if (order.paymentMethod === 'monetra_client_ticket') {
				iframeElement.contentWindow.postMessage(
					JSON.stringify({ type: "submitPaymentData" }),
					paymentFormHost
				);
			} else {
				submit_order();
			}
		};
	}
);
