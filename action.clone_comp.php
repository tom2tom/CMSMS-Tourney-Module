<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (! $this->CheckAccess('admod'))
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

$bid = (int)$params['bracket_id'];
$funcs = new tmtClone();
$res = $funcs->CloneBracket($this,$bid);
if($res === TRUE)
	$this->Redirect($id,'addedit_comp',$returnid,array('bracket_id'=>$bid));
else
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage($res,FALSE)));

?>
