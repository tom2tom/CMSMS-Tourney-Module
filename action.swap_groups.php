<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (!isset($params['category_id']))
	$this->Redirect($id, 'defaultadmin', '', array('showtab'=>1, 'tmt_message'=>$this->PrettyMessage('err_value',FALSE)));

$pref = cms_db_prefix();
$sql = 'SELECT displayorder FROM '.$pref.'module_tmt_groups WHERE group_id=?';
$thiscat = array($params['category_id']);
$num1 = $db->GetOne($sql,$thiscat);
if ($num1 === FALSE)
	$this->Redirect($id, 'defaultadmin', '', array('showtab'=>1, 'tmt_message'=>$this->PrettyMessage('err_value',FALSE)));

$othercat = array();
if (isset($params['next_id']))
	$othercat[] = $params['next_id'];
elseif (isset($params['prev_id']))
	$othercat[] = $params['prev_id'];
else
	$this->Redirect($id, 'defaultadmin', '', array('showtab'=>1));

$num2 = $db->GetOne($sql,$othercat);
if ($num2 === FALSE)
	$this->Redirect($id, 'defaultadmin', '', array('showtab'=>1, 'tmt_message'=>$this->PrettyMessage('err_value',FALSE)));

$sql = 'UPDATE '.$pref.'module_tmt_groups SET displayorder=? WHERE group_id=?';
array_unshift($thiscat,$num2);
$db->Execute($sql,$thiscat);
array_unshift($othercat,$num1);
$db->Execute($sql,$othercat);

$this->Redirect($id, 'defaultadmin','',array('showtab'=>1));

?>
