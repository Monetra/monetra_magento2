<?php

namespace Monetra\Monetra\Model\Source;

class RequiredPaymentFields implements \Magento\Framework\Option\ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'account', 'label' => 'Account Number'],
			['value' => 'expdate', 'label' => 'Expiration Date'],
			['value' => 'cardholdername', 'label' => 'Cardholder Name'],
			['value' => 'cv', 'label' => 'Card Verification Value'],
			['value' => 'street', 'label' => 'Street Address'],
			['value' => 'zip', 'label' => 'Zip Code']
		];
	}
}
