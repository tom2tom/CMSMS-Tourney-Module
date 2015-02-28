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
	
	private function __construct()
	{
		$this->utils = new cgsms_utils(); //never FALSE, cuz class created after check
		$this->gateway = $this->utils->get_gateway(); //maybe FALSE
	}

	/**
	DoSend:
	Sends SMS(s) about a match
	@mod: reference to current module object
	@codes: associative array of 4 Twitter access-codes
	@to: array of validated phone-no(s) for recipient(s)
	@tpl: smarty template to use for message body
	Returns: 2-member array -
	 [0] FALSE if no addressee or no CGSMS-module gateway, otherwise boolean cumulative result of gateway->send()
	 [1] '' or error message e.g. from gateway->send()
	*/
	private function DoSend(&$mod,$codes,$to,$tpl)
	{
		if(!$to)
			return array(FALSE,'');
		if(!$this->gateway)
			return array(FALSE,$mod->Lang('err_system'));
		$body = $mod->ProcessDataTemplate($tpl);
		if(!$this->utils->text_is_valid($body)
			return array(FALSE,$mod->Lang('err_template'));
		$this->gateway->set_msg($body);
		$err = '';
		foreach($to as $ph)
		{
			$this->gateway->set_num($ph);
			if(!$this->gateway->send())
			{
				if($err) $err .= '<br />';
				$err .= $ph.': '.$this->gateway->get_statusmsg();
			}
		}
		return array(($err==''),$err);
	}

	/**
	GetTeamContacts:
	@team_id: enumerator of team being processed
	@first: whether to only try for the first relevant tag, optional, default = FALSE
	Returns: array of validated phone nos (maybe empty), or FALSE.
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
	Check whether @address is a valid phone no.
	@address: one, or a comma-separated series of, address(es) to check
	@patn: optional, regex for matching acceptable phone nos, defaults to module preference
	Returns: a trimmed valid phone no., or array of them, or FALSE
	*/
	public function ValidateAddress(&$mod,$address,$pattern=FALSE)
	{
		if(!$pattern)
			$pattern = $mod->GetPreference('phone_regex');
		if(!$pattern)
			return FALSE;
		$pattern = '~'.$pattern.'~';
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
	TellOwner:
	Sends SMS to tournament owner if possible, and then to one member of both teams
	in the match, using same template as for tweets
	@mod: reference to current module object
	@smarty: reference to CMSMS smarty object
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@lines: array of lines for message body
	@patn: optional, default FALSE
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success
	 [1] '' or specific failure message
	*/
	public function TellOwner(&$mod,&$smarty,&$bdata,&$mdata,$lines,$patn=FALSE)
	{
		//owner
		$clean = self::ValidateAddress($mod,$bdata['contact'],$patn);
		if($clean)
			$to = array($clean);
		else
			return array(FALSE,''); //silent, try another channel
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
	Sends SMS to one or all members of both teams in the match, plus the owner,
	using same template as for tweets
	@mod: reference to current module object
	@smarty: reference to CMSMS smarty object
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@first: TRUE to send only to first recognised address, FALSE to send per
		the teams' respective contactall settings, optional, default FALSE
	@patn: optional, default FALSE
	Returns: 2-member array -
	 [0] TRUE|FALSE representing success, or TRUE if nobody to send to
	 [1] '' or specific failure message
	*/
	public function TellTeams(&$mod,&$smarty,&$bdata,&$mdata,$first=FALSE,$patn=FALSE)
	{
		$tpl = $mod->GetTemplate('tweetout_'.$bdata['bracket_id'].'_template');
		if($tpl == FALSE)
			$tpl = $mod->GetTemplate('tweetout_default_template');

		$owner = self::ValidateAddress($mod,$bdata['contact'],$patn);
		$err = '';
		$resA = TRUE; //we're ok if nothing sent
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
		{
			$to = self::GetTeamContacts($mod,$tid,$first);
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
			$to = self::GetTeamContacts($mod,$tid,$first);
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
