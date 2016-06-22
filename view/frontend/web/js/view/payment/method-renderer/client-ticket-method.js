define(
	[
		'jquery',
		'Magento_Payment/js/view/payment/cc-form',
		'Magento_Checkout/js/model/quote',
		'Monetra_Monetra/js/client-ticket-request',
	],
	function ($, Component, quote, clientTicketRequest) {
		'use strict';

		return Component.extend({
			defaults: {
				template: 'Monetra_Monetra/payment/client-ticket-form'
			},
			getCode: function() {
				return clientTicketRequest.method_code;
			},
			context: function() {
				return this;
			},

			placeOrderHandler: null,
			validateHandler: null,

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

			getClientTicket: function() {

				if (!this.validateHandler()) {
					return;
				}

				var monetra_values = window.checkoutConfig.payment[this.getCode()];
				var post_url = monetra_values.url;
				var values_to_post = monetra_values;

				var submit_order = this.placeOrder.bind(this);
				var append_ticket_field = this.appendTicketField.bind(this);

				var cc_exp_month_input = $('#' + clientTicketRequest.method_code + '_expiration');
				var cc_exp_year_input = $('#' + clientTicketRequest.method_code + '_expiration_yr');
				var exp_date = clientTicketRequest.formatExpDate(cc_exp_month_input, cc_exp_year_input);
				var cvv_input = $('#' + clientTicketRequest.method_code + '_cc_cid');

				delete values_to_post.url;
				values_to_post.account = $('#' + clientTicketRequest.method_code + '_cc_number').val();
				values_to_post.expdate = exp_date;
				values_to_post.zip = quote.billingAddress().postcode;
				if (cvv_input.length > 0) {
					values_to_post.cvv2 = $('#' + clientTicketRequest.method_code + '_cc_cid').val();
				}

				clientTicketRequest.sendRequest(
					post_url,
					values_to_post,
					append_ticket_field,
					submit_order
				);
			}
		});
	}
);
