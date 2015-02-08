<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (!function_exists ('ImportXML'))
{
/**
 ImportXML:
 tmp_file
 Read, parse and check high-level structure of xml file whose path is tmp_file
 Returns: xml'ish tree-shaped array (with text encoded as UTF-8), or FALSE
*/
 function ImportXML($tmp_file)
 {
	//xml-namespacing rules out some simpler, neater approaches here
	$parser = xml_parser_create_ns('UTF-8',':');
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
	xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, 'UTF-8').
	$res = xml_parse_into_struct($parser, file_get_contents($tmp_file), $xmlarray);
	xml_parser_free($parser);
	if ($res === 0) return FALSE;
	if (empty($xmlarray[0]) || empty($xmlarray[0]['tag']) || $xmlarray[0]['tag'] != 'tourney')
		return FALSE;

	array_shift($xmlarray);
	$arrsize = count($xmlarray);

	$opened = array();
	$opened[1] = 0;
	for($j = 0; $j < $arrsize; $j++)
	{
		$val = $xmlarray[$j];
		switch($val['type'])
		{
			case 'open': //start of a new level
				$opened[$val['level']]=0;
			case 'complete': //a single value
				$lvl = $val['level'];
				$index = '';
				for($i = 1; $i < $lvl; $i++)
					$index .= '['.$opened[$i].']';
				$path = explode('][', substr($index, 1, -1));
				if($val['type'] == 'complete')
					array_pop($path);
				$value = &$array;
				foreach($path as $segment)
					$value = &$value[$segment];
				$v = (!empty($val['value'])) ? $val['value'] : null; //default value is null
				$value[$val['tag']] = $v;
				if($val['type'] == 'complete' && $lvl > 1)
					$opened[$lvl-1]++;
				break;
			case 'close': //end of a level
				if ($val['level'] > 1)
					$opened[$val['level']-1]++;
				unset($opened[$val['level']]);
				break;
		}
	}
	unset($value);
	//clear top-level numeric keys and related tags
 	foreach($array as $indx=>&$value)
	{
		if (is_numeric($indx))
		{
			$key = key(array_slice($value,0,1,TRUE));
			array_shift($value);
			$array[$key] = $value;
			unset($array[$indx]);
		}
	}
	unset($value);
	//and lower-level tags
	ClearTags($array);
	//and namespace prefixes
	ClearSpaces($array);

	$expected = array('version','date','properties','teams','people','matches');
	foreach ($array as $indx=>&$check)
	{
		if (!in_array($indx,$expected))
		{
			unset($check);
			return FALSE;
		}
	}
	unset($check);
	foreach ($expected as &$check)
	{
		if (!array_key_exists($check, $array))
		{
			unset($check);
			return FALSE;
		}
	}
	unset($check);
	return $array;
 }

 function ClearTags(&$array)
 {
 	foreach($array as $indx=>&$val)
	{
		if (is_array($val))
		{
			if (is_numeric($indx))
				array_shift($val);
			ClearTags($val); //recurse
		}
	}
	unset($val);
 }

 function ClearSpaces(&$array)
 {
 	foreach($array as $indx=>&$val)
	{
		if (is_array($val))
			ClearSpaces($val); //recurse
		$done = 0;
		$tmp = preg_replace('~^file:///[[:alnum:]]+:~','',$indx,10,$done); //$done-counter is for PHP5.1+ !
		if ($tmp != null && $done == 1)
		{
			$array[$tmp] = $val;
			unset($array[$indx]);
		}
	}
	unset($val);
 }

}

if (! $this->CheckAccess('admod'))
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

$file_data = $_FILES[$id.'xmlfile'];
if ($file_data['type'] != 'text/xml'
 || $file_data['size'] <= 0
 || $file_data['error'] != 0)
{
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('err_import_failed',FALSE)));
}

$success = FALSE;
$bracketdata = ImportXML($file_data['tmp_name']);
if ($bracketdata)
{
	//TODO convert imported data from UTF-8 to site-encoding, if needed
	//$bracketdata['version'] = string e.g. '0.1[.2]' CHECKME use this ?
	//$bracketdata['date'] = string CHECKME use this ?
	$fields = array_keys($bracketdata['properties']);
	$values = array_values($bracketdata['properties']);
	//don't 'force' NULL for fields which default to something else
	foreach ($values as $indx=>$val)
	{
		if ($val === NULL)
		{
			unset($values[$indx]);
			unset($fields[$indx]);
		}
	}
	$pref = cms_db_prefix();
	$bid = $db->GenID($pref.'module_tmt_brackets_seq');
	$fixer = array_search('bracket_id',$fields);
	if ($fixer === FALSE)
	{
		array_unshift($fields,'bracket_id');
		array_unshift($values,$bid);
	}
	else
		$values[$fixer] = $bid;
	$fixer = array_search('name',$fields);
	if ($fixer !== FALSE)
	{
		if($values[$fixer])
			$values[$fixer] .= ' '.$this->Lang('import');
		else
			$values[$fixer] = $this->Lang('import').' '.$this->Lang('noname');
	}
	$fixer = array_search('alias',$fields);
	if ($fixer !== FALSE)
	{
		unset($values[$fixer]);
		unset($fields[$fixer]);
	}
	$fixer = array_search('chartbuild',$fields);
	if ($fixer !== FALSE)
		$values[$fixer] = 0; //clear flag
	$fc = count($fields);
	$fillers = str_repeat('?,',$fc-1).'?';
	$sql = 'INSERT INTO '.$pref.'module_tmt_brackets ('.implode(',',$fields).') VALUES ('.$fillers.')';
	if ($db->Execute($sql,$values))
	{
		if (!empty($bracketdata['teams']) && is_array ($bracketdata['teams']))
		{
			$teamswaps = array();
			$fields = array_keys($bracketdata['teams'][0]);
			$fixer = array_search('bracket_id',$fields);
			$fixer2 = array_search('team_id',$fields);
			if ($fixer !== FALSE && $fixer2 !== FALSE)
			{
				$fixer3 = array_search('flags',$fields);
				foreach ($bracketdata['teams'] as $thisone)
				{
					$values = array_values($thisone);
					$values[$fixer] = $bid;
					$id = $db->GenID($pref.'module_tmt_teams_seq');
					$teamswaps[$values[$fixer2]] = $id; //log, for changing team-members
					$values[$fixer2] = $id;
					$values[$fixer3] = 1; //flag added team
					$fields = array_keys($thisone);
					foreach ($values as $indx=>$val)
					{
						if ($val === NULL)
						{
							unset($values[$indx]);
							unset($fields[$indx]);
						}
					}
					$fc = count($fields);
					$fillers = str_repeat('?,',$fc-1).'?';
					$sql = 'INSERT INTO '.$pref.'module_tmt_teams ('.implode(',',$fields).') VALUES ('.$fillers.')';
					$db->Execute($sql,$values);
				}
			}
			if (!empty($bracketdata['people']) && is_array ($bracketdata['people']))
			{
				$fields = array_keys($bracketdata['people'][0]);
				$fixer = array_search('id',$fields);
				$fixer2 = array_search('flags',$fields);
				foreach ($bracketdata['people'] as $thisone)
				{
					$values = array_values($thisone);
					if (array_key_exists($values[$fixer],$teamswaps))
						$values[$fixer] = $teamswaps[$values[$fixer]]; //new team id
					$values[$fixer2] = 1; //added member
					$fields = array_keys($thisone);
					foreach ($values as $indx=>$val)
					{
						if ($val === NULL)
						{
							unset($values[$indx]);
							unset($fields[$indx]);
						}
					}
					$fc = count($fields);
					$fillers = str_repeat('?,',$fc-1).'?';
					$sql = 'INSERT INTO '.$pref.'module_tmt_people ('.implode(',',$fields).') VALUES ('.$fillers.')';
					$db->Execute($sql,$values);
				}
			}
			if (!empty($bracketdata['matches']) && is_array ($bracketdata['matches']))
			{
				$fields = array_keys($bracketdata['matches'][0]);
				$fixer = array_search('bracket_id',$fields);
				$fixer2 = array_search('match_id',$fields);
				if ($fixer !== FALSE && $fixer2 !== FALSE)
				{
					//TODO flag 'added'
					$fixer3 = array_search('nextm',$fields); //irrelevant for RR
					$fixer4 = array_search('nextlm',$fields); //ditto and for KO
					$mc = count($bracketdata['matches']);
					$sch = new tmtSchedule();
					$mid = $sch->ReserveMatches($db,$pref,$mc);
					$offset = $mid - $bracketdata['matches'][0]['match_id']; //lowest match-no. is sorted 1st
					foreach ($bracketdata['matches'] as $thisone)
					{
						$values = array_values($thisone);
						$values[$fixer] = $bid;
						$values[$fixer2] = $mid;
						if($fixer3 !== FALSE)
						{
							if($values[$fixer3])
								$values[$fixer3] += $offset;
						}
						if($fixer4 !== FALSE)
						{
							if($values[$fixer4])
								$values[$fixer4] += $offset;
						}
						$fields = array_keys($thisone);
						foreach ($values as $indx=>$val)
						{
							if ($val === NULL)
							{
								unset($values[$indx]);
								unset($fields[$indx]);
							}
						}
						$fc = count($fields);
						$fillers = str_repeat('?,',$fc-1).'?';
						$sql = 'INSERT INTO '.$pref.'module_tmt_matches ('.implode(',',$fields).') VALUES ('.$fillers.')';
						$db->Execute($sql,$values);
						$mid++;
					}
				}
			}
		}
		$success = TRUE;
	}
}

if ($success)
{
	$message = $this->PrettyMessage('success_import');
	//signal added bracket
	$this->Redirect($id,'addedit_comp',$returnid,
		array('bracket_id'=>$bid,'newbracket'=>$bid,'tmt_message'=>$message));
}
else
{
	$message = $this->PrettyMessage('err_import_failed',FALSE);
	$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$message));
}

?>
