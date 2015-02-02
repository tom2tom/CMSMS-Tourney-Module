<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
if (!$this->CheckAccess())
	$this->Redirect($id, 'defaultadmin', '',
		array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));
/*
Arrive here following adminpanel add or edit click, or one of the various actions
initiated from the page setup and displayed here
Determine whether to add a new comp
*/
$add = FALSE;
if (isset($params['real_action']))
{
	//back here after operation initiated from previous iteration (and with js enabled)
	if (empty($params['bracket_id']))
	{
		$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$this->PrettyMessage($params['real_action'],FALSE))); //TODO DEBUG
		if (0) //TODO $params['real_action'] == ?)
			$add = TRUE;
	}
}
elseif(isset($params['action']))
{
	//add|edit initiated from adminpanel (or others if js disabled)
	if (empty($params['bracket_id']))
	{
		$params['real_action'] = 'add';
		$add = TRUE;
	}
	else
		$params['real_action'] = 'edit';
}
else
	$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$this->PrettyMessage('error',FALSE)));

$tab = $this->GetActiveTab($params);
$message = (!empty($params['tmt_message'])) ? $params['tmt_message'] : FALSE;
if ($add)
{
	$funcs = new tmtData();
	$data = $funcs->GetBracketData($this,'add',$id);
	unset($funcs);
	//save initial (non-default) data for bracket
	$args = array(
	'bracket_id' => $data->bracket_id,
	'type' => KOTYPE, //$data-> is empty string
	'seedtype' => $data->seedtype,
	'teamsize' => $data->teamsize,
	'final' => $data->final,
	'semi' => $data->semi,
	'quarter' => $data->quarter,
	'eighth' => $data->eighth,
	'roundname' => $data->roundname,
	'versus' => $data->versus,
	'defeated' => $data->defeated,
	'tied' => $data->tied,
	'bye' => $data->bye,
	'forfeit' => $data->forfeit,
	'nomatch' => $data->nomatch,
	'timezone' => $data->timezone,
	'playgap' => $data->playgap,
	'playgaptype' => $data->playgaptype,
	'match_days' => $data->match_days, 
	'placegap' => $data->placegap,
	'placegaptype' => $data->placegaptype);
	$fields = implode(',',array_keys($args));
	$fillers = str_repeat('?,',count($args)-1).'?';
	$sql = 'INSERT INTO '.cms_db_prefix().'module_tmt_brackets ('.$fields.') VALUES ('.$fillers.');';
	$db->Execute($sql,array_values($args));
	if ($data->motemplate)
		$this->SetTemplate('mailout_'.$data->bracket_id.'_template',$data->motemplate);
	if ($data->mitemplate)
		$this->SetTemplate('mailin_'.$data->bracket_id.'_template',$data->mitemplate);
	if ($data->totemplate)
		$this->SetTemplate('tweetout_'.$data->bracket_id.'_template',$data->totemplate);
	if ($data->titemplate)
		$this->SetTemplate('tweetin_'.$data->bracket_id.'_template',$data->titemplate);
	if ($data->chttemplate)
		$this->SetTemplate('chart_'.$data->bracket_id.'_template',$data->chttemplate);
}
else
{
	$bid = (int)$params['bracket_id'];
	switch ($params['real_action'])
	{
	 case 'result_view':
	 case 'match_view':
		break;
	 case 'connect':
	 	//see also: action.twtauth.php which does the same sorts of things
		$twt = new tmtTweet();
		list($key,$secret) = $twt->ModuleAppTokens();
		try
		{
			$conn = new TwitterCredential($key,$secret);
			$backto = 'addedit_comp'; //come back directly to here
			//parameters needed upon return
			$keeps = array('real_action'=>'fromtwt','bracket_id'=>$bid,'active_tab'=>$tab);
			$url = $this->CreateLink($id,$backto,NULL,NULL,$keeps,NULL,TRUE);
			//cleanup Would be nice to also force https: for return-communication
			// BUT that freaks the browser first time, and stuffs up
			// on-page-include-URLS, requiring a local redirect to fix
			$callback = str_replace('amp;','',$url);
			$name = ($params['tmt_twtfrom']) ? substr($params['tmt_twtfrom'],1) : FALSE;
			$message = $conn->gogetToken($callback,$name); //should redirect to get token
			//if we're still here, an error occurred
		}
		catch (TwitterException $e)
		{
			$message = $e->getMessage();
		}
		if(!empty($message))
			$message = $this->PrettyMessage($message,FALSE,FALSE,FALSE);
		$params['real_action'] = 'edit';
	 	break;
	 case 'fromtwt':
		if(isset($_REQUEST['oauth_verifier'])) //authorisation done
		{
			$twt = new tmtTweet();
			list($key,$secret) = $twt->ModuleAppTokens();
			try
			{
				$conn = new TwitterCredential($key,$secret,$_REQUEST['oauth_token'],NULL);
				//seek enduring credentials
				$token = $conn->getAuthority($_REQUEST['oauth_verifier']);
				if(is_array($token))
				{
					if(!$twt->SaveTokens($token['oauth_token'],
						$token['oauth_token_secret'],$token['screen_name'],$bid))
							$message = $this->Lang('err_data');
				}
				else
					$message = $token;
			}
			catch (TwitterException $e)
			{
				$message = $e->getMessage();
			}
			if(!empty($message))
				$message = $this->PrettyMessage($message,FALSE,FALSE,FALSE);
		}
/*		else
		{
			$pref = cms_db_prefix();
			$sql = 'DELETE FROM '.$pref.'module_tmt_tweet WHERE bracket_id=?';
			$db->Execute($sql,array($bid));
			$message = $this-Lang('TODO');
		}
*/
		$params['real_action'] = 'edit';
		break;
	 default:
		//refresh matches if appropriate
		$pref = cms_db_prefix();
		$sql = 'SELECT COUNT(match_id) AS num FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
		if ($db->GetOne($sql,array($bid)))
		{
			$sch = new tmtSchedule();
			$sql = 'SELECT type FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$type = $db->GetOne($sql,array($bid));
			switch ($type)
			{
			 case DETYPE:
				$sch->UpdateDEMatches($bid);
				break;
			 case RRTYPE:
				$sch->NextRRMatches($this,$bid);
				break;
			 default:
			 //case KOTYPE:
				$sch->UpdateKOMatches($bid);
				break;
			}
			unset($sch);
		}
	}

	$funcs = new tmtData();
	$data = $funcs->GetBracketData($this,$params['real_action'],$id,$bid,$params);
	unset($funcs);

	if (isset ($params['tmt_message']))
		$message = $params['tmt_message'];
}

if ($data)
{
	$funcs = new tmtEditSetup();
	$funcs->Setup($this,$smarty,$data,$id,$returnid,$tab,$message);
	unset($funcs);
	unset($data);
	echo $this->ProcessTemplate('addedit_comp.tpl');
}
else
	$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$this->PrettyMessage('err_data',FALSE)));

?>
