<?php

namespace Monetra\Monetra\Model;

use \Magento\Framework\DataObject;
use \Magento\Framework\Exception\LocalizedException;
use \Magento\Vault\Model\PaymentToken;
use \Magento\Vault\Model\PaymentTokenFactory;
use \Magento\Vault\Model\Ui\VaultConfigProvider;
use \Monetra\Monetra\Helper\MonetraException;
use \Monetra\Monetra\Helper\MonetraInterface;

class ClientTicket extends \Magento\Payment\Model\Method\Cc
{
	const METHOD_CODE = 'monetra_client_ticket';
	const VAULT_METHOD_CODE = 'monetra_account_vault';

	public $_code = self::METHOD_CODE;

	protected $_isGateway = true;
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = true;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = true;
	protected $_canSaveCc = true;

	private $paymentTokenFactory;
	private $tokenManagement;
	private $monetraInterface;
	private $encryptor;

	private static $cardtypeMap = [
		'MC' => 'MC',
		'VISA' => 'VI',
		'AMEX' => 'AE',
		'DISC' => 'DI',
		'JCB' => 'JCB'
	];

	public function __construct(
		\Magento\Framework\Model\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
		\Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
		\Magento\Payment\Helper\Data $paymentData,
		\Magento\Vault\Model\PaymentTokenFactory $paymentTokenFactory,
		\Magento\Vault\Api\PaymentTokenManagementInterface $tokenManagement,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Payment\Model\Method\Logger $logger,
		\Magento\Framework\Module\ModuleListInterface $moduleList,
		\Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
		\Magento\Framework\Encryption\EncryptorInterface $encryptor,
		\Monetra\Monetra\Helper\MonetraInterface $monetraInterface,
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
		$this->paymentTokenFactory = $paymentTokenFactory;
		$this->tokenManagement = $tokenManagement;
		$this->monetraInterface = $monetraInterface;
		$this->encryptor = $encryptor;
	}

	public function validate()
	{
		return true;
	}

	public function assignData(DataObject $data)
	{
		$additional_data = new DataObject($data->getAdditionalData());
		$info_instance = $this->getInfoInstance();

		$ticket = $additional_data->getData('ticket_response_ticket');

		if (!empty($ticket)) {
			$this->validateResponseHmac($additional_data, $ticket);
			$info_instance->setAdditionalInformation('ticket', $ticket);
		}

		if ($this->vaultIsActive()) {

			$tokenize_selected = $additional_data->getData(VaultConfigProvider::IS_ACTIVE_CODE);
			if (empty($tokenize_selected)) {
				$tokenize_selected = false;
			}
			$info_instance->setAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE, $tokenize_selected);

			$token_public_hash = $additional_data->getData('public_hash');
			if (!empty($token_public_hash)) {
				$info_instance->setAdditionalInformation('token_public_hash', $token_public_hash);
			} else {
				$info_instance->setAdditionalInformation('token_public_hash', null);
			}

		} else {

			$info_instance->setAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE, null);
			$info_instance->setAdditionalInformation('token_public_hash', null);
			
		}

		return $this;
	}

	public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
	{
		$ticket = $this->getInfoInstance()->getAdditionalInformation('ticket');
		$tokenize = $this->getInfoInstance()->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
		$token_public_hash = $this->getInfoInstance()->getAdditionalInformation('token_public_hash');
		try {
			$order = $payment->getOrder();

			if (!empty($token_public_hash)) {
				$paymentToken = $this->getTokenFromPublicHash($token_public_hash, $order->getCustomerId());
				$account_data = ['token' => strval($paymentToken->getGatewayToken())];
			} else {
				$account_data = ['cardshieldticket' => strval($ticket)];
			}

			$response = $this->monetraInterface->authorize($account_data, $amount, $order, $tokenize);

		} catch (MonetraException $e) {
			$this->_logger->critical("Error occurred while attempting Monetra authorization. Details: " . $e->getMessage());
			throw new LocalizedException(__($this->getConfigData('user_facing_error_message')));
		}
		if ($response['code'] !== 'AUTH') {
			if (isset($response['ttid'])) {
				$this->_logger->info(
					sprintf('Monetra authorization failed for TTID %d. Verbiage: %s', $response['ttid'], $response['verbiage'])
				);
			} else {
				$this->_logger->info(
					sprintf('Monetra authorization failed. Verbiage: %s', $response['verbiage'])
				);
			}
			throw new LocalizedException(__($this->getConfigData('user_facing_deny_message')));
		} else {
			if (empty($paymentToken)) {
				$paymentToken = null;
			}
			$this->handleAuthResponse($response, $payment, $paymentToken);
		}

		$payment->setTransactionId($response['ttid']);
		$payment->setIsTransactionClosed(false);

		return $this;
	}

	public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
	{
		$order = $payment->getOrder();
		try {
			$ttid = $payment->getParentTransactionId();
			if (!empty($ttid)) {
				$response = $this->monetraInterface->capture($ttid, $order, $amount);
			} else {
				$ticket = $this->getInfoInstance()->getAdditionalInformation('ticket');
				$tokenize = $this->getInfoInstance()->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
				$token = $this->getInfoInstance()->getAdditionalInformation('token');
				$token_public_hash = $this->getInfoInstance()->getAdditionalInformation('token_public_hash');
				if (!empty($token)) {
					$account_data = ['token' => strval($token)];
				} elseif (!empty($token_public_hash)) {
					$paymentToken = $this->getTokenFromPublicHash($token_public_hash, $order->getCustomerId());
					$account_data = ['token' => strval($paymentToken->getGatewayToken())];
				} else {
					$account_data = ['cardshieldticket' => strval($ticket)];
				}

				$response = $this->monetraInterface->sale($account_data, $amount, $order, $tokenize);
			}
		} catch (MonetraException $e) {
			$this->_logger->critical("Error occurred while attempting Monetra capture. Details: " . $e->getMessage());
			throw new LocalizedException(__($this->getConfigData('user_facing_error_message')));
		}
		if ($response['code'] !== 'AUTH') {
			if (isset($response['ttid'])) {
				$this->_logger->info(
					sprintf('Monetra capture failed for TTID %d. Verbiage: %s', $response['ttid'], $response['verbiage'])
				);
			} else {
				$this->_logger->info(
					sprintf('Monetra capture failed. Verbiage: %s', $response['verbiage'])
				);
			}
			throw new LocalizedException(__($this->getConfigData('user_facing_deny_message')));
		} else {
			if (empty($paymentToken)) {
				$paymentToken = null;
			}
			$this->handleAuthResponse($response, $payment, $paymentToken);
		}

		$payment->setTransactionId($response['ttid']);

		return $this;
	}

	public function void(\Magento\Payment\Model\InfoInterface $payment)
	{
		$ttid = $payment->getParentTransactionId();

		try {
			$response = $this->monetraInterface->void($ttid);
		} catch (MonetraException $e) {
			$this->_logger->critical("Error occurred while attempting Monetra void. Details: " . $e->getMessage());
			throw new LocalizedException(__($this->getConfigData('user_facing_error_message')));
		}

		if ($response['code'] !== 'AUTH') {
			$this->_logger->info(
				sprintf('Monetra void failed for TTID %d. Verbiage: %s', $ttid, $response['verbiage'])
			);
			throw new LocalizedException(__('Void request failed. Details: ' . $response['verbiage']));
		}

		return $this;
	}

	public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
	{
		$ttid = $payment->getParentTransactionId();

		try {
			$response = $this->monetraInterface->refund($ttid, $amount);
		} catch (MonetraException $e) {
			$this->_logger->critical("Error occurred while attempting Monetra refund. Details: " . $e->getMessage());
			throw new LocalizedException(__($this->getConfigData('user_facing_error_message')));
		}

		if ($response['code'] !== 'AUTH') {
			if (isset($response['ttid'])) {
				$this->_logger->info(
					sprintf('Monetra refund failed for TTID %d. Attempted refund TTID is %d. Verbiage: %s', $ttid, $response['ttid'], $response['verbiage'])
				);
			} else {
				$this->_logger->info(
					sprintf('Monetra refund failed for TTID %d. Verbiage: %s', $ttid, $response['verbiage'])
				);
			}
			throw new LocalizedException(__('Refund request failed. Details: ' . $response['verbiage']));
		}

		return $this;
	}

	public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = NULL)
	{
		return $this->getConfigData('active') || $this->getConfigData('active');
	}

	public function vaultMethodTitle()
	{
		return $this->_scopeConfig->getValue('payment/' . self::VAULT_METHOD_CODE . '/title');
	}

	private function handleAuthResponse($response, $payment, $paymentToken = null)
	{
		if (isset($response['token'])) {
			$paymentToken = $this->addTokenToVault($payment, $response);
			$this->getInfoInstance()->setAdditionalInformation('token', $paymentToken->getGatewayToken());
		} elseif (!empty($paymentToken)) {
			$this->applyTokenToPaymentRecord($paymentToken, $payment);
		} elseif (isset($response['cardtype']) && isset($response['account'])) {
			$cardtypeValue = self::$cardtypeMap[$response['cardtype']];
			$accountValue = substr($response['account'], -4);
			$payment->setCcType($cardtypeValue);
			$payment->setCcLast4($accountValue);
		}
		$this->getInfoInstance()->setAdditionalInformation('ticket', null);
	}

	private function applyTokenToPaymentRecord($paymentToken, $payment)
	{
		$extensionAttributes = $payment->getExtensionAttributes();
		if ($extensionAttributes === null) {
			$extensionAttributes = $this->extensionAttributesFactory->create($payment);
			$payment->setExtensionAttributes($extensionAttributes);
		}
		$extensionAttributes->setVaultPaymentToken($paymentToken);
		$jsonDetails = json_decode($paymentToken->getTokenDetails() ? : '{}', true);

		$payment->setCcType($jsonDetails['type']);
		$payment->setCcLast4($jsonDetails['maskedCC']);

	}

	private function addTokenToVault($payment, $response)
	{
		$last_four = substr($response['account'], -4);
		$token = $response['token'];
		$unformattedExpirationDate = $this->monetraInterface->getTokenExpirationDate($token);
		if (array_key_exists($response['cardtype'], self::$cardtypeMap)) {
			$cardtype = self::$cardtypeMap[$response['cardtype']];
		} else {
			$cardtype = $response['cardtype'];
		}

		if (!empty($unformattedExpirationDate)) {
			$expirationDate = \DateTime::createFromFormat('!my', $unformattedExpirationDate);
			$formattedExpirationDate = $expirationDate->format('m/Y');
		} else {
			$formattedExpirationDate = "";
		}

		$paymentToken = $this->paymentTokenFactory->create('card');
		$paymentToken->setGatewayToken($token);
		$paymentToken->setTokenDetails(json_encode([
			'type' => $cardtype,
			'maskedCC' => $last_four,
			'expirationDate' => $formattedExpirationDate
		]));
		$paymentToken->setExpiresAt($expirationDate->add(\DateInterval::createFromDateString('+1 month')));
		
		$this->applyTokenToPaymentRecord($paymentToken, $payment);

		return $paymentToken;
	}

	private function validateResponseHmac($additional_data, $ticket)
	{
		$separate_users = $this->getConfigData('separate_users');
		if ($separate_users) {
			$password = $this->encryptor->decrypt($this->getConfigData('monetra_ticket_password'));
		} else {
			$password = $this->encryptor->decrypt($this->getConfigData('monetra_password'));
		}

		$username = $additional_data->getData('ticket_request_username');
		$sequence = $additional_data->getData('ticket_request_sequence');
		$timestamp = $additional_data->getData('ticket_request_timestamp');

		$response_hmac = $additional_data->getData('ticket_response_hmac');

		$data_to_hash = $username . $sequence . $timestamp . $ticket;

		$hmac_to_compare = hash_hmac('sha256', $data_to_hash, $password);

		if (strtolower($response_hmac) !== strtolower($hmac_to_compare)) {
			$this->_logger->critical("Unable to validate ticket response HMAC");
			throw new LocalizedException(__($this->getConfigData('user_facing_error_message')));
		}
	}

	private function getTokenFromPublicHash($public_hash, $customer_id)
	{
		$paymentToken = $this->tokenManagement->getByPublicHash($public_hash, $customer_id);
		if (!empty($paymentToken)) {
			return $paymentToken;
		} else {
			return null;
		}
	}

	private function vaultIsActive()
	{
		return $this->_scopeConfig->getValue('payment/' . self::VAULT_METHOD_CODE . '/active');
	}

}
