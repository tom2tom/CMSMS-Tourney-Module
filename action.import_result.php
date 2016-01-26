<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (!function_exists('GetSplitLine'))
{
 function ToChr($match)
 {
 	$st = ($match[0][0] == '&') ? 2:1;
	return chr(substr($match[0],$st,2));
 }

 function GetSplitLine(&$fh)
 {
	do
	{
		$fields = fgetcsv($fh,4096);
		if(is_null($fields) || $fields == FALSE)
			return FALSE;
	} while(count($fields) == 1 && is_null($fields[0])); //blank line
 	//convert any separator supported by tmtCSV::TeamsToCSV()
	foreach ($fields as &$thisfield)
		$thisfield = trim(preg_replace_callback(
			array('/&#\d\d;/','/%\d\d%/'),'ToChr',$thisfield));
	unset($thisfield);
	return $fields;
 }
}

if (!$this->CheckAccess('admod'))
	exit ($this->Lang('lackpermission'));

if (isset($params['cancel']))
	$this->Redirect($id, 'addedit_comp', $returnid, $this->GetEditParms($params,'resultstab'));

$fn = $id.'csvfile';
if (isset($_FILES) && isset($_FILES[$fn]))
{
	$file_data = $_FILES[$fn];
	$parts = explode('.',$file_data['name']);
	$ext = end($parts);
//tmtUtils()?
	if ($file_data['type'] != 'text/csv'
	 || !($ext == 'csv' || $ext == 'CSV')
     || $file_data['size'] <= 0 || $file_data['size'] > 2048
     || $file_data['error'] != 0)
	{
		$newparms = $this->GetEditParms($params,'resultstab',$this->PrettyMessage('err_file',FALSE));
		$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
	}

	$handle = fopen($file_data['tmp_name'],'r');
	if (!$handle )
	{
		$newparms = $this->GetEditParms($params,'resultstab',$this->PrettyMessage('lackpermission',FALSE));
		$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
	}
	$bad = FALSE;
	//some basic validation of file-content
	$firstline = GetSplitLine($handle);
	if ($firstline == FALSE)
		$bad = TRUE;
	if (!$bad)
	{
		$num = count($firstline);
		if ($num == 0)
			$bad = TRUE;
	}
	if (!$bad)
	{
		$namecols = array(); //[0]=bracketcol, [1]=matchcol, [2]=teamAcol, [3]=teamBcol, [4]=resultcol, [5]=scorecol, [6]=finishcol
		foreach (array('Bracket','Match','CompetitorA','CompetitorB','Result','Score','Finished') as $i=>$custom)
		{
			$col = array_search($custom, $firstline);
			if ($col !== FALSE)
			{
				if ($col < 7)
					$namecols[$i] = $col;
				else
				{
					$bad = TRUE;
					break;
				}
			}
		}
	}
	if ($bad)
	{
		$newparms = $this->GetEditParms($params,'playerstab',$this->PrettyMessage('err_file',FALSE));
		$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
	}

	$pref = cms_db_prefix();
	$sql = 'SELECT bracket_id,type FROM '.$pref.'module_tmt_brackets WHERE bracket_id=? OR alias=? OR name=?';

	$brackets = array();
	$fails = array();
	$parked = array();

	//mid-level data check
	while(!feof($handle))
	{
		$imports = GetSplitLine($handle);
		if ($imports)
		{
			if (count($imports) < 7)
			{
				$fails[] = $imports;
				continue;
			}
			$val = $imports[$namecols[0]];
			if (!array_key_exists($val,$brackets))
			{
				$bdata = $db->GetRow($sql,array($val,$val,$val));
				if($bdata)
					$brackets[$val] = $bdata;
				else
				{
					$fails[] = $imports;
					continue;
				}
			}
			$bid = $brackets[$val]['bracket_id'];
			if (!isset($params['bracket_id']) || $params['bracket_id'] == $bid)
			{
				$imports[$namecols[0]] = $bid;
				$parked[$bid][] = $imports;
			}
		}
	}
	fclose($handle);

	if($parked)
	{
		//low-level validation
		$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=?';
		$sql3 = 'SELECT teamA, teamB FROM '.$pref.'module_tmt_matches WHERE match_id=?';
		$sql2 = 'SELECT match_id FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND ((teamA=? AND teamB=?) OR (teamA=? AND teamB=?))';
		foreach($parked as $bid=>&$matches)
		{
			//cache teamnames for lookup
			$ttdata = $db->GetCol($sql,array($bid));
			$tdata = array();
			foreach($ttdata as $one)
				$tdata[$one] = $this->TeamName($one);
			unset($ttdata);
			foreach($matches as $i=>&$mdata)
			{
				$val = trim($mdata[$namecols[1]]); //match
				if($val)
				{
					$row = $db->GetRow($sql3,array($val));
					if($row)
					{
						$ta = (int)$row['teamA'];
						$tb = (int)$row['teamB'];
						if($mdata[$namecols[2]])
						{
							$mdata[$namecols[1]] = (int)$val;
							if($mdata[$namecols[2]] == $tdata[$ta])
							{
								$mdata[$namecols[2]] = $ta;
								if($tb == 0 && $mdata[$namecols[3]])
									$tb = array_search($mdata[$namecols[3]],$tdata);
								$mdata[$namecols[3]] = $tb;
							}
							else
							{
								$mdata[$namecols[2]] = $tb;
								if($ta == 0 && $mdata[$namecols[3]])
									$ta = array_search($mdata[$namecols[3]],$tdata);
								$mdata[$namecols[3]] = $ta;
							}
						}
						elseif($mdata[$namecols[3]])
						{
							$mdata[$namecols[1]] = (int)$val;
							if($mdata[$namecols[3]] == $tdata[$tb])
							{
								$mdata[$namecols[3]] = $tb;
								if($ta == 0 && $mdata[$namecols[2]])
									$ta = array_search($mdata[$namecols[2]],$tdata);
								$mdata[$namecols[2]] = $ta;
							}
							else
							{
								$mdata[$namecols[3]] = $ta;
								if($tb == 0 && $mdata[$namecols[2]])
									$tb = array_search($mdata[$namecols[2]],$tdata);
								$mdata[$namecols[2]] = $tb;
							}
						}
						else
						{
							$fails[] = $mdata;
							unset($parked[$bid][$i]);
							continue;
						}
					}
					else
						$val = '';
				}
				if(!$val)
				{
					//attempt to identify the match from the supplied competitor-names
					//cache id's if found
					$ta = trim($mdata[$namecols[2]]);
					if($ta)
						$ta = array_search($ta,$tdata);
					else
						$ta = FALSE;
					if($ta !== FALSE)
						$mdata[$namecols[2]] = $ta;
					else
					{
						$fails[] = $mdata;
						unset($parked[$bid][$i]);
						continue;
					}

					$tb = trim($mdata[$namecols[3]]);
					if($tb)
						$tb = array_search($tb,$tdata);
					else
						$tb = FALSE;
					if($tb !== FALSE)
						$mdata[$namecols[3]] = $tb;
					else
					{
						$fails[] = $mdata;
						unset($parked[$bid][$i]);
						continue;
					}
					$val = $db->GetOne($sql2,array($bid,$ta,$tb,$tb,$ta));
					if($val)
						$mdata[$namecols[1]] = (int)$val;
					else
					{
						$fails[] = $mdata;
						unset($parked[$bid][$i]);
						continue;
					}
				}
				//result-field must identify which of the competitors (if any) prevailed,
				//by 'win A', 'win B', 'draw', 'tie', or 'abandon' (not 'forfeit' - see score)
				//cache corresponding enums - though the 'relation' may need to be reversed when stored
				$val = trim($mdata[$namecols[4]]); //result
				switch($val)
				{
				 case 'win A':
					$mdata[$namecols[4]] = Tourney::WONA;
					break;
				 case 'win B':
					$mdata[$namecols[4]] = Tourney::WONB;
					break;
				 case 'draw':
				 case 'tie':
					$mdata[$namecols[4]] = Tourney::MTIED;
					break;
				 case 'abandon':
					$mdata[$namecols[4]] = Tourney::NOWIN;
					$mdata[$namecols[5]] = '';
					break;
				 default:
					$fails[] = $mdata;
					unset($parked[$bid][$i]);
					break 2;
				}

				$ta = trim($mdata[$namecols[5]]); //score
				if(strpos($ta,'forfeit') !== FALSE)
				{
					if($mdata[$namecols[4]] == Tourney::WONA)
						$mdata[$namecols[4]] = Tourney::FORFB;
					elseif($mdata[$namecols[4]] == Tourney::WONB)
						$mdata[$namecols[4]] = Tourney::FORFA;
				}
				if($ta)
					$mdata[$namecols[5]] = $ta;
				elseif(!($val == 'abandon' || $val == 'draw'))
				{
					$fails[] = $mdata;
					unset($parked[$bid][$i]);
					continue;
				}
				//finished-field may be a time (as HH:MM), or a full datestamp (as YYYY-MM-DD HH:MM), or empty
				//if not empty, it's not parsed here
				$val = trim($mdata[$namecols[6]]); //finished
				if($val)
				{
					if(date_create($val) === FALSE)
					{
						$fails[] = $mdata;
						unset($parked[$bid][$i]);
						continue;
					}
				}
			}
			unset($mdata);
		}
		unset($matches);
	}

	if($parked)
	{
		$sql = 'SELECT teamA,teamM,playwhen FROM '.$pref.'module_tmt_matches WHERE match_id=?';
		$sql2 = 'UPDATE '.$pref.'module_tmt_matches SET teamA=?,teamB=?,playwhen=?score=?,status=? WHERE match_id=?';
		$sql3 = 'INSERT INTO '.$pref.'module_tmt_matches () VALUES ()';
		foreach($parked as $bid=>$matches)
		{
			$mids = array();
			foreach($matches as $i=>$mdata)
				$mids[$i] = $mdata[$namecols[1]];
			array_multisort($mids,SORT_ASC,SORT_NUMERIC,$matches);
			foreach($matches as $mdata)
			{
				$row = $db->GetRow($sql,array($mdata[$namecols[1]]));
				if($row)
				{
$this->Crash1();
					$ta = $TODO; //which one of $mdata[$namecols[2]] or $mdata[$namecols[3]]
					$tb = $TODO; //other one
					$ended = $TODO; //absolute time from $mdata[$namecols[6]] or that rel to $row['playwhen'] or ...?
					$stat = $TODO; //$mdata[$namecols[4]] or maybe flipped
					$db->Execute($sql2,array($ta,$tb,$ended,$mdata[$namecols[5]],$stat,$mdata[$namecols[1]]));
				}
				else
				{
//TODO insert data for un-scheduled match
$this->Crash2();
					$db->Execute($sql3,array());
				}
//TODO setup successor matches competitors
			}
		}
	}

	if($fails)
	{
//TODO warn
	}
	$newparms = $this->GetEditParms($params,'resultstab');
	$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
}

$tplvars = array(
	'start_form' => $this->CreateFormStart($id, 'import_result', $returnid, 'post','multipart/form-data'),
	'end_form' => $this->CreateFormEnd(),
	'hidden' => $this->GetHiddenParms($id,$params,'resultstab'),
	'title' => $this->Lang('title_resultimport',$params['tmt_name']),
	'chooser' => $this->CreateInputFile($id, 'csvfile', 'text/csv', 25),
	'apply' => $this->CreateInputSubmitDefault($id, 'import', $this->Lang('upload')),
	'cancel' => $this->CreateInputSubmit($id, 'cancel', $this->Lang('cancel')),
	'help' => $this->Lang('help_resultimport')
);

tmtTemplate::Process($this,'onepage.tpl',$tplvars);
?>
