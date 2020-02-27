require(
	[
		"jquery"
	],
	function($) {

		var payment_server_dropdown = $('#payment_us_monetra_client_ticket_payment_server');
		var host_field_container = $('#row_payment_us_monetra_client_ticket_monetra_host');
		var port_field_container = $('#row_payment_us_monetra_client_ticket_monetra_port');

		payment_server_dropdown.change(function() {
			if (payment_server_dropdown.val() === 'custom') {
				host_field_container.show();
				port_field_container.show();
			} else {
				host_field_container.hide();
				port_field_container.hide();
			}
		});

		payment_server_dropdown.change();

	}
);