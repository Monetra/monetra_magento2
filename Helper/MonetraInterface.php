<?php

namespace Monetra\Monetra\Helper;

class MonetraInterface
{
	private $username;
	private $password;

	public function __construct($config_data)
	{
		if (!isset($config_data['host']) || !isset($config_data['port'])
		|| !isset($config_data['username']) || !isset($config_data['password'])) {
			throw new MonetraException(__('Monetra hostname, port, username and password must be provided.'));
		}
		if (!M_InitEngine()) {
			throw new MonetraException(__('Could not initialize engine.'));
		}

		$this->conn = M_InitConn();

		if (!M_SetBlocking($this->conn, 1)) {
			throw new MonetraException(__('Could not set blocking mode.'));
		}

		if (!M_SetSSL($this->conn, $config_data['host'], $config_data['port'])) {
			throw new MonetraException(__('Could not set method to SSL.'));
		}

		$this->username = $config_data['username'];
		$this->password = $config_data['password'];
	}

	public function authorize($ticket, $amount)
	{
		return $this->request([
			'action' => 'sale',
			'capture' => 'no',
			'cardshieldticket' => $ticket,
			'amount' => $amount
		]);
	}

	public function capture($ttid)
	{
		return $this->request([
			'action' => 'capture',
			'ttid' => $ttid
		]);
	}

	public function sale($ticket, $amount)
	{
		return $this->request([
			'action' => 'sale',
			'cardshieldticket' => $ticket,
			'amount' => $amount
		]);
	}

	public function void($ttid)
	{
		return $this->request([
			'action' => 'void',
			'ttid' => $ttid
		]);
	}

	public function refund($ttid, $amount)
	{
		/* A "refund" will trigger either a return, reversal, or void,
		 * depending on the circumstances. First check to see if the
		 * transaction in question is still unsettled.
		 */
		$unsettled = $this->request([
			'ttid' => $ttid,
			'action' => 'admin',
			'admin' => 'gut'
		]);
		if (count($unsettled) > 0) {
			/* If transaction is unsettled, only use return if amount specified for return
			 * is less than original transaction amount.
			 */
			if ($amount < $unsettled[0]['amount']) {
				return $this->request([
					'action' => 'return',
					'ttid' => $ttid,
					'amount' => $amount
				]);
			} else {
				/* If return amount matches original amount, attempt reversal first.
				 * If that doesn't work, use void.
				 */
				$reversal = $this->request([
					'action' => 'reversal',
					'ttid' => $ttid,
					'amount' => $amount
				]);
				if ($reversal['code'] === 'AUTH') {
					return $reversal;
				} else {
					unset($params['amount']);
					return $this->void($ttid);
				}
			}
		} else {
			/* If transaction is settled, use return. */
			return $this->request(array_merge($params, [
				'action' => 'return'
			]));
		}
	}

	private function request($params)
	{
		$params['username'] = $this->username;
		$params['password'] = $this->password;

		if (empty($this->conn['fd'])) {
			if (!M_Connect($this->conn)) {
				throw new MonetraException(__(M_ConnectionError($this->conn)));
			}
		}
		$identifier = M_TransNew($this->conn);
		foreach ($params as $key => $value) {
			M_TransKeyVal($this->conn, $identifier, $key, $value);
		}
		return $this->executeRequest($identifier);
	}

	private function executeRequest($identifier)
	{
		if (!M_TransSend($this->conn, $identifier)) {
			throw new MonetraException(__('Transaction improperly structured.'));
		}
		if (M_ReturnStatus($this->conn, $identifier) === M_SUCCESS) {
			if (M_IsCommaDelimited($this->conn, $identifier)) {
				$csv_string = M_GetCommaDelimited($this->conn, $identifier);
				if ($csv_string === false) {
					throw new MonetraException(__('Transaction does not exist or is incomplete.'));
				}
				$response = $this->parseCSVResponse($csv_string);
			} else {
				$response = $this->parseKeyValueResponse($identifier);
			}
		} else {
			$response = $this->parseKeyValueResponse($identifier);
		}
		M_DeleteTrans($this->conn, $identifier);
		return $response;
	}

	private function parseKeyValueResponse($identifier)
	{
		$keys_and_values = array();
		$keys = M_ResponseKeys($this->conn, $identifier);
		if ($keys === false) {
			throw new MonetraException(__('Specified transaction does not exist or is not yet completed.'));
		}
		for ($i = 0; $i < count($keys); $i++) {
			$key = $keys[$i];
			try {
				$param = M_ResponseParam($this->conn, $identifier, $key);
				if ($param === false) {
					throw new MonetraException(__(sprintf('Could not retrieve param "%s" for transaction "%s".', $key, $identifier)));
				}
				$keys_and_values[$key] = $param;
			} catch (\Exception $e) {
				continue;
			}
		}
		return $keys_and_values;
	}

	private function parseCSVResponse($csv_string)
	{
		$temp_file = tmpfile();
		fwrite($temp_file, mb_convert_encoding($csv_string, 'UTF-8', 'UTF-8'));
		rewind($temp_file);
		$data = array();
		$count = 0;
		while (($row = fgetcsv($temp_file, 0, ',', '"', '"')) !== false) {
			if (!isset($headers)) {
				$headers = $row;
				continue;
			}
			$associative_row = [];
			foreach ($headers as $index => $header) {
				if (isset($row[$index])) {
					$associative_row[$header] = $row[$index];
				}
			}
			$data[] = $associative_row;
			$count++;
		}
		fclose($temp_file);
		return $data;
	}
}
