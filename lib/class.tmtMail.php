<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Functions involved with email communications
*/
class tmtMail
{
	private $mod; //reference to Tourney module object
	private $mlr; //reference to EmailSender object

	function __construct(&$mod,&$mlr)
	{
		$this->mod = $mod;
		$this->mlr = $mlr;
	}

	/**
	DoSend:
	Sends email notice(s) about a match
	@bdata: reference to array of bracket-table data
	@to: array of 'To' destinations. Key = recipient name, value = validated email address
	@cc: array of 'CC' destinations, or FALSE. Array key = recipient name, value = validated email address
	@tpltxt: smarty template to use for message body
	@tplvars: reference to array of template variables
	Returns: 2-member array -
	 [0] FALSE if no addressee or no mlr module, otherwise boolean result of mlr->Send()
	 [1] '' or error message e.g. from mlr->Send()
	*/
	private function DoSend(&$bdata,$to,$cc,$tpltxt,$tplvars)
	{
		if(!($to || $cc))
			return array(FALSE,''); //nothing to do
		if(!$this->mlr)
			return array(FALSE,$this->mod->Lang('err_system'));

		$subject = $this->mod->Lang('tournament').' - '.$bdata['name'];
		if(!array_key_exists($bdata['owner'],$to))
			$from = $bdata['owner'];
		else
			$from = FALSE;
		$body = tmtTemplate::ProcessfromData($this->mod,$tpltxt,$tplvars);
		$html = ($bdata['html'] == '1');

		return $this->mlr->Send($subject,$to,$cc,FALSE,$from,$body,$html);
	}

	/**
	GetTeamContacts:
	@team_id: enumerator of team being processed
	@first: whether to only try for the first relevant email address, optional, default = FALSE
	Returns: array, or FALSE. Array members' key = contact name, value = validated email address
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
				$clean = $this->mlr->ValidateAddress($one['contact']);
				if($clean)
				{
					$sends[$one['name']] = $clean;
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
	Sends email to tournament owner if possible, and then with cc to one member of both teams in the match
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
		$clean = $this->mlr->ValidateAddress($bdata['contact']);
		if($clean)
			$to[$bdata['owner']] = $clean;
		else
			return array(FALSE,''); //silent, try another channel
		//teams
		$cc = array();
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
		{
			$more = self::GetTeamContacts($tid,TRUE);
			if($more)
				$cc = $cc + $more;
		}
		$tid = (int)$mdata['teamB'];
		if($tid > 0)
		{
			$more = self::GetTeamContacts($tid,TRUE);
			if($more)
				$cc = $cc + $more;
		}
		//submitted data
		$sep = ($bdata['html']) ? '<br>' : PHP_EOL;
		$tplvars['report'] = implode($sep,$lines);

		$tpltxt = tmtTemplate::Get($this->mod,'mailin_'.$bdata['bracket_id'].'_template');
		if($tpltxt == FALSE)
			$tpltxt = tmtTemplate::Get($this->mod,'mailin_default_template');
		return self::DoSend($bdata,$to,$cc,$tpltxt,$tplvars);
	}

	/**
	TellTeams:
	Sends email to one or all members of both teams in the match, with cc to the owner
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
			$tpltxt = tmtTemplate::Get($this->mod,'mailout_'.$bdata['bracket_id'].'_template');
			if($tpltxt == FALSE)
				$tpltxt = tmtTemplate::Get($this->mod,'mailout_default_template');
			break;
		 case 2:
			$tpltxt = tmtTemplate::Get($this->mod,'mailcancel_'.$bdata['bracket_id'].'_template');
			if($tpltxt == FALSE)
				$tpltxt = tmtTemplate::Get($this->mod,'mailcancel_default_template');
			break;
		 case 3:
			$tpltxt = tmtTemplate::Get($this->mod,'mailrequest_'.$bdata['bracket_id'].'_template');
			if($tpltxt == FALSE)
				$tpltxt = tmtTemplate::Get($this->mod,'mailrequest_default_template');
			break;
		}

		$clean = $this->mlr->ValidateAddress($bdata['contact']);
		$cc = ($clean) ? array($bdata['owner']=>$clean) : FALSE;
		$err = '';
		$resA = TRUE; //we're ok if nothing sent
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
		{
			$to = self::GetTeamContacts($tid,$first);
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
/*				switch($bdata['type'])
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
				list($resA,$msg) = self::DoSend($bdata,$to,$cc,$tpltxt,$tplvars);
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
			$to = self::GetTeamContacts($tid,$first);
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
/*				switch($bdata['type'])
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
				list($resB,$msg) = self::DoSend($bdata,$to,$cc,$tpltxt,$tplvars);
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
