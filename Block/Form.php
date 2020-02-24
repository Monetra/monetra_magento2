<?php

namespace Monetra\Monetra\Block;

class Form extends \Magento\Payment\Block\Form\Cc
{
	private $ticketRequestData;

	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Magento\Payment\Model\Config $paymentConfig,
		\Monetra\Monetra\Helper\ClientTicketData $clientTicketData,
		array $data = []
	) {
		parent::__construct($context, $paymentConfig, $data);
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

	public function getMonetraUsername()
	{
		return $this->ticketRequestData->hmac_fields['username'];
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
	
	public function getMonetraFields()
	{
		return $this->ticketRequestData->monetra_req_fields;
	}

	public function getMonetraHmac()
	{
		return $this->ticketRequestData->hmac_fields['hmacsha256'];
	}
}
