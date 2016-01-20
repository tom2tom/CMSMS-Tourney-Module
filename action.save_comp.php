<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

This action deals with 'permanent' saving of data into module tables,and also
updates which set flags to enable undo if so wanted
*/

if(!$this->CheckAccess('admod'))
{
	if(isset($params['cancel']))
		$this->Redirect($id,'defaultadmin');
	elseif(!$this->CheckAccess('score'))
		$this->Redirect($id,'defaultadmin','',
			array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));
}

$pref = cms_db_prefix();

if(isset($params['apply']) || isset($params['submit']))
{
	$funcs = new tmtData();
	$data = $funcs->GetValidData($this,$params);
	if(empty($data->error))
	{
		//cache current data,for comparisons with new
		$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
		$bdata = $db->GetRow($sql,array($data->bracket_id));
		//cache data for related tables and other data not in brackets-table
		$teams = $data->teams;
		unset($data->teams);
		$matches = $data->matches;
		unset($data->matches);
		$results = $data->results;
		unset($data->results);
		//these values may be NULL if not present in data from UI
		$motpl = $data->motemplate;
		unset($data->motemplate);
		$mctpl = $data->mcanctemplate;
		unset($data->mcanctemplate);
		$mrtpl = $data->mreqtemplate;
		unset($data->mreqtemplate);
		$mitpl = $data->mitemplate;
		unset($data->mitemplate);
		$totpl = $data->totemplate;
		unset($data->totemplate);
		$tctpl = $data->tcanctemplate;
		unset($data->tcanctemplate);
		$trtpl = $data->treqtemplate;
		unset($data->treqtemplate);
		$titpl = $data->titemplate;
		unset($data->titemplate);
		if($data->html == NULL)
			$data->html = (int)$bdata['html']; //retain current value

		$chttpl = $data->chttemplate;
		unset($data->chttemplate);
		$cssfile = $data->cssfile;
		unset($data->cssfile);
		$matchview = $data->matchview;
		unset($data->matchview);
		$resultview = $data->resultview;
		unset($data->resultview);
		$bracket_id = $data->bracket_id;
		unset($data->bracket_id);

		$old = empty($data->added);
		if(isset($data->added))
			unset($data->added);
		$mainfields = (array)$data;

		$newtype = ($bdata['type'] != $data->type);
		//assume dirty if any match saved here, or if all matches completed
		$stat = ($matches || $newtype)?1:0;
		if(!($stat || $matches))
		{
			$sql = 'SELECT 1 AS yes FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0';
			$rs = $db->SelectLimit($sql,1,-1,array($bracket_id));
			if($rs)
			{
				if(!$rs->EOF) //match(es) exist, so must be completed bracket
					$stat = 1;
				$rs->Close();
			}
		}
		$mainfields['chartbuild'] = $stat;

		$fields = implode('=?,',array_keys($mainfields)).'=?';
		$sql = 'UPDATE '.$pref.'module_tmt_brackets SET '.$fields.' WHERE bracket_id=?';
		$args = array_values($mainfields);
		$args[] = $bracket_id;
		$res = $db->Execute($sql,$args);

		if($res)
		{
			if($motpl != NULL)
			{
				if($motpl)
					tmtTemplate::Set($this,'mailout_'.$bracket_id.'_template',$motpl);
				else
					tmtTemplate::Delete($this,'mailout_'.$bracket_id.'_template');
			}
			if($mctpl != NULL)
			{
				if($mctpl)
					tmtTemplate::Set($this,'mailcancel_'.$bracket_id.'_template',$mctpl);
				else
					tmtTemplate::Delete($this,'mailcancel_'.$bracket_id.'_template');
			}
			if($mrtpl != NULL)
			{
				if($mrtpl)
					tmtTemplate::Set($this,'mailrequest_'.$bracket_id.'_template',$mrtpl);
				else
					tmtTemplate::Delete($this,'mailrequest_'.$bracket_id.'_template');
			}
			if($mitpl != NULL)
			{
				if($mitpl)
					tmtTemplate::Set($this,'mailin_'.$bracket_id.'_template',$mitpl);
				else
					tmtTemplate::Delete($this,'mailin_'.$bracket_id.'_template');
			}
			if($totpl != NULL)
			{
				if($totpl)
					tmtTemplate::Set($this,'tweetout_'.$bracket_id.'_template',$totpl);
				else
					tmtTemplate::Delete($this,'tweetout_'.$bracket_id.'_template');
			}
			if($tctpl != NULL)
			{
				if($tctpl)
					tmtTemplate::Set($this,'tweetcancel_'.$bracket_id.'_template',$tctpl);
				else
					tmtTemplate::Delete($this,'tweetcancel_'.$bracket_id.'_template');
			}
			if($trtpl != NULL)
			{
				if($trtpl)
					tmtTemplate::Set($this,'tweetrequest_'.$bracket_id.'_template',$trtpl);
				else
					tmtTemplate::Delete($this,'tweetrequest_'.$bracket_id.'_template');
			}
			if($titpl != NULL)
			{
				if($titpl)
					tmtTemplate::Set($this,'tweetin_'.$bracket_id.'_template',$titpl);
				else
					tmtTemplate::Delete($this,'tweetin_'.$bracket_id.'_template');
			}
			if($chttpl)
				tmtTemplate::Set($this,'chart_'.$bracket_id.'_template',$chttpl);
			else
				tmtTemplate::Delete($this,'chart_'.$bracket_id.'_template');

			$funcs = new tmtData();
			$sch = FALSE;
			if($newtype)
			{
				//re-schedule all matches
				$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
				$db->Execute($sql,array($bracket_id));
			}
			else
			{
				if($matches || $results)
				{
					$sql = 'SELECT match_id FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0';
					$current = $db->GetCol($sql,array($bracket_id));
					if(!$current && $matches)
					{
						reset($matches);
						if(key($matches) > 0)
							$this->Redirect($id,'defaultadmin','',
								array('tmt_message'=>$this->PrettyMessage('err_match',FALSE)));
					}
				}
				//save match data 1st, lowest priority
				if($matches)
				{
					$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=?,place=?,status=? WHERE match_id=?';
					//for new matches specified in RRTYPE plan view
					$sql2 = 'INSERT INTO '.$pref.'module_tmt_matches (match_id,bracket_id,teamA,teamB,playwhen,place,status) VALUES (?,?,?,?,?,?,?)';
					$ids = array_keys($params['mat_status']);
					foreach($matches as $mid=>$row)
					{
						if($mid < 0 || in_array($mid,$current))
						{
							$indx = array_search($mid,$ids);
							$tA = $params['mat_teamA'][$indx];
							$tB = $params['mat_teamB'][$indx];
							$when = $row['playwhen'];
							if($when)
								$on = $funcs->GetFormattedDate($when,FALSE,TRUE);
							if(!$when || !$on)
								$on = NULL;
							$at = trim($row['place']);
							if($at == FALSE)
								$at = NULL;
							$stat = (int)$row['status'];
							if($on)
							{
								if($stat < 0) //notyet
									$stat = ($tA && $tB) ? Tourney::SOFT:Tourney::ASOFT;
							}
							else
							{
								if($stat < Tourney::ANON)
									$stat = 0;
								elseif($stat == Tourney::AFIRM)
									$stat = Tourney::ASOFT;
							}
							$matches[$mid]['status'] = $stat; //for use during results processing
							if ($mid > 0)
								$db->Execute($sql,array($on,$at,$stat,$mid));
							else
							{
								//create RRTYPE match on-the-fly
								$mid = $db->GenID($pref.'module_tmt_matches_seq');
								if(!$tA)
									$tA = NULL;
								if(!$tB)
									$tB = NULL;
								$db->Execute($sql2,array($mid,$bracket_id,$tA,$tB,$on,$at,$stat));
							}
						}
						else
							$this->Redirect($id,'defaultadmin','',
								array('tmt_message'=>$this->PrettyMessage('err_match',FALSE)));
					}
				}
				//results data next priority
				if($results)
				{
					$ids = array_keys($params['res_status']);
					if($resultview == 'past')
					{
						//before any update of recorded match data, check for status-change
						//if so, do consequential team-change(s) in nextm(,nextlm)
						if(!$sch) $sch = new tmtSchedule();
						$sch->ConformNewResults($bracket_id,$bdata['type'],$ids,$params['res_status']);
					}
					$sql2 = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=?,status=?,score=? WHERE match_id=?';
					foreach($results as $mid=>$row)
					{
						if(in_array($mid,$current))
						{
							$when = $row['playwhen'];
							if(!$when && !empty($matches[$mid]['playwhen']))
								$when = $matches[$mid]['playwhen'];
							if($when)
								$on = $funcs->GetFormattedDate($when,FALSE,TRUE);
							if(!$when || !$on)
								$on = NULL;
							$how = trim($row['score']);
							if(!$how)
								$how = NULL;
							$stat = (int)$row['status'];
							if($stat < Tourney::MRES)
								$stat = $matches[$mid]['status'];
							$db->Execute($sql2,array($on,$stat,$how,$mid));
						}
						else
							$this->Redirect($id,'defaultadmin','',
								array('tmt_message'=>$this->PrettyMessage('err_match',FALSE)));
					}
				}
			}
			//next,team-changes (which may change matches)
			$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags=2';
			$gone = $db->GetCol($sql,array($bracket_id));
			if($gone)
			{
				//don't actually delete flagged teams/members, who may be needed for display of previous-match results
				foreach($gone as $tid)
				{
					if(isset($teams[$tid])) //some disconnect/bug ??
						unset($teams[$tid]);
				}
				if($old) //not new bracket
				{
					if(!$sch) $sch = new tmtSchedule();
					$sch->ConformGoneteams($this,$bracket_id,$data->type,$data->timezone,$gone);
				}
				//else TODO CHECKME
			}
			if($old) //not a new bracket
			{
				//CHECKME just find in $teams[] ?
				$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags=1';
				$xtra = $db->GetCol($sql,array($bracket_id));
				if($xtra)
				{
					if(!$sch) $sch = new tmtSchedule();
					$sch->ConformNewteams($this,$bracket_id,$data->type,$data->timezone,$xtra);
				}
			}
			//else TODO CHECKME
			if($teams)
			{
				$order = 1;
				//clears flags for all present teams and their members
				$sql = 'UPDATE '.$pref.'module_tmt_teams SET name=?,seeding=?,contactall=?,displayorder=?,flags=0 WHERE team_id=?';
				$sql2 = 'UPDATE '.$pref.'module_tmt_people SET flags=0 WHERE id=? AND flags IN(1,3)';
				$sql3 = 'DELETE FROM '.$pref.'module_tmt_people WHERE id=? AND flags=2';
				foreach($teams as $tid=>&$row) //CHECKME $teams definitely conforms to saved data now?
				{
					list($pname,$pcontact) = $funcs->GetforFirstPlayer($tid);
					$name = $row['name'];
					if($name == FALSE || $name == $pname)
						$name = null;
					$db->Execute($sql,array($name,$row['seeding'],$row['contactall'],$order,$tid));
					$order++;
					$contact = $row['contact'];
					if($contact != $pcontact)
						$funcs->UpdateFirstPlayer($tid,FALSE,$contact); //update contact in people table
					$db->Execute($sql2,array($tid));
					$db->Execute($sql3,array($tid));
				}
				unset($row);
			}
			if($newtype)
			{
				if(!$sch) $sch = new tmtSchedule();
				$sch->ScheduleMatches($this,$bracket_id);
			}
			if($newtype || $matches || $results)
			{
				$sql = 'UPDATE '.$pref.'module_tmt_brackets SET chartbuild=1 WHERE bracket_id=?';
				$db->Execute($sql,array($bracket_id));
			}
		}
		$message = ($res) ? '' : $this->PrettyMessage('err_save',FALSE);
	}
	elseif(!empty($data->errmsg))
		$message = $data->errmsg;

	if(isset($params['apply']))
	{
		if(isset($params['newbracket']))
			unset($params['newbracket']);
		unset($params['apply']);
		$newparms = $this->GetEditParms($params,$params['active_tab'],$message);
		$this->Redirect($id,'addedit_comp',$returnid,$newparms);
	}
	$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$message));
} //end of 'apply|submit' processing
elseif(isset($params['update']))
{
	reset($params['update']);
	$op = key($params['update']);
	unset($params['update']);
 	switch(substr($op,strlen($id)))
	{
	 case 'teams':
		if(isset($params['tsel']))
		{
			$sql = 'UPDATE '.$pref.'module_tmt_teams SET name=?,seeding=?,contactall=?,flags=3 WHERE team_id=?';
			$funcs = new tmtData();
			foreach($params['tsel'] as $tid)
			{
				list($pname,$pcontact) = $funcs->GetforFirstPlayer($tid);
				$indx = array_search($tid,$params['tem_teamid']);
				$contact = $params['tem_contact'][$indx];
				if($contact != $pcontact)
					$pcontact = $contact; //empty string ok
				else
					$pcontact = FALSE; //no update
				$toall = $params['tem_contactall'][$indx];
				$name = $params['tem_name'][$indx];
				//decide how/where to save $name
				if($name && $name != $pname && $params['tmt_teamsize'] == 1)
				{
					$pname = $name;
					$name = NULL;
				}
				else
					$pname = FALSE;
				if($name == FALSE || $name == $pname || $params['tmt_teamsize'] == 1)
					$name = NULL;
				$seed = $params['tem_seed'][$indx];
				if($seed == FALSE) $seed = NULL;
				//displayorder not updated cuz not all teams processed
				$db->Execute($sql,array($name,$seed,$toall,$tid));
				$funcs->UpdateFirstPlayer($tid,$pname,$pcontact); //update name and/or contact in people table
			}
			$newparms = $this->GetEditParms($params,'playerstab');
		}
		break;
	 case 'matches':
		if(isset($params['msel']))
		{
			$bracket_id = (int)$params['bracket_id'];
			$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=?,place=?,status=? WHERE match_id=?';
			//for new matches specified in RRTYPE plan view
			$sql2 = 'INSERT INTO '.$pref.'module_tmt_matches (match_id,bracket_id,teamA,teamB,playwhen,place,status) VALUES (?,?,?,?,?,?,?)';
			$known = array_keys($params['mat_status']);
			$funcs = new tmtData();
			foreach($params['msel'] as $mid)
			{
				$indx = array_search($mid,$known);
				$tA = $params['mat_teamA'][$indx];
				$tB = $params['mat_teamB'][$indx];
				$when = $params['mat_playwhen'][$indx];
				if($when)
					$on = $funcs->GetFormattedDate($when,FALSE,TRUE);
				if(!$when || !$on)
					$on = NULL;
				$at = trim($params['mat_playwhere'][$indx]);
				if(!$at)
					$at = NULL;
				$stat = (int)$params['mat_status'][$mid];
				if($on)
				{
					if($stat < 0) //notyet
						$stat = ($tA && $tB) ? Tourney::SOFT:Tourney::ASOFT;
				}
				else
				{
					if($stat < Tourney::ANON)
						$stat = 0;
					elseif($stat == Tourney::AFIRM)
						$stat = Tourney::ASOFT;
				}
				if($mid > 0)
					$db->Execute($sql,array($on,$at,$stat,$mid));
				else
				{
					$mid = $db->GenID($pref.'module_tmt_matches_seq');
					if(!$tA)
						$tA = NULL;
					if(!$tB)
						$tB = NULL;
					$db->Execute($sql2,array($mid,$bracket_id,$tA,$tB,$on,$at,$stat));
				}
			}
			$sql = 'UPDATE '.$pref.'module_tmt_brackets SET chartbuild=1 WHERE bracket_id=?';
			$db->Execute($sql,array($params['bracket_id']));
			$newparms = $this->GetEditParms($params,'matchestab');
		}
		break;
	 case 'results':
		if(isset($params['rsel']))
		{
			$past = ($params['resultview'] == 'past');
			if($past)
			{
				//before any update of recorded match data, check for status-change
				//if so, do consequential team-change(s) in nextm[,nextlm]
				$funcs = new tmtSchedule();
				$funcs->ConformNewResults($params['bracket_id'],$params['tmt_type'],$params['rsel'],$params['res_status']);
			}
			$funcs = new tmtData();
			$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=?,status=?,score=? WHERE match_id=?';
			$sql2 = 'UPDATE '.$pref.'module_tmt_matches SET status=?,score=? WHERE match_id=?';
			foreach($params['rsel'] as $mid)
			{
				$indx = array_search($mid,$params['res_matchid']);
				$stat = (int)$params['res_status'][$mid];
				if(!$past) //future-view
				{
				 	if($stat != Tourney::NOTYET) //non-default status applies
					{
						$when = $params['res_playwhen'][$indx];
						$on = $funcs->GetFormattedDate($when,FALSE,TRUE);
						if(!$on)
							$on = NULL;
						$how = trim($params['res_score'][$indx]);
						if(!$how)
							$how = NULL;
						$db->Execute($sql,array($on,$stat,$how,$mid));
					}
					else
						$db->Execute($sql2,array(0,NULL,$mid));
				}
				else //past-view: process always
				{
					$when = $params['res_playwhen'][$indx];
					if($when)
					{
						$on = $funcs->GetFormattedDate($when,FALSE,TRUE);
						if(!$on)
							$on = NULL;
					}
					//else don't change the 'played-when' field
					if($stat == Tourney::NOTYET)
						$stat = 0;
					if($stat < Tourney::MRES)
						$how = NULL;
					else
					{
							$how = trim($params['res_score'][$indx]);
							if(!$how)
								$how = NULL;
					}
					if($when)
						$db->Execute($sql,array($on,$stat,$how,$mid));
					else
						$db->Execute($sql2,array($stat,$how,$mid));
				}
			}
			$sql = 'UPDATE '.$pref.'module_tmt_brackets SET chartbuild=1 WHERE bracket_id=?';
			$db->Execute($sql,array($params['bracket_id']));
			$newparms = $this->GetEditParms($params,'resultstab');
		}
		break;
	}

	$this->Redirect($id,'addedit_comp',$returnid,$newparms);
} //end of 'update' processing
elseif(isset($params['cancel']))
{
	$bracket_id = $params['bracket_id'];
	if(isset($params['newbracket'])) //new bracket && !previously applied
	{
		$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=?';
		$teams = $db->GetCol($sql,array($bracket_id));
		if($teams)
		{
			$sql = 'DELETE FROM '.$pref.'module_tmt_people WHERE id=?';
			foreach($teams as $tid)
				$db->Execute($sql,array($tid));
			$sql = 'DELETE FROM '.$pref.'module_tmt_teams WHERE bracket_id=?';
			$db->Execute($sql,array($bracket_id));
			$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
			$db->Execute($sql,array($bracket_id));
		}
		$sql = 'DELETE FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
		$db->Execute($sql,array($bracket_id));
		tmtTemplate::Delete($this,'mailout_'.$bracket_id.'_template');
		tmtTemplate::Delete($this,'mailcancel_'.$bracket_id.'_template');
		tmtTemplate::Delete($this,'mailrequest_'.$bracket_id.'_template');
		tmtTemplate::Delete($this,'mailin_'.$bracket_id.'_template');
		tmtTemplate::Delete($this,'tweetout_'.$bracket_id.'_template');
		tmtTemplate::Delete($this,'tweetcancel_'.$bracket_id.'_template');
		tmtTemplate::Delete($this,'tweetrequest_'.$bracket_id.'_template');
		tmtTemplate::Delete($this,'tweetin_'.$bracket_id.'_template');
		tmtTemplate::Delete($this,'chart_'.$bracket_id.'_template');
	}
	else //cancel edit of old bracket or after changes 'applied' to new one
	{
		//simply ignore data for brackets table and related templates
		$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags=1';
		$teams = $db->GetCol($sql,array($bracket_id));
		if($teams)
		{
			$sql = 'DELETE FROM '.$pref.'module_tmt_people WHERE id=?';
			$sql2 = 'UPDATE '.$pref.'module_tmt_matches SET teamA=null,playwhen=null,place=null,status=0,score=null WHERE bracket_id=? AND teamA=?';
			$sql3 = 'UPDATE '.$pref.'module_tmt_matches SET teamB=null,playwhen=null,place=null,status=0,score=null WHERE bracket_id=? AND teamB=?';
			foreach($teams as $tid)
			{
				$db->Execute($sql,array($tid));
				$db->Execute($sql2,array($bracket_id,$tid));
				$db->Execute($sql3,array($bracket_id,$tid));
			}
			$sql = 'DELETE FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags=1';
			$db->Execute($sql,array($bracket_id));
			//TODO any consequential changes to matches e.g. bye, forfeit
		}
		$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags IN (2,3)';
		$teams = $db->GetCol($sql,array($bracket_id));
		if($teams)
		{
			//TODO support content reversion where flags = 3
			$sql = 'UPDATE '.$pref.'module_tmt_people SET flags=0 WHERE id=? AND flags IN (2,3)';
			foreach($teams as $tid)
				$db->Execute($sql,array($tid));
			$sql = 'UPDATE '.$pref.'module_tmt_teams SET flags=0 WHERE bracket_id=? AND flags IN (2,3)';
			$db->Execute($sql,array($bracket_id));
			//TODO any consequential changes to matches for reverted/changed teams
		}
	}
	//fall into redirect to defaultadmin
}//end of 'cancel' processing

$this->Redirect($id,'defaultadmin');
?>
