<?php

namespace Monetra\Monetra\Model\Source;

class ExpDateFormat implements \Magento\Framework\Option\ArrayInterface
{

	public function toOptionArray()
	{
		return [
			[
				'value' => 'single-text',
				'label' => __('Freeform text entry (with auto MM/YY formatting)'),
			],
			[
				'value' => 'separate-selects',
				'label' => __('Two dropdown select elements, one for month and one for year')
			],
			[
				'value' => 'coupled-selects',
				'label' => __('Two dropdown select elements, one for month and one for year, inside a container element (for styling purposes)')
			]
		];
	}
}
