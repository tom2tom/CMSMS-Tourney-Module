<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Functions involved with communications
*/
class tmtComm
{
	//reference to current module object
	protected $mod;
	//channel-objects for polling
	protected $text;
	protected $mail;
	protected $tweet;

	function __construct(&$mod)
	{
		$this->mod = $mod;
		$this->text = new tmtSMS();
		$this->mail = new tmtMail($mod);
		$this->tweet = new tmtTweet();
	}

	/**
	ValidateAddress:
	Check that @address is suitable for sending message via a supported channel
	@address: The address to check
	Returns: TRUE if valid
	*/
	public function ValidateAddress($address)
	{
		if($this->text->ValidateAddress($address))
			return TRUE;
		if($this->mail->ValidateAddress($address))
			return TRUE;
		if($this->tweet->ValidateAddress($address))
			return TRUE;
		return FALSE;
	}

	/**
	SetTplVars:
	Set generic vars for use in templates
	@mod: reference to current module object
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@smarty: reference to CMSMS smarty object
	*/
	private function SetTplVars(&$mod,&$bdata,&$mdata,&$smarty)
	{
		$smarty->assign('title',$bdata['name']);
		$smarty->assign('description',$bdata['description']);
		$smarty->assign('owner',$bdata['owner']);
		$smarty->assign('contact',$bdata['contact']);
		$smarty->assign('where',$mdata['place']);
		$tfmt = $mod->GetPreference('time_format');
		$dfmt = $mod->GetZoneDateFormat($bdata['timezone']);
		$stamp = strtotime($mdata['playwhen']);
		$smarty->assign('time',date($tfmt,$stamp));
		$smarty->assign('date',date($dfmt,$stamp));
		$smarty->assign('when',date($tfmt.','.$dfmt,$stamp));
		$tid = (int)$mdata['teamA'];
		$tA = ($tid > 0) ? $mod->TeamName($mdata['teamA']) : '';
		$tid = (int)$mdata['teamB'];
		$tB = ($tid > 0) ? $mod->TeamName($mdata['teamB']) : '';
		if($tA && $tB)
			$smarty->assign('teams',$tA.', '.$tB);
		else
			$smarty->assign('teams',$tA.$tB);
	}

	/**
	TellOwner:
	Sends message via relevant channel(s) to tournament owner, and to one member of both teams in the match
	@bracket_id: index of competition to be processed
	@match_id: index of match to be processed
	@body: array of lines for message body
	Returns: array with two members: 1st TRUE|FALSE representing success, 2nd specific problem message or FALSE
	*/
	public function TellOwner($bracket_id,$match_id,$body)
	{
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT teamA,teamB,place,playwhen FROM '.$pref.'module_tmt_matches WHERE match_id=?';
		$mdata = $db->GetRow($sql,array($match_id));
		if($mdata)
		{
			$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$bdata = $db->GetRow($sql,array($bracket_id));
			$smarty = cmsms()->GetSmarty();
			//general vars for template
			$this->SetTplVars($this->mod,$bdata,$mdata,$smarty);
			//channel-specific var report set downstream
			$res = FALSE;
			$patn = '^04\d{2} ?\d{3} ?\d{3} ?\d{3}$'; //TODO
			list($ok,$msg1) = $this->text->TellOwner($this->mod,$smarty,$bdata,$mdata,$body,$patn);
			if($ok)
				$res = TRUE;
			list($ok,$msg2) = $this->mail->TellOwner($this->mod,$smarty,$bdata,$mdata,$body);
			if($ok)
				$res = TRUE;
			list($ok,$msg3) = $this->tweet->TellOwner($this->mod,$smarty,$bdata,$mdata,$body);
			if($ok)
				$res = TRUE;
			if($res)
				return array(TRUE,'');
			return array(FALSE,implode('<br />',array($msg1,$msg2,$msg3)));
		}
		return array(FALSE,$mod->Lang('err_match'));
	}

	/**
	TellTeams:
	Sends message via relevant channel(s) to one or all members of both teams in the match
	@bracket_id: index of competition to be processed
	@match_id: index of match to be processed
	@first: TRUE to send only to first recognised address, FALSE to send per
		the teams' respective contactall settings, optional, default FALSE
	Returns: array with two members: 1st TRUE|FALSE representing success, 2nd specific problem message or FALSE
	*/
	public function TellTeams($bracket_id,$match_id,$first=FALSE)
	{
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT teamA,teamB,place,playwhen FROM '.$pref.'module_tmt_matches WHERE match_id=?';
		$mdata = $db->GetRow($sql,array($match_id));
		if($mdata)
		{
			$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$bdata = $db->GetRow($sql,array($bracket_id));
			$smarty = cmsms()->GetSmarty();
			//general vars for template
			$this->SetTplVars($this->mod,$bdata,$mdata,$smarty);
			//team-specific vars recipient(for email),toall,opponent set downstream
			$res = FALSE;
			$patn = '^04\d{2} ?\d{3} ?\d{3} ?\d{3}$'; //TODO
			list($ok,$msg1) = $this->text->TellTeams($this->mod,$smarty,$bdata,$mdata,$first,$patn);
			if($ok)
				$res = TRUE;
			list($ok,$msg2) = $this->mail->TellTeams($this->mod,$smarty,$bdata,$mdata,$first);
			if($ok)
				$res = TRUE;
			list($ok,$msg3) = $this->tweet->TellTeams($this->mod,$smarty,$bdata,$mdata,$first);
			if($ok)
				$res = TRUE;
			if($res)
				return array(TRUE,'');
			return array(FALSE,implode('<br />',array($msg1,$msg2,$msg3)));
		}
		return array(FALSE,$mod->Lang('err_match'));
	}
}

?>
