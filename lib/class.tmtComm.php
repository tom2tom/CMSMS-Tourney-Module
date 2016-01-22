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
	protected $cansend;
	protected $incpath;

	function __construct(&$mod)
	{
		$this->mod = $mod;
		if(class_exists('Notifier',FALSE))
		{
			$this->cansend = TRUE;
			$path = $mod->GetModulePath();
			$name = $mod->GetName();
			$offs = strpos($path,$name);
			$base = substr($path,0,$offs);
			$this->incpath = cms_join_path($base,'Notifier','lib','class');
			spl_autoload_register(array($this,'loader'));
		}
		else
			$this->cansend = FALSE;
	}

	private function loader($className)
	{
		include $this->incpath.$className.'.php';
	}

	/**
	SetTplVars:
	Set generic vars for use in templates
	@mod: reference to current module object
	@bdata: reference to array of bracket-table data
	@mdata: reference to array of match data (from which we need 'teamA', 'teamB')
	@tplvars: reference to array of template variables
	*/
	private function SetTplVars(&$mod,&$bdata,&$mdata,&$tplvars)
	{
		$tplvars += array(
			'title' => $bdata['name'],
			'description' => $bdata['description'],
			'owner' => $bdata['owner'],
			'contact' => $bdata['contact'],
			'smsfrom' => $bdata['smsfrom'],
			'where' => $mdata['place']
		);
		$tfmt = $mod->GetPreference('time_format');
//	tmtUtils()?
		$dfmt = $mod->GetZoneDateFormat($bdata['timezone']);
		$dt = new DateTime($mdata['playwhen'],new DateTimeZone($bdata['timezone']));
		$stamp = $dt->GetTimestamp();
		$tplvars['time'] = date($tfmt,$stamp);
		$tplvars['date'] = date($dfmt,$stamp);
		//time before date, can't rely on $bdata['atformat']
		$tplvars['when'] = date($tfmt.', '.$dfmt,$stamp);
		$tid = (int)$mdata['teamA'];
		$tA = ($tid > 0) ? $mod->TeamName($mdata['teamA']) : '';
		$tid = (int)$mdata['teamB'];
		$tB = ($tid > 0) ? $mod->TeamName($mdata['teamB']) : '';
		if($tA && $tB)
			$tplvars['teams'] = $tA.', '.$tB;
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
			$tplvars['teams'] = $tA.$tB.', '.$op;
		}
	}

	/**
	TellOwner:
	Sends message via relevant channel(s) to tournament owner, and to one member of both teams in the match
	@bracket_id: index of competition to be processed
	@match_id: index of match to be processed
	@bodylines: array of lines for message body
	Returns: array with two members: 1st TRUE|FALSE representing success, 2nd specific problem message or FALSE
	*/
	public function TellOwner($bracket_id,$match_id,$bodylines)
	{
		if(!$this->cansend)
			return array(FALSE,$this->mod->Lang('nonotifier'));

		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT teamA,teamB,place,playwhen FROM '.$pref.'module_tmt_matches WHERE match_id=? AND flags=0';
		$mdata = $db->GetRow($sql,array($match_id));
		if($mdata)
		{
			$sender = new MessageSender();
			$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$bdata = $db->GetRow($sql,array($bracket_id));
			//general vars for template
			$tplvars = array();
			$this->SetTplVars($this->mod,$bdata,$mdata,$tplvars);
			//channel-specific var report set downstream
			$ok = FALSE;
			$msgs = array();

			$funcs = new tmtSMS($this->mod);
			list($ok,$msg1) = $funcs->TellOwner($sender,$bdata,$mdata,$tplvars,$bodylines);
			if(!$ok && $msg1)
				$msgs[] = $msg1;
			else
				$ok = TRUE;

			$funcs = new tmtMail($this->mod);
			list($ok,$msg1) = $funcs->TellOwner($sender,$bdata,$mdata,$tplvars,$bodylines);
			if(!$ok && $msg1)
				$msgs[] = $msg1;
			else
				$ok = TRUE;

			$funcs = new tmtTweet($this->mod);
			list($ok,$msg1) = $funcs->TellOwner($sender,$bdata,$mdata,$tplvars,$bodylines);
			if(!$ok && $msg1)
				$msgs[] = $msg1;
			else
				$ok = TRUE;

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
	@type: enum for type of message: 1 = announcement, 2 = cancellation, 3 = score-request
	@first: TRUE to send only to first recognised address, FALSE to send per
		the teams' respective contactall settings, optional, default FALSE
	Returns: array with two members: 1st TRUE|FALSE representing success or nothing to do,
		2nd a specific problem message or FALSE
	*/
	public function TellTeams($bracket_id,$match_id,$type,$first=FALSE)
	{
		if(!$this->cansend)
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
				$sender = new MessageSender();
				$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
				$bdata = $db->GetRow($sql,array($bracket_id));
				//general vars for template
				$tplvars = array();
				$this->SetTplVars($this->mod,$bdata,$mdata,$tplvars);
				//team-specific vars recipient(for email),toall,opponent set downstream
				$ok = FALSE;
				$msgs = array();

				$funcs = new tmtSMS($this->mod);
				list($ok,$msg1) = $funcs->TellTeams($sender,$bdata,$mdata,$tplvars,$type,$first);
				if(!$ok && $msg1)
					$msgs[] = $msg1;

				$funcs = new tmtMail($this->mod);
				list($ok,$msg1) = $funcs->TellTeams($sender,$bdata,$mdata,$tplvars,$type,$first);
				if(!$ok && $msg1)
					$msgs[] = $msg1;

				$funcs = new tmtTweet($this->mod);
				list($ok,$msg1) = $funcs->TellTeams($sender,$bdata,$mdata,$tplvars,$type,$first);
				if(!$ok && $msg1)
					$msgs[] = $msg1;

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
