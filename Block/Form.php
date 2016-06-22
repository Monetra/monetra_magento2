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

	public function getMonetraUrl()
	{
		return $this->ticketRequestData->url;
	}

	public function getMonetraUsername()
	{
		return $this->ticketRequestData->username;
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
		return $this->ticketRequestData->monetra_req_sequence;
	}

	public function getMonetraTimestamp()
	{
		return $this->ticketRequestData->monetra_req_timestamp;
	}

	public function getMonetraHmac()
	{
		return $this->ticketRequestData->monetra_req_hmacsha256;
	}
}
