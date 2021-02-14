define([
	'jquery',
	'uiComponent'
], function ($, Class) {
	'use strict';

	return Class.extend({
		defaults: {
			$selector: null,
			selector: 'edit_form'
		},

		initObservable: function () {

			var self = this;

			self.$selector = $('#' + self.selector);
			self._super();

			self.initEventHandlers();

			return self;
		},

		getCode: function () {
			return this.code;
		},

		initEventHandlers: function () {
			$('#' + this.container).find('[name="payment[token_switcher]"]')
				.on('click', this.selectPaymentMethod.bind(this));
		},

		selectPaymentMethod: function () {
			this.disableEventListeners();
			this.enableEventListeners();
		},

		enableEventListeners: function () {
			this.$selector.on('submitOrder.' + this.getCode(), this.submitOrder.bind(this));
		},

		disableEventListeners: function () {
			this.$selector.off('submitOrder');
		},

		setPaymentDetails: function () {
			this.$selector.find('[name="payment[public_hash]"]').val(this.publicHash);
		},

		submitOrder: function () {
			this.setPaymentDetails();
			$('input[name="payment[method]"][value="monetra_account_vault"]').val("monetra_client_ticket");
			this.$selector.trigger('realOrder');
		}

	});
});