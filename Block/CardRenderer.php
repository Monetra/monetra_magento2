<?php

namespace Monetra\Monetra\Block;

use \Magento\Vault\Block\AbstractCardRenderer;
use \Monetra\Monetra\Model\ClientTicket;
use \Magento\Vault\Api\Data\PaymentTokenInterface;

class CardRenderer extends AbstractCardRenderer
{

	public function canRender(PaymentTokenInterface $token)
	{
		return $token->getPaymentMethodCode() === ClientTicket::METHOD_CODE;
	}

	public function getNumberLast4Digits()
	{
		return $this->getTokenDetails()['maskedCC'];
	}

	public function getExpDate()
	{
		return $this->getTokenDetails()['expirationDate'];
	}

	public function getIconUrl()
	{
		return $this->getIconForType($this->getTokenDetails()['type'])['url'];
	}

	public function getIconHeight()
	{
		return $this->getIconForType($this->getTokenDetails()['type'])['height'];
	}

	public function getIconWidth()
	{
		return $this->getIconForType($this->getTokenDetails()['type'])['width'];
	}
}