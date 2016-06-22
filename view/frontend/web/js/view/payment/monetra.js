define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/payment/renderer-list'
	],
	function (
		Component,
		rendererList
	) {
		'use strict';
		rendererList.push(
			{
				type: 'monetra_client_ticket',
				component: 'Monetra_Monetra/js/view/payment/method-renderer/client-ticket-method'
			}
		);
		return Component.extend({});
	}
);
