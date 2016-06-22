<?php

namespace Monetra\Monetra\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

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
				'monetra_client_ticket' => $this->ticketRequestData
			]
		];
	}
}
