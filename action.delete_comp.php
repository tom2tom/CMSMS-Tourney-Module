<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (!$this->CheckAccess('admod'))
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

if (empty($params['bracket_id']))
	$this->Redirect($id,'defaultadmin');

$funcs = new tmtDelete();
$funcs->DeleteBracket($this,(int)$params['bracket_id']);

$this->Redirect($id,'defaultadmin','',
	array('tmt_message'=>$this->PrettyMessage('comp_deleted')));

?>
