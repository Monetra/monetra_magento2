<?php

namespace Monetra\Monetra\Model\Source;

class PaymentAction implements \Magento\Framework\Data\OptionSourceInterface
{

	public function toOptionArray()
	{
		return [
			[
				'value' => \Monetra\Monetra\Model\ClientTicket::ACTION_AUTHORIZE,
				'label' => __('Authorize Only'),
			],
			[
				'value' => \Monetra\Monetra\Model\ClientTicket::ACTION_AUTHORIZE_CAPTURE,
				'label' => __('Authorize and Capture')
			]
		];
	}
}
