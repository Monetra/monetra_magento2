<?php

namespace Monetra\Monetra\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Model\PaymentToken;
use Magento\Vault\Model\PaymentTokenFactory;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Monetra\Monetra\Helper\MonetraException;
use Monetra\Monetra\Helper\MonetraInterface;

class ClientTicket extends \Magento\Payment\Model\Method\Cc
{
	const METHOD_CODE = 'monetra_client_ticket';
	const VAULT_METHOD_CODE = 'monetra_account_vault';
	const PREAUTH_MAX_AGE_DEFAULT = 2;

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
	private $monetraInterface;
	private $encryptor;
	private $tokenManagement;

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
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Payment\Model\Method\Logger $logger,
		\Magento\Framework\Module\ModuleListInterface $moduleList,
		\Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
		\Magento\Vault\Model\PaymentTokenFactory $paymentTokenFactory,
		\Magento\Vault\Api\PaymentTokenManagementInterface $tokenManagement,
		\Magento\Framework\Encryption\EncryptorInterface $encryptor,
		MonetraInterface $monetraInterface,
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
		$token_public_hash = $this->getInfoInstance()->getAdditionalInformation('token_public_hash');

		$auto_tokenize = $this->getConfigData('auto_tokenize_preauth');
		$customer_selected_tokenize = $this->getInfoInstance()->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);

		if ($auto_tokenize || $customer_selected_tokenize) {
			$tokenize = true;
		} else {
			$tokenize = false;
		}

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
			$this->handleAuthResponse($response, $payment, $paymentToken, $customer_selected_tokenize);
		}

		$transaction_id = $response['ttid'];

		if ($auto_tokenize && !$customer_selected_tokenize && isset($response['token'])) {
			$transaction_id .= "-" . $response['token'];
		}

		// Get Transaction Details
		$transaction = $this->monetraInterface->transaction($transaction_id);
		$expDate = \DateTime::createFromFormat('my', $transaction['expdate'] ?? '0101');
		$expDateMonth = $expDate->format('n') ?? date('n');
		$expDateYear = $expDate->format('Y') ?? date('Y');

		$payment->setTransactionId($transaction_id);
		$payment->setCcApproval($transaction['cv'] ?? 'GOOD');
		$payment->setCcAvsStatus($transaction['avs'] ?? 'GOOD');
		$payment->setCcExpMonth($expDateMonth);
		$payment->setCcExpYear($expDateYear);
		$payment->setIsTransactionClosed(false);

		return $this;
	}

	public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
	{
		$order = $payment->getOrder();

		try {
			$transaction_id = $payment->getParentTransactionId();

			if (!empty($transaction_id)) {

				list($ttid, $token) = self::getTTIDAndTokenFromTransactionId($transaction_id);

				$order_created_at = new \DateTime($order->getCreatedAt());
				$now = new \DateTime();

				$preauth_age_in_hours = $now->diff($order_created_at, true)->h;
				$preauth_max_age_in_days = $this->getConfigData('preauth_max_age');
				if (preg_match("/\d+/", $preauth_max_age_in_days) !== 1) {
					$preauth_max_age_in_days = self::PREAUTH_MAX_AGE_DEFAULT;
				}

				if ($preauth_age_in_hours > $preauth_max_age_in_days * 24) {

					$account_data = ['ttid' => strval($ttid)];
					$response = $this->monetraInterface->sale($account_data, $amount, $order);

					if ($response['code'] === 'AUTH') {

						$this->monetraInterface->void($ttid);

						if (!empty($token)) {
							$this->monetraInterface->deleteToken($token);
						}

					} elseif (($response['msoft_code'] === 'DATA_INVALIDMOD' 
					|| $response['msoft_code'] === 'DATA_RECORDNOTFOUND')
					&& !empty($token)) {

						$account_data = ['token' => strval($token)];
						$response = $this->monetraInterface->sale($account_data, $amount, $order);

						if ($response['code'] === 'AUTH') {

							$this->monetraInterface->deleteToken($token);

						}

					}

				} else {

					$response = $this->monetraInterface->capture($ttid, $order, $amount);

				}

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

		$transaction_id = $response['ttid'];

		// Get Transaction Details
		$transaction = $this->monetraInterface->transaction($transaction_id);
		$expDate = \DateTime::createFromFormat('my', $transaction['expdate'] ?? '0101');
		$expDateMonth = $expDate->format('n') ?? date('n');
		$expDateYear = $expDate->format('Y') ?? date('Y');

		$payment->setTransactionId($transaction_id);
		$payment->setCcApproval($transaction['cv'] ?? 'GOOD');
		$payment->setCcAvsStatus($transaction['avs'] ?? 'GOOD');
		$payment->setCcExpMonth($expDateMonth);
		$payment->setCcExpYear($expDateYear);

		return $this;
	}

	public function void(\Magento\Payment\Model\InfoInterface $payment)
	{
		$transaction_id = $payment->getParentTransactionId();

		list($ttid, $token) = self::getTTIDAndTokenFromTransactionId($transaction_id);

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
		} elseif (!empty($token)) {
			$this->monetraInterface->deleteToken($token);
		}

		return $this;
	}

	public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
	{
		$transaction_id = $payment->getParentTransactionId();

		list($ttid, $token) = self::getTTIDAndTokenFromTransactionId($transaction_id);

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

	private function handleAuthResponse($response, $payment, $paymentToken = null, $customer_selected_tokenize = true)
	{
		if (isset($response['token']) && $customer_selected_tokenize) {
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

	private static function getTTIDAndTokenFromTransactionId($transaction_id)
	{
		if (strpos($transaction_id, "-") === false) {
			$ttid = $transaction_id;
			$token = null;
		} else {
			$transaction_id_parts = explode("-", $transaction_id);
			$ttid = $transaction_id_parts[0];
			$token = $transaction_id_parts[1];
		}
		return [$ttid, $token];
	}

}
