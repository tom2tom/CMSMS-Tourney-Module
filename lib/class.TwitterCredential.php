<?php

class TwitterCredential extends TTwitter
{
	public function __construct($consumerKey, $consumerSecret, $accessToken = NULL, $accessTokenSecret = NULL) {
		parent::__construct($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
	}

	/**
	 * Redirect to Twitter to get temporary access token
	 * @param string, fully qualified URL to return to from Twitter, best if https://....
	 * @param optional string, account name for inclusion in Twitter sign-in form
	 * @return error message string if redirection impossible
	 */
	public function gogetToken($callback,$name=FALSE) {
		try {
			$results = $this->request(
				'https://api.twitter.com/oauth/request_token', //override parent's URL-constructor
				'POST', array('oauth_callback'=>$callback));
		} catch (TwitterException $e) {
			return $e->getMessage();
		}
		if($results) {
			$token = Twitter_OAuthUtil::parse_parameters($results);
			if (is_array($token) && isset($token['oauth_callback_confirmed'])
					&& $token['oauth_callback_confirmed'] == 'true') {
				$url = 'https://api.twitter.com/oauth/authenticate'.
					'?oauth_consumer_key='.$this->consumer->key.
					'&oauth_token='.$token['oauth_token'].
					'&oauth_token_secret='.$token['oauth_token_secret'].
					'&force_login=1';
				if($name)
					$url .= '&screen_name='.$name;
				header('Location: '.$url);
				exit;
			}
		}
		return 'Twitter authority-request failed';
	}

	/**
	 * Get 'enduring' access token and secret
	 * @param string, token returned by Twitter
	 * @return associative array with keys: oauth_token, oauth_token_secret, screen_name
	 *   or error message string
	 */
	public function getAuthority($verifier) {
		try {
			$results = $this->request(
				'https://api.twitter.com/oauth/access_token', //override parent's URL-constructor
				'POST', array('oauth_verifier' => $verifier));
		} catch (TwitterException $e) {
			return $e->getMessage();
		}
		if($results) {
			$token = Twitter_OAuthUtil::parse_parameters($results);
			if (is_array($token) && isset($token['oauth_token'])) {
				unset($token['user_id']);
				return $token;
			}
		}
		return 'Twitter authority-request failed';
	}
}

?>
