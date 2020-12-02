<?php

namespace Monetra\Monetra\Helper;

use \Monetra\Monetra\Model\ClientTicket;

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
		$separate_users = $this->getConfigValue('separate_users');
		if ($separate_users) {
			$username = $this->getConfigValue('monetra_ticket_username');
			$password = $this->_encryptor->decrypt($this->getConfigValue('monetra_ticket_password'));
		} else {
			$username = $this->getConfigValue('monetra_username');
			$password = $this->_encryptor->decrypt($this->getConfigValue('monetra_password'));
		}

		$req_sequence = random_int();
		$req_timestamp = time();

		$hmac_fields = [
			'timestamp' => $req_timestamp,
			'domain' => 'https://' . $_SERVER['HTTP_HOST'],
			'sequence' => $req_sequence,
			'username' => $username,
		];
		$css_url = $this->getConfigValue('css_url');
		if (!empty($css_url)) {
			$hmac_fields['css-url'] = $css_url;
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

		$payment_server = $this->getConfigValue('payment_server');
		if ($payment_server === 'custom') {
			$payment_form_host = 'https://' . $this->getConfigValue('monetra_host') . ':' . $this->getConfigValue('monetra_port');
		} elseif ($payment_server === 'live') {
			$payment_form_host = 'https://' . MonetraInterface::LIVE_SERVER_URL . ':' . MonetraInterface::LIVE_SERVER_PORT;
		} else {
			$payment_form_host = 'https://' . MonetraInterface::TEST_SERVER_URL . ':' . MonetraInterface::TEST_SERVER_PORT;
		}

		$data = [
			'payment_form_host' => $payment_form_host,
			'hmac_fields' => $hmac_fields,
			'vault_active' => $this->getVaultConfigValue('active')
		];
		
		return $data;
	}

	private function getConfigValue($key)
	{
		return $this->scopeConfig->getValue('payment/' . ClientTicket::METHOD_CODE . '/' . $key, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
	}

	public function getVaultConfigValue($key)
	{
		return $this->scopeConfig->getValue('payment/' . ClientTicket::VAULT_METHOD_CODE . '/' . $key, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
	}
}
