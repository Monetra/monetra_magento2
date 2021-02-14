<?php

namespace Monetra\Monetra\Model\Ui\Admin;

use \Magento\Vault\Api\Data\PaymentTokenInterface;
use \Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use \Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use \Magento\Framework\UrlInterface;
use \Magento\Framework\View\Element\Template;
use \Monetra\Monetra\Model\ClientTicket;

class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{

	private $componentFactory;

	public function __construct(
		TokenUiComponentInterfaceFactory $componentFactory
	) {
		$this->componentFactory = $componentFactory;
	}

	public function getComponentForToken(\Magento\Vault\Api\Data\PaymentTokenInterface $paymentToken)
	{
		$jsonDetails = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
		$component = $this->componentFactory->create(
			[
				'config' => [
					'code' => ClientTicket::VAULT_METHOD_CODE,
					TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
					TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash(),
					'template' => 'Monetra_Monetra::vault.phtml'
				],
				'name' => Template::class
			]
		);

		return $component;
	}
}