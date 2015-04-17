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
	private $utils;
	private $gateway;
	private $ccmsg;
	private $fromnum; //whether gateway supports a specific sender-number
	private $addprefix; //whether gateway requires country-prefix for each phone no. or else supports phone no. as-is
	private $addplus; //whether gateway requires a leading '+' in the country-prefix, if any

	function __construct()
	{
		$this->utils = new cgsms_utils(); //never FALSE, cuz class created after check
		$this->gateway = $this->utils->get_gateway(); //maybe FALSE
		$this->ccmsg = FALSE;
		$this->fromnum = FALSE;
		$this->addprefix = TRUE;
		$this->addplus = FALSE;
		if($this->gateway)
		{
			if(method_exists($this->gateway,'support_custom_sender'))
				$this->fromnum = $this->gateway->support_custom_sender();
			if(method_exists($this->gateway,'require_country_prefix'))
			{
				$this->addprefix = $this->gateway->require_country_prefix();
				if($this->addprefix && method_exists($this->gateway,'require_plus_prefix'))
					$this->addplus = $this->gateway->require_plus_prefix();
			}
		}
	}

	/**
	DoSend:
	Sends SMS(s) about a match
	@mod: reference to current module object
	@codes: associative array of 4 Twitter access-codes
	@to: array of validated phone-no(s) for recipient(s)
	@cc: array of validated phone-no(s) for bracket owner(s), or FALSE
	@from: validated phone-no to be used (if possible) as sender, or FALSE
	@tpl: smarty template to use for message body
	Returns: 2-member array -
	 [0] FALSE if no addressee or no SMSG-module gateway, otherwise boolean cumulative result of gateway->send()
	 [1] '' or error message e.g. from gateway->send() to $to ($cc errors ignored)
	*/
	private function DoSend(&$mod,$to,$cc,$from,$tpl)
	{
		if(!($to || $cc))
			return array(FALSE,'');
		if(!$this->gateway)
			return array(FALSE,$mod->Lang('err_system'));
		$body = $mod->ProcessDataTemplate($tpl);
		if(!$body || !$this->utils->text_is_valid($body))
			return array(FALSE,$mod->Lang('err_text').' \''.$body.'\'');
		if($from && $this->fromnum)
			$this->gateway->set_from($from);
		$this->gateway->set_msg($body);
		$err = '';
		//assume gateway doesn't support batching
		foreach($to as $num)
		{
			$this->gateway->set_num($num);
			if(!$this->gateway->send())
			{
				if($err) $err .= '<br />';
				$err .= $num.': '.$this->gateway->get_statusmsg();
			}
		}
		if($cc && ($body != $this->ccmsg))
		{
			$this->ccmsg = $body; //forestall duplication
			foreach($cc as $num)
			{
				$this->gateway->set_num($num);
				if($from && $this->fromnum)
				{
					if($num == $from)
						$this->gateway->set_from(FALSE);
					else
						$this->gateway->set_from($from);
				}
				$this->gateway->send(); //ignore any error to cc
			}
		}
		return array(($err==''),$err);
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
				$clean = self::ValidateAddress($one['contact'],$prefix,$pattern);
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

	//$pattern is for matching $number AFTER whitespace gone, BEFORE any prefix-adjustment,
	//already has surrrounding delimiter-chars
	private function AdjustPhone($number,$country,$pattern)
	{
		$n = str_replace(' ','',$number);
		if(!preg_match($pattern,$n))
			return FALSE;
		if(!$this->addprefix)
			return $n;
		$p = str_replace(' ','',$country);
		$plus = ($p[0] == '+');
		if($plus)
			$p = substr($p,1);
		$l = strlen($p);
		if($l > 0)
		{
			if(substr($n,0,$l) != $p)
			{
				if($n[0] === '0')
					$n = $p.substr($n,1);
			}
		}
		if($this->addplus && $n[0] != '+')
			$n = '+'.$n;
		elseif(!$this->addplus && $n[0] == '+')
			$n = substr($n,1); //assume it's already a full number i.e. +countrylocal
		return $n;
	}

	/**
	ValidateAddress:
	Check whether @address is a valid phone no.
	@number: one, or a comma-separated series of, number(s) to check
	@prefix: default country-code for phone-numbers to receive text
	@pattern: regex for matching acceptable phone nos
	Returns: a trimmed valid phone no., or array of them, or FALSE
	*/
	public function ValidateAddress($number,$prefix,$pattern)
	{
		if(!$pattern)
			return FALSE;
		$pattern = '~'.$pattern.'~';
		if(strpos($number,',') === FALSE)
		{
			return self::AdjustPhone($number,$prefix,$pattern);
		}
		else
		{
			$parts = explode(',',$number);
			$number = array();
			foreach($parts as $one)
			{
				$to = self::AdjustPhone($one,$prefix,$pattern);
				if($to)
					$number[] = $to;
			}
			if($number)
				return $number;
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
	public function TellOwner(&$mod,&$smarty,&$bdata,&$mdata,$lines)
	{
		//owner(s)
		$to = self::ValidateAddress($bdata['contact'],$bdata['smsprefix'],$bdata['smspattern']);
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
		$from = self::ValidateAddress($bdata['smsfrom'],$bdata['smsprefix'],$bdata['smspattern']);
		if(is_array($from))
			$from = reset($from);

		//submitted data
		$smarty->assign('report',implode(' ',$lines));

		$tpl = $mod->GetTemplate('tweetin_'.$bdata['bracket_id'].'_template');
		if($tpl == FALSE)
			$tpl = $mod->GetTemplate('tweetin_default_template');
		return self::DoSend($mod,$to,FALSE,$from,$tpl);
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
	public function TellTeams(&$mod,&$smarty,&$bdata,&$mdata,$tpl,$first=FALSE)
	{
		switch($tpl)
		{
		 case 1:
			$tpl = $mod->GetTemplate('tweetout_'.$bdata['bracket_id'].'_template');
			if($tpl == FALSE)
				$tpl = $mod->GetTemplate('tweetout_default_template');
			break;
		 case 2:
			$tpl = $mod->GetTemplate('tweetcancel_'.$bdata['bracket_id'].'_template');
			if($tpl == FALSE)
				$tpl = $mod->GetTemplate('tweetcancel_default_template');
			break;
		 case 3:
			$tpl = $mod->GetTemplate('tweetrequest_'.$bdata['bracket_id'].'_template');
			if($tpl == FALSE)
				$tpl = $mod->GetTemplate('tweetrequest_default_template');
			break;
		}

		$this->ccmsg = FALSE;
		$from = self::ValidateAddress($bdata['smsfrom'],$bdata['smsprefix'],$bdata['smspattern']);
		$owner = self::ValidateAddress($bdata['contact'],$bdata['smsprefix'],$bdata['smspattern']);
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
				$smarty->assign('recipient',key($to));
				$tc = count($to);
				$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
				$smarty->assign('toall',$toall);
			}
			else
			{
				$smarty->assign('recipient','');
				$smarty->assign('toall',FALSE);
			}
			$op = ((int)$mdata['teamB'] > 0) ? $mod->TeamName($mdata['teamB']) : '';
			$smarty->assign('opponent',$op);
			list($resA,$msg) = self::DoSend($mod,$to,$owner,$from,$tpl);
			if(!$resA)
			{
				if(!$msg)
					$msg = $mod->Lang('err_notice');
				$err .= $mod->TeamName($tid).': '.$msg;
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
				$smarty->assign('recipient',key($to));
				$tc = count($to);
				$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
				$smarty->assign('toall',$toall);
			}
			else
			{
				$smarty->assign('recipient','');
				$smarty->assign('toall',FALSE);
			}
			$op = ((int)$mdata['teamA'] > 0) ? $mod->TeamName($mdata['teamA']) : '';
			$smarty->assign('opponent',$op);
			list($resB,$msg) = self::DoSend($mod,$to,$owner,$from,$tpl);
			if(!$resB)
			{
				if($err) $err .= '<br />'; 
				if(!$msg)
					$msg = $mod->Lang('err_notice');
				$err .= $mod->TeamName($tid).': '.$msg;
			}
		}

		if($resA && $resB)
			return array(TRUE,'');
		return array(FALSE,$err);
	}
}

?>
