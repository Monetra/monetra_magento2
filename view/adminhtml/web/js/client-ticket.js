define(
	[
		"jquery",
		"Magento_Ui/js/modal/alert",
		"Magento_Sales/order/create/scripts",
		"Magento_Sales/order/create/form",
		'https://test.transafe.com:8665/PaymentFrame/PaymentFrame.js'
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

			submit_order();
		}

		var iframeElement = document.getElementById("monetra-payment-iframe");
		var paymentFormHost = iframeElement.dataset.paymentFormHost;

		var paymentFrame = new PaymentFrame(
			iframeElement.getAttribute('id'),
			paymentFormHost
		);

		paymentFrame.setPaymentSubmittedCallback(handlePaymentTicketResponse);

		paymentFrame.request();


		order.submit = function() {

			iframeElement.contentWindow.postMessage(
				JSON.stringify({ type: "submitPaymentData" }),
				paymentFormHost
			);

		};
	}
);
