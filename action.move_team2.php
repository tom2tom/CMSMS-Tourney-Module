<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

action for adjusting a bracket's team-order after a down- or up-button click
 (i.e. when js disabled, and so no DnD)
$params[] includes all standard data for the bracket, and also
 'real_action' => 'm1_down_team[N]' or 'm1_up_team[N]'
 'down_team' or 'up_team' => array (N => string 'X')
 where N = team id, X is what/irrelevant? 
*/
if (!$this->CheckAccess('admod'))
		$this->Redirect($id,'defaultadmin','',
			array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

//TODO save/cache other data?

$pref = cms_db_prefix();
$sql1 = "SELECT displayorder FROM ".$pref."module_tmt_teams WHERE team_id=? AND flags!=2";
$sql2 = "SELECT team_id FROM ".$pref."module_tmt_teams WHERE bracket_id=? AND displayorder=? AND flags!=2";
$sql3 = "UPDATE ".$pref."module_tmt_teams SET displayorder=? WHERE team_id=?";
if(isset($params['down_team']))
{
	reset($params['down_team']);
	$tid = key($params['down_team']);
	$o1 = $db->GetOne($sql1,array($tid));
	$t2 = $db->GetOne($sql2,array($params['bracket_id'],$o1+1));
	if ($t2 !== FALSE)
		$db->Execute($sql3,array($o1,$t2));
	$db->Execute($sql3,array($o1+1,$tid));
}
elseif(isset($params['up_team']))
{
	reset($params['up_team']);
	$tid = key($params['up_team']);
	$o1 = $db->GetOne($sql1,array($tid));
	if($o1 > 1)
	{
		$t2 = $db->GetOne($sql2,array($params['bracket_id'],$o1-1));
		if ($t2 !== FALSE)
			$db->Execute($sql3,array($o1,$t2));
		$db->Execute($sql3,array($o1-1,$tid));
	}
}

$this->Redirect($id,'addedit_comp',$returnid,array(
	'bracket_id'=>$params['bracket_id'],
	'real_action' =>'edit',
	'active_tab' =>'playerstab')
	);

?>
