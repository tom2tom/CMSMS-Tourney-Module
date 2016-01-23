<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
if (!$this->CheckAccess())
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

if(isset($params['cancel']))
	$this->Redirect($id,'defaultadmin','');

/*
Arrive here following adminpanel add or edit click, or one of the various actions
initiated from the page setup and displayed here
Determine whether to add a new comp
*/
$add = FALSE;
if (isset($params['real_action']))
{
	//back here after operation initiated from previous iteration (and with js enabled)
	if (empty($params['bracket_id']))
	{
		$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$this->PrettyMessage($params['real_action'],FALSE))); //TODO DEBUG
		if (0) //TODO $params['real_action'] == ?)
			$add = TRUE;
	}
	if (strpos($params['real_action'],'move') === 0)
	{
		//strip off the [teamid]
		$tmp = $params['real_action'];
		$pos = strpos($tmp,'[');
		$movetid = substr($tmp,$pos+1,-1);
		$params['real_action'] = substr($tmp,0,$pos);
	}
}
elseif(isset($params['action']))
{
	//add|edit initiated from adminpanel (or others if js disabled)
	if (empty($params['bracket_id']))
	{
		$params['real_action'] = 'add';
		$add = TRUE;
	}
	else
		$params['real_action'] = 'edit';
}
else
	$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$this->PrettyMessage('err_system',FALSE)));

$tab = $this->GetActiveTab($params);
$message = (!empty($params['tmt_message'])) ? $params['tmt_message'] : FALSE;
if ($add)
{
	$funcs = new tmtData();
	$data = $funcs->GetBracketData($this,'add',$id);
	unset($funcs);
	//save initial (non-default) data for bracket
	$args = array(
	'bracket_id' => $data->bracket_id,
	'type' => Tourney::KOTYPE, //$data->type is empty string
	'seedtype' => $data->seedtype,
	'fixtype' => $data->fixtype,
	'teamsize' => $data->teamsize,
	'atformat' => $data->atformat,
	'final' => $data->final,
	'semi' => $data->semi,
	'quarter' => $data->quarter,
	'eighth' => $data->eighth,
	'roundname' => $data->roundname,
	'versus' => $data->versus,
	'defeated' => $data->defeated,
	'tied' => $data->tied,
	'bye' => $data->bye,
	'forfeit' => $data->forfeit,
	'nomatch' => $data->nomatch,
	'timezone' => $data->timezone,
	'playgap' => $data->playgap,
	'playgaptype' => $data->playgaptype,
	'available' => $data->available,
	'placegap' => $data->placegap,
	'placegaptype' => $data->placegaptype);
	$fields = implode(',',array_keys($args));
	$fillers = str_repeat('?,',count($args)-1).'?';
	$sql = 'INSERT INTO '.cms_db_prefix().'module_tmt_brackets ('.$fields.') VALUES ('.$fillers.');';
	$db->Execute($sql,array_values($args));
	if ($data->motemplate)
		tmtTemplate::Set($this,'mailout_'.$data->bracket_id.'_template',$data->motemplate);
	if($data->mcanctemplate)
		tmtTemplate::Set($this,'mailcancel_'.$data->bracket_id.'_template',$data->mcanctemplate);
	if($data->mreqtemplate)
		tmtTemplate::Set($this,'mailrequest_'.$data->bracket_id.'_template',$data->mreqtemplate);
	if ($data->mitemplate)
		tmtTemplate::Set($this,'mailin_'.$data->bracket_id.'_template',$data->mitemplate);
	if ($data->totemplate)
		tmtTemplate::Set($this,'tweetout_'.$data->bracket_id.'_template',$data->totemplate);
	if($data->tcanctemplate)
		tmtTemplate::Set($this,'tweetcancel_'.$data->bracket_id.'_template',$data->tcanctemplate);
	if($data->treqtemplate)
		tmtTemplate::Set($this,'tweetrequest_'.$data->bracket_id.'_template',$data->treqtemplate);
	if ($data->titemplate)
		tmtTemplate::Set($this,'tweetin_'.$data->bracket_id.'_template',$data->titemplate);
	if ($data->chttemplate)
		tmtTemplate::Set($this,'chart_'.$data->bracket_id.'_template',$data->chttemplate);
}
else
{
	$bid = (int)$params['bracket_id'];
	switch ($params['real_action'])
	{
	 case 'result_view':
	 case 'match_view':
		break;
	 case 'connect':
		$ob = $this->GetModuleInstance('Notifier');
		if($ob)
		{
			$ob->DoAction('start',$id,array());
		}
		//if still here, some problem occurred
		$params['real_action'] = 'edit';
	 	break;
	 case 'movedown':
	 case 'moveup':
		$pref = cms_db_prefix();
		$sql = 'SELECT displayorder FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND team_id=?';
		$from = $db->GetOne($sql,array($bid,$movetid));
		if($from)
		{
			$to = ($params['real_action']=='moveup') ? $from-1 : $from+1;
			if($to > 0)
			{
				$sql = 'UPDATE '.$pref.'module_tmt_teams SET displayorder='.$from.' WHERE bracket_id=? AND displayorder='.$to;
				$db->Execute($sql,array($bid));
				$sql = 'UPDATE '.$pref.'module_tmt_teams SET displayorder='.$to.' WHERE bracket_id=? AND team_id=?';
				$db->Execute($sql,array($bid,(int)$movetid));
			}
		}
		$params['real_action'] = 'edit';
		break;
	 default:
		//refresh matches if appropriate
		$pref = cms_db_prefix();
		$sql = 'SELECT COUNT(match_id) AS num FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0';
		if ($db->GetOne($sql,array($bid)))
		{
			$sch = new tmtSchedule();
			$sql = 'SELECT type FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$type = $db->GetOne($sql,array($bid));
			switch ($type)
			{
			 case Tourney::DETYPE:
				$sch->UpdateDEMatches($this,$bid);
				break;
			 case Tourney::RRTYPE:
				$sch->NextRRMatches($this,$bid);
				break;
			 default:
			 //case Tourney::KOTYPE:
				$sch->UpdateKOMatches($this,$bid);
				break;
			}
			unset($sch);
		}
	}

	$funcs = new tmtData();
	$data = $funcs->GetBracketData($this,$params['real_action'],$id,$bid,$params);
	unset($funcs);
	if (isset ($params['tmt_message']))
		$message = $params['tmt_message'];
}

if ($data)
{
	if ($params['real_action'] == 'view')
		$data->readonly = 1;
	$tplvars = array();
	$funcs = new tmtEditSetup();
	$funcs->Setup($this,$tplvars,$data,$id,$returnid,$tab,$message);
	unset($funcs);
	unset($data);
	tmtTemplate::Process($this,'addedit_comp.tpl',$tplvars);
}
else
{
	if (!empty($message))
		$message .= '<br />/<br />'.$this->Lang('err_missing');
	else
		$message = $this->PrettyMessage('err_missing',FALSE);
	$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$message));
}

?>
