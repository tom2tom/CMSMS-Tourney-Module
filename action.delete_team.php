<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Mark single team, or selected team(s), as deleted (flags = 2)
*/
$newparms = $this->GetEditParms($params,'playerstab');
if ($this->CheckAccess('admod'))
{
	if(isset($params['delete_team'])) //single team
	{
		$cond = '=?';
		reset($params['delete_team']);
		$tid = key($params['delete_team']);
		$args = array($tid);
	}
	elseif(!empty($params['tsel'])) //selected teams
	{
		$cond = ' IN ('.str_repeat('?,',count($params['tsel'])-1).'?)';
		$args = $params['tsel'];
	}
	$pref = cms_db_prefix();
	$sql = 'UPDATE '.$pref.'module_tmt_people SET flags=2 WHERE id'.$cond;
	$db->Execute($sql,$args);
	$sql = 'UPDATE '.$pref.'module_tmt_teams SET flags=2 WHERE team_id'.$cond;
	$db->Execute($sql,$args);
	//defer changes to other teams' displayorder and any consequent match-changes until comp save/cancel
}
else
	$newparms['tmt_message'] = $this->PrettyMessage('lackpermission',FALSE);

$this->Redirect($id,'addedit_comp',$returnid,$newparms);
?>
