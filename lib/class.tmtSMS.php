<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Functions involved with SMS communications
*/
class tmtSMS
{
	private $mod; //reference to Tourney module object

	function __construct(&$mod)
	{
		$this->mod = $mod;
	}

	/**
	DoSend:
	Sends SMS(s) about a match
	@sender: reference to MessageSender object
	@prefix: country-code to be prepended to destination phone-numbers, or
		name of country to be used to look up that code
	@from: validated phone-no to be used (if possible) as sender, or FALSE
	@to: array of validated phone-no(s) for recipient(s)
	@cc: array of validated phone-no(s) for bracket owner(s), or FALSE
	@tpltxt: smarty template to use for message body
	@tplvars: array of template variables
	Returns: 2-member array -
	 [0] FALSE if no addressee or no SMSG-module gateway, otherwise boolean cumulative result of gateway->send()
	 [1] '' or error message e.g. from gateway->send() to $to ($cc errors ignored)
	*/
	private function DoSend(&$sender,$prefix,$from,$to,$cc,$tpltxt,$tplvars)
	{
		if(!($to || $cc))
			return array(FALSE,'');

		$body = tmtTemplate::ProcessfromData($this->mod,$tpltxt,$tplvars);
		if(!$body)
			return array(FALSE,$this->mod->Lang('err_text'));

		if($cc)
		{
			foreach($cc as $num)
			{
				if(!(in_array($num,$to) || $num == $from))
					$to[] = $num;
			}
		}
		return $sender->text->Send(array(
			'prefix'=>$prefix,
			'from'=>$from,
			'to'=>$to,
			'body'=>$body));
	}

	/**
	GetTeamContacts:
	@sender: reference to MessageSender object
	@team_id: enumerator of team being processed
	@pattern: regex for matching acceptable phone nos, defaults to module preference
	@first: whether to only try for the first relevant tag, optional, default = FALSE
	Returns: array of validated phone no(s), or FALSE.
	*/
	private function GetTeamContacts(&$sender,$team_id,$pattern,$first=FALSE)
	{
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT contact FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder';
		$contacts = $db->GetCol($sql,array($team_id));
		if($contacts)
		{
			$clean = $sender->ValidateAddress($contacts,$pattern);
			if($clean['text'])
			{
				if(!$first) //maybe override
				{
					$sql = 'SELECT contactall FROM '.$pref.'module_tmt_teams WHERE team_id=?';
					if(!$db->GetOne($sql,array($team_id)))
						$first = TRUE;
				}
				if($first)
					return array(reset($clean['text']));
				return $clean['text'];
			}
		}
		return FALSE;
	}

	/**
	TellOwner:
	Sends SMS to tournament owner if possible, and then to one member of both teams
	in the match, using same template as for tweets
	@sender: reference to MessageSender object
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@tplvars: array of template variables
	@lines: array of lines for message body
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success
	 [1] '' or specific failure message
	*/
	public function TellOwner(&$sender,&$bdata,&$mdata,$tplvars,$lines)
	{
		//owner(s)
		$clean = $sender->ValidateAddress($bdata['contact'],$bdata['smspattern']);
		$to = $clean['text'];
		if(!$to)
			return array(FALSE,''); //silent, try another channel

		$clean = $sender->ValidateAddress($bdata['smsfrom'],$bdata['smspattern']);
		$from = $clean['text'];
		if($from)
			$from = reset($from); //scalar

		//teams
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
		{
			$more = self::GetTeamContacts($sender,$tid,$bdata['smspattern'],TRUE);
			if($more)
				$to = array_merge($to,$more);
		}
		$tid = (int)$mdata['teamB'];
		if($tid > 0)
		{
			$more = self::GetTeamContacts($sender,$tid,$bdata['smspattern'],TRUE);
			if($more)
				$to = array_merge($to,$more);
		}
		//submitted data
		$tplvars['report'] = implode(' ',$lines);

		$tpltxt = tmtTemplate::Get($this->mod,'tweetin_'.$bdata['bracket_id'].'_template');
		if($tpltxt == FALSE)
			$tpltxt = tmtTemplate::Get($this->mod,'tweetin_default_template');
		return self::DoSend($sender,$bdata['smsprefix'],$from,$to,FALSE,$tpltxt,$tplvars);
	}

	/**
	TellTeams:
	Sends SMS to one or all members of both teams in the match, plus the owner,
	using same template as for tweets
	@sender: reference to MessageSender object
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@tplvars: array of template variables
	@type: enum for type of message: 1 = announcement, 2 = cancellation, 3 = score-request
	@first: TRUE to send only to first recognised address, FALSE to send per
		the teams' respective contactall settings, optional, default FALSE
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success, or TRUE if nobody to send to
	 [1] '' or specific failure message
	*/
	public function TellTeams(&$sender,&$bdata,&$mdata,$tplvars,$type,$first=FALSE)
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

		$this->ccmsg = FALSE;
		$clean = $sender->ValidateAddress($bdata['smsfrom'],$bdata['smspattern']);
		$from = $clean['text'];

		$clean = $sender->ValidateAddress($bdata['contact'],$bdata['smspattern']);
		$owner = $clean['text'];
		if($owner && !$from)
			$from = $owner;

		if($from)
			$from = reset($from); //scalar

		$err = '';
		$resA = TRUE; //we're ok if nothing sent
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
			$to = self::GetTeamContacts($sender,$tid,$bdata['smspattern'],$first);
		else
			$to = FALSE;
		if($to || $owner)
		{
			if($to)
			{
				$tplvars['recipient'] = reset($to);
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
			list($resA,$msg) = self::DoSend($sender,$bdata['smsprefix'],$from,$to,$owner,$tpltxt,$tplvars);
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
			$to = self::GetTeamContacts($sender,$tid,$bdata['smspattern'],$first);
		else
			$to = FALSE;
		if($to || $owner)
		{
			if($to)
			{
				$tplvars['recipient'] = reset($to);
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
			list($resB,$msg) = self::DoSend($sender,$bdata['smsprefix'],$from,$to,$owner,$tpltxt,$tplvars);
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
