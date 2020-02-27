<?php

namespace Monetra\Monetra\Model;

use \Magento\Framework\DataObject;
use \Magento\Framework\Exception\LocalizedException;
use \Monetra\Monetra\Helper\MonetraException;
use \Monetra\Monetra\Helper\MonetraInterface;

class ClientTicket extends \Magento\Payment\Model\Method\Cc
{
	const METHOD_CODE = 'monetra_client_ticket';

	public $_code = self::METHOD_CODE;

	protected $_isGateway = true;
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = true;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = true;

	private $_encryptor;

	public function __construct(
		\Magento\Framework\Model\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
		\Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
		\Magento\Payment\Helper\Data $paymentData,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Payment\Model\Method\Logger $logger,
		\Magento\Framework\Module\ModuleListInterface $moduleList,
		\Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
		\Magento\Framework\Encryption\EncryptorInterface $encryptor,
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
		\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
		array $data = []
	) {
		parent::__construct(
			$context,
			$registry,
			$extensionFactory,
			$customAttributeFactory,
			$paymentData,
			$scopeConfig,
			$logger,
			$moduleList,
			$localeDate,
			$resource,
			$resourceCollection,
			$data
		);
		$this->_encryptor = $encryptor;
	}

	public function validate()
	{
		return true;
	}

	public function assignData(DataObject $data)
	{
		$additional_data = new DataObject($data->getAdditionalData());
		$info_instance = $this->getInfoInstance();
		$info_instance->setAdditionalInformation('ticket', $additional_data->getData('ticket_response_ticket'));
		return $this;
	}

	public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
	{
		$ticket = $this->getInfoInstance()->getAdditionalInformation('ticket');
		try {
			$monetra = new MonetraInterface($this->getMonetraConfigData());
			$order = $payment->getOrder();
			$response = $monetra->authorize($ticket, $amount, $order);
		} catch (MonetraException $e) {
			$this->_logger->critical("Error occurred while attempting Monetra authorization. Details: " . $e->getMessage());
			throw new LocalizedException(__($this->_scopeConfig->getValue('payment/monetra_client_ticket/user_facing_error_message')));
		}

		if ($response['code'] !== 'AUTH') {
			$this->_logger->info(
				sprintf('Monetra authorization failed for TTID %d. Verbiage: %s', $response['ttid'], $response['verbiage'])
			);
			throw new LocalizedException(__($this->_scopeConfig->getValue('payment/monetra_client_ticket/user_facing_deny_message')));
		}

		$payment->setTransactionId($response['ttid']);
		$payment->setIsTransactionClosed(false);

		return $this;
	}

	public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
	{
		$order = $payment->getOrder();
		try {
			$monetra = new MonetraInterface($this->getMonetraConfigData());

			$ttid = $payment->getParentTransactionId();

			if (!empty($ttid)) {
				$response = $monetra->capture($ttid, $order);
			} else {
				$ticket = $this->getInfoInstance()->getAdditionalInformation('ticket');
				$response = $monetra->sale($ticket, $amount, $order);
			}
		} catch (MonetraException $e) {
			$this->_logger->critical("Error occurred while attempting Monetra capture. Details: " . $e->getMessage());
			throw new LocalizedException(__($this->_scopeConfig->getValue('payment/monetra_client_ticket/user_facing_error_message')));
		}
		if ($response['code'] !== 'AUTH') {
			$this->_logger->info(
				sprintf('Monetra capture failed for TTID %d. Verbiage: %s', $response['ttid'], $response['verbiage'])
			);
			throw new LocalizedException(__($this->_scopeConfig->getValue('payment/monetra_client_ticket/user_facing_deny_message')));
		}

		$payment->setTransactionId($response['ttid']);

		return $this;
	}

	public function void(\Magento\Payment\Model\InfoInterface $payment)
	{
		$ttid = $payment->getParentTransactionId();

		try {
			$monetra = new MonetraInterface($this->getMonetraConfigData());
			$response = $monetra->void($ttid);
		} catch (MonetraException $e) {
			$this->_logger->critical("Error occurred while attempting Monetra void. Details: " . $e->getMessage());
			throw new LocalizedException(__($this->_scopeConfig->getValue('payment/monetra_client_ticket/user_facing_error_message')));
		}

		if ($response['code'] !== 'AUTH') {
			$this->_logger->info(
				sprintf('Monetra void failed for TTID %d. Verbiage: %s', $response['ttid'], $response['verbiage'])
			);
			throw new LocalizedException(__('Void request failed. Details: ' . $response['verbiage']));
		}

		return $this;
	}

	public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
	{
		$ttid = $payment->getParentTransactionId();

		try {
			$monetra = new MonetraInterface($this->getMonetraConfigData());
			$response = $monetra->refund($ttid, $amount);
		} catch (MonetraException $e) {
			$this->_logger->critical("Error occurred while attempting Monetra refund. Details: " . $e->getMessage());
			throw new LocalizedException(__($this->_scopeConfig->getValue('payment/monetra_client_ticket/user_facing_error_message')));
		}

		if ($response['code'] !== 'AUTH') {
			$this->_logger->info(
				sprintf('Monetra refund failed for TTID %d. Verbiage: %s', $response['ttid'], $response['verbiage'])
			);
			throw new LocalizedException(__('Refund request failed. Details: ' . $response['verbiage']));
		}

		return $this;
	}

	private function getMonetraConfigData()
	{
		$payment_server = $this->getConfigData('payment_server');
		if ($payment_server === 'custom') {
			$host = $this->getConfigData('monetra_host');
			$port = $this->getConfigData('monetra_port');
		} elseif ($payment_server === 'live') {
			$host = MonetraInterface::LIVE_SERVER_URL;
			$port = MonetraInterface::LIVE_SERVER_PORT;
		} else {
			$host = MonetraInterface::TEST_SERVER_URL;
			$port = MonetraInterface::TEST_SERVER_PORT;
		}
		return [
			'host' => $host,
			'port' => $port,
			'username' => $this->getConfigData('monetra_username'),
			'password' => $this->_encryptor->decrypt($this->getConfigData('monetra_password'))
		];
	}
}
