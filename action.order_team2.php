<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

action for adjusting a team's members-order after a down- or up-button click
 (i.e. when js disabled, and so no DnD)
$params[] includes all standard data for the team, and also
 'real_action' => 'm1_plr_down[N,name]' or 'm1_plr_up[N,name]'
 'plr_down' or 'plr_up' => array (N,name => string 'X')
 where N = player id (not unique), name = player name, X is what/irrelevant?
*/
if (!$this->CheckAccess('admod'))
		$this->Redirect($id,'defaultadmin','',
			array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

//TODO save/cache other data?

$pref = cms_db_prefix();
$sql1 = "SELECT displayorder FROM ".$pref."module_tmt_people WHERE id=? AND name=? AND flags!=2";
$sql2 = "SELECT name FROM ".$pref."module_tmt_people WHERE id=? AND displayorder=? AND flags!=2";
$sql3 = "UPDATE ".$pref."module_tmt_people SET displayorder=? WHERE id=? AND name=?";
if(isset($params['plr_down']))
{
	reset($params['plr_down']);
	list($pid,$pname) = explode(',',key($params['plr_down']));
	$o1 = $db->GetOne($sql1,array($pid,$pname));
	$n2 = $db->GetOne($sql2,array($pid,$o1+1));
	if ($n2 !== FALSE)
		$db->Execute($sql3,array($o1,$pid,$n2));
	$db->Execute($sql3,array($o1+1,$pid,$pname));
	$this->Redirect($id,'addedit_team',$returnid,array(
		'bracket_id'=>$params['bracket_id'],'team_id'=>$params['team_id'],'movedown'=>$pid));
}
elseif(isset($params['plr_up']))
{
	reset($params['plr_up']);
	list($pid,$pname) = explode(',',key($params['plr_up']));
	$o1 = $db->GetOne($sql1,array($pid,$pname));
	if($o1 > 1)
	{
		$n2 = $db->GetOne($sql2,array($pid,$o1-1));
		if ($n2 !== FALSE)
			$db->Execute($sql3,array($o1,$pid,$n2));
		$db->Execute($sql3,array($o1-1,$pid,$pname));
	}
	$this->Redirect($id,'addedit_team',$returnid,array(
		'bracket_id'=>$params['bracket_id'],'team_id'=>$params['team_id'],'moveup'=>$pid));
}

$this->Redirect($id,'defaultadmin','',
	array('tmt_message'=>$this->PrettyMessage('error',FALSE)));

?>
