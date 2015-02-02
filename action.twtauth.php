<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
/* NOTE: always come here as redirection from 'default' action
	After callback from Twitter, redirection is needed to allow use of a $returnid
   See also: action.addedit_comp.php :: real_action 'connect'
*/

if(!empty($params['alias']))
{
	$sql = 'SELECT name,twtfrom FROM '.cms_db_prefix().'module_tmt_brackets WHERE alias=?';
	$alias = $params['alias'];
	$bdata = $db->GetRow($sql,array($alias));
}
else
{
	$alias = FALSE;
	$bdata = FALSE;
}

if(isset($params['connect'])) //action initiated
{
	$twt = new tmtTweet();
	list($key,$secret) = $twt->ModuleAppTokens();
	try
	{
		$conn = new TwitterCredential($key,$secret);
		//when returning from twitter, MUST redirect with a valid $returnid, so we don't return here directly
		$backto = 'default';
		//parameters in return-URL sent to twitter
		$keeps = array('tweetauth'=>TRUE,'returnid'=>$returnid);
		if($alias)
			$keeps['alias'] = $alias;
		$url = $this->CreateLink($id,$backto,NULL,NULL,$keeps,NULL,TRUE);
		//cleanup & force a secure return-communication (which freaks the browser first-time,
		// AND stuffs up on-page-include-URLS, requiring a local redirect to fix)
		$callback = str_replace(array($config['root_url'],'amp;'),array($config['ssl_url'],''),$url);
		$name = ($bdata && $bdata['twtfrom']) ? substr($bdata['twtfrom'],1) : FALSE;
		$message = $conn->gogetToken($callback,$name); //should redirect to get token
		//if we're still here, an error occurred
	}
	catch (TwitterException $e)
	{
		$message = $e->getMessage();
	}
}
elseif(isset($params['oauth_verifier'])) //authorisation completed
{
	$twt = new tmtTweet();
	list($key,$secret) = $twt->ModuleAppTokens();
	try
	{
		$conn = new TwitterCredential($key,$secret,$params['oauth_token'],NULL);
		//seek enduring credentials
		$token = $conn->getAuthority($params['oauth_verifier']);
		if(is_array($token))
		{
			$bracket_id = (!empty($params['bracket_id'])) ? $params['bracket_id'] : FALSE;
			if($twt->SaveTokens($token['oauth_token'],
				$token['oauth_token_secret'],$token['screen_name'],$bracket_id))
				$message = $this->Lang('status_complete');
			else
				$message = $this->Lang('err_data');
		}
		else
			$message = $token;
	}
	catch (TwitterException $e)
	{
		$message = $e->getMessage();
	}
}

$smarty->assign('start_form',$this->CreateFormStart($id,'twtauth',$returnid));
$smarty->assign('end_form',$this->CreateFormEnd());
if(!empty($message))
	$smarty->assign('message',$message); //tell about success or failure
$hidden = ($alias) ? $this->CreateInputHidden($id,'alias',$alias) : '';
$smarty->assign('hidden',$hidden);
$smarty->assign('icon',$this->GetModuleURLPath().'/images/twtauth.gif');
$smarty->assign('title',$this->Lang('title_auth'));
if($bdata)
{
	$name = ($bdata['name']) ? $bdata['name'] : $alias;
	$desc = $this->Lang('help_auth1',$name);
	if($bdata['twtfrom'])
		$desc .= '<br />'.$this->Lang('help_auth2',$bdata['twtfrom']);
	$smarty->assign('description',$desc);
}
$smarty->assign('submit',$this->CreateInputSubmit($id,'connect',$this->Lang('connect')));

echo $this->ProcessTemplate('tweet_auth.tpl');

?>
