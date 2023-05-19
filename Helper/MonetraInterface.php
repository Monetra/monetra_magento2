<?php

namespace Monetra\Monetra\Helper;
use Monetra\Monetra\Helper\MonetraException;
use Monetra\Monetra\Model\ClientTicket;

class MonetraInterface extends \Magento\Framework\App\Helper\AbstractHelper
{

	const TEST_SERVER_URL = 'test.transafe.com';
	const TEST_SERVER_PORT = '443';

	const LIVE_SERVER_URL = 'post.live.transafe.com';
	const LIVE_SERVER_PORT = '443';

	private $origin;
	private $credentials;
	private $encryptor;
	private $logger;

	public function __construct(
		\Magento\Framework\App\Helper\Context $context,
		\Magento\Framework\Encryption\EncryptorInterface $encryptor,
		\Magento\Payment\Model\Method\Logger $logger
	) {
		parent::__construct($context);
		$this->encryptor = $encryptor;
		$this->logger = $logger;

		$config_data = $this->getMonetraConfigData();

		$this->origin = 'https://' . $config_data['host'] . ':' . $config_data['monetra_port'];
		if (!empty($config_data['monetra_apikey_id']) && !empty($config_data['monetra_apikey_secret'])) {
			$this->credentials = [
				'apikey_id' => $config_data['monetra_apikey_id'],
				'apikey_secret' => $config_data['monetra_apikey_secret']
			];
		} else {
			$this->credentials = [
				'username' => $config_data['monetra_username'],
				'password' => $config_data['monetra_password']
			];
		}
	}

	public function authorize($account_data, $amount, $order, $tokenize = false)
	{
		$order_num = $order->getIncrementId();
		$cardholdername = $order->getCustomerName();
		$address = $order->getBillingAddress();
		$street = $address->getStreetLine(1);
		$zip = $address->getPostcode();
		$tax_amount = $order->getBaseTaxAmount();

		$account_data['cardholdername'] = $cardholdername;

		$data = [
			'account_data' => $account_data,
			'verification' => [
				'street' => strval($street),
				'zip' => strval($zip)
			],
			'money' => [
				'amount' => strval($amount),
				'tax' => strval($tax_amount)
			],
			'order' => [
				'ordernum' => strval($order_num)
			]
		];

		if ($tokenize) {
			$data['tokenize'] = 'yes';
		}
		
		return $this->request($this->credentials, 'POST', 'transaction/preauth', $data);
	}

	public function capture($ttid, $order, $amount = null)
	{
		$order_num = $order->getIncrementId();
		$data = [
			'order' => ['ordernum' => strval($order_num)]
		];
		if ($amount !== null) {
			$data['money']['amount'] = strval($amount);
		}
		return $this->request($this->credentials, 'PATCH', 'transaction/' . $ttid . '/complete', $data);
	}

	public function sale($account_data, $amount, $order, $tokenize = false)
	{
		$order_num = $order->getIncrementId();
		$cardholdername = $order->getCustomerName();
		$address = $order->getBillingAddress();
		$street = $address->getStreetLine(1);
		$zip = $address->getPostcode();
		$tax_amount = $order->getBaseTaxAmount();

		$account_data['cardholdername'] = $cardholdername;

		$data = [
			'account_data' => $account_data,
			'verification' => [
				'street' => strval($street),
				'zip' => strval($zip)
			],
			'money' => [
				'amount' => strval($amount),
				'tax' => strval($tax_amount)
			],
			'order' => [
				'ordernum' => strval($order_num)
			]
		];

		if ($tokenize) {
			$data['tokenize'] = 'yes';
		}

		return $this->request($this->credentials, 'POST', 'transaction/purchase', $data);
	}

	public function void($ttid)
	{
		return $this->request($this->credentials, 'DELETE', 'transaction/' . $ttid . '/void');
	}

	public function getTokenExpirationDate($token)
	{
		$response = $this->request($this->credentials, 'GET', 'vault/account/' . $token);
		if (empty($response) || empty($response['expdate'])) {
			return "";
		}
		return $response['expdate'];
	}

	public function deleteToken($token)
	{
		$response = $this->request($this->credentials, 'DELETE', 'vault/account/' . $token);
		return $response;
	}

	public function refund($ttid, $amount)
	{
		$refund_data = [
			'money' => [
				'amount' => strval($amount)
			]
		];

		/* A "refund" will trigger either a return, reversal, or void,
		 * depending on the circumstances. First check to see if the
		 * transaction in question is still unsettled.
		 */
		$unsettled = $this->request($this->credentials, 'GET', 'report/unsettled', ['ttid' => strval($ttid)]);

		if (isset($unsettled['report']) && count($unsettled['report']) > 0) {

			/* If transaction is unsettled, only use return if amount specified for return
			 * is less than original transaction amount.
			 */
			if ($amount < $unsettled['report'][0]['amount']) {

				return $this->request($this->credentials, 'POST', 'transaction/' . $ttid . '/refund', $refund_data);

			} else {

				/* If return amount matches original amount, attempt reversal first.
				 * If that doesn't work, use void.
				 */
				$reversal = $this->request($this->credentials, 'DELETE', 'transaction/' . $ttid);

				if ($reversal['code'] === 'AUTH') {
					return $reversal;
				} else {
					return $this->void($ttid);
				}
			}
		} else {

			/* If transaction is settled, use return. */
			return $this->request($this->credentials, 'POST', 'transaction/' . $ttid . '/refund', $refund_data);

		}
	}

	public function request($credentials, $method, $path, $data = [])
	{
		$url = $this->origin . '/api/v2/' . $path;

		if (empty($credentials['apikey_id']) || empty($credentials['apikey_secret'])) {

			$username = str_replace(':', '|', $credentials['username']);

			$headers = [
				"Authorization: Basic " . base64_encode($username . ':' . $credentials['password'])
			];
			if (isset($credentials['mfa_code']) && trim($credentials['mfa_code']) !== "") {
				$headers[] = 'X-MFA-CODE: ' . $credentials['mfa_code'];
			}

		} else {

			$headers = [
				'X-API-KEY-ID: ' . $credentials['apikey_id'],
				'X-API-KEY: ' . $credentials['apikey_secret']
			];

		}

		if (!empty($data)) {
			if ($method === 'GET') {
				$url .= '?' . http_build_query($data);
			} else {
				$request_body = json_encode($data);
				$headers[] = "Content-Type: application/json";
				$headers[] = "Content-Length: " . strlen($request_body);
			}
		}

		$curl = curl_init();

		curl_setopt($curl, \CURLOPT_URL, $url);
		curl_setopt($curl, \CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, \CURLOPT_HTTPAUTH, \CURLAUTH_BASIC);
		curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, \CURLOPT_HTTPHEADER, $headers);
		if ($method !== 'GET' && !empty($request_body)) {
			curl_setopt($curl, \CURLOPT_POSTFIELDS, $request_body);
		}

		$response = curl_exec($curl);

		$curl_error_code = curl_errno($curl);

		if ($curl_error_code !== 0) {
			$this->logger->info(
				'cURL error message from attempted Monetra request to ' . $url . ': ' . curl_error($curl)
			);
			$this->logger->info(
				'cURL error code from attempted Monetra request to ' . $url . ': ' . $curl_error_code
			);
		}

		curl_close($curl);

		return json_decode($response, true);
	}

	private function getMonetraConfigData()
	{
		$payment_server = $this->getConfigValue('payment_server');
		if ($payment_server === 'custom') {
			$host = $this->getConfigValue('monetra_host');
			$monetra_port = $this->getConfigValue('monetra_port');
		} elseif ($payment_server === 'live') {
			$host = self::LIVE_SERVER_URL;
			$monetra_port = self::LIVE_SERVER_PORT;
		} else {
			$host = self::TEST_SERVER_URL;
			$monetra_port = self::TEST_SERVER_PORT;
		}

		$config_data = [
			'host' => $host,
			'monetra_port' => $monetra_port
		];
		
		$apikey_id = $this->getConfigValue('monetra_apikey_id');
		$encrypted_apikey_secret = $this->getConfigValue('monetra_apikey_secret');

		if (empty($apikey_id) || empty($encrypted_apikey_secret)) {

			$separate_users = $this->getConfigValue('separate_users');
			if ($separate_users) {
				$username = $this->getConfigValue('monetra_post_username');
				$encrypted_password = $this->getConfigValue('monetra_post_password');
			} else {
				$username = $this->getConfigValue('monetra_username');
				$encrypted_password = $this->getConfigValue('monetra_password');
			}

			$config_data['monetra_username'] = $username;
			$config_data['monetra_password'] = $this->encryptor->decrypt($encrypted_password);
		
		} else {

			$config_data['monetra_apikey_id'] = $apikey_id;
			$config_data['monetra_apikey_secret'] = $this->encryptor->decrypt($encrypted_apikey_secret);

		}

		return $config_data;
	}

	private function getConfigValue($key)
	{
		return $this->scopeConfig->getValue('payment/' . ClientTicket::METHOD_CODE . '/' . $key, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
	}

}
