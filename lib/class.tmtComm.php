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
	private $mod;	//reference to current module object
	private $sender = NULL; //Notifier::MessageSender object

	function __construct(&$mod)
	{
		$this->mod = $mod;
		if(class_exists('Notifier',FALSE))
			$this->sender = new MessageSender();
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
		$smarty->assign('smsfrom',$bdata['smsfrom']);
		$smarty->assign('where',$mdata['place']);
		$tfmt = $mod->GetPreference('time_format');
		$dfmt = $mod->GetZoneDateFormat($bdata['timezone']);
		$dt = new DateTime($mdata['playwhen'],new DateTimeZone($bdata['timezone']));
		$stamp = $dt->GetTimestamp();
		$smarty->assign('time',date($tfmt,$stamp));
		$smarty->assign('date',date($dfmt,$stamp));
		//time before date, can't rely on $bdata['atformat']
		$smarty->assign('when',date($tfmt.', '.$dfmt,$stamp));
		$tid = (int)$mdata['teamA'];
		$tA = ($tid > 0) ? $mod->TeamName($mdata['teamA']) : '';
		$tid = (int)$mdata['teamB'];
		$tB = ($tid > 0) ? $mod->TeamName($mdata['teamB']) : '';
		if($tA && $tB)
			$smarty->assign('teams',$tA.', '.$tB);
		else
		{
			switch($bdata['type'])
			{
			 case Tourney::KOTYPE:
				$op = $mod->Lang('anonwinner');
				break;
			 default:
				$op = $mod->Lang('anonother');
				break;
			}
			$smarty->assign('teams',$tA.$tB.', '.$op);
		}
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
		if($this->sender == NULL)
			return array(FALSE,$this->mod->Lang('nonotifier'));

		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT teamA,teamB,place,playwhen FROM '.$pref.'module_tmt_matches WHERE match_id=? AND flags=0';
		$mdata = $db->GetRow($sql,array($match_id));
		if($mdata)
		{
			$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$bdata = $db->GetRow($sql,array($bracket_id));
			$smarty = cmsms()->GetSmarty();
			//general vars for template
			$this->SetTplVars($this->mod,$bdata,$mdata,$smarty);
			//channel-specific var report set downstream
			$ok = FALSE;
			$msgs = array();
			if($this->sender->text)
			{
				$funcs = new tmtSMS($this->mod,$this->sender->text,$smarty);
				list($ok,$msg1) = $funcs->TellOwner($bdata,$mdata,$body);
				if(!$ok && $msg1)
					$msgs[] = $msg1;
				else
					$ok = TRUE;
			}
			if($this->sender->mail)
			{
				$funcs = new tmtMail($this->mod,$this->sender->mail,$smarty);
				list($ok,$msg1) = $funcs->TellOwner($bdata,$mdata,$body);
				if(!$ok && $msg1)
					$msgs[] = $msg1;
				else
					$ok = TRUE;
			}
			if($this->sender->tweet)
			{
				$funcs = new tmtTweet($this->mod,$this->sender->tweet,$smarty);
				list($ok,$msg1) = $funcs->TellOwner($bdata,$mdata,$body);
				if(!$ok && $msg1)
					$msgs[] = $msg1;
				else
					$ok = TRUE;
			}
			if($msgs)
				return array(FALSE,implode('<br />',$msgs));
			elseif(!$ok)
				return array(FALSE,$mod->Lang('nochannel'));
 			else
				return array(TRUE,'');
		}
		return array(FALSE,$mod->Lang('err_match'));
	}

	/**
	TellTeams:
	Sends message via relevant channel(s) to one or all members of both teams in the match
	@bracket_id: index of competition to be processed
	@match_id: index of match to be processed
	@tpl: enum for type of message: 1 = announcement, 2 = cancellation, 3 = score-request
	@first: TRUE to send only to first recognised address, FALSE to send per
		the teams' respective contactall settings, optional, default FALSE
	Returns: array with two members: 1st TRUE|FALSE representing success or nothing to do,
		2nd a specific problem message or FALSE
	*/
	public function TellTeams($bracket_id,$match_id,$tpl,$first=FALSE)
	{
		if($this->sender == NULL)
			return array(FALSE,$this->mod->Lang('nonotifier'));

		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT teamA,teamB,place,playwhen FROM '.$pref.'module_tmt_matches WHERE match_id=? AND flags=0';
		$mdata = $db->GetRow($sql,array($match_id));
		if($mdata)
		{
		 //need at least 1 non-bye, known-team, to tell
			$tA = (int)$mdata['teamA'];
			$tB = (int)$mdata['teamB'];
			if($tA > 0 || $tB > 0)
			{
				$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
				$bdata = $db->GetRow($sql,array($bracket_id));
				$smarty = cmsms()->GetSmarty();
				//general vars for template
				$this->SetTplVars($this->mod,$bdata,$mdata,$smarty);
				//team-specific vars recipient(for email),toall,opponent set downstream
				$ok = FALSE;
				$msgs = array();
				if($this->sender->text)
				{
					$funcs = new tmtSMS($this->mod,$this->sender->text,$smarty);
					list($ok,$msg1) = $funcs->TellTeams($bdata,$mdata,$tpl,$first);
					if(!$ok && $msg1)
						$msgs[] = $msg1;
				}
				if($this->sender->mail)
				{
					$funcs = new tmtMail($this->mod,$this->sender->mail,$smarty);
					list($ok,$msg1) = $funcs->TellTeams($bdata,$mdata,$tpl,$first);
					if(!$ok && $msg1)
						$msgs[] = $msg1;
				}
				if($this->sender->tweet)
				{
					$funcs = new tmtTweet($this->mod,$this->sender->tweet,$smarty);
					list($ok,$msg1) = $funcs->TellTeams($bdata,$mdata,$tpl,$first);
					if(!$ok && $msg1)
						$msgs[] = $msg1;
				}
				if($msgs)
					return array(FALSE,implode('<br />',$msgs));
				elseif(!$ok)
					return array(FALSE,$mod->Lang('nochannel'));
			}
			return array(TRUE,'');
		}
		return array(FALSE,$this->mod->Lang('err_match'));
	}
}

?>
