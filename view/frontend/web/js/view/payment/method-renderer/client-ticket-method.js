define(
	[
		'Magento_Payment/js/view/payment/cc-form',
		'Magento_Ui/js/modal/alert',
		'Magento_Checkout/js/model/quote',
		'https://test.transafe.com:8665/PaymentFrame/PaymentFrame.js'
	],
	function (Component, alert, quote) {
		'use strict';

		return Component.extend({
			defaults: {
				template: 'Monetra_Monetra/payment/client-ticket-form'
			},
			getCode: function() {
				return 'monetra_client_ticket';
			},
			context: function() {
				return this;
			},

			placeOrderHandler: null,
			validateHandler: null,
			iframeElement: null,
			paymentFormHost: null,

			setPlaceOrderHandler: function(handler) {
				this.placeOrderHandler = handler;
			},

			setValidateHandler: function(handler) {
				this.validateHandler = handler;
			},

			isActive: function() {
				return true;
			},

			getData: function() {
				return {
					'method': this.item.method,
					'additional_data': this.ticketFields
				};
			},

			ticketFields: {},

			appendTicketField: function(key, value) {
				this.ticketFields[key] = value;
			},

			populateIframeAttributes: function(target) {

				var iframe_data = window.checkoutConfig.payment[this.getCode()];
				var hmac_fields = iframe_data.hmac_fields;
				var paymentFrame;

				this.iframeElement = target;
				this.paymentFormHost = iframe_data.payment_form_host;

				for (var key in hmac_fields) {
					this.iframeElement.setAttribute('data-hmac-' + key, hmac_fields[key]);
				}

				paymentFrame = new PaymentFrame(
					this.iframeElement.getAttribute('id'),
					this.paymentFormHost
				);

				paymentFrame.setPaymentSubmittedCallback(this.handlePaymentTicketResponse.bind(this));

				paymentFrame.request();

				quote.paymentMethod.subscribe((function(method_data) {
					if (method_data.method === 'monetra_client_ticket') {
						this.iframeElement.contentWindow.postMessage(
							JSON.stringify({ type: "getHeight" }),
							this.paymentFormHost
						);
					}
				}).bind(this));

			},

			handlePaymentTicketResponse: function(response) {

				if (!this.validateHandler()) {
					return;
				}

				if (response.code !== 'AUTH') {
					alert({
						title: 'Payment Error',
						content: response.verbiage
					});
					return;
				}

				this.appendTicketField('ticket_response_ticket', response.ticket);

				this.placeOrder();

			},

			getPaymentTicket: function() {

				this.iframeElement.contentWindow.postMessage(
					JSON.stringify({ type: "submitPaymentData" }),
					this.paymentFormHost
				);

			}

		});
	}
);
