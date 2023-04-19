require(
	[
		"jquery",
		"Magento_Ui/js/modal/modal",
		"Magento_Ui/js/modal/alert"
	],
	function($, modal, alert) {

		$(function() {

			var apikey_id_field = $('input[id$="monetra_apikey_id"]');
			var apikey_secret_field = $('input[id$="monetra_apikey_secret"]');

			var generate_api_key_button = $('#generate-api-key-button');
			var generate_api_key_modal = $('#generate-api-key-modal');
			var generate_api_key_form_content = $('#generate-api-key-form-content');
			var generate_api_key_form = $('<form></form>')
			var username_input = $('#monetra-username-input');
			var password_input = $('#monetra-password-input');

			var profile_select_container = $('#monetra-profile-select-container');
			var profile_select = $('#monetra-profile-select');

			var mfa_code_input_container = $('#monetra-mfa-code-input-container');
			var mfa_code_input = $('#monetra-mfa-code-input');

			var modal_options = {
				title: 'Generate API Key',
				buttons: [{
					text: 'Submit',
					click: submit_api_key_request
				}],
				clickableOverlay: true
			}

			var popup = modal(modal_options, generate_api_key_modal);

			generate_api_key_button.on('click', function() {
				generate_api_key_modal.modal('openModal');
			});

			generate_api_key_form.attr('id', 'generate-api-key-form');
			generate_api_key_form_content.wrap(generate_api_key_form);

			$('body').on('submit', '#generate-api-key-form', function(e) {
				e.preventDefault();
				submit_api_key_request();
			});

			function submit_api_key_request() {

				var data = {
					username: username_input.val().replace(':', '|'),
					password: password_input.val(),
					mfa_code: mfa_code_input.val(),
					profile_id: profile_select.val()
				};

				$.ajax({
					type: 'POST',
					url: generate_api_key_form_content.data('url'),
					data: data, 
					showLoader: true,
					success: function(response) {

						if (response.success === 1) {

							apikey_id_field.val(response.data.apikey_id);
							apikey_secret_field.val(response.data.apikey_secret);
							generate_api_key_modal.modal('closeModal');

						} else if (typeof response.next_step !== 'undefined') {

							if (response.next_step === 'select_profile') {

								profile_select.find('option:not([value=""])').remove();

								response.data.profiles.forEach(function(profile) {
									var option = $('<option></option>');
									option.attr('value', profile.id);
									option.text(profile.display_name);
									profile_select.append(option);
								});

								profile_select.prop('disabled', false);
								profile_select_container.attr('style', '');

							} else if (response.next_step === 'enter_mfa_code') {

								mfa_code_input.prop('disabled', false);
								mfa_code_input_container.attr('style', '');

							}

						} else {

							alert({
								title: 'Monetra API Key Error',
								content: response.message
							});

						}
					},
					error: function(jqXHR) {
						var response;
						var message;
						if (jqXHR.responseText) {

							response = JSON.parse(jqXHR.responseText);
							message = response.message;

							alert({
								title: 'Monetra API Key Error',
								content: response.message
							});

						}
					},
					dataType: 'JSON'
				});
			}

		});

	}
);