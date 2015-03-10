<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
 
Processes form-submission from admin brackets tab
*/
if(isset($params['cancel']) || empty($params['selitems']))
	$this->Redirect($id,'defaultadmin');
elseif(isset($params['clone']))
{
	$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$this->PrettyMessage('NOT YET IMPLEMENTED',FALSE,FALSE,FALSE)));
	foreach($params['selitems'] as $thisid)
	{
		//TODO
	}
}
elseif(isset($params['delete_item']))
{
	$vals = array_flip($params['selitems']); //convert strings
	$vals = array_flip($vals);
	$vc = count($vals);
	$fillers = str_repeat('?,',$vc-1).'?';
	$pref = cms_db_prefix();
	//TODO people
	$sql = 'DELETE FROM'.$pref.'module_tmt_teams WHERE bracket_id IN ('.$fillers.')';
//	$db->Execute($sql,$vals);
	$sql = 'DELETE FROM'.$pref.'module_tmt_matches WHERE bracket_id IN ('.$fillers.')';
//	$db->Execute($sql,$vals);
	$sql = 'DELETE FROM'.$pref.'module_tmt_brackets WHERE bracket_id IN ('.$fillers.')';
//	$db->Execute($sql,$vals);
$this->DoNothing();
}
elseif(isset($params['group']))
{
	$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$this->PrettyMessage('NOT YET IMPLEMENTED',FALSE,FALSE,FALSE)));
	//TODO cache $params['selitems']
	$gid = 0; //TODO get choice for new group
	$vals = array_flip($params['selitems']); //convert strings
	$vals = array_flip($vals);
	$vc = count($vals);
	$fillers = str_repeat('?,',$vc-1).'?';
	$pref = cms_db_prefix();
	$sql = 'UPDATE '.$pref.'module_tmt_brackets SET groupid=? WHERE bracket_id IN ('.$fillers.')';
	array_unshift($vals,$gid);
	$db->Execute($sql,$vals);
}
elseif(isset($params['export']))
{
	$funcs = new tmtXML();
	$res = $funcs->ExportXML($this,$params['selitems']);
	if ($res === TRUE)
		exit;
	$this->Redirect($id,'defaultadmin','',
			array('tmt_message'=>$this->PrettyMessage($res,FALSE)));
}

$this->Redirect($id,'defaultadmin');

?>
