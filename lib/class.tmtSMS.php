<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Functions involved with SMS communications
This class is not suitable for static method-calling
*/
class tmtSMS
{
	private $mod; //reference to Tourney module object
	private $text; //reference to SMSSender object
	private $smarty; //reference to current smarty object

	function __construct(&$mod,&$text,&$smarty)
	{
		$this->mod = $mod;
		$this->text = $text;
		$this->smarty = $smarty;
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
	Returns: 2-member array -
	 [0] FALSE if no addressee or no SMSG-module gateway, otherwise boolean cumulative result of gateway->send()
	 [1] '' or error message e.g. from gateway->send() to $to ($cc errors ignored)
	*/
	private function DoSend($prefix,$to,$cc,$from,$tpl)
	{
		if(!($to || $cc))
			return array(FALSE,'');
		if(!$this->text)
			return array(FALSE,$this->mod->Lang('err_system'));

		$body = $this->mod->ProcessDataTemplate($tpl);
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
		return $this->text->Send($prefix,$to,FALSE,$body);
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
	@mod: reference to current module object
	@smarty: reference to CMSMS smarty object
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@lines: array of lines for message body
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success
	 [1] '' or specific failure message
	*/
	public function TellOwner(&$bdata,&$mdata,$lines)
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
		$this->smarty->assign('report',implode(' ',$lines));

		$tpl = $this->mod->GetTemplate('tweetin_'.$bdata['bracket_id'].'_template');
		if($tpl == FALSE)
			$tpl = $this->mod->GetTemplate('tweetin_default_template');
		return self::DoSend($bdata['smsprefix'],$to,FALSE,$from,$tpl);
	}

	/**
	TellTeams:
	Sends SMS to one or all members of both teams in the match, plus the owner,
	using same template as for tweets
	@mod: reference to current module object
	@smarty: reference to CMSMS smarty object
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@tpl: enum for type of message: 1 = announcement, 2 = cancellation, 3 = score-request
	@first: TRUE to send only to first recognised address, FALSE to send per
		the teams' respective contactall settings, optional, default FALSE
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success, or TRUE if nobody to send to
	 [1] '' or specific failure message
	*/
	public function TellTeams(&$bdata,&$mdata,$tpl,$first=FALSE)
	{
		switch($tpl)
		{
		 case 1:
			$tpl = $this->mod->GetTemplate('tweetout_'.$bdata['bracket_id'].'_template');
			if($tpl == FALSE)
				$tpl = $this->mod->GetTemplate('tweetout_default_template');
			break;
		 case 2:
			$tpl = $this->mod->GetTemplate('tweetcancel_'.$bdata['bracket_id'].'_template');
			if($tpl == FALSE)
				$tpl = $this->mod->GetTemplate('tweetcancel_default_template');
			break;
		 case 3:
			$tpl = $this->mod->GetTemplate('tweetrequest_'.$bdata['bracket_id'].'_template');
			if($tpl == FALSE)
				$tpl = $this->mod->GetTemplate('tweetrequest_default_template');
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
				$this->smarty->assign('recipient',key($to));
				$tc = count($to);
				$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
				$this->smarty->assign('toall',$toall);
			}
			else
			{
				$this->smarty->assign('recipient','');
				$this->smarty->assign('toall',FALSE);
			}
			$op = ((int)$mdata['teamB'] > 0) ? $this->mod->TeamName($mdata['teamB']) : '';
			$this->smarty->assign('opponent',$op);
			list($resA,$msg) = self::DoSend($bdata['smsprefix'],$to,$owner,$from,$tpl);
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
				$this->smarty->assign('recipient',key($to));
				$tc = count($to);
				$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
				$this->smarty->assign('toall',$toall);
			}
			else
			{
				$this->smarty->assign('recipient','');
				$this->smarty->assign('toall',FALSE);
			}
			$op = ((int)$mdata['teamA'] > 0) ? $this->mod->TeamName($mdata['teamA']) : '';
			$this->smarty->assign('opponent',$op);
			list($resB,$msg) = self::DoSend($bdata['smsprefix'],$to,$owner,$from,$tpl);
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
