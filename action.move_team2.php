<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

action for adjusting a bracket's team-order after a down- or up-button click
 (i.e. when js disabled, and so no DnD)
$params[] includes all standard data for the bracket, and also
 'real_action' => 'm1_movedown[N]' or 'm1_moveup[N]'
 'movedown' or 'moveup' => array (N => string 'X')
 where N = team id, X is what/irrelevant? 
*/
if (!$this->CheckAccess('admod'))
		$this->Redirect($id,'defaultadmin','',
			array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

//TODO save/cache other data?

$pref = cms_db_prefix();
$sql = "UPDATE ".$pref."module_tmt_teams SET displayorder=? WHERE team_id=?";
if(isset($params['movedown']))
{
	reset($params['movedown']);
	$tid = key($params['movedown']);
	$k = array_search($tid,$params['tem_teamid']);
	if(isset($params['tem_teamid'][$k+1]))
	{
		$o2 = $k+1; //1-based order numbers
		$t2 = $params['tem_teamid'][$o2];
		$db->Execute($sql,array($o2,$t2));
		$db->Execute($sql,array($o2+1,$tid));
	}
}
elseif(isset($params['moveup']))
{
	reset($params['moveup']);
	$tid = key($params['moveup']);
	$k = array_search($tid,$params['tem_teamid']);
	if($k > 0)
	{
		$o2 = $k+1; //1-based order numbers
		$t2 = $params['tem_teamid'][$k-1];
		$db->Execute($sql,array($o2,$t2));
		$db->Execute($sql,array($k,$tid));
	}
}

$this->Redirect($id,'addedit_comp',$returnid,array(
	'bracket_id'=>$params['bracket_id'],
	'real_action' =>'edit',
	'active_tab' =>'playerstab')
	);

?>
