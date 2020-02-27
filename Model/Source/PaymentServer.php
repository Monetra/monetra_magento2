<?php

namespace Monetra\Monetra\Model\Source;

class PaymentServer implements \Magento\Framework\Option\ArrayInterface
{

	public function toOptionArray()
	{
		return [
			[
				'value' => 'test',
				'label' => __('TranSafe Test Server'),
			],
			[
				'value' => 'live',
				'label' => __('TranSafe Live/Production Server')
			],
			[
				'value' => 'custom',
				'label' => 'Custom'
			]
		];
	}
}
