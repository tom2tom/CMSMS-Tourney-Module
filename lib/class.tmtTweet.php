<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Functions involved with twitter communications
This class is not suitable for static method-calling
*/
class tmtTweet
{
	private $twt;
	//details for default twitter application: CMSMS TourneyModule, owned by @CMSMSTourney
	private $api_key = 'JnUL9AU1RxOW8xIjrBXeZfTnr';
	private $api_secret = 'MUVQMUJ6ZGZWMGc1TGhYaU9Xbkk0a2tXakJiOThFU2hzOFFRMW5Ca3JYVFJrWUVERVU='; 
	private $access_token = '2426259434-64ADfkcEKgyUr1BL63HIPLOdCbgmkM6Zjdt55tp';
	private $access_secret = 'bEF5R3BqQzBOQ2lUanV3QVBtQUJTOWNTSjNNWnYxWDN2UkFSeDdHVDNuSFdS';

	function ModuleAppTokens()
	{
		return array($this->api_key,base64_decode($this->api_secret));
	}

	/**
	GetTokens:
	@bracket_id: bracket identifier
	@exact: boolean, whether to revert to codes for CMSMSTourney, optional
	@test: boolean, whether to return TRUE or array of codes, optional
	Returns: associative array of Twitter access codes, or FALSE
	*/
	function GetTokens($bracket_id,$exact=FALSE,$test=FALSE)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDB();
		$sql = 'SELECT twtfrom FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
		$handle = $db->GetOne($sql,array($bracket_id));
		if($handle)
		{
			if($handle[0] != '@')
				$handle = '@'.$handle;
			if(!self::ValidateAddress($handle))
				return FALSE;
			if($handle != '@CMSMSTourney')
			{
				$sql = 'SELECT DISTINCT bracket_id,pubtoken,privtoken FROM '.$pref.'module_tmt_tweet WHERE handle=? AND bracket_id IN (?,0)';
				$choices = $db->GetAssoc($sql,array($handle,$bracket_id));
				if(!$choices)
					return FALSE;
				if($test)
					return TRUE;
				if(!isset($choices[$bracket_id]))
					$bracket_id = 0;
				$apub = $choices[$bracket_id]['pubtoken'];
				$apriv = base64_decode($choices[$bracket_id]['privtoken']);
			}
			else
			{
				if($test)
					return TRUE;
				$apub = $this->access_token;
				$apriv = base64_decode($this->access_secret);
			}
		}
		elseif(!$exact)
		{
			if($test)
				return TRUE;
			$apub = $this->access_token;
			$apriv = base64_decode($this->access_secret);
		}
		else
			return FALSE;

		$pub = $this->api_key;
		$priv = base64_decode($this->api_secret);

		return array ('api_key'=>$pub,'api_secret'=>$priv,
			'access_token'=>$apub,'access_secret'=>$apriv);
	}

	/**
	SaveTokens:
	Save Twitter keys.
	At least one of $handle or @bracket_id must be provided
	@pub: public-key to be saved
	@priv: private-key to be saved
	@handle: twitter handle (maybe without leading '@'), optional
	@bracket_id: bracket identifier, optional
	Returns: boolean - whether the save succeeded
	*/
	function SaveTokens($pub,$priv,$handle=FALSE,$bracket_id=0)
	{
		if(!($handle || $bracket_id))
			return FALSE;
		$pref = cms_db_prefix();
		$db = cmsms()->GetDB();
		if(!$handle)
		{
			$sql = 'SELECT twtfrom FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$handle = $db->GetOne($sql,array($bracket_id));
			if(!$handle)
				return FALSE;
		}
		if($handle[0] != '@')
			$handle = '@'.$handle;
		if(!self::ValidateAddress($handle))
			return FALSE;
		$priv = base64_encode($priv); //no point in more sophistication e.g. encryption
		$sql = 'UPDATE '.$pref.'module_tmt_tweet SET pubtoken=?,privtoken=? WHERE bracket_id=? AND handle=?';
		$sql2 = 'INSERT INTO '.$pref.'module_tmt_tweet (bracket_id,handle,pubtoken,privtoken)
SELECT DISTINCT ?,?,?,? FROM '.$pref.'module_tmt_tweet WHERE NOT EXISTS
(SELECT 1 FROM '.$pref.'module_tmt_tweet WHERE bracket_id=? AND handle=?)';
		//minimal race-chance here
		$db->Execute('BEGIN TRANSACTION');
		$db->Execute($sql,array($pub,$priv,$bracket_id,$handle));
		$db->Execute($sql2,array($bracket_id,$handle,$pub,$priv,$bracket_id,$handle));
		$db->Execute('COMMIT');
		if($bracket_id)
		{
			//ensure sender is current
			$sql = 'UPDATE '.$pref.'module_tmt_brackets SET twtfrom=? WHERE bracket_id=?';
			$db->Execute($sql,array($handle,$bracket_id));
		}
		return TRUE;
	}

	/**
	DoSend:
	Sends tweet(s) about a match
	@mod: reference to current module object
	@codes: associative array of 4 Twitter access-codes
	@to: array of validated hashtag(s) for recipient(s)
	@tpl: smarty template to use for message body
	Returns: 2-member array -
	 [0] FALSE if no addressee or no twitter module, otherwise boolean cumulative result of twt->Send()
	 [1] '' or error message e.g. from twt->send()
	*/
	private function DoSend(&$mod,$codes,$to,$tpl)
	{
		if(!$to)
			return array(FALSE,'');
		if(!$this->twt)
		{
			$this->twt = new TTwitter($codes['api_key'],$codes['api_secret'],
				$codes['access_token'],$codes['access_secret']);
			if(!$this->twt)
				return array(FALSE,$mod->Lang('err_system'));
		 }

		$err = '';
		$body = $mod->ProcessDataTemplate($tpl);
		$lb = strlen($body);
		
		foreach($to as $hash)
		{
			$lh = strlen($hash);
			if($lb+$lh <= 139)
				$main = $body;
			else
				$main = substr($body,0,139-$lh);
			try
			{
				$this->twt->send($main.' '.$hash);
			}
			catch (TwitterException $e)
			{
				if($err) $err .= '<br />';
				$err .= $hash.': '.$e->getMessage();
			}
		}
		return array(($err==''),$err);
	}

	/**
	GetTeamContacts:
	@team_id: enumerator of team being processed
	@first: whether to only try for the first relevant tag, optional, default = FALSE
	Returns: array of validated hashtags (maybe empty), or FALSE.
	*/
	private function GetTeamContacts($team_id,$first=FALSE)
	{
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT name,contact FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder';
		$members = $db->GetAll($sql,array($team_id));
		if($members)
		{
			if(!$first)
			{
				$sql = 'SELECT contactall FROM '.$pref.'module_tmt_teams WHERE team_id=?';
				$all = $db->GetOne($sql,array($team_id));
				if(!$all)
					$first = TRUE;
			}
			$sends = array();
			foreach($members as $one)
			{
				$clean = self::ValidateAddress($one['contact']);
				if($clean)
				{
					$clean[0] = '#';
					$sends[] = $clean;
					if($first)
						break;
				}
			}
			return $sends;
		}
		return FALSE;
	}

	/**
	ValidateAddress:
	Check whether @address is a valid twitter handle
	@address: one, or a comma-separated series of, address(es) to check
	Returns: a trimmed valid twitter handle, or array of them, or FALSE
	*/
	public function ValidateAddress($address)
	{
		$pattern = '/^@\w{1,15}$/';
		if(strpos($address,',') === FALSE)
		{
			$address = trim($address);
			if(preg_match($pattern,$address))
				return $address;
		}
		else
		{
			$parts = explode(',',$address);
			$address = array();
			foreach($parts as $one)
			{
				$one = trim($one);
				if(preg_match($pattern,$one))
					$address[] = $one;
			}
			if($address)
				return $address;
		}
		return FALSE;
	}

	/**
	NeedAuth:
	@bracket_id: identifier of the bracket being processed
	@mid: match identifier, or array of them
	Returns: TRUE if temporary send-authority is needed from Twitter
	*/
	public function NeedAuth($bracket_id,$mid)
	{
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT twtfrom FROM '.$pref.'tmt_module_brackets WHERE bracket_id=?';
		$source = $db->GetOne($sql,array($bracket_id));
		if(!$source || !self::ValidateAddress($source))
			return FALSE;
		$sql = 'SELECT teamA,teamB FROM '.$pref.'tmt_module_matches WHERE match_id';
		if(!is_array($mid))
		{
			$mid = array($mid);
			$sql .= '=?';
		}
		else
			$sql .= ' IN ('.str_repeat('?,',count($mids)-1).'?)';
		$teams = $db->GetAll($sql,$mid);
		foreach($teams as $mteam)
		{
			$tid = (int)$mteam['teamA'];
			if($tid > 0 && self::GetTeamContacts($tid))
				return TRUE;
			$tid = (int)$mteam['teamB'];
			if($tid > 0 && self::GetTeamContacts($tid))
				return TRUE;
		}
		return FALSE;
	}

	/**
	TellOwner:
	Sends tweet to tournament owner if possible, and then to one member of both teams in the match
	@mod: reference to current module object
	@smarty: reference to CMSMS smarty object
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@lines: array of lines for message body
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success
	 [1] '' or specific failure message
	*/
	public function TellOwner(&$mod,&$smarty,&$bdata,&$mdata,$lines)
	{
		//owner
		$clean = self::ValidateAddress($bdata['contact']);
		if($clean)
		{
			$clean[0] = '#';
			$to = array($clean);
		}
		else
			return array(FALSE,''); //silent, try another channel
		$tokens = self::GetTokens($bdata['bracket_id']);
		if(!$tokens)
			return array(FALSE,$mod->Lang('lackpermission'));
		//teams
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
		{
			$more = self::GetTeamContacts($tid,TRUE);
			if($more)
				$to = array_merge($to,$more);
		}
		$tid = (int)$mdata['teamB'];
		if($tid > 0)
		{
			$more = self::GetTeamContacts($tid,TRUE);
			if($more)
				$to = array_merge($to,$more);
		}
		//submitted data
		$smarty->assign('report',implode(' ',$lines));

		$tpl = $mod->GetTemplate('tweetin_'.$bdata['bracket_id'].'_template');
		if($tpl == FALSE)
			$tpl = $mod->GetTemplate('tweetin_default_template');
		return self::DoSend($mod,$tokens,$to,$tpl);
	}
	
	/**
	TellTeams:
	Sends tweet to one or all members of both teams in the match, plus the owner
	@mod: reference to current module object
	@smarty: reference to CMSMS smarty object
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@first: TRUE to send only to first recognised address, FALSE to send per
		the teams' respective contactall settings, optional, default FALSE
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success, or TRUE if nobody to send to
	 [1] '' or specific failure message
	*/
	public function TellTeams(&$mod,&$smarty,&$bdata,&$mdata,$first=FALSE)
	{
		$tpl = $mod->GetTemplate('tweetout_'.$bdata['bracket_id'].'_template');
		if($tpl == FALSE)
			$tpl = $mod->GetTemplate('tweetout_default_template');

		$owner = self::ValidateAddress($bdata['contact']);
		if($owner)
			$owner[0] = '#';
		$tokens = self::GetTokens($bdata['bracket_id']);
		$err = '';
		$resA = TRUE; //we're ok if nothing sent
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
		{
			$to = self::GetTeamContacts($tid,$first);
			if($to)
			{
				if($tokens)
				{
					reset($to);
					$smarty->assign('recipient',key($to));
					$tc = count($to);
					$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
					$smarty->assign('toall',$toall);
					$smarty->assign('opponent',$mod->TeamName($mdata['teamB']));
					if($owner)
						$to[] = $owner;
					list($resA,$msg) = self::DoSend($mod,$tokens,$to,$tpl);
					if(!$resA)
					{
						if(!$msg)
							$msg = $mod->Lang('err_notice');
						$err .= $mod->TeamName($tid).': '.$msg;
					}
				}
				else
					$err = $mod->TeamName($tid).': '.$mod->Lang('lackpermission');
			}
		}

		$resB = TRUE;
		$tid = (int)$mdata['teamB'];
		if($tid > 0)
		{
			$to = self::GetTeamContacts($tid,$first);
			if($to)
			{
				if($tokens)
				{
					reset($to);
					$smarty->assign('recipient',key($to));
					$tc = count($to);
					$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
					$smarty->assign('toall',$toall);
					$smarty->assign('opponent',$mod->TeamName($mdata['teamA']));
					if($owner)
						$to[] = $owner;
					list($resB,$msg) = self::DoSend($mod,$tokens,$to,$tpl);
					if(!$resB)
					{
						if($err) $err .= '<br />'; 
						if(!$msg)
							$msg = $mod->Lang('err_notice');
						$err .= $mod->TeamName($tid).': '.$msg;
					}
				}
				else
				{
					if($err) $err .= '<br />';
					$err .= $mod->TeamName($tid).': '.$mod->Lang('lackpermission');
				}
			}
		}
		if($resA && $resB)
			return array(TRUE,'');
		return array(FALSE,$err);
	}
}

?>
