<?php

namespace Monetra\Monetra\Block;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class GenerateApiKeyButton extends Field
{
	protected $_template = 'Monetra_Monetra::generate-api-key.phtml';

	public function render(AbstractElement $element)
	{
		$element->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
		return parent::render($element);
	}

	protected function _getElementHtml(AbstractElement $element)
	{
		return $this->_toHtml();
	}

	public function getAjaxUrl()
	{
		return $this->getUrl('monetra_monetra/apikey/generate');
	}

	public function getButtonHtml()
	{
		$button = $this->getLayout()->createBlock(
			'Magento\Backend\Block\Widget\Button'
		)->setData(
			[
				'id' => 'generate-api-key-button',
				'label' => __('Generate Key')
			]
		);
		return $button->toHtml();
	}
}