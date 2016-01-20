<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Functions involved with SMS communications
*/
class tmtSMS
{
	private $mod; //reference to Tourney module object
	private $text; //reference to SMSSender object

	function __construct(&$mod,&$text)
	{
		$this->mod = $mod;
		$this->text = $text;
	}

	/**
	DoSend:
	Sends SMS(s) about a match
	@prefix: country-code to be prepended to destination phone-numbers, or
		name of country to be used to look up that code
	@to: array of validated phone-no(s) for recipient(s)
	@cc: array of validated phone-no(s) for bracket owner(s), or FALSE
	@from: validated phone-no to be used (if possible) as sender, or FALSE
	@tpl: smarty template to use for message body
	@tplvars: reference to array of template variables
	Returns: 2-member array -
	 [0] FALSE if no addressee or no SMSG-module gateway, otherwise boolean cumulative result of gateway->send()
	 [1] '' or error message e.g. from gateway->send() to $to ($cc errors ignored)
	*/
	private function DoSend($prefix,$to,$cc,$from,$tpl,$tplvars)
	{
		if(!($to || $cc))
			return array(FALSE,'');
		if(!$this->text)
			return array(FALSE,$this->mod->Lang('err_system'));

		$body = tmtTemplate::ProcessfromData($this->mod,$tpl,$tplvars);
		if(!$body || !$this->utils->text_is_valid($body))
			return array(FALSE,$this->mod->Lang('err_text').' \''.$body.'\'');

		if($cc)
		{
			foreach($cc as $num)
			{
				if(!(in_array($num,$to) || $num == $from))
					$to[] = $num;
			}
		}
		return $this->text->Send(array(
			'prefix'=>$prefix,
			'to'=>$to,
			'from'=>FALSE,
			'body'=>$body));
	}

	/**
	GetTeamContacts:
	@team_id: enumerator of team being processed
	@prefix: default country-code for phone-numbers to receive text
	@pattern: regex for matching acceptable phone nos, defaults to module preference
	@first: whether to only try for the first relevant tag, optional, default = FALSE
	Returns: array of validated phone nos (maybe empty), or FALSE.
	*/
	private function GetTeamContacts($team_id,$prefix,$pattern,$first=FALSE)
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
				$clean = $this->text->ValidateAddress($one['contact'],$prefix,$pattern);
				if($clean)
				{
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
	TellOwner:
	Sends SMS to tournament owner if possible, and then to one member of both teams
	in the match, using same template as for tweets
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
		//owner(s)
		$to = $this->text->ValidateAddress($bdata['contact'],$bdata['smsprefix'],$bdata['smspattern']);
		if($to)
		{
			if(!is_array($to))
				$to = array($to);
		}
		else
			return array(FALSE,''); //silent, try another channel
		//teams
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
		{
			$more = self::GetTeamContacts($tid,$bdata['smsprefix'],$bdata['smspattern'],TRUE);
			if($more)
				$to = array_merge($to,$more);
		}
		$tid = (int)$mdata['teamB'];
		if($tid > 0)
		{
			$more = self::GetTeamContacts($tid,$bdata['smsprefix'],$bdata['smspattern'],TRUE);
			if($more)
				$to = array_merge($to,$more);
		}
		//maybe >1 owner
		$from = $this->text->ValidateAddress($bdata['smsfrom'],$bdata['smsprefix'],$bdata['smspattern']);
		if(is_array($from))
			$from = reset($from);

		//submitted data
		$tplvars['report'] = implode(' ',$lines);

		$tpl = tmtTemplate::Get($this->mod,'tweetin_'.$bdata['bracket_id'].'_template');
		if($tpl == FALSE)
			$tpl = tmtTemplate::Get($this->mod,'tweetin_default_template');
		return self::DoSend($bdata['smsprefix'],$to,FALSE,$from,$tpl,$tplvars);
	}

	/**
	TellTeams:
	Sends SMS to one or all members of both teams in the match, plus the owner,
	using same template as for tweets
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@tplvars: reference to array of template variables
	@tpl: enum for type of message: 1 = announcement, 2 = cancellation, 3 = score-request
	@first: TRUE to send only to first recognised address, FALSE to send per
		the teams' respective contactall settings, optional, default FALSE
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success, or TRUE if nobody to send to
	 [1] '' or specific failure message
	*/
	public function TellTeams(&$bdata,&$mdata,&$tplvars,$tpl,$first=FALSE)
	{
		switch($tpl)
		{
		 case 1:
			$tpl = tmtTemplate::Get($this->mod,'tweetout_'.$bdata['bracket_id'].'_template');
			if($tpl == FALSE)
				$tpl = tmtTemplate::Get($this->mod,'tweetout_default_template');
			break;
		 case 2:
			$tpl = tmtTemplate::Get($this->mod,'tweetcancel_'.$bdata['bracket_id'].'_template');
			if($tpl == FALSE)
				$tpl = tmtTemplate::Get($this->mod,'tweetcancel_default_template');
			break;
		 case 3:
			$tpl = tmtTemplate::Get($this->mod,'tweetrequest_'.$bdata['bracket_id'].'_template');
			if($tpl == FALSE)
				$tpl = tmtTemplate::Get($this->mod,'tweetrequest_default_template');
			break;
		}

		$this->ccmsg = FALSE;
		$from = $this->text->ValidateAddress($bdata['smsfrom'],$bdata['smsprefix'],$bdata['smspattern']);
		$owner = $this->text->ValidateAddress($bdata['contact'],$bdata['smsprefix'],$bdata['smspattern']);
		if($owner)
		{
			if(!is_array($owner))
				$owner = array($owner);
			if(!$from)
				$from = reset($owner);
		}
		$err = '';
		$resA = TRUE; //we're ok if nothing sent
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
			$to = self::GetTeamContacts($tid,$bdata['smsprefix'],$bdata['smspattern'],$first);
		else
			$to = FALSE;
		if($to || $owner)
		{
			if($to)
			{
				reset($to);
				$tplvars['recipient'] = key($to);
				$tc = count($to);
				$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
				$tplvars['toall'] = $toall;
			}
			else
			{
				$tplvars['recipient'] = '';
				$tplvars['toall'] = FALSE;
			}
			$op = ((int)$mdata['teamB'] > 0) ? $this->mod->TeamName($mdata['teamB']) : '';
			$tplvars['opponent'] = $op;
			list($resA,$msg) = self::DoSend($bdata['smsprefix'],$to,$owner,$from,$tpl,$tplvars);
			if(!$resA)
			{
				if(!$msg)
					$msg = $this->mod->Lang('err_notice');
				$err .= $this->mod->TeamName($tid).': '.$msg;
			}
		}

		$resB = TRUE;
		$tid = (int)$mdata['teamB'];
		if($tid > 0)
			$to = self::GetTeamContacts($tid,$bdata['smsprefix'],$bdata['smspattern'],$first);
		else
			$to = FALSE;
		if($to || $owner)
		{
			if($to)
			{
				reset($to);
				$tplvars['recipient'] = key($to);
				$tc = count($to);
				$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
				$tplvars['toall'] = $toall;
			}
			else
			{
				$tplvars['recipient'] = '';
				$tplvars['toall'] = FALSE;
			}
			$op = ((int)$mdata['teamA'] > 0) ? $this->mod->TeamName($mdata['teamA']) : '';
			$tplvars['opponent'] = $op;
			list($resB,$msg) = self::DoSend($bdata['smsprefix'],$to,$owner,$from,$tpl,$tplvars);
			if(!$resB)
			{
				if($err) $err .= '<br />';
				if(!$msg)
					$msg = $this->mod->Lang('err_notice');
				$err .= $this->mod->TeamName($tid).': '.$msg;
			}
		}

		if($resA && $resB)
			return array(TRUE,'');
		return array(FALSE,$err);
	}
}

?>
