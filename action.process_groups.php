<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
 
Processes form-submission from admin groups tab
*/

if(isset($params['cancel']) || empty($params['selgroups']))
	$this->Redirect($id, 'defaultadmin','',array('showtab'=>1));

$vals = array_flip($params['selgroups']); //convert strings
$vals = array_flip($vals);

if(isset($params['update']))
{
	$pref = cms_db_prefix();
	$sql = 'UPDATE '.$pref.'module_tmt_groups SET name=?,flags=? WHERE group_id=?';
	foreach($vals as $indx=>$thisid)
	{
		$name = $params['group_names'][$indx];
		if($thisid)
			$active = (int)$params['activegroups'][$indx]; //TODO mask lowest bit
		else
			$active = 1; //default/ungrouped always active
		$db->Execute($sql,array($name,$active,(int)$thisid));
	}
}
elseif(isset($params['delete_group']))
{
	$vals = array_diff($vals,array(0,'0','')); //preserve default
	$vc = count($vals);
	if($vc)
	{
		$fillers = str_repeat('?,',$vc-1).'?';
		$pref = cms_db_prefix();
		$sql = 'UPDATE '.$pref.'module_tmt_brackets SET groupid = 0 WHERE groupid IN ('.$fillers.')';
		$db->Execute($sql,$vals);
		$sql = 'DELETE FROM'.$pref.'module_tmt_groups WHERE group_id IN ('.$fillers.')';
		$db->Execute($sql,$vals);
	}
}
elseif(isset($params['activate']))
{
	$vals = array_diff($vals,array(0,'0','')); //preserve default
	$vc = count($vals);
	if($vc)
	{
		$fillers = str_repeat('?,',$vc-1).'?';
		$pref = cms_db_prefix();
		$state = FALSE; //TODO $state = func(VALUES OF $params['activegroups']);
		$sql = 'UPDATE '.$pref.'module_tmt_groups SET flags=? WHERE groupid IN ('.$fillers.')';
		array_unshift($vals,$state);
		$ares = $db->Execute($sql,$vals);
	}
}

$this->Redirect($id, 'defaultadmin','',array('showtab'=>1));

?>
