<?php

/**
 * SocialMedia Twitter class.
 *
 * @package   App
 *
 * @copyright YetiForce Sp. z o.o
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Arkadiusz Adach <a.adach@yetiforce.com>
 */

namespace App\SocialMedia;

/**
 * Class Twitter.
 */
class Twitter extends AbstractSocialMedia
{
	/**
	 * @var string
	 */
	protected static $socialMediaType = 'twitter';
	/**
	 * Tweet mode.
	 *
	 * @link https://developer.twitter.com/en/docs/tweets/tweet-updates.html
	 */
	public const TWEET_MODE = 'extended';
	/**
	 * Turn on/off logs.
	 *
	 * @var bool
	 */
	public static $logOn = true;
	/**
	 * User account name.
	 *
	 * @var string
	 */
	private $userName;

	/**
	 * @var \Abraham\TwitterOAuth\TwitterOAuth
	 */
	private static $twitterConnection = null;

	/**
	 * Is configured.
	 *
	 * @throws \App\Exceptions\AppException
	 *
	 * @return bool
	 */
	public static function isConfigured()
	{
		$configTitter = \App\SocialMedia::getInstance('twitter');
		return !empty($configTitter->get('twitter_api_key')) && !empty($configTitter->get('twitter_api_secret'));
	}

	/**
	 * Twitter constructor.
	 *
	 * @param string $userName
	 *
	 * @throws \App\Exceptions\AppException
	 */
	public function __construct(string $userName)
	{
		$this->userName = $userName;
		if (!\is_object(static::$twitterConnection)) {
			$configTitter = \App\SocialMedia::getInstance('twitter');
			static::$twitterConnection = new \Abraham\TwitterOAuth\TwitterOAuth(
				$configTitter->get('twitter_api_key'),
				$configTitter->get('twitter_api_secret')
			);
			static::$twitterConnection->setDecodeJsonAsArray(true);
		}
	}

	/**
	 * Get all records by twitter account.
	 *
	 * @param array $start
	 * @param int   $start
	 * @param int   $limit
	 *
	 * @return \SocialMedia_Record_Model[]
	 */
	public static function getAllRecords(array $twitterLogin, int $start = 0, int $limit = 50)
	{
		$query = (new \App\Db\Query())->from('u_#__social_media_twitter');
		if (!empty($twitterLogin)) {
			$query->where(['twitter_login' => $twitterLogin]);
		}
		$dataReader = $query->orderBy(['created' => SORT_DESC])
			->limit($limit)
			->offset($start)
			->createCommand()
			->query();
		while (($row = $dataReader->read())) {
			yield $row;
		}
		$dataReader->close();
	}

	/**
	 * {@inheritdoc}
	 */
	public function retrieveDataFromApi()
	{
		if (!\App\RequestUtil::isNetConnection()) {
			$this->logErrorDb('No internet connection');
			return;
		}
		$db = \App\Db::getInstance();
		$maxId = (new \App\Db\Query())
			->from('u_#__social_media_twitter')
			->where(['twitter_login' => $this->userName])
			->max((new \yii\db\Expression('CAST(id_twitter AS INT)')));
		$param['screen_name'] = $this->userName;
		$indexOfText = 'text';
		if (static::TWEET_MODE === 'extended') {
			$param['tweet_mode'] = 'extended';
			$indexOfText = 'full_text';
		}
		if (!\is_null($maxId)) {
			//Retrieve only new data from Api
			//"There are limits to the number of Tweets that can be accessed through the API. If the limit of Tweets has occured since the since_id, the since_id will be forced to the oldest ID available."
			$param['since_id'] = $maxId;
		}
		$this->logInfoDb('Begin downloading new messages');
		$allMessages = $this->getFromApi('statuses/user_timeline', $param);
		$cnt = 0;
		foreach ($allMessages as $rowTwitter) {
			$rowTwitter['id'] = \App\Purifier::encodeHtml($rowTwitter['id']);
			$rowTwitter['created_at'] = \App\Purifier::encodeHtml($rowTwitter['created_at']);
			$rowTwitter[$rowTwitter[$indexOfText]] = \App\Purifier::encodeHtml($rowTwitter[$indexOfText]);
			if (!isset($rowTwitter['user']['name'])) {
				throw new \App\Exceptions\AppException('ERR_NO_VALUE||"user name"');
			}
			$rowTwitter['user']['name'] = \App\Purifier::encodeHtml($rowTwitter['user']['name']);
			if (!(new \App\Db\Query())->from('u_#__social_media_twitter')->where(['id_twitter' => $rowTwitter['id']])->exists()) {
				$db->createCommand()->insert('u_#__social_media_twitter', [
					'id_twitter' => $rowTwitter['id'],
					'twitter_login' => $this->userName,
					'twitter_name' => $rowTwitter['user']['name'],
					'message' => $rowTwitter[$indexOfText],
					'created' => \DateTimeField::convertToUserTimeZone($rowTwitter['created_at'])->format('Y-m-d H:i:s'),
				])->execute();
				$cnt++;
			}
		}
		if ($cnt > 0) {
			$this->logInfoDb($cnt . ' new messages downloaded from');
		} else {
			$this->logInfoDb('No new messages');
		}
	}

	/**
	 * Log info.
	 *
	 * @param $message
	 *
	 * @throws \App\Exceptions\AppException
	 * @throws \yii\db\Exception
	 */
	private function logInfoDb($message)
	{
		if (static::$logOn) {
			static::logInfo('[' . $this->userName . ']: ' . $message);
		}
	}

	/**
	 * Log error.
	 *
	 * @param $message
	 *
	 * @throws \App\Exceptions\AppException
	 * @throws \yii\db\Exception
	 */
	private function logErrorDb($message)
	{
		if (static::$logOn) {
			static::logError('[' . $this->userName . ']: ' . $message);
		}
	}

	/**
	 * Log warning.
	 *
	 * @param $message
	 *
	 * @throws \App\Exceptions\AppException
	 * @throws \yii\db\Exception
	 */
	private function logWarningDb($message)
	{
		if (static::$logOn) {
			static::logWarning('[' . $this->userName . ']: ' . $message);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function remove()
	{
		$this->logInfoDb('Remove account');
		$db = \App\Db::getInstance();
		$db->createCommand()->delete('u_#__social_media_twitter', ['twitter_login' => $this->userName])->execute();
		$db->createCommand()->delete('b_#__social_media_twitter', ['twitter_login' => $this->userName])->execute();
	}

	/**
	 * Make GET requests to the API.
	 *
	 * @param string $path
	 * @param array  $parameters
	 *
	 * @throws \App\Exceptions\AppException
	 *
	 * @return mixed
	 */
	private function getFromApi(string $path, array $parameters = [])
	{
		try {
			$response = static::$twitterConnection->get($path, $parameters);
			if (isset($response['errors'])) {
				if (\is_array($response['errors'])) {
					foreach ($response['errors'] as $error) {
						$errorMessage = \App\Purifier::encodeHtml($error['message']);
						$errorCode = (int) $error['code'];
						$this->logErrorDb('Twitter API error[code: ' . $errorCode . ']: ' . $errorMessage);
					}
				} else {
					$this->logErrorDb('Twitter API unknown error');
				}
				throw new \App\Exceptions\AppException('ERR_API');
			}
			return $response;
		} catch (\Throwable $e) {
			$this->logErrorDb('Twitter API error: ' . $e->getMessage());
		}
		throw new \App\Exceptions\AppException('ERR_API');
	}
}
