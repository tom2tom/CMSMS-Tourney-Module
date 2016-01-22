<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
 
Processes form-submission from admin groups tab
*/

if(isset($params['update']))
{
	if(!empty($params['selgroups']))
	{
		$vals = array_flip($params['selgroups']); //convert strings
		$vals = array_flip($vals);
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
				$thisid = $db->GenID($pref.'module_tmt_groups_seq');
				$nc = count($params['group_name']);
				$db->Execute('INSERT INTO '.$pref.'module_tmt_groups (group_id,name,displayorder) VALUES (?,?,?)',
					array((int)$thisid,$name,$nc));
			}
			else
			{
				if($thisid) //not the default group (0)
				{
					$active = (int)$params['group_active'][$indx]; //TODO mask lowest bit
					if($active > 1)
						$active = 1;
				}
				else
					$active = 1; //default/ungrouped always active
				$db->Execute($sql,array($name,$active,(int)$thisid));
			}
		}
	}
}
elseif(isset($params['delete_group']))
{
	if(!empty($params['selgroups']))
	{
		$vals = array_flip($params['selgroups']);
		$vals = array_flip($vals);
		$vals = array_diff($vals,array(0,'0','')); //preserve default
		$vc = count($vals);
		if($vc)
		{
			$fillers = str_repeat('?,',$vc-1).'?';
			$pref = cms_db_prefix();
			$sql = 'UPDATE '.$pref.'module_tmt_brackets SET groupid = 0 WHERE groupid IN ('.$fillers.')';
			$db->Execute($sql,$vals);
			$sql = 'DELETE FROM '.$pref.'module_tmt_groups WHERE group_id IN ('.$fillers.')';
			$db->Execute($sql,$vals);
		}
	}
}
elseif(isset($params['activate']))
{
	if(!empty($params['selgroups']))
	{
		$vals = array_diff($params['selgroups'],array(0,'0','')); //exclude/preserve default
		$vc = count($vals);
		if($vc)
		{
			foreach($vals as $k=>$id)
				$vals[$k] = (int)$id; //integer from string
			$pref = cms_db_prefix();
			$fillers = str_repeat('?,',$vc-1).'?';
			$sql = 'SELECT COUNT(1) AS num FROM '.$pref.'module_tmt_groups WHERE group_id IN ('.$fillers.') AND flags = 0';
			$inact = $db->GetOne($sql,$vals);
			$toggled = ($inact !== FALSE && (int)$inact == 0) ? '0':'1';
			$sql = 'UPDATE '.$pref.'module_tmt_groups SET flags='.$toggled.' WHERE group_id IN ('.$fillers.')';
			$db->Execute($sql,$vals);
		}
	}
}
elseif(isset($params['sort']))
{
	if(version_compare(PHP_VERSION,'5.3.0') >= 0 && extension_loaded('intl'))
	{
		$locale = setlocale(LC_COLLATE,'0'); //TODO
		$cl = new Collator($locale);
		$cl->asort($params['group_name'],Collator::SORT_STRING);
	}
	else
		asort($params['group_name'],SORT_LOCALE_STRING); //probably too bad about i18n
	$ord = 1;
	$pref = cms_db_prefix();
	$sql = 'UPDATE '.$pref.'module_tmt_groups SET displayorder=? WHERE group_id=?';
	foreach ($params['group_name'] as $indx=>&$name)
	{
		$id = (int)$params['group_active'][$indx];
		$db->Execute($sql,array($ord,$id));
		$ord++;
	}
	unset($name);
}

$this->Redirect($id, 'defaultadmin','',array('showtab'=>1));

?>
