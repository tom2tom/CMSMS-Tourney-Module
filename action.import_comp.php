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

$file_data = $_FILES[$id.'xmlfile'];
if ($file_data['type'] != 'text/xml'
 || $file_data['size'] <= 0
 || $file_data['error'] != 0)
{
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('err_import_failed',FALSE)));
}

$success = FALSE;
$funcs = new tmtXML();
$bracketdata = $funcs->ImportXML($file_data['tmp_name']);
if ($bracketdata && !empty($bracketdata['count'])) //proxy for valid version check
{
	//TODO convert imported data from UTF-8 to site-encoding, if needed
	//$bracketdata['version'] = string e.g. '0.1[.2]' CHECKME use this ?
	//$bracketdata['date'] = string CHECKME use this ?
	$pref = cms_db_prefix();
	for ($i=1; $i<=$bracketdata['count']; $i++)
	{
		$fields = array_keys($bracketdata['bracket'.$i]['properties']);
		$values = array_values($bracketdata['bracket'.$i]['properties']);
		//don't 'force' NULL for fields which default to something else
		foreach ($values as $indx=>$val)
		{
			if ($val === NULL)
			{
				unset($values[$indx]);
				unset($fields[$indx]);
			}
		}
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
		else
			$success = FALSE;
	}
}

if ($success)
{
	//signal added bracket(s)
	$message = $this->PrettyMessage('success_import');
	if($bracketdata['count'] == 1)
		$this->Redirect($id,'addedit_comp',$returnid,
			array('bracket_id'=>$bid,'newbracket'=>$bid,'tmt_message'=>$message));
}
else
{
	$message = $this->PrettyMessage('err_import_failed',FALSE);
	$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$message));
}

?>
