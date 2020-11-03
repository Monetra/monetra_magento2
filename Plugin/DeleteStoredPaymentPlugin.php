<?php

namespace Monetra\Monetra\Plugin;

use \Magento\Vault\Api\Data\PaymentTokenInterface;
use \Magento\Vault\Api\PaymentTokenRepositoryInterface;
use \Monetra\Monetra\Helper\MonetraInterface;

class DeleteStoredPaymentPlugin
{

	public function __construct(
		\Monetra\Monetra\Helper\MonetraInterface $monetraInterface,
		\Psr\Log\LoggerInterface $logger
	) {
		$this->monetraInterface = $monetraInterface;
		$this->logger = $logger;
	}

	public function beforeDelete(PaymentTokenRepositoryInterface $subject, PaymentTokenInterface $paymentToken)
	{
		$token = $paymentToken->getGatewayToken();
		$response = $this->monetraInterface->deleteToken($token);
		if ($response['code'] !== 'AUTH') {
			$this->logger->info('Unable to delete token ' . $token . '. Reason: ' . $response['verbiage']);
		}
		return null;
	}
}