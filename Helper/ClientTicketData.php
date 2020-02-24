<?php

namespace Monetra\Monetra\Helper;

class ClientTicketData extends \Magento\Framework\App\Helper\AbstractHelper
{
	private $_encryptor;

	public function __construct(
		\Magento\Framework\App\Helper\Context $context,
		\Magento\Framework\Encryption\EncryptorInterface $encryptor
	) {
		parent::__construct($context);
		$this->_encryptor = $encryptor;
	}

	public function generateTicketRequestData()
	{
		$username = $this->getConfigValue('monetra_username');
		$password = $this->_encryptor->decrypt($this->getConfigValue('monetra_password'));

		$req_sequence = mt_rand();
		$req_timestamp = time();

		$hmac_fields = [
			'timestamp' => $req_timestamp,
			'domain' => 'https://' . $_SERVER['HTTP_HOST'],
			'sequence' => $req_sequence,
			'username' => $username,
		];
		$css_url = $this->getConfigValue('css_url');
		if (!empty($css_url)) {
			$hmac_fields['css_url'] = $css_url;
		}
		$hmac_fields = array_merge($hmac_fields, [
			'include-cardholdername' => 'no',
			'include-street' => 'no',
			'include-zip' => 'no',
			'expdate-format' => $this->getConfigValue('expdate_format'),
			'auto-reload' => $this->getConfigValue('auto_reload'),
			'autocomplete' => $this->getConfigValue('autocomplete'),
			'include-submit-button' => 'no'
		]);

		$data_to_hash = implode("", $hmac_fields);

		$hmac = hash_hmac('sha256', $data_to_hash, $password);

		$hmac_fields = array_merge(['hmacsha256' => $hmac], $hmac_fields);

		$data = [
			'payment_form_host' => 'https://' . $this->getConfigValue('monetra_host') . ':' . $this->getConfigValue('monetra_port'),
			'hmac_fields' => $hmac_fields
		];
		if (!empty($req_fields)) {
			$data['monetra_req_fields'] = $req_fields;
		}
		return $data;
	}

	private function getConfigValue($key)
	{
		return $this->scopeConfig->getValue('payment/monetra_client_ticket/' . $key, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
	}
}
