<?php

namespace Monetra\Monetra\Model\Source;

class CcType extends \Magento\Payment\Model\Source\Cctype
{
	public function getAllowedTypes()
	{
		return ['VI', 'MC', 'AE', 'DI', 'JCB', 'OT'];
	}
}
