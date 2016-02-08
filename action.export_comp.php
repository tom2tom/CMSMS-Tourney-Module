<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
if(!$this->CheckAccess('adview'))
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

$funcs = new tmtXML();
$res = $funcs->ExportXML($this,$params['bracket_id']);
if ($res === TRUE)
	exit;
else
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage($res,FALSE)));
?>
