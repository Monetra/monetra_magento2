define([
	'Magento_Vault/js/view/payment/method-renderer/vault',
], function (VaultComponent) {
	'use strict';
	return VaultComponent.extend({

		defaults: {
			template: 'Magento_Vault/payment/form'
		},

		getMaskedCard: function() {
			return this.details.maskedCC;
		},

		getCardType: function() {
			return this.details.type;
		},

		getExpirationDate: function() {
			return this.details.expirationDate || "";
		},

		getData: function() {
			var data = {
				method: 'monetra_client_ticket',
				additional_data: {
					public_hash: this.publicHash
				}
			};
			return data;
		}

	});
});