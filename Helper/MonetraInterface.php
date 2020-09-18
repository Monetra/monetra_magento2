<?php

namespace Monetra\Monetra\Helper;
use \Monetra\Monetra\Helper\MonetraException;

class MonetraInterface
{

	const TEST_SERVER_URL = 'test.transafe.com';
	const TEST_SERVER_PORT = '443';

	const LIVE_SERVER_URL = 'post.live.transafe.com';
	const LIVE_SERVER_PORT = '443';

	private $origin;
	private $username;
	private $password;

	public function __construct($config_data)
	{
		$this->origin = 'https://' . $config_data['host'] . ':' . $config_data['monetra_port'];
		$this->username = $config_data['monetra_username'];
		$this->password = $config_data['monetra_password'];
	}

	public function authorize($ticket, $amount, $order)
	{
		$cardholdername = $order->getCustomerName();
		$address = $order->getBillingAddress();
		$street = $address->getStreet1();
		$zip = $address->getPostcode();

		$data = [
			'account_data' => [
				'cardshieldticket' => $ticket,
				'cardholdername' => $cardholdername
			],
			'verification' => [
				'street' => strval($street),
				'zip' => strval($zip)
			],
			'money' => [
				'amount' => strval($amount)
			]
		];
		
		return $this->request('POST', 'transaction/preauth', $data);
	}

	public function capture($ttid, $order)
	{
		$order_num = $order->getIncrementId();
		$data = [
			'order' => ['ordernum' => $order_num]
		];
		return $this->request('PATCH', 'transaction/' . $ttid . '/complete', $data);
	}

	public function sale($ticket, $amount, $order)
	{
		$order_num = $order->getIncrementId();
		$cardholdername = $order->getCustomerName();
		$address = $order->getBillingAddress();
		$street = $address->getStreet1();
		$zip = $address->getPostcode();

		$data = [
			'account_data' => [
				'cardshieldticket' => $ticket,
				'cardholdername' => $cardholdername
			],
			'verification' => [
				'street' => strval($street),
				'zip' => strval($zip)
			],
			'money' => [
				'amount' => strval($amount)
			],
			'order' => [
				'ordernum' => strval($order_num)
			]
		];

		return $this->request('POST', 'transaction/purchase', $data);
	}

	public function void($ttid)
	{
		return $this->request('DELETE', 'transaction/' . $ttid . '/void');
	}

	public function refund($ttid, $amount)
	{
		$refund_data = [
			'money' => [
				'amount' => $amount
			]
		];

		/* A "refund" will trigger either a return, reversal, or void,
		 * depending on the circumstances. First check to see if the
		 * transaction in question is still unsettled.
		 */
		$unsettled = $this->request('GET', 'report/unsettled', ['ttid' => $ttid]);

		if (count($unsettled) > 0) {

			/* If transaction is unsettled, only use return if amount specified for return
			 * is less than original transaction amount.
			 */
			if ($amount < $unsettled[0]['amount']) {

				return $this->request('POST', 'transaction/' . $ttid . '/refund', $refund_data);

			} else {

				/* If return amount matches original amount, attempt reversal first.
				 * If that doesn't work, use void.
				 */
				$reversal = $this->request('DELETE', 'transaction/' . $refund_data);

				if ($reversal['code'] === 'AUTH') {
					return $reversal;
				} else {
					return $this->void($ttid);
				}
			}
		} else {

			/* If transaction is settled, use return. */
			return $this->request('POST', 'transaction/' . $ttid . '/refund', $refund_data);

		}
	}

	private function request($method, $path, $data = [])
	{

		$url = $this->origin . '/api/v1/' . $path;

		$username = str_replace(':', '|', $this->username);

		$headers = [
			"Authorization: Basic " . base64_encode($username . ':' . $this->password)
		];

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

		curl_close($curl);

		return json_decode($response, true);
	}

}
