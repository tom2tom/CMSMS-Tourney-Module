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
	$sql = 'SELECT bracket_id,type,timezone,playgap,playgaptype FROM '.$pref.
		'module_tmt_brackets WHERE bracket_id=? OR alias=? OR name=?';

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

	$msg = '';
	if($parked)
	{
		//faster lookups
		$looks = array();
		foreach($brackets as $row)
			$looks[$row['bracket_id']] = $row;
		$funcs = new tmtSchedule();
		$added = 0;
		$sql = 'SELECT teamA,teamB,playwhen FROM '.$pref.'module_tmt_matches WHERE match_id=?';
		$sql2 = 'UPDATE '.$pref.'module_tmt_matches SET teamA=?,teamB=?,playwhen=?,score=?,status=? WHERE match_id=?';
		$sql3 = 'INSERT INTO '.$pref.'module_tmt_matches (match_id,bracket_id,teamA,teamB,playwhen,status) VALUES (?,?,?,?,?,?)';

		foreach($parked as $bid=>$matches)
		{
			$tz = new DateTimeZone($looks[$bid]['timezone']);
			$mids = array();
			foreach($matches as $i=>$mdata)
				$mids[$i] = $mdata[$namecols[1]];
			//assist match-succession
			array_multisort($mids,SORT_ASC,SORT_NUMERIC,$matches);
			foreach($matches as $mdata)
			{
				$mid = $mdata[$namecols[1]];
				$ta = $mdata[$namecols[2]];
				$tb = $mdata[$namecols[3]];
				$row = $db->GetRow($sql,array($mid));
				if($row)
				{
					if(($row['teamA'] == 0 && $row['teamB'] == $ta) || $row['teamA'] == $tb)
					{
						$t = $ta;
						$ta = $tb;
						$tb = $ta;
						switch($mdata[$namecols[4]])
						{
						 case Tourney::WONA:
							$stat = Tourney::WONB;
							break;
						 case Tourney::WONB:
							$stat = Tourney::WONB;
							break;
						 case Tourney::FORFA:
							$stat = Tourney::FORFB;
						 case Tourney::FORFB:
							$stat = Tourney::FORFA;
						 default:
							$stat = $mdata[$namecols[4]];
						}
					}
					else
						$stat = $mdata[$namecols[4]];

					$dt = new DateTime('1900-1-1',$tz);
					if($mdata[$namecols[6]])
					{
						$tok = strtok($mdata[$namecols[6]],'-/\\: ');
						if(is_numeric($tok) && $tok > 1900)
							$dt->modify($mdata[$namecols[6]]);
						elseif($row['playwhen'])
						{
							$dt->modify($row['playwhen']);
							$dt->setTime(0,0,0);
							$t = explode(':',$mdata[$namecols[6]]);
							$as = $t[0]*3600;
							if(!empty($t[1]))
								$as += $t[1]*60;
							$dt->modify('+'.$as.' seconds');
						}
						else
							$dt->modify('now');
					}
					elseif($row['playwhen'])
					{
						$t = $funcs->GapSeconds($looks[$bid]['playgaptype'],$looks[$bid]['playgap']);
						$dt->modify($row['playhen']);
						$dt->modify('+'.$t.'seconds');
					}
					else
						$dt = new DateTime('now',$tz);
					$ended = $dt->format('Y-m-d H:i').':00';
					$db->Execute($sql2,array($ta,$tb,$ended,$mdata[$namecols[5]],$stat,$mid));
				}
				else
				{
					//un-scheduled match ?! should never happen
					$dt = new DateTime('now',$tz);
					$ended = $dt->format('Y-m-d H:i').':00';
					$db->Execute($sql3,array($mid,$bid,$ta,$tb,$ended,$stat));
				//TODO update value in module_tmt_matches_seq if needed
				}
				//setup successor matches c.f. action.save_comp::case 'matches'
				$funcs->ConformNewResults($bid,$looks[$bid]['type'],$mid,$stat);
			}
			$added += count($mids);
		}
		$msg .= $this->Lang('imports_done',$added);
	}

	if($fails)
	{
		$mids = array();
		foreach($fails  as $mdata)
			$mids[] = $mdata[$namecols[1]];
		if($msg)
			$msg .= '<br />';
		$nums = implode(',',$mids);
		$msg .= $this->Lang('imports_skipped',count($fails),$nums);
	}
	$newparms = $this->GetEditParms($params,'resultstab',$msg);
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
