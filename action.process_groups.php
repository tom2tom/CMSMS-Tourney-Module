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
	foreach($vals as $thisid)
	{
		$indx = array_search($thisid,$params['group_active']);
		$name = trim($params['group_name'][$indx]);
		if(!$name)
			$name = '<'.$this->Lang('noname').'>';
		if($thisid == '-1')
		{
			$nc = count($params['group_name']);
			$thisid = $db->GenID($pref.'module_tmt_groups_seq');
			$db->Execute('INSERT INTO '.$pref.'module_tmt_groups (group_id,name,vieworder) VALUES (?,?,?)',
				array((int)$thisid,$name,$nc));
		}
		else
		{
			if($thisid)
				$active = (int)$params['group_active'][$indx]; //TODO mask lowest bit
			else
				$active = 1; //default/ungrouped always active
			$db->Execute($sql,array($name,$active,(int)$thisid));
		}
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
elseif(isset($params['sort']))
{
	//TODO
}

$this->Redirect($id, 'defaultadmin','',array('showtab'=>1));

?>
