<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

action for adjusting a team's members-order after a down- or up-button click
 (i.e. when js disabled, and so no DnD)
$params[] includes all standard data for the team, and also
 'real_action' => 'm1_movedown[N]' or 'm1_moveup[N]'
 'movedown' or 'moveup' => array (N => string 'X')
 where N = displayorder of player in team, X is what/irrelevant?
*/
if (!$this->CheckAccess('admod'))
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

//TODO save/cache other data?

$tid = $params['team_id'];
$pref = cms_db_prefix();
$sql = "UPDATE ".$pref."module_tmt_people SET displayorder=? WHERE id=? AND name=?";
if(isset($params['movedown']))
{
	reset($params['movedown']);
	$o1 = key($params['movedown']);
	$k = array_search($o1,$params['plr_order']);
	if(isset($params['plr_order'][$k+1]))
	{
		if($params['plr_order'][$k+1] < $o1+2)
			$db->Execute($sql,array($o1,$tid,$params['plr_name'][$k+1]));
		$db->Execute($sql,array($o1+1,$tid,$params['plr_name'][$k]));
	}

	$this->Redirect($id,'addedit_team',$returnid,array(
		'bracket_id'=>$params['bracket_id'],'team_id'=>$params['team_id'],'movedown'=>$pid));
}
elseif(isset($params['moveup']))
{
	reset($params['moveup']);
	$o1 = key($params['moveup']);
	$k = array_search($o1,$params['plr_order']);
	if($k > 0)
	{
		if($params['plr_order'][$k-1] > $o1-2)
			$db->Execute($sql,array($o1,$tid,$params['plr_name'][$k-1]));
		$db->Execute($sql,array($o1-1,$tid,$params['plr_name'][$k]));
	}

	$this->Redirect($id,'addedit_team',$returnid,array(
		'bracket_id'=>$params['bracket_id'],'team_id'=>$params['team_id'],'moveup'=>$pid));
}

$this->Redirect($id,'defaultadmin','',
	array('tmt_message'=>$this->PrettyMessage('error',FALSE)));

?>

