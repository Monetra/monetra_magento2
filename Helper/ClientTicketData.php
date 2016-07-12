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
		$action = 'admin';
		$admin = 'cardshieldticket';
		$req_sequence = mt_rand();
		$req_timestamp = time();
		$data_to_hash = $username . $action . $admin . $req_sequence . $req_timestamp;
		$password = $this->_encryptor->decrypt($this->getConfigValue('monetra_password'));

		$hmac = hash_hmac('sha256', $data_to_hash, $password);
		return [
			'url' => 'https://' . $this->getConfigValue('monetra_host') . ':' . $this->getConfigValue('monetra_port'),
			'username' => $username,
			'action' => $action,
			'admin' => $admin,
			'monetra_req_timestamp' => $req_timestamp,
			'monetra_req_sequence' => $req_sequence,
			'monetra_req_hmacsha256' => $hmac
		];
	}

	private function getConfigValue($key)
	{
		return $this->scopeConfig->getValue('payment/monetra_client_ticket/' . $key, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
	}
}
