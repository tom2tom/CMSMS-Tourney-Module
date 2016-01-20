<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Functions involved with twitter communications
*/
class tmtTweet
{
	private $mod; //reference to Tourney module object
	private $twt; //reference to TweetSender object
	//details for default twitter application: CMSMS TourneyModule, owned by @CMSMSTourney
	private $api_key = 'JnUL9AU1RxOW8xIjrBXeZfTnr';
	private $api_secret;
	private $access_token = '2426259434-64ADfkcEKgyUr1BL63HIPLOdCbgmkM6Zjdt55tp';
	private $access_secret;

	function __construct(&$mod,&$twt)
	{
		$this->mod = $mod;
		$this->twt = $twt;
		$this->api_secret = $mod->GetPreference('privapi'); //not yet decoded
		$this->access_secret = $mod->GetPreference('privaccess');
	}

	/**
	DoSend:
	Sends tweet(s) about a match
	@codes: associative array of 4 Twitter access-codes
	@to: array of validated hashtag(s) for recipient(s)
	@tpltxt: smarty template to use for message body
	@tplvars: reference to array of template variables
	Returns: 2-member array -
	 [0] FALSE if no addressee or no twitter module, otherwise boolean cumulative result of twt->Send()
	 [1] '' or error message e.g. from twt->send()
	*/
	private function DoSend($codes,$to,$tpltxt,$tplvars)
	{
		if(!$to)
			return array(FALSE,'');
		if(!$this->twt)
			return array(FALSE,$this->mod->Lang('err_system'));

		$funcs = new tmtUtils();
		$creds = array(
			'api_key'=>$this->api_key,
			'api_secret'=>$funcs->decrypt_value($this->mod,$this->api_secret),
			'access_token'=>$this->access_token,
			'access_secret'=>$funcs->decrypt_value($this->mod,$this->access_secret)
		);
		$body = tmtTemplate::ProcessfromData($this->mod,$tpltxt,$tplvars);

		return $this->twt->Send($creds,FALSE,$to,$body);
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
				$clean = $this->twt->ValidateAddress($one['contact']);
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
		if(!$source || !$this->twt->ValidateAddress($source))
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
	Posts tweets with respective hashtags of tournament owner, and one member of both teams in the match
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@tplvars: reference to array of template variables
	@lines: array of lines for message body
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success
	 [1] '' or specific failure message
	*/
	public function TellOwner(&$bdata,&$mdata,&$tplvars,$lines)
	{
		//owner
		$clean = $this->twt->ValidateAddress($bdata['contact']);
		if($clean)
		{
			$clean[0] = '#';
			$to = array($clean);
		}
		else
			return array(FALSE,''); //silent, try another channel
		$tokens = self::GetTokens($bdata['bracket_id']);
		if(!$tokens)
			return array(FALSE,$this->mod->Lang('lackpermission'));
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
		$tplvars['report'] = implode(' ',$lines);

		$tpltxt = tmtTemplate::Get($this->mod,'tweetin_'.$bdata['bracket_id'].'_template');
		if($tpltxt == FALSE)
			$tpltxt = tmtTemplate::Get($this->mod,'tweetin_default_template');
		return self::DoSend($tokens,$to,$tpltxt,$tplvars);
	}

	/**
	TellTeams:
	Posts tweets with respective hashtags of one or all members of both teams in the match, plus the owner
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@tplvars: reference to array of template variables
	@type: enum for type of message: 1 = announcement, 2 = cancellation, 3 = score-request
	@first: TRUE to send only to first recognised address, FALSE to send per
		the teams' respective contactall settings, optional, default FALSE
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success, or TRUE if nobody to send to
	 [1] '' or specific failure message
	*/
	public function TellTeams(&$bdata,&$mdata,&$tplvars,$type,$first=FALSE)
	{
		switch($type)
		{
		 case 1:
			$tpltxt = tmtTemplate::Get($this->mod,'tweetout_'.$bdata['bracket_id'].'_template');
			if($tpltxt == FALSE)
				$tpltxt = tmtTemplate::Get($this->mod,'tweetout_default_template');
			break;
		 case 2:
			$tpltxt = tmtTemplate::Get($this->mod,'tweetcancel_'.$bdata['bracket_id'].'_template');
			if($tpltxt == FALSE)
				$tpltxt = tmtTemplate::Get($this->mod,'tweetcancel_default_template');
			break;
		 case 3:
			$tpltxt = tmtTemplate::Get($this->mod,'tweetrequest_'.$bdata['bracket_id'].'_template');
			if($tpltxt == FALSE)
				$tpltxt = tmtTemplate::Get($this->mod,'tweetrequest_default_template');
			break;
		}

		$owner = $this->twt->ValidateAddress($bdata['contact']);
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
					$tplvars['recipient'] = key($to);
					$tc = count($to);
					$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
					$tplvars['toall'] = $toall;
					if ((int)$mdata['teamB'] > 0)
						$op = $this->mod->TeamName($mdata['teamB']);
					else
					{
/*					switch($bdata['type'])
						{
						 case Tourney::KOTYPE:
							$op = $this->mod->Lang('anonwinner');
							break;
						 default:
							$op = $this->mod->Lang('anonother');
							break;
						}
*/
						$op = '';
					}
					$tplvars['opponent'] = $op;
					if($owner)
						$to[] = $owner;
					list($resA,$msg) = self::DoSend($tokens,$to,$tpltxt,$tplvars);
					if(!$resA)
					{
						if(!$msg)
							$msg = $this->mod->Lang('err_notice');
						$err .= $this->mod->TeamName($tid).': '.$msg;
					}
				}
				else
					$err = $this->mod->TeamName($tid).': '.$this->mod->Lang('lackpermission');
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
					$tplvars['recipient'] = key($to);
					$tc = count($to);
					$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
					$tplvars['toall'] = $toall;
					if ((int)$mdata['teamA'] > 0)
						$op = $this->mod->TeamName($mdata['teamA']);
					else
					{
/*					switch($bdata['type'])
						{
						 case Tourney::KOTYPE:
							$op = $this->mod->Lang('anonwinner');
							break;
						 default:
							$op = $this->mod->Lang('anonother');
							break;
						}
*/
						$op = '';
					}
					$tplvars['opponent'] = $op;
					if($owner)
						$to[] = $owner;
					list($resB,$msg) = self::DoSend($tokens,$to,$tpltxt,$tplvars);
					if(!$resB)
					{
						if($err) $err .= '<br />';
						if(!$msg)
							$msg = $this->mod->Lang('err_notice');
						$err .= $this->mod->TeamName($tid).': '.$msg;
					}
				}
				else
				{
					if($err) $err .= '<br />';
					$err .= $this->mod->TeamName($tid).': '.$this->mod->Lang('lackpermission');
				}
			}
		}

		if($resA && $resB)
			return array(TRUE,'');
		return array(FALSE,$err);
	}
}

?>
