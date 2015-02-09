<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Functions involved with email communications
This class is not suitable for static method-calling
*/
class tmtMail
{
	private $mlr;

	/**
	DoSend:
	Sends email notice(s) about a match
	@mod: reference to current module object
	@bdata: reference to array of bracket-table data
	@to: array of 'To' destinations. Key = recipient name, value = validated email address
	@cc: array of 'CC' destinations, or FALSE. Array key = recipient name, value = validated email address
	@tpl: smarty template to use for message body 
	Returns: 2-member array -
	 [0] FALSE if no addressee or no mlr module, otherwise boolean result of mlr->Send()
	 [1] '' or error message e.g. from mlr->Send()
	*/
	private function DoSend(&$mod,&$bdata,$to,$cc,$tpl)
	{
		if(!($to || $cc))
			return array(FALSE,'');
		if(!$this->mlr)
		{
			$this->mlr = $mod->GetModuleInstance('CMSMailer');
			if($this->mlr)
				$this->mlr->_load();
			else
				return array(FALSE,$mod->Lang('err_system'));
		}

		$subject = $mod->Lang('tournament').' - '.$bdata['name'];
		$html = ($bdata['html'] == '1');

		$body = $mod->ProcessDataTemplate($tpl);
		if($html)
		{
			//PHP is bad at setting suitable line-breaks;
			$body2 = str_replace(array('<br /><br />','<br />','<br><br>','<br>'),
				array('','','',''),$body);
			$body2 = strip_tags(html_entity_decode($body2));
		}
//TODO	conform message encoding to $mlr->CharSet

		$m = $this->mlr;
		$m->reset();
		if($to)
		{
			foreach($to as $name=>$address)
				$m->AddAddress($address,$name);
			if($cc)
				foreach($cc as $name=>$address)
					$m->AddCC($address,$name);
			if(!array_key_exists($bdata['owner'],$to))
				$m->SetFromName($bdata['owner']);
		}
		elseif($cc)
			foreach($cc as $name=>$address)
				$m->AddAddress($address,$name);
//if default sender isn't wanted $m->SetFrom();
		$m->SetSubject($subject);
		$m->IsHTML($html);
		if($html)
		{
			$m->SetBody($body);
			$m->SetAltBody($body2);
		}
		else
		{
			$m->SetBody(html_entity_decode($body));
		}
		$res = $m->Send();
		$err = ($res) ? '' : $m->GetErrorInfo();
		$m->reset();
		return array($res,$err);
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
		$sql = 'SELECT name,contact FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder ASC';
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
				$clean = $this->ValidateAddress($one['contact']);
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
	ValidateAddress:
	Check that @address looks roughly like a valid email address
	Tries to use PHP(5.2+) built-in validator, reverts to a reasonably competent
	regex validator. Conforms approximately to RFC2822.
	Sourced from CMSMailer module.
	@address: one, or a comma-separated series of, address(es) to check
	Returns: a trimmed valid email address, or array of them, or FALSE
	*/
	public function ValidateAddress($address)
	{
		$internal = function_exists('filter_var'); //PHP 5.2+
		if(!$internal)
			$pattern = '/^(?:[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+\.)*[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+@(?:(?:(?:[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!\.)){0,61}[a-zA-Z0-9_-]?\.)+[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!$)){0,61}[a-zA-Z0-9_]?)|(?:\[(?:(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\]))$/';
		if(strpos($address,',') === FALSE)
		{
			$address = trim($address);
			if($internal)
			{
				if(filter_var($address,FILTER_VALIDATE_EMAIL) !== FALSE)
					return $address;
			}
			elseif(preg_match($pattern,$address))
				return $address;
		}
		else
		{
			$parts = explode(',',$address);
			$address = array();
			foreach($parts as $one)
			{
				$one = trim($one);
				if($internal)
				{
					if(filter_var($one,FILTER_VALIDATE_EMAIL) !== FALSE)
						$address[] = $one;
				}
				elseif(preg_match($pattern,$one))
					$address[] = $one;
			}
			if($address)
				return $address;
		}
		return FALSE;
	}

	/**
	TellOwner:
	Sends email to tournament owner if possible, and then with cc to one member of both teams in the match
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
		$clean = $this->ValidateAddress($bdata['contact']);
		if($clean)
			$to[$bdata['owner']] = $clean;
		else
			return array(FALSE,''); //silent, try another channel
		//teams
		$cc = array();
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
		{
			$more = $this->GetTeamContacts($tid,TRUE);
			if($more)
				$cc = $cc + $more;
		}
		$tid = (int)$mdata['teamB'];
		if($tid > 0)
		{
			$more = $this->GetTeamContacts($tid,TRUE);
			if($more)
				$cc = $cc + $more;
		}
		//submitted data
		$sep = ($bdata['html']) ? '<br />' : "\n";
		$smarty->assign('report',implode($sep,$lines));

		$tpl = $mod->GetTemplate('mailin_'.$bdata['bracket_id'].'_template');
		if($tpl == FALSE)
			$tpl = $mod->GetTemplate('mailin_default_template');
		return $this->DoSend($mod,$bdata,$to,$cc,$tpl);
	}

	/**
	TellTeams:
	Sends email to one or all members of both teams in the match, with cc to the owner
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
		$tpl = $mod->GetTemplate('mailout_'.$bdata['bracket_id'].'_template');
		if($tpl == FALSE)
			$tpl = $mod->GetTemplate('mailout_default_template');

		$clean = $this->ValidateAddress($bdata['contact']);
		$cc = ($clean) ? array($bdata['owner']=>$clean) : FALSE;
		$err = '';
		$resA = TRUE; //we're ok if nothing sent
		$tid = (int)$mdata['teamA'];
		if($tid > 0)
		{
			$to = $this->GetTeamContacts($mod,$tid,$first);
			if($to)
			{
				reset($to);
				$smarty->assign('recipient',key($to));
				$tc = count($to);
				$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
				$smarty->assign('toall',$toall);
				$smarty->assign('opponent',$mod->TeamName($mdata['teamB']));
				list($resA,$msg) = $this->DoSend($mod,$bdata,$to,$cc,$tpl);
				if(!$resA)
				{
					if(!$msg)
						$msg = $mod->Lang('err_notice');
					$err .= $mod->TeamName($tid).': '.$msg;
				}
			}
		}
		
		$resB = TRUE;
		$tid = (int)$mdata['teamB'];
		if($tid > 0)
		{
			$to = $this->GetTeamContacts($mod,$tid,$first);
			if($to)
			{
				reset($to);
				$smarty->assign('recipient',key($to));
				$tc = count($to);
				$toall = (($bdata['teamsize'] < 2 && $tc > 0) || $tc > 1);
				$smarty->assign('toall',$toall);
				$smarty->assign('opponent',$mod->TeamName($mdata['teamA']));
				list($resB,$msg) = $this->DoSend($mod,$bdata,$to,$cc,$tpl);
				if(!$resB)
				{
					if($err) $err .= '<br />';
					if(!$msg)
						$msg = $mod->Lang('err_notice');
					$err .= $mod->TeamName($tid).': '.$msg;
				}
			}
		}
		if($resA && $resB)
			return array(TRUE,'');
		return array(FALSE,$err);
	}
}

?>
