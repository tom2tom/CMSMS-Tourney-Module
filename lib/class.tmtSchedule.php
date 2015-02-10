<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Class: tmtSchedule. Functions involved with tournament match scheduling
NOTE this class does not support static method-calling
*/
class tmtSchedule
{
	/**
	ReserveMatches:
	@db: reference to database connection
	@pref: database table prefix string
	@count: no. of matches to reserve, >= 1
	Safely reserve a block of match id's in the matches table
	Returns: index of first reserved match
	*/
	function ReserveMatches(&$db,$pref,$count)
	{
		if($count < 1)
			return FALSE;
		$id1 = $db->GenID($pref.'module_tmt_matches_seq');
		if($count == 1)
			return $id1;
		$last = $count - 2;
		do {
			if($last > 0)
			{
				$sql = 'UPDATE '.$pref.'module_tmt_matches_seq SET id=id+'.$last;
				$db->Execute($sql);
			}
			$id2 = $db->GenID($pref.'module_tmt_matches_seq');
			if($id2 == $id1 + $count - 1)
				return $id1;
			$id1 = $id2; //race occurred,try again
		} while(1);
	}

	/**
	OrderMatches:
	@teamcount: the actual no. of starting competitors i.e exclude byes
	@seedcount: no. of competitors(<= @teamcount) to be 'specially assigned' per @seedtype
	@seedtype: enum
	 0 no special treatment of seeds
	 1 seeds 1 & 2 in opposite halves of draw (in which case @seedcount >= 2)
	 2 all seeds alternate,with ratings closer than for option 3
	 3 all seeds alternate,with ratings as divergent as possible
	Returns: Array of team numbers,in match-order.
	The array keys are ascending, 1 to 0.5 * a power of 2 which is equal to
	 @teamcount,or else the next highest power of 2 > @teamcount.
	The corresponding values are team numbers. Any randomising must be done
	upstream.
	*/
	private function OrderMatches($teamcount,$seedcount,$seedtype)
	{
		$i = 2;
		while($i < $teamcount) $i *= 2;
		$full = $i;
		$half = $i / 2;
		$order = array();
		//first,the seeds if any
		$tout = FALSE;
		$bout = FALSE;
		//initial array indices
		$to = 1;
		$ti = $half;
		$bi = $half + 1;
		$bo = $full;
		for($i = 1; $i <= $seedcount; $i++)
		{
			//which half of draw?
			$top = ($seedtype != 3) ?($i % 2 != 0) :(intval($i/2) % 2 == 0);
			if($top)
			{
				$tout = !$tout; //toggle between 'ends' of the half-draw
				$indx = ($tout) ? $to++ : $ti--;
				$order[$indx] = $i;
				$vs = $full + 1 - $i;
				if($vs > $seedcount)
				{
					$indx = ($tout) ? $to++ : $ti--;
					$order[$indx] = $vs;
				}
				else if($tout) //leave gap for seeded opponent
					$to++;
				else
					$ti--;
			}
			else
			{
				$bout = !$bout; //toggle between 'ends' of the half-draw
				$indx = ($bout) ? $bo-- : $bi++;
				$order[$indx] = $i;
				$vs = $full + 1 - $i;
				if($vs > $seedcount)
				{
					$indx = ($bout) ? $bo-- : $bi++;
					$order[$indx] = $vs;
				}
				else if($bout)
					$bo--; //leave gap for seeded opponent
				else
					$bi++;
			}
		}
		//now the rest
		$r = $full - $seedcount;
		for($i = 1; $i <= $full; $i++)
		{
			//split numbers between halves,to spread any further byes
			if($i % 2 == 1)
			{
				if(!isset($order[$i]))
					$order[$i] = $r--;
			}
			else
			{
				$vs = $full - $i;
				if($vs > 0 && !isset($order[$vs]))
					$order[$vs] = $r--;
			}
		}
		for($i = $full; $i > 0; $i--)
		{
			if(!isset($order[$i]))
				$order[$i] = $r--;
		}
		ksort($order);
		return $order;
	}

	/**
	RandomOrder:
	@start: first team id in the series to be randomised
	@count: no. of team id's in the series
	Returns: Array of random team ids. Array keys and values are integers in the
	range @start to @start+@count-1. The keys are in ascending order, the
	corresponding values are in random order.
	*/
	private function RandomOrder($start,$count)
	{
		$last = $start + $count - 1;
		if($last > $start)
		{
			$keys = range($start,$last);
			$vals = $keys;
			shuffle($vals);
			return array_combine($keys,$vals);
		}
		return array($start=>$start);
	}

	/**
	RandomAdd:
	@bracket_id:
	@tid: id of team to be added
	For KO,DE bracket,substitute @tid for a bye in the 1st round.
	Assumes prior check for permission to do this (see tmtEditSetup::SpareSlot())
	Returns: TRUE upon success
	*/
	function RandomAdd($bracket_id,$tid)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql = 'SELECT match_id,teamA,teamB,nextm FROM '.$pref.'module_tmt_matches WHERE bracket_id=?
AND (teamA = -1 OR teamB = -1)
AND match_id NOT IN (SELECT DISTINCT nextm FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND nextm IS NOT NULL)';
		$byes = $db->GetAssoc($sql,array($bracket_id,$bracket_id));
		if($byes)
		{
			$mids = array_keys($byes);
			$i = count($mids);
			if($i > 1)
				$i = rand(0,$i-1);
			else
				$i = 0;
			$mid = $mids[$i];
			$mdata = $byes[$mid];
			if((int)$mdata['teamA'] == -1)
			{
				$winner = (int)$mdata['teamB'];
				$args = array($tid,$winner,$mid);
			}
			else
			{
				$winner = (int)$mdata['teamA'];
				$args = array($winner,$tid,$mid);
			}
			$sql = 'UPDATE '.$pref.'module_tmt_matches SET teamA=?,teamB=?,status='.NOTYET.' WHERE match_id=?';
			$db->Execute($sql,$args);
			$sql = 'UPDATE '.$pref.'module_tmt_matches SET teamA=NULL,playwhen=NULL WHERE match_id=? AND teamA=?';
			$db->Execute($sql,array($mdata['nextm'],$winner));
			$sql = 'UPDATE '.$pref.'module_tmt_matches SET teamB=NULL,playwhen=NULL WHERE match_id=? AND teamB=?';
			$rs2 = $db->Execute($sql,array($mdata['nextm'],$winner));
			return TRUE;
		}
		return FALSE;
	}

	/**
	InitByes:
	@bracket_id: identifier of bracket being processed
	@db: reference to database connection
	@pref: database table-prefix string
	Store status for any match(es) with a bye
	*/
	private function InitByes($bracket_id,&$db,$pref)
	{
		$sql = 'SELECT match_id,teamA,teamB FROM '.$pref
		.'module_tmt_matches WHERE bracket_id=? AND (teamA=-1 OR teamB=-1) ORDER BY match_id ASC';
		$byes = $db->GetAssoc($sql,array($bracket_id));
		if($byes)
		{
			$sql = 'UPDATE '.$pref.'module_tmt_matches SET status=? WHERE match_id=?';
			foreach($byes as $indx=>$mdata)
			{
				if($mdata['teamA'] == '-1')
					$status = ($mdata['teamB'] != '-1') ? WONB : NOWIN;
				else //$mdata['teamB'] == '-1'
					$status = ($mdata['teamA'] != '-1') ? WONA : NOWIN;
				$db->Execute($sql,array($status,$indx));
			}
		}
	}

	/**
	SetTeam:
	@tid: id of team (or -1 for bye) to be progressed
	@mid: id of match to be updated
	@prevm: id of match whose result is being reported
	@db: reference to database connection
	@pref: database table-prefix string
	@loser: optional, whether processing the losers-draw in a DE bracket, default = FALSE
	For match whose id is @mid,set teamA or teamB to @tid.
	If both teams are then known, @mid's status is updated in some cases.
	*/
	private function SetTeam($tid,$mid,$prevm,&$db,$pref,$loser=FALSE)
	{
		$status = 0;
		$field = ($loser) ? 'nextlm':'nextm';
		$sql = 'SELECT match_id FROM '.$pref.'module_tmt_matches WHERE '.$field.'=? AND match_id!=? AND status>='.ANON;
		$prevo = $db->GetOne($sql,array($mid,$prevm));
		if($prevo == FALSE) //this is the 1st report for this match
			$vals = array($tid,NULL); //store this winner as teamA (even if a bye)
		else
		{
			//store winners in match-order so they will be displayed in same order as previous matches
			$sql = 'SELECT teamA,status FROM '.$pref.'module_tmt_matches WHERE match_id=?';
			$mdata = $db->GetRow($sql,array($mid));
			$oid = ($mdata['teamA']) ? (int)$mdata['teamA'] : NULL; //don't convert NULL to 0
			if($prevo < $prevm) //previous report from lower-numbered match
			{
				$vals = array($oid,$tid); //store this winner as teamB
				if($vals[0] == -1) //bye
					$status = WONB;
				elseif($tid == -1)
					$status = WONA;
			}
			else
			{
				$vals = array($tid,$oid); //store as teamA
				if($vals[1] == -1)
					$status = WONA;
				elseif($tid == -1)
					$status = WONB;
			}
			if($status == 0)
			{
				if($mdata['status'] == ASOFT)
					$status = SOFT;
				elseif($mdata['status'] == AFIRM)
					$status = FIRM;
			}
		}
		$sql = 'UPDATE '.$pref.'module_tmt_matches SET teamA=?,teamB=?';
		if($status)
			$sql .= ',status='.$status;
		$sql .= ' WHERE match_id=?';
		$vals[] = $mid;
		$db->Execute($sql,$vals);
	}

	/**
	ScheduleMatches:
	@bracket_id: the bracket identifier
	Setup dates/times for matches whose participants are known.
	The matches table is updated accordingly.
	Returns: FALSE upon error or nothing to process or
	 no startdate or timezone for the bracket
	*/
	function ScheduleMatches($bracket_id)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
		$bdata = $db->GetRow($sql,array($bracket_id));
		if($bdata == FALSE)
			return FALSE;
		if(empty($bdata['startdate']) || empty($bdata['timezone']))
			return FALSE;

		$sdt = new DateTime($bdata['startdate']);
		$sstamp = $sdt->getTimestamp(); //when the tournament start{s|ed}
		//allow minimum leadtime before the next match
		$dt = new DateTime('+'.LEADHOURS.' hours',new DateTimeZone($bdata['timezone']));
		$stamp = $dt->getTimestamp();
 		if($stamp < $sstamp)
			$stamp = $sstamp;
		$at = $this->GetNextSlot($bdata,$stamp,FALSE); //find 1st slot
		if($at === FALSE)
			return FALSE;
		
		//matches in DESC order so next foreach overwrites newer ones in $allteams array
		//CHECKME also get SOFT matches before now?
		$sql = 'SELECT match_id,teamA,teamB FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND status<'
		 .ANON.' AND playwhen IS NULL AND teamA IS NOT NULL AND teamB IS NOT NULL ORDER BY match_id DESC';
		$mdata = $db->GetAssoc($sql,array($bracket_id));
		if($mdata == FALSE)
			return FALSE;
		$playorder = array();
		$allteams = array(); 
		$sql1 = 'SELECT MAX(playwhen) AS last FROM '.$pref.'module_tmt_matches WHERE teamA=? OR teamA=?';
		$sql2 = 'SELECT MAX(playwhen) AS last FROM '.$pref.'module_tmt_matches WHERE teamB=? OR teamB=?';
		foreach($mdata as $mid=>$mteams)
		{
			$t = $db->GetOne($sql1,array($mteams['teamA'],$mteams['teamB']));
			$dt = ($t != null) ? new DateTime($t) : $sdt;
			$playorder[$mteams['teamA']] = $dt->getTimestamp();
			$allteams[$mteams['teamA']] = $mid;
			$t = $db->GetOne($sql2,array($mteams['teamA'],$mteams['teamB']));
			$dt = ($t != null) ? new DateTime($t) : $sdt;
			$playorder[$mteams['teamB']] = $dt->getTimestamp();
			$allteams[$mteams['teamB']] = $mid;
		}
		asort($playorder,SORT_NUMERIC); //TODO for RR?

		$save = strftime('%F %R',$at);
		$threshold = $this->GetCutoff($bdata,$at);
		$diff = $at - $threshold; //doesn't change
		//now set real threshold
 		if($stamp == $sstamp)
			$threshold = $sstamp;
		$slotcount = $bdata['sametime']; //maybe null
		$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=?,status=? WHERE match_id=?';
		foreach($playorder as $tid=>$last)
		{
			if($last <= $threshold)
			{
				$mid = $allteams[$tid];
				$pair = $mdata[$mid];
				$oid = ($tid == $pair['teamA']) ?(int)$pair['teamB'] :(int)$pair['teamA'];
				if($oid != -1 && $playorder[$oid] <= $threshold) //teams $tid & $oid both last-played before $threshold
				{
					$db->Execute($sql,array($save,SOFT,$mid));
					if(!empty($slotcount))
					{
						if(--$slotcount == 0)
						{
							$at = $this->GetNextSlot($bdata,$at,TRUE);
							if($at === FALSE)
								return FALSE;
							$save = strftime('%F %R',$at);
							$threshold = $at - $diff;
							$slotcount = $bdata['sametime'];
						}
					}
					unset($playorder[$oid]); //no need to process that one again
				}
			}
		}
		return TRUE;
	}

	/**
	GetNextSlot:
	@bdata: array of bracket data
	@base: timestamp expressed for local timezone
	@withgap: optional, whether to append bracket's placegap to @base, default FALSE
	Returns: timestamp or FALSE
	*/
	private function GetNextSlot($bdata,$base,$withgap=FALSE)
	{
		$stamp = floor($base/3600)*3600; //set its min:sec to 0:0
		if($withgap)
		{
			switch($bdata['placegaptype'])
			{
			 case 'hours':
			 	$f = 3600;
				break;
			 case 'days':
			 	$f = 86400; //3600*24
				break;
			 case 'weeks':
			 	$f = 604800; //3600*24*7
				break;
//			 case 'months':
//			    $f = -1; //special handling
//				break;
			 default: //minutes
				$f = 60;
				break;
			}
			if(empty($bdata['placegap']))
				$stamp += $f;
			else
				$stamp += $f * $bdata['placegap'];
		}
		$stampf = $stamp + 604800; //stop after a week of checks
		$thisday = date('w',$stamp) + 1;	//1(for Sunday) .. 7(for Saturday) to match recorded data
		$thishour = intval(date('G',$stamp));

		$days = $bdata['match_days'];
		if($days == null)
			$days == ''; //simple matching cuz single-digit days used
		$hours = $bdata['match_hours'];
		if($hours == null)
			$hours == '';
		if($hours != '')
			$hours = explode(';',$hours); //array matching cuz multi-digit days used

		while(1)
		{
			if($days == '' || strpos($days,(string)$thisday) !== FALSE)
			{
				if($hours == '' || in_array((string)$thishour,$hours))
					return $stamp;
				elseif($thishour < 23)
					$thishour++;
				else
				{
					$thishour = 0;
					if(++$thisday > 7)
						$thisday -= 7;
				}
				$stamp += 3600;
				if($stamp > $stampf)
					return FALSE;
			}
			else
			{
				$stamp += 86400;
				if($stamp > $stampf)
					return FALSE;
				if(++$thisday > 7)
					$thisday -= 7;
			}
		}
	}

	/**
	GetCutoff:
	@bdata: array of bracket data
	@base: timestamp expressed for local timezone
	Returns: timestamp before @base
	*/
	private function GetCutoff($bdata,$base)
	{
		switch($bdata['playgaptype'])
		{
		 case 'hours':
			$f = 3600;
			break;
		 case 'days':
			$f = 86400; //3600*24
			break;
		 case 'weeks':
			$f = 604800; //3600*24*7
			break;
//		 case 'months':
//		    $f = -1; //special handling
//			break;
		 default: //minutes
			$f = 60;
			break;
		}
		if(empty($bdata['playgap']))
			$stamp = $base - $f;
		else
			$stamp = $base - $f * $bdata['playgap'];
		return $stamp;
	}

	/**
	InitKOMatches:
	@mod: reference to module object
	@bracket_id: the bracket identifier
	Create a set of match records, in the matches table, for the bracket
	Clears any existing matches for the bracket
	Stores status for matches which have a bye
	Returns: TRUE on success, or lang-key for error message if bad no. of teams in the bracket
	*/
	function InitKOMatches(&$mod,$bracket_id)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql = 'SELECT team_id,seeding FROM '.$pref.'module_tmt_teams
WHERE bracket_id=? AND flags!=2 ORDER BY (CASE WHEN seeding IS NULL THEN 1 ELSE 0 END),seeding ASC';
		$allteams = $db->GetAssoc($sql,array($bracket_id));
		if($allteams == FALSE)
			return 'info_nomatch'; //no teams means no matches
		$numteams = count($allteams);
		list($min,$max) = $mod->GetLimits(KOTYPE);
		if($numteams > $max || $numteams < $min)
			return 'err_value';
		$numseeds = count(array_filter($allteams));
		if($numseeds > 1)
		{
			$sql = 'SELECT seedtype FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$stype = $db->GetOne($sql,array($bracket_id));
		}
		else
			$stype = 0; //default to random
		$rs = FALSE;
		switch($stype)
		{
		 case 0: //randomise all seeds
			if($numseeds > 1)
				$rs = 1; //start randomising from beginning
			break;
		 case 1: //randomise seeds 3 ...(if there are enough to work with)
			if($numseeds > 3 && $numteams > 3)
				$rs = 3;
			break;
		 case 2: //no seed randomising
		 case 3:
		 	break;
		 default: //ignore seeds,should never happen
			$numseeds = 0; //don't use $allteams at all
			break;
		}

		if($rs)
		{
			$exseeds = $this->RandomOrder($rs,$numseeds - $rs);
			foreach($exseeds as $old=>$new)
			{
				$tmp = $allteams[$old];
				$allteams[$old] = $allteams[$new];
				$allteams[$new] = $tmp;
			}
		}

		if($numteams > $numseeds)
			$randoms = $this->RandomOrder($numseeds+1,$numteams-$numseeds);

		$allteams = array_keys($allteams); //convert seed-no's to teamid's
		$order = $this->OrderMatches($numteams,$numseeds,$stype);
		foreach($order as $i=>$tid)
		{
			if($tid <= $numseeds)
				$order[$i] = $allteams[$tid-1];
			elseif($tid <= $numteams)
				$order[$i] = $allteams[$randoms[$tid]-1];
			else
				$order[$i] = -1; //bye
		}
		$numteams = count($order);
		$LC = ((log($numteams,2)+0.0001)|0) + 1; //no. of levels
		$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
		$db->Execute($sql,array($bracket_id));

		//safely 'reserve' enough match_id's - for knockouts,N teams means N-1 total matches
		$id1 = $this->ReserveMatches($db,$pref,$numteams-1);
		$sql = 'INSERT INTO '.$pref.'module_tmt_matches (match_id,bracket_id,nextm,teamA,teamB) VALUES (?,?,?,?,?)';
		if($LC > 1) //> 2 teams in the comp
		{
			//work backwards from the final
			$idF = $id1 + $numteams - 2;
			$sql2 = 'INSERT INTO '.$pref."module_tmt_matches (match_id,bracket_id) VALUES ($idF,$bracket_id)"; //final has no parent
			$db->Execute($sql2);
			$sql2 = 'INSERT INTO '.$pref.'module_tmt_matches (match_id,bracket_id,nextm) VALUES (?,?,?)';
			for($L=2; $L<=$LC; $L++)
			{
				$stop = pow(2,$L-1);
				$moff = $id1 + $numteams - $stop;
				$poff = $id1 + $numteams - 1 - $stop/2;
				for($i=1; $i<=$stop; $i++)
				{
					$parent = $poff - intval(($i-1)/2);
					if($L < $LC)
						$db->Execute($sql2,array($moff-$i,$bracket_id,$parent));
					else //also process the order array in reverse
					{
						$i2 = ($stop+1-$i)*2;
						$db->Execute($sql,array($moff-$i,$bracket_id,$parent,$order[$i2-1],$order[$i2]));
					}
				}
			}
			//store status for matches with a bye
			$this->InitByes($bracket_id,$db,$pref);
			//chain up
			$this->UpdateKOMatches($bracket_id);
		}
		else
			$db->Execute($sql,array($id1,$bracket_id,null,$allteams[0],$allteams[1])); //one match,the final

		return TRUE;
	}

	/**
	UpdateKOMatches()
	@bracket_id: the bracket identifier
	Migrate match winners to corresponding next matches. Byes are propagated.
	Returns: TRUE if update done, FALSE if not
	*/
	function UpdateKOMatches($bracket_id)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql1 = 'SELECT M.*,N.teamA AS nexttA,N.teamB AS nexttB
FROM '.$pref.'module_tmt_matches M JOIN '.$pref.'module_tmt_matches N ON M.nextm=N.match_id
WHERE M.bracket_id=? AND M.status>='.ANON.' AND (N.teamA IS NULL OR N.teamB IS NULL)';
		$so = ' ORDER BY M.match_id ASC';
		$done = FALSE;
		$skips = array();
		$updates = $db->GetAll($sql1.$so,array($bracket_id));
		while($updates)
		{
			$more = FALSE;
			foreach($updates as &$mdata)
			{
				switch($mdata['status'])
				{
				case WONA:
				case FORFB:
					$winner = $mdata['teamA'];
					break;
				case WONB:
				case FORFA:
					$winner = $mdata['teamB'];
					break;
				case NOWIN:
					$winner = -1; //propagate a bye
					break;
				default:
					$winner = null; //do nothing
					break;
				}
				$mid = (int)$mdata['match_id'];
				if(!($winner == $mdata['nexttA'] || $winner == $mdata['nexttB']
				   || $winner == -1 || $winner == null))
				{
					$this->SetTeam((int)$winner,(int)$mdata['nextm'],$mid,$db,$pref);
					$more = TRUE;
				}
				elseif($winner == -1 &&($mdata['nexttA'] == FALSE || $mdata['nexttB'] == FALSE))
				{
					$this->SetTeam((int)$winner,(int)$mdata['nextm'],$mid,$db,$pref);
					$more = TRUE;
				}
				$skips[] = $mid;
			}
			unset($mdata);
			if($more)
			{
				$done = TRUE;
				$sql = $sql1.' AND M.match_id NOT IN ('.implode(',',$skips).')'.$so;
				$updates = $db->GetAll($sql,array($bracket_id));
			}
			else
				break;
		}
		$this->ScheduleMatches($bracket_id);
		return $done;
	}

	/**
	InitDEMatches()
	@mod: reference to module object
	@bracket_id: the bracket identifier
	Create a set of match records, in the matches table, for the bracket
	Clears any existing matches for the bracket
	Stores status for matches which have a bye
	Returns: TRUE on success, or lang-key for error message if bad no. of teams in the bracket
	*/
	function InitDEMatches(&$mod,$bracket_id)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql = 'SELECT team_id,seeding FROM '.$pref.'module_tmt_teams
WHERE bracket_id=? AND flags!=2 ORDER BY (CASE WHEN seeding IS NULL THEN 1 ELSE 0 END),seeding ASC';
		$allteams = $db->GetAssoc($sql,array($bracket_id));
		if($allteams == FALSE)
			return 'info_nomatch';
		$numteams = count($allteams);
		list($min,$max) = $mod->GetLimits(DETYPE);
		if($numteams < $min || $numteams > $max)
			return 'err_value';
		$numseeds = count(array_filter($allteams));
		if($numseeds > 1)
		{
			$sql = 'SELECT seedtype FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$stype = $db->GetOne($sql,array($bracket_id));
		}
		else
			$stype = 0; //default to random
		$rs = FALSE;
		switch($stype)
		{
		 case 0: //randomise all seeds
			if($numseeds > 1)
				$rs = 1; //start randomising from beginning
			break;
		 case 1: //randomise seeds 3 ...(if there are enough to work with)
			if($numseeds > 3 && $numteams > 3)
				$rs = 3;
			break;
		 case 2: //no seed randomising
		 case 3:
		 	break;
		 default: //ignore seeds,should never happen
			$numseeds = 0; //don't use $allteams at all
			break;
		}

		if($rs)
		{
			$exseeds = $this->RandomOrder($rs,$numseeds - $rs);
			foreach($exseeds as $old=>$new)
			{
				$tmp = $allteams[$old];
				$allteams[$old] = $allteams[$new];
				$allteams[$new] = $tmp;
			}
		}

		if($numteams > $numseeds)
			$randoms = $this->RandomOrder($numseeds+1,$numteams-$numseeds);
		$allteams = array_keys($allteams);
		$order = $this->OrderMatches($numteams,$numseeds,$stype);
		foreach($order as $i=>$tid)
		{
			if($tid <= $numseeds)
				$order[$i] = $allteams[$tid-1];
			elseif($tid <= $numteams)
				$order[$i] = $allteams[$randoms[$tid]-1];
			else
				$order[$i] = -1; //bye
		}
		$numteams = count($order); //notional i.e. teams + byes

		$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
		$db->Execute($sql,array($bracket_id));
		//safely 'reserve' enough match_id's : N teams means 2N-2 matches
		$idW1 = $this->ReserveMatches($db,$pref,$numteams*2-2); //1st winners' draw match no.
		$idL1 = $idW1 + $numteams - 1; //1st losers' draw match no. (half matches in each draw)
		//setup winners' draw bands, the last of which is a semi-final i.e. one match
		$sql = 'INSERT INTO '.$pref.'module_tmt_matches (match_id,bracket_id,nextm,nextlm,teamA,teamB) VALUES (?,?,?,?,?,?)';
		$wbase = $idW1;
		$stop = $numteams/2;
		for($i=0; $i<$stop; $i++)
		{
			$off = (int)($i/2);
			$nextm = $wbase + $stop + $off;
			$nextlm = $idL1 + $off;
			$db->Execute($sql,array($wbase+$i,$bracket_id,$nextm,$nextlm,$order[$i*2+1],$order[$i*2+2]));
		}
		$sql = 'INSERT INTO '.$pref.'module_tmt_matches (match_id,bracket_id,nextm,nextlm) VALUES (?,?,?,?)';
		$BC = ((log($numteams,2)+0.0001)|0) + 1; //winners' semi band
		$wbase += $i;
		for($B=2; $B<$BC; $B++)
		{
			$stop /= 2;
			$lbase = $idL1 + $wbase - $idW1 - $stop;
			$losers = array();
			for($i=0; $i<$stop; $i++)
				$losers[] = $lbase+$i;
			if($stop > 1)
			{
				//reduce chance of re-playing past opponents
				if($B == 2)
					$losers = array_reverse($losers);
				else
					shuffle($losers);
			}
			for($i=0; $i<$stop; $i++)
			{
				$off = (int)($i/2);
				$nextm = $wbase + $stop + $off;
				$nextlm = $losers[$i];
				$db->Execute($sql,array($wbase+$i,$bracket_id,$nextm,$nextlm));
			}
			$wbase += $i;
		}
		//winners' semi
		$nextm = $idW1 + $numteams*2 - 3; //final
		$nextlm = $nextm - 1; //losers' semi
		$db->Execute($sql,array($wbase,$bracket_id,$nextm,$nextlm));

		$BF = $BC*3 - 1; //the final band
		//losers' bands
		$BM = $BC + 1; //the first one
		$wbase++; //now $idL1
		$sql = 'INSERT INTO '.$pref.'module_tmt_matches (match_id,bracket_id,nextm) VALUES (?,?,?)';
		for($B=$BM; $B<$BF-1; $B++)
		{
			$odd = ($B-$BM) % 2; //odd's halve next-round matches, even's don't
			$stop = $numteams/pow(2,2+(int)(($B-$BM)/2));
			for($i=0; $i<$stop; $i++)
			{
				$off = ($odd) ? (int)($i/2) : $i;
				$nextm = $wbase + $stop + $off;
				$db->Execute($sql,array($wbase+$i,$bracket_id,$nextm));
			}
			$wbase += $i;
		}
		$db->Execute($sql,array($wbase,$bracket_id,$wbase+1)); //semi final
		$db->Execute($sql,array($wbase+1,$bracket_id,NULL)); //final
		//store status for band-1 matches with a bye
		$this->InitByes($bracket_id,$db,$pref);
		//chain up,including loser-band byes
		$this->UpdateDEMatches($bracket_id);
		return TRUE;
	}

	/**
	UpdateDEMatches:
	@bracket_id: the bracket identifier
	Migrate match winners and losers to corresponding next matches. Byes are propagated.
	Returns: TRUE if update done, FALSE if not
	*/
	function UpdateDEMatches($bracket_id)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql1 = 'SELECT M.*,N.teamA AS nexttA,N.teamB as nexttB
FROM '.$pref.'module_tmt_matches M JOIN '.$pref.'module_tmt_matches N ON M.nextm=N.match_id
WHERE M.bracket_id=? AND M.status>='.MRES.' AND (N.teamA IS NULL OR N.teamB IS NULL)';
		$so = ' ORDER BY M.match_id ASC';
		$done = FALSE;
		$skips = array();
		$updates = $db->GetAll($sql1.$so,array($bracket_id));
		while($updates)
		{
			$more = FALSE;
			foreach($updates as &$mdata)
			{
				switch($mdata['status'])
				{
				case WONA:
				case FORFB:
					$winner = $mdata['teamA'];
					$loser = $mdata['teamB'];
					break;
				case WONB:
				case FORFA:
					$winner = $mdata['teamB'];
					$loser = $mdata['teamA'];
					break;
				case NOWIN:
					$winner = -1; //propagate a bye
					$loser = -1;
					break;
				default:
					$winner = NULL; //do nothing
					$loser = NULL;
					break;
				}
				$mid = (int)$mdata['match_id'];
				if(!($winner == $mdata['nexttA'] || $winner == $mdata['nexttB']
				   || $winner == -1 || $winner == NULL))
				{
					$this->SetTeam((int)$winner,(int)$mdata['nextm'],$mid,$db,$pref);
					if($mdata['nextlm'] && $loser != NULL)
						$this->SetTeam((int)$loser,(int)$mdata['nextlm'],$mid,$db,$pref,TRUE);
					$more = TRUE;
				}
				elseif($winner == -1 &&($mdata['nexttA'] == FALSE || $mdata['nexttB'] == FALSE))
				{
					$this->SetTeam((int)$winner,(int)$mdata['nextm'],$mid,$db,$pref);
					if($mdata['nextlm'] && $loser != NULL)
						$this->SetTeam((int)$loser,(int)$mdata['nextlm'],$mid,$db,$pref,TRUE);
					$more = TRUE;
				}
				$skips[] = $mid;
			}
			unset($mdata);
			if($more)
			{
				$done = TRUE;
				$sql = $sql1.' AND M.match_id NOT IN ('.implode(',',$skips).')'.$so;
				$updates = $db->GetAll($sql,array($bracket_id));
			}
			else
				break;
		}
		$this->ScheduleMatches($bracket_id);
		return $done;
	}

	/**
	NextRRMatches:
	@mod: reference to module object
	@bracket_id: the bracket identifier
	Setup match entries for the first or next series of matches, if any.
	The matches table is updated accordingly.
	Returns: TRUE on success, or lang-key for error message if bad no. of teams
	 in the bracket, or update not done
	*/
	function NextRRMatches(&$mod,$bracket_id)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2 ORDER BY displayorder ASC';
		$allteams = $db->GetCol($sql,array($bracket_id));
		if($allteams == FALSE)
			return 'info_nomatch';
		list($min,$max) = $mod->GetLimits(RRTYPE);
		if($allteams > $max || $allteams < $min)
			return 'err_value';

		shuffle($allteams);
		$played = array();
		foreach($allteams as $mdata)
			$played['T'.$mdata] = array(); //must be text key,to preserve in array_multisort()
		unset($allteams);

		$sql = 'SELECT * FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
		$stored = $db->GetAll($sql,array($bracket_id));
		if($stored)
		{
			foreach($stored as &$mdata)
			{
				if($mdata['status'] > 0) //TODO validate this test
				{
					$played['T'.$mdata['teamA']][] = $mdata['teamB'];
					$played['T'.$mdata['teamB']][] = $mdata['teamA'];
					unset($mdata); //no more use for the scheduled/finished ones
				}
			}
			unset($mdata);
			$counts = array_map('count',$played);
			array_multisort($counts,SORT_ASC,SORT_NUMERIC,$played);
			unset($counts);
		}

		$play2 = $played;
		$matches = array();
		foreach($played as $tid=>&$selfops)
		{
			foreach($play2 as $vid=>&$thisops)
			{
				if($tid != $vid) //ignore self
				{
					$id = intval(substr($tid,1)); //omit the T
					if(!in_array($id,$thisops))
					{
						$vs = intval(substr($vid,1));
						$log = TRUE;
						if($stored) //unplayed match[es] in the table
						{
							foreach($stored as &$mdata)
							{
								if($mdata['teamA'] == $id || $mdata['teamA'] == $vs
								 || $mdata['teamB'] == $id || $mdata['teamB'] == $vs)
								{
									$log = FALSE;
									break;
								}
							}
							unset($mdata);
						}
						if($log)
						{
							//log these opponents
							if($id < $vs)
								$matches[$id] = $vs;
							else
								$matches[$vs] = $id;
						}
						//prevent those from further consideration
						unset($play2[$tid]);
						unset($play2[$vid]);
						unset($played[$tid]);
						unset($played[$vid]);
						reset($played);
						break;
					}
				}
				unset($vid);
			}
			unset($thisops);
			unset($tid);
		}
		unset($selfops);

		$c = count($matches);
		if($c > 0)
		{
			if($c > 1)
				ksort($matches,SORT_NUMERIC);
			//safely 'reserve' enough match_id's
			$mid = $this->ReserveMatches($db,$pref,$c);
			$sql = 'INSERT INTO '.$pref.'module_tmt_matches (match_id,bracket_id,teamA,teamB) VALUES (?,?,?,?)';
			foreach($matches as $A=>$B)
			{
				$db->Execute($sql,array($mid,$bracket_id,$A,$B));
				$mid++;
			}
			return TRUE;
		}
		$this->ScheduleMatches($bracket_id);
		return 'info_nomatch';
	}

	/**
	MatchCommitted:
	@db: reference to database connection object
	@pref: database tables prefix
	@bracket_id: the bracket identifier
	@zone: bracket timezone, optional, default FALSE
	See also: tmtEditSetup::MatchExists()
	Returns: TRUE if any match in bracket @bracket_id is 'locked in'
	*/
	private function MatchCommitted(&$db,$pref,$bracket_id,$zone=FALSE)
	{
		$sql = 'SELECT 1 AS yes FROM '.$pref.
		'module_tmt_matches WHERE bracket_id=? AND status>='.MRES.
		' AND teamA IS NOT NULL AND teamA!=-1 AND teamB IS NOT NULL AND teamB!=-1';
		$rs = $db->SelectLimit($sql,1,-1,array($bracket_id));
		if($rs && !$rs->EOF) //match(es) other than byes recorded
		{
			$rs->Close();
			return TRUE;
		}
		else
		{
			if($rs) $rs->Close();
			if(!$zone)
			{
				$sql = 'SELECT timezone FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
				$zone = $db->GetOne($sql,array($bracket_id));
			}			
			$dt = new DateTime('+'.LEADHOURS.' hours',new DateTimeZone($zone));
			$sql = 'SELECT 1 AS yes FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND status = '.FIRM.
			' AND playwhen IS NOT NULL AND playwhen < '.$dt->format('Y-m-d G:i:s').
			' AND teamA IS NOT NULL AND teamA != -1 AND teamB IS NOT NULL AND teamB != -1';
			$rs = $db->SelectLimit($sql,1,-1,array($bracket_id));
			if($rs && !$rs->EOF) //FIRM match(es) scheduled before min. leadtime from now
			{
				$rs->Close();
				return TRUE;
			}
		}
		if($rs)
			$rs->Close();
		return FALSE;
	}

	/**
	ConformNewteams:
	@mod: reference to module object
	@bracket_id: the bracket identifier
	@type: enumerator of bracket type, KOTYPE etc
	@zone: bracket timezone
	@tid: team id, or array of team id's to be processed
	Adjust matches to reflect addition of team(s) identified by @tid.
	Returns: TRUE if completed successfully
	*/
	function ConformNewteams(&$mod,$bracket_id,$type,$zone,$tid)
	{
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();

		if($this->MatchCommitted($db,$pref,$bracket_id,$zone))
		{
			if($type != RRTYPE)
			{
				if(!is_array($tid))
					$tid = array($tid);
				foreach($tid as $one)
				{
					if(!$this->RandomAdd($bracket_id,$tid))
						return FALSE;
				}
			}
			elseif($this->NextRRMatches($mod,$bracket_id) !== TRUE) //TODO add & schedule a match for $team_id
				return FALSE;
		}
		return TRUE;
	}

	/**
	ConformGoneteams:
	@mod: reference to module object
	@bracket_id: the bracket identifier
	@type: enumerator of bracket type, KOTYPE etc
	@zone: bracket timezone
	@tid: team id, or array of team id's to be processed
	Adjust matches to reflect removal of team(s) identified by @tid.
	Relevant matches are marked as forfeited, or all matches are re-scheduled.
	Returns: TRUE, or result of matches-recreation func
	*/
	function ConformGoneteams(&$mod,$bracket_id,$type,$zone,$tid)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
	
		if($this->MatchCommitted($db,$pref,$bracket_id,$zone))
		{
			$args = array($bracket_id);
			if(is_array($tid))
			{
				$fillers = str_repeat('?,',count($tid)-1).'?';
				$test = ' IN ('.$fillers.')';
				$args = array_merge($args,$tid);
			}
			else
			{
				$test = '=?';
				$args[] = $tid;
			}

			if($type == RRTYPE)
			{
				$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND status<'.MRES.' AND teamA'.$test;
				$db->Execute($sql,array($args));
				$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND status<'.MRES.' AND teamB'.$test;
				$db->Execute($sql,array($args));
			}
			else
			{
				$args2 = $args;
				array_unshift($args2,$mod->Lang('teamgone'));
				$sql = 'UPDATE '.$pref.'module_tmt_matches SET teamA=-1,playwhen=NULL WHERE bracket_id=? AND teamB IS NULL AND teamA'.$test;
				$db->Execute($sql,array($args));
				$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=NULL,status='.NOWIN.',score=? WHERE bracket_id=? AND status<'.MRES.' AND teamB=-1 AND teamA'.$test;
				$db->Execute($sql,array($args2));
				$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=NULL,status='.FORFA.',score=? WHERE bracket_id=? AND status<'.MRES.' AND teamA'.$test;
				$db->Execute($sql,array($args2));

				$sql = 'UPDATE '.$pref.'module_tmt_matches SET teamB=-1,playwhen=NULL WHERE bracket_id=? AND teamA IS NULL AND teamB'.$test;
				$ares = $db->Execute($sql,array($args));
				$sql2 = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=NULL,status='.NOWIN.',score=? WHERE bracket_id=? AND status<'.MRES.' AND teamA=-1 AND teamB'.$test;
				$ares2 = $db->Execute($sql2,array($args2));
				$sql3 = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=NULL,status='.FORFB.',score=? WHERE bracket_id=? AND status<'.MRES.' AND teamB'.$test;
				$ares3 = $db->Execute($sql3,array($args2));
			}
			return TRUE;
		}
		else //fresh start
		{
			$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
			$db->Execute($sql,array($bracket_id));
			switch($type)
			{
			 case DETYPE:
				return ($this->InitDEMatches($mod,$bracket_id) === TRUE);
			 case RRTYPE:
				return ($this->NextRRMatches($mod,$bracket_id) === TRUE);
			 default:
			 //case KOTYPE:
				return ($this->InitKOMatches($mod,$bracket_id) === TRUE);
			}
		}
	}

	/**
	ConformNewResults:
	@bracket_id: the bracket identifier
	@type: enumerator of bracket type, KOTYPE etc
	@mid: match id, or array of match id's, to be processed
	@status: match status corresponding to non-array @mid, or array of match statuses (each member like $mid=>$status)
	For 'plan' view match results processing.
	Adjust matches to reflect result of each match identified by @mid.
	If a status >= MRES is changed, the winner (and loser if relevant) are used
	to update the teams in subsequent match(es).
	Recorded match status,score,venue etc are not changed here.
	*/
	function ConformNewResults($bracket_id,$type,$mid,$status)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql = 'SELECT match_id,nextm,nextlm,teamA,teamB,status FROM '.$pref.'module_tmt_matches WHERE match_id';
		if(is_array($mid))
		{
			$fillers = str_repeat('?,',count($mid)-1).'?';
			$sql .= ' IN ('.$fillers.') ORDER BY match_id ASC';
			$args = $mid;
		}
		else
		{
			$sql .= '=?';
			$args = array($mid);
			$status = array($mid=>$status);
		}
		$matches = $db->GetAssoc($sql,$args);
		$sql = 'UPDATE '.$pref.'module_tmt_matches SET teamA=? WHERE match_id>=? AND teamA=?';
		$sql2 = 'UPDATE '.$pref.'module_tmt_matches SET teamB=? WHERE match_id>=? AND teamB=?';
		foreach($matches as $id=>$mdata)
		{
			if($mdata['status'] != $status[$id])
			{
				switch($mdata['status'])
				{
				 case WONA:
				 case FORB:
					//ensure teamA is one of teams in nextm & beyond
					$args = array($mdata['teamA'],$mdata['nextm'],$mdata['teamB']);
					$db->Execute($sql,$args);
					$db->Execute($sql2,$args);
					if($type == DETYPE)
					{
						//ensure teamB is one of teams in nextlm & beyond
						$args = array($mdata['teamB'],$mdata['nextlm'],$mdata['teamA']);
						$db->Execute($sql,$args);
						$db->Execute($sql2,$args);
					}
					break;
				 case WONB:
				 case FORA:
					//ensure teamB is one of teams in nextm & beyond
					$args = array($mdata['teamB'],$mdata['nextm'],$mdata['teamA']);
					$db->Execute($sql,$args);
					$db->Execute($sql2,$args);
					if($type == DETYPE)
					{
						//ensure teamA is one of teams in nextlm & beyond
						$args = array($mdata['teamA'],$mdata['nextlm'],$mdata['teamB']);
						$db->Execute($sql,$args);
						$db->Execute($sql2,$args);
					}
					break;
				 default:
					break;
				}
			}
		}
 	}

	/**
	UnRecorded:
	@bracket_id: the bracket identifier
	Returns: Count of match(es) ready for their status to be recorded, FALSE upon error
	*/
	function UnRecorded($bracket_id)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql = 'SELECT COUNT(match_id) AS num FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND status<'
		 .ANON.' AND teamA IS NOT NULL AND teamB IS NOT NULL';
		return $db->GetOne($sql,array($bracket_id));
	}

}

?>
