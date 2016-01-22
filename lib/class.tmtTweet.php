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

	function __construct(&$mod)
	{
		$this->mod = $mod;
	}

	/**
	DoSend:
	Sends tweet(s) about a match
	@sender: reference to MessageSender object
	@handle: twitter handle of poseter, or FALSE
	@to: array of validated handle(s)
	@tpltxt: smarty template to use for message body
	@tplvars: reference to array of template variables
	Returns: 2-member array -
	 [0] FALSE if no addressee or no twitter module, otherwise boolean cumulative result of twt->Send()
	 [1] '' or error message e.g. from twt->send()
	*/
	private function DoSend(&$sender,$handle,$to,$tpltxt,$tplvars)
	{
		if(!$to)
			return array(FALSE,'');
		if(!$this->twt)
			return array(FALSE,$this->mod->Lang('err_system'));

		$body = tmtTemplate::ProcessfromData($this->mod,$tpltxt,$tplvars);

		return $sender->tweet->Send(array(
			'handle'=>$handle,
			'to'=>$to,
			'body'=>$body));
	}

	/**
	GetTeamContacts:
	@sender: reference to MessageSender object
	@team_id: enumerator of team being processed
	@first: whether to only try for the first relevant tag, optional, default = FALSE
	Returns: array of validated handles, or FALSE.
	*/
	private function GetTeamContacts(&$sender,$team_id,$first=FALSE)
	{
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT contact FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder';
		$contacts = $db->GetCol($sql,array($team_id));
		if($contacts)
		{
			$clean = $sender->ValidateAddress($contacts);
			if(!empty($clean['tweet']))
			{
				if(!$first)
				{
					$sql = 'SELECT contactall FROM '.$pref.'module_tmt_teams WHERE team_id=?';
					if(!$db->GetOne($sql,array($team_id)))
						$first = TRUE;
				}
				if($first)
					return array(reset($clean['tweet']));
				return $clean['tweet'];
			}
		}
		return FALSE;
	}

	/**
	TellOwner:
	Posts tweets with respective hashtags of tournament owner, and one member of both teams in the match
	@sender: reference to MessageSender object
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@tplvars: reference to array of template variables
	@lines: array of lines for message body
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success
	 [1] '' or specific failure message
	*/
	public function TellOwner(&$sender,&$bdata,&$mdata,&$tplvars,$lines)
	{
		//owner
		$clean = $sender->ValidateAddress($bdata['contact']);
		if(!empty($clean['tweet']))
			$to = $clean['tweet'];
		else
			return array(FALSE,''); //silent, try another channel

		$handle = (!empty($bdata['twtfrom'])) ? $bdata['twtfrom'] : FALSE;
		//teams
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
		{
			$more = self::GetTeamContacts($sender,$tid,TRUE);
			if($more)
				$to = array_merge($to,$more);
		}
		$tid = (int)$mdata['teamB'];
		if($tid > 0)
		{
			$more = self::GetTeamContacts($sender,$tid,TRUE);
			if($more)
				$to = array_merge($to,$more);
		}
		//submitted data
		$tplvars['report'] = implode(' ',$lines);

		$tpltxt = tmtTemplate::Get($this->mod,'tweetin_'.$bdata['bracket_id'].'_template');
		if($tpltxt == FALSE)
			$tpltxt = tmtTemplate::Get($this->mod,'tweetin_default_template');
		return self::DoSend($sender,$handle,$to,$tpltxt,$tplvars);
	}

	/**
	TellTeams:
	Posts tweets with respective hashtags of one or all members of both teams in the match, plus the owner
	@sender: reference to MessageSender object
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
	public function TellTeams(&$sender,&$bdata,&$mdata,&$tplvars,$type,$first=FALSE)
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

		$clean = $sender->ValidateAddress($bdata['contact']);
		$owner = (!empty($clean['tweet'])) ? $clean['tweet'] : FALSE;

		$handle = (!empty($bdata['twtfrom'])) ? $bdata['twtfrom'] : FALSE;
		$err = '';
		$resA = TRUE; //we're ok if nothing sent
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
		{
			$to = self::GetTeamContacts($sender,$tid,$first);
			if($to)
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
				list($resA,$msg) = self::DoSend($sender,$handle,$to,$tpltxt,$tplvars);
				if(!$resA)
				{
					if(!$msg)
						$msg = $this->mod->Lang('err_notice');
					$err .= $this->mod->TeamName($tid).': '.$msg;
				}
			}
		}

		$resB = TRUE;
		$tid = (int)$mdata['teamB'];
		if($tid > 0)
		{
			$to = self::GetTeamContacts($sender,$tid,$first);
			if($to)
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
				list($resB,$msg) = self::DoSend($sender,$handle,$to,$tpltxt,$tplvars);
				if(!$resB)
				{
					if($err) $err .= '<br />';
					if(!$msg)
						$msg = $this->mod->Lang('err_notice');
					$err .= $this->mod->TeamName($tid).': '.$msg;
				}
			}
		}

		if($resA && $resB)
			return array(TRUE,'');
		return array(FALSE,$err);
	}
}

?>
