<?php

namespace Monetra\Monetra\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use \Monetra\Monetra\Model\ClientTicket;

class ConfigProvider implements ConfigProviderInterface
{
	private $ticketRequestData;

	public function __construct(
		\Monetra\Monetra\Helper\ClientTicketData $clientTicketData
	) {
		$this->ticketRequestData = $clientTicketData->generateTicketRequestData();
	}

	public function getConfig()
	{
		return [
			'payment' => [
				ClientTicket::METHOD_CODE => $this->ticketRequestData
			]
		];
	}
}
