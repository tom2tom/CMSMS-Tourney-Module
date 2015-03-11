<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (!$this->CheckAccess('admod'))
	$this->Redirect($id,'defaultadmin','',
		array('showtab'=>1,'tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

if (!empty($params['group_id']))
{
	$pref = cms_db_prefix();
	$sql = 'UPDATE '.$pref.'module_tmt_brackets SET groupid=0 WHERE groupid=?';
	$db->Execute($sql,array($params['group_id']));
	$sql = 'DELETE FROM '.$pref.'module_tmt_groups WHERE group_id=?';
	$db->Execute($sql,array($params['group_id']));
}
$this->Redirect($id,'defaultadmin','',array('showtab'=>1));

?>
