<?php

namespace Monetra\Monetra\Block;

use \Magento\Vault\Model\Ui\VaultConfigProvider;

class Form extends \Magento\Payment\Block\Form\Cc
{
	private $ticketRequestData, $vaultEnabled;

	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Magento\Payment\Model\Config $paymentConfig,
		\Monetra\Monetra\Helper\ClientTicketData $clientTicketData,
		array $data = []
	) {
		parent::__construct($context, $paymentConfig, $data);
		$this->vaultEnabled = $clientTicketData->getVaultConfigValue('active');
		$this->ticketRequestData = (object) $clientTicketData->generateTicketRequestData();
	}

	public function getHostDomain()
	{
		return $this->ticketRequestData->hmac_fields['domain'];
	}

	public function getMonetraUrl()
	{
		return $this->ticketRequestData->payment_form_host;
	}

	public function usingApiKey()
	{
		$hmac_fields = $this->ticketRequestData->hmac_fields;
		return array_key_exists('auth_apikey_id', $hmac_fields);
	}

	public function getMonetraUsername()
	{
		return $this->ticketRequestData->hmac_fields['username'];
	}

	public function getMonetraApiKeyId()
	{
		return $this->ticketRequestData->hmac_fields['auth_apikey_id'];
	}

	public function getMonetraAction()
	{
		return $this->ticketRequestData->action;
	}

	public function getMonetraAdmin()
	{
		return $this->ticketRequestData->admin;
	}

	public function getMonetraSequence()
	{
		return $this->ticketRequestData->hmac_fields['sequence'];
	}

	public function getMonetraTimestamp()
	{
		return $this->ticketRequestData->hmac_fields['timestamp'];
	}

	public function getCssUrl() 
	{
		if (array_key_exists('css-url', $this->ticketRequestData->hmac_fields)) {
			return $this->ticketRequestData->hmac_fields['css-url'];
		}
		return '';
	}

	public function getExpdateFormat()
	{
		return $this->ticketRequestData->hmac_fields['expdate-format'];
	}

	public function getAutoReload()
	{
		return $this->ticketRequestData->hmac_fields['auto-reload'];
	}

	public function getAutocomplete()
	{
		return $this->ticketRequestData->hmac_fields['autocomplete'];
	}

	public function getMonetraHmac()
	{
		return $this->ticketRequestData->hmac_fields['hmacsha256'];
	}

	public function isVaultEnabled()
	{
		return $this->vaultEnabled;
	}

	public function getVaultIsActiveCode()
	{
		return VaultConfigProvider::IS_ACTIVE_CODE;
	}
}
