var method_code = 'monetra_client_ticket';
var iframe_data = window.checkoutConfig.payment[method_code];
var payment_form_host = iframe_data.payment_form_host;

define(
	[
		'Magento_Payment/js/view/payment/cc-form',
		'Magento_Vault/js/view/payment/vault-enabler',
		'Magento_Ui/js/modal/alert',
		'Magento_Checkout/js/model/quote',
		payment_form_host + '/PaymentFrame/PaymentFrame.js'
	],
	function (Component, VaultEnabler, alert, quote) {
		'use strict';

		return Component.extend({
			defaults: {
				template: 'Monetra_Monetra/payment/client-ticket-form'
			},

			storeAccountSelected: true,

			initialize: function () {
				var self = this;
				self._super();
				this.vaultEnabler = new VaultEnabler();
				this.vaultEnabler.setPaymentCode(this.getVaultCode());
				return self;
			},

			getCode: function() {
				return method_code;
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
				var data = {
					'method': this.item.method,
					'additional_data': this.ticketFields
				};
				if (iframe_data.vault_active) {
					this.vaultEnabler.visitAdditionalData(data);
				}
				return data;
			},

			isVaultEnabled: function () {
				return iframe_data.vault_active == 1;
			},

			getVaultCode: function () {
				return 'monetra_account_vault';
			},

			ticketFields: {},

			appendTicketField: function(key, value) {
				this.ticketFields[key] = value;
			},

			populateIframeAttributes: function(target) {

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
				this.appendTicketField('ticket_request_sequence', iframe_data.hmac_fields.sequence);
				this.appendTicketField('ticket_request_timestamp', iframe_data.hmac_fields.timestamp);
				this.appendTicketField('ticket_request_auth_apikey_id', iframe_data.hmac_fields.auth_apikey_id);

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
