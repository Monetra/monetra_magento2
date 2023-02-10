<?php

namespace Monetra\Monetra\Controller\Adminhtml\ApiKey;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Monetra\Monetra\Helper\MonetraInterface;
use Monetra\Monetra\Model\ClientTicket;

class Generate extends Action
{
	private $resultJsonFactory;
	private $monetraInterface;
	private $configWriter;

	private static $apikey_admin_perms = [
		'TOKEN_ADD',
		'TOKEN_LIST',
		'TOKEN_EDIT',
		'TOKEN_DEL',
		'TRAN_DETAIL',
		'TICKETREQUEST'
	];
	private static $apikey_trans_perms = [
		'SALE',
		'PREAUTH',
		'PREAUTHCOMPLETE',
		'CAPTURE',
		'REFUND',
		'REVERSAL',
		'VOID'
	];

	public function __construct(
		Context $context,
		JsonFactory $resultJsonFactory,
		MonetraInterface $monetraInterface,
		WriterInterface $configWriter
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->monetraInterface = $monetraInterface;
		$this->configWriter = $configWriter;
	}

	public function execute()
	{
		$username = $this->getRequest()->getPostValue('username');
		$password = $this->getRequest()->getPostValue('password');
		$mfa_code = $this->getRequest()->getPostValue('mfa_code');
		$profile_id = $this->getRequest()->getPostValue('profile_id');

		$result = $this->resultJsonFactory->create();

		if (empty($username) || empty($password)) {
			return $result
				->setHttpResponseCode(400)
				->setData([
					'success' => 0, 
					'message' => 'Username and password must be provided.'
				]);
		}

		$credentials = [
			'username' => $username,
			'password' => $password
		];
		if ($mfa_code !== null) {
			$credentials['mfa_code'] = $mfa_code;
		}

		if (empty($profile_id)) {

			$user_info = $this->monetraInterface->request($credentials, 'GET', 'user/permissions');

			if ($user_info['code'] !== 'AUTH') {

				if ($user_info['msoft_code'] === 'ACCT_MFA_REQUIRED') {

					return $result->setData([
						'success' => 0,
						'message' => 'Please enter your multi-factor authentication code.',
						'next_step' => 'enter_mfa_code'
					]);

				} elseif ($user_info['msoft_code'] === 'ACCT_MFA_GENERATE') {

					return $result
						->setHttpResponseCode(403)
						->setData([
							'success' => 0,
							'message' => 'Multi-factor authentication must be set up before a key can be generated.',
						]);

				} elseif ($user_info['msoft_code'] === 'ACCT_PASSEXPIRED') {

					return $result
						->setHttpResponseCode(403)
						->setData([
							'success' => 0,
							'message' => 'Your password has expired. It must be changed before a key can be generated.',
						]);

				} else {

					return $result
						->setHttpResponseCode(401)
						->setData([
							'success' => 0, 
							'message' => 'Credentials are incorrect.',
						]);

				}

			}

			$user_can_list_profiles = false;

			if (isset($user_info['sys_perms'])) {

				$user_sys_perms = explode('|', $user_info['sys_perms']);

				if (in_array('PROFILE_LIST', $user_sys_perms)) {
					$user_can_list_profiles = true;
				}

			}

			if (isset($user_info['profile_id'])) {

				$profile_id = $user_info['profile_id'];

			} elseif ($user_can_list_profiles) {

				$profile_data = $this->monetraInterface->request($credentials, 'GET', 'boarding/profile');

				$profile_list = [];

				foreach ($profile_data['report'] as $profile) {
					$profile_list_item = [
						'id' => $profile['id'],
						'display_name' => $profile['profile_name']
					];
					if (isset($profile['name'])) {
						$profile_list_item['display_name'] .= ' (' . $profile['name'] . ')';
					}
					$profile_list[] = $profile_list_item;
				}

				return $result->setData([
					'success' => 0, 
					'message' => 'Profile must be selected.',
					'next_step' => 'select_profile',
					'data' => ['profiles' => $profile_list]
				]);

			} else {

				return $result
					->setHttpResponseCode(403)
					->setData([
						'success' => 0,
						'message' => 'User does not have profile access.'
					]);

			}

		}

		$apikey_options = [
			'type' => 'profile',
			'name' => 'Magento Key ' . time(),
			'admin_perms' => implode('|', self::$apikey_admin_perms),
			'trans_perms' => implode('|', self::$apikey_trans_perms),
			'expire_sec' => 'infinite',
			'profile_id' => $profile_id
		];
		$apikey_data = $this->monetraInterface->request($credentials, 'POST', 'apikey', $apikey_options);

		if ($apikey_data['code'] !== 'AUTH') {
			return $result
				->setHttpResponseCode(400)
				->setData([
					'success' => 0, 
					'message' => $apikey_data['verbiage']
				]);
		}

		$this->deleteLegacyCredentials();

		return $result->setData([
			'success' => 1, 
			'data' => $apikey_data
		]);

	}

	private function deleteLegacyCredentials()
	{
		$this->configWriter->delete('payment/' . ClientTicket::METHOD_CODE . '/monetra_username');
		$this->configWriter->delete('payment/' . ClientTicket::METHOD_CODE . '/monetra_ticket_username');
		$this->configWriter->delete('payment/' . ClientTicket::METHOD_CODE . '/monetra_post_username');

		$this->configWriter->delete('payment/' . ClientTicket::METHOD_CODE . '/monetra_password');
		$this->configWriter->delete('payment/' . ClientTicket::METHOD_CODE . '/monetra_ticket_password');
		$this->configWriter->delete('payment/' . ClientTicket::METHOD_CODE . '/monetra_post_password');
	}
}