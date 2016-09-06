<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Class: tmtSchedule. Functions involved with tournament match scheduling
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
	@teamcount: the actual no. of starting competitors i.e. exclude byes
	@seedcount: no. (<= @teamcount) of competitors to be 'specially assigned' per @seedtype
	@seedtype: enum
	 0 no special treatment of seeds
	 1 seeds 1 & 2 in opposite halves of draw (in which case @seedcount >= 2)
	 2 all seeds alternate, with ratings closer than for option 3
	 3 all seeds alternate, with ratings as divergent as possible
	 4 (random 2 or 3) but not seen here due to upstream subsititution
	@fixcount: 0 or even no. (<= @teamcount) of competitors to be excluded from random assignment
	Returns: Array of team indices in match-order.
	The array keys are ascending, 1 to 0.5 * a power of 2 which is equal to
	 @teamcount, or else the next highest power of 2 > @teamcount.
	The corresponding values are team indices in the same range, except for @fixcount
	placeholder values (-1). Any randomising of actual team numbers associated with
	the indices must be done upstream.
	*/
	private function OrderMatches($teamcount,$seedcount,$seedtype,$fixcount)
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
			$top = ($seedtype != 3) ? ($i % 2 != 0) : (intval($i/2) % 2 == 0);
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
		$r = $full - $seedcount;
		//next, pairs of slots for fixers, if any
		$fp = 1; //placement enum
		$f1 = $fixcount; //CHECKME fudge-factor ??
		$f2 = $f1 + 1;
		while($fixcount)
		{
			//try to bias fixers' positions toward centre of bracket (maybe lower-ranked vicinity)
			for($i = $full; $i > 0; $i--)
			{
				switch($fp)
				{
				 case 1:
				 case 3:
					$vs = $i - $fp - $f2; //CHECKME fudge-factor
					if($i > ($fp-$f2) && $i < ($full+$fp+$f2) && !isset($order[$vs]) && !isset($order[$vs-1]))
					{
						$order[$vs] = -5; //anything < 0 will do
						$order[$vs-1] = -5;
						$r -= 2;
						$fixcount -= 2;
						if($fixcount == 0) break 2;
						$i--;
						if(++$fp > 4) $fp = 1;
					}
					break;
				 case 2:
				 case 4:
					$vs = $full - $i - $fp + $f2; //$fp-1 {1,3} CHECKME fudge-factor
					if($i > ($f1-$fp) && $i < ($full-$fp+$f1) && !isset($order[$vs]) && !isset($order[$vs-1]))
					{
						$order[$vs] = -10;
						$order[$vs-1] = -10;
						$r -= 2;
						$fixcount -= 2;
						if($fixcount == 0) break 2;
						$i--;
						if(++$fp > 4) $fp = 1;
					}
					break;
				}
			}
			if(++$fp > 4)
				break;	//abort after a few total fails
		}
		//next, the rest
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
		$sql = 'SELECT match_id,teamA,teamB,nextm FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0
AND (teamA = -1 OR teamB = -1)
AND match_id NOT IN (SELECT DISTINCT nextm FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0 AND nextm IS NOT NULL)';
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
			$sql = 'UPDATE '.$pref.'module_tmt_matches SET teamA=?,teamB=?,status='.Tourney::NOTYET.' WHERE match_id=?';
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
		.'module_tmt_matches WHERE bracket_id=? AND (teamA=-1 OR teamB=-1) ORDER BY match_id';
		$byes = $db->GetAssoc($sql,array($bracket_id));
		if($byes)
		{
			$sql = 'UPDATE '.$pref.'module_tmt_matches SET status=? WHERE match_id=?';
			foreach($byes as $indx=>$mdata)
			{
				if($mdata['teamA'] == '-1')
					$status = ($mdata['teamB'] != '-1') ? Tourney::WONB : Tourney::NOWIN;
				else //$mdata['teamB'] == '-1'
					$status = ($mdata['teamA'] != '-1') ? Tourney::WONA : Tourney::NOWIN;
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
		$sql = 'SELECT match_id FROM '.$pref.'module_tmt_matches WHERE '.$field.'=? AND match_id!=? AND flags=0 AND status>='.Tourney::ANON;
		$prevo = $db->GetOne($sql,array($mid,$prevm));
		if($prevo == FALSE) //this is the 1st report for this match
			$vals = array($tid,NULL); //store this winner as teamA (even if a bye)
		else
		{
			//store winners in match-order so they will be displayed in same order as previous matches
			$sql = 'SELECT teamA,status FROM '.$pref.'module_tmt_matches WHERE match_id=? AND flags=0';
			$mdata = $db->GetRow($sql,array($mid));
			$oid = ($mdata['teamA']) ? (int)$mdata['teamA'] : NULL; //don't convert NULL to 0
			if($prevo < $prevm) //previous report from lower-numbered match
			{
				$vals = array($oid,$tid); //store this winner as teamB
				if($oid == -1) //bye
					$status = Tourney::WONB;
				elseif($tid == -1)
					$status = Tourney::WONA;
			}
			else
			{
				$vals = array($tid,$oid); //store as teamA
				if($oid == -1)
					$status = Tourney::WONA;
				elseif($tid == -1)
					$status = Tourney::WONB;
			}
			if($status == 0)
			{
				switch($mdata['status'])
				{
				 case Tourney::ASOFT:
					$status = Tourney::SOFT;
					break;
				 case Tourney::AFIRM:
				 case Tourney::TOLD:
				 case Tourney::ASKED:
					$status = Tourney::FIRM; //revert to this
					break;
				}
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
	GroupTeams:
	Identify teams equivalent to @teamA or @teamB in grouped brackets
	@mod: reference to module object
	@bdata: reference to array of parameters for the 'target' bracket
	@teamA: team identifier
	@teamB: ditto
	Returns: array with at least @teamA and @teamB
	*/
	private function GroupTeams(&$mod,&$bdata,$teamA,$teamB)
	{
		if($bdata['groupid'] == 0) //ungrouped
			return array($teamA,$teamB);
		$sigA = $mod->TeamName($teamA); //'signature' for matching TODO may rely on team-members recorded in same order
		$sigB = $mod->TeamName($teamB);
		$allteams = array();
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id IN (
SELECT bracket_id FROM '.$pref.'module_tmt_brackets WHERE groupid=?
)';
		$data = $db->GetCol($sql,array($bdata['groupid']));
		foreach($data as $tid)
		{
			$tid = (int)$tid;
			$sig = $mod->TeamName($tid);
			if($sig == $sigA || $sig == $sigB)
				$allteams[] = $tid;
		}
		return $allteams;
	}
	
	/**
	ScheduleMatches:
	@mod: reference to module object
	@bracket_id: the bracket identifier
	Setup dates/times for matches whose participants are known.
	The matches table is updated accordingly.
	Returns: FALSE upon error or nothing to process or no startdate or timezone for the bracket
	*/
	function ScheduleMatches(&$mod,$bracket_id)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
		$bdata = $db->GetRow($sql,array($bracket_id));
		if($bdata == FALSE)
			return FALSE;
		if(empty($bdata['startdate']) || empty($bdata['timezone']))
			return FALSE;
		$cal = new WhenRules($mod);
		if(!$cal->ParseDescriptor($bdata['available']/*,$bdata['locale']*/))
			return FALSE;
		$tz = new DateTimeZone($bdata['timezone']);
		$sdt = new DateTime($bdata['startdate'],$tz);
		$sstamp = $sdt->getTimestamp(); //when the tournament start{s|ed}
		//allow minimum leadtime before the next match
		$dt = new DateTime('+'.Tourney::LEADHOURS.' hours',$tz);
		$stamp = $dt->getTimestamp();
 		if($stamp < $sstamp)
			$stamp = $sstamp;

		$at = self::GetNextSlot($cal,$bdata,$stamp,FALSE); //find 1st slot
		if($at == FALSE)
			return FALSE;

		//matches in DESC order so next foreach overwrites newer ones in $allteams array
		//CHECKME also get SOFT matches before now?
		$sql = 'SELECT match_id,teamA,teamB FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0 AND status<'.
		 Tourney::ANON.' AND playwhen IS NULL AND teamA IS NOT NULL AND teamB IS NOT NULL ORDER BY match_id DESC';
		$mdata = $db->GetAssoc($sql,array($bracket_id));
		if($mdata == FALSE)
			return FALSE;
		$tz = new DateTimeZone($bdata['timezone']);
		$playorder = array();
		$allteams = array();
		//CHECKME does MAX() work for date-time values?
		$sql = 'SELECT MAX(playwhen) AS latest FROM '.$pref.'module_tmt_matches WHERE %s IN (%s) AND flags=0';
		foreach($mdata as $mid=>$mteams)
		{
			$allteams = self::GroupTeams($mod,$bdata,$mteams['teamA'],$mteams['teamB']);
			$tdata = implode(',',$allteams);
			$sql1 = sprintf($sql,'teamA',$tdata);
			$t = $db->GetOne($sql1);
			$dt = ($t != NULL) ? new DateTime($t,$tz) : $sdt;
			$playorder[$mteams['teamA']] = $dt->getTimestamp();
			$allteams[$mteams['teamA']] = $mid;

			$sql1 = sprintf($sql,'teamB',$tdata);
			$t = $db->GetOne($sql1);
			$dt = ($t != NULL) ? new DateTime($t,$tz) : $sdt;
			$playorder[$mteams['teamB']] = $dt->getTimestamp();
			$allteams[$mteams['teamB']] = $mid;
		}
		asort($playorder,SORT_NUMERIC); //TODO for RR?

		$save = strftime('%F %R',$at);
		$diff = self::GapSeconds($bdata['playgaptype'],$bdata['playgap']);
		//initial threshold
 		if($stamp == $sstamp)
			$threshold = $sstamp;
		else
			$threshold = $at - $diff;
		$slotcount = $bdata['sametime']; //maybe NULL
		$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=?,status=? WHERE match_id=?';
		foreach($playorder as $tid=>$last)
		{
//TODO support player-availability 'SELECT available FROM '.$pref'.'module_tmt_people WHERE ...'
			if($last <= $threshold)
			{
				$mid = $allteams[$tid];
				$pair = $mdata[$mid];
				$oid = ($tid == $pair['teamA']) ?(int)$pair['teamB'] :(int)$pair['teamA'];
				if($oid != -1 && $playorder[$oid] <= $threshold) //teams $tid & $oid both last-played before $threshold
				{
					$db->Execute($sql,array($save,Tourney::SOFT,$mid));
					if(!empty($slotcount))
					{
						if(--$slotcount == 0)
						{
							$at = self::GetNextSlot($cal,$bdata,$at,TRUE);
							if($at == FALSE)
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
	GapSeconds:
	Get no. of seconds for a gap
	@type: gap-type enum 0 = none, 1 = second .. 5 = month
	@count: no. of @type in the gap
	*/
	function GapSeconds($type,$count)
	{
		switch($type)
		{
		 case 1:
			$f = 60;
			break;
		 case 2:
		 	$f = 3600;
			break;
		 case 3:
		 	$f = 86400; //3600*24
			break;
		 case 4:
		 	$f = 604800; //3600*24*7
			break;
		 case 5:
			$f = 18144000; //3600*24*7*30 TODO special handling for months
			break;
		 default: //none
			$f = 0;
			break;
		}
		if(empty($count))
			return $f;
		else
			return $f * $count;
	}

	/**
	GetNextSlot:
	@cal: reference to WhenRules-class object including parsed availability-conditions
	@bdata: reference to array of bracket data
	@stamp: timestamp expressed for bracket timezone
	@withgap: optional, whether to append bracket's placegap to @stamp, default FALSE
	@later: optional, no. of days-ahead to scan for the slot, default 14
	Returns: timestamp or 0
	*/
	private function GetNextSlot(&$cal,&$bdata,$stamp,$withgap=FALSE,$later=14)
	{
		if($withgap)
		{
			//TODO find relevant resource, its last end-time, gap from then
			$stamp += self::GapSeconds($bdata['placegaptype'],$bdata['placegap']);
		}
		$dts = new DateTime('@'.$stamp,NULL);
		$dte = clone $dts;
		if($later < 1)
			$later = 1;
		$dte->modify('+'.$later.' days');
		$timeparms = $cal->TimeParms($bdata);
		$slotlen = self::GapSeconds($bdata['playgaptype'],$bdata['playgap']);

		$at = $cal->NextInterval($bdata['available'],$dts,$dte,$timeparms,$slotlen);
		if ($at)
			return floor($at[0]/60)*60; //ensure 0 sec
		return 0;
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
WHERE bracket_id=? AND flags!=2 ORDER BY (CASE WHEN seeding IS NULL THEN 1 ELSE 0 END),seeding';
		$allteams = $db->GetAssoc($sql,array($bracket_id));
		if($allteams == FALSE)
			return 'info_nomatch'; //no teams means no matches
		$numteams = count($allteams);
//	tmtUtils()?
		list($min,$max) = $mod->GetLimits(Tourney::KOTYPE);
		if($numteams > $max || $numteams < $min)
			return 'err_value';
		$numseeds = count(array_filter($allteams));
		if($numseeds > 1)
		{
			$sql = 'SELECT seedtype,fixtype FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$row = $db->GetRow($sql,array($bracket_id));
			$stype = (int)$row['seedtype'];
			$ftype = (int)$row['fixtype']; /*enum
				0 any seed < 0 is ignored
	 			1 closest seeds < 0 play each other
	 			2 treat seeds < 0 like seedtype 2
				3 treat seeds < 0 like seedtype 3
*/
		}
		else
		{
			$stype = 0; //default to random
			$ftype = 0; //and no special-case handling
		}
		//extract any seednums < 0
		$fixteams = array();
		$numfix = 0;
		foreach($allteams as $seed)
		{
			if($seed < 0)
				$numfix++;
			else
				break;
		}
		$numseeds -= $numfix; //= 'real' seeds
		if($numfix > 0)
		{
			if($ftype == 0) //ignore them all
				$mc = $numfix;
			elseif($numteams - $numfix < $numseeds*2) //need at least as many non-fixers as seeds
				$mc = $numseeds*2 - $numteams + $numfix;
			else
				$mc = 0;
			$numfix -= $mc;
			if($numfix % 2)	//need even no. of fixers
			{
				$mc++;
				$numfix--;
			}
			while($mc > 0)
			{
				//migrate current 1st member of $allteams to end, as unseeded
				reset($allteams);
				$tid = key($allteams);
				unset($allteams[$tid]);
				$allteams[$tid] = '';
				$mc--;
			}
			if($numfix)
			{
				//migrate fixers to separate array
				for($i=0; $i<$numfix; $i++)
				{
					reset($allteams);
					$tid = key($allteams);
					unset($allteams[$tid]);
					$fixteams[] = $tid;
				}
				//$allteams left with floaters only
			}
		}

		$numteams -= $numfix; //no. of floating teams
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
			else
				$rs = 1;
			break;
		 case 4: //random choice 2 or 3
		  $stype = rand(2,3);
		 case 3: //no seed randomising
		 case 2:
		 	break;
		 default: //ignore seeds,should never happen
			$numseeds = 0; //don't use $allteams at all
			break;
		}
	
		if($rs)
		{
			$exseeds = self::RandomOrder($rs,$numseeds - $rs);
			foreach($exseeds as $old=>$new)
			{
				$tmp = $allteams[$old];
				$allteams[$old] = $allteams[$new];
				$allteams[$new] = $tmp;
			}
		}

		//shuffle $fixteams per $ftype
		//0: already handled, 1: teams already in match-pair order
		//2: or 3: handled here
		if($numfix > 2 && $ftype > 1)
		{
			$h = $numfix/2;
			$l = $numfix-1; //0-based count for $ftype 3
			for($i=0; $i<$h; $i+=2)
			{
				$s = ($ftype == 2) ? $i+$h : $l-$i;
				$tmp = $fixteams[$i+1];
				$fixteams[$i+1] = $fixteams[$s];
				$fixteams[$s] = $tmp;
			} 							
		}
		$f = 0; //start with first (if any) member of $fixteams[]

		if($numteams > $numseeds)
			$randoms = self::RandomOrder($numseeds+1,$numteams-$numseeds);
		$allteams = array_keys($allteams); //team-id's now values
		$order = self::OrderMatches($numteams+$numfix,$numseeds,$stype,$numfix);
		foreach($order as $i=>$tid)
		{
			if($tid < 0)
				$order[$i] = $fixteams[$f++];
			elseif($tid <= $numseeds)
				$order[$i] = $allteams[$tid-1];
			elseif($tid <= $numteams)
				$order[$i] = $allteams[$randoms[$tid]-1];
			else
				$order[$i] = -1; //bye
		}
		$numteams = count($order); //notional i.e. teams + byes (power of 2)
		$LC = ((log($numteams,2)-0.0001)|0) + 1; //no. of levels
		$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
		$db->Execute($sql,array($bracket_id));

		//safely 'reserve' enough match_id's - for knockouts,N teams means N-1 total matches
		$id1 = self::ReserveMatches($db,$pref,$numteams-1);
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
			self::InitByes($bracket_id,$db,$pref);
			//chain up
			self::UpdateKOMatches($mod,$bracket_id);
		}
		else
			$db->Execute($sql,array($id1,$bracket_id,NULL,$allteams[0],$allteams[1])); //one match,the final

		return TRUE;
	}

	/**
	UpdateKOMatches()
	@mod: reference to module object
	@bracket_id: the bracket identifier
	Migrate match winners to corresponding next matches. Byes are propagated.
	Returns: TRUE if update done, FALSE if not
	*/
	function UpdateKOMatches(&$mod,$bracket_id)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql1 = 'SELECT M.*,N.teamA AS nexttA,N.teamB AS nexttB
FROM '.$pref.'module_tmt_matches M JOIN '.$pref.'module_tmt_matches N ON M.nextm=N.match_id
WHERE M.bracket_id=? AND M.status>='.Tourney::ANON.' AND (N.teamA IS NULL OR N.teamB IS NULL)';
		$so = ' ORDER BY M.match_id';
		$done = FALSE;
		$skips = array();
		$updates = $db->GetArray($sql1.$so,array($bracket_id));
		while($updates)
		{
			$more = FALSE;
			foreach($updates as &$mdata)
			{
				switch($mdata['status'])
				{
				case Tourney::WONA:
				case Tourney::FORFB:
					$winner = $mdata['teamA'];
					break;
				case Tourney::WONB:
				case Tourney::FORFA:
					$winner = $mdata['teamB'];
					break;
				case Tourney::NOWIN:
					$winner = -1; //propagate a bye
					break;
				default:
					$winner = NULL; //do nothing
					break;
				}
				$mid = (int)$mdata['match_id'];
				if(!($winner == $mdata['nexttA'] || $winner == $mdata['nexttB']
				   || $winner == -1 || $winner == NULL))
				{
					self::SetTeam((int)$winner,(int)$mdata['nextm'],$mid,$db,$pref);
					$more = TRUE;
				}
				elseif($winner == -1 &&($mdata['nexttA'] == FALSE || $mdata['nexttB'] == FALSE))
				{
					self::SetTeam((int)$winner,(int)$mdata['nextm'],$mid,$db,$pref);
					$more = TRUE;
				}
				$skips[] = $mid;
			}
			unset($mdata);
			if($more)
			{
				$done = TRUE;
				$sql = $sql1.' AND M.match_id NOT IN ('.implode(',',$skips).')'.$so;
				$updates = $db->GetArray($sql,array($bracket_id));
			}
			else
				break;
		}
		self::ScheduleMatches($mod,$bracket_id);
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
WHERE bracket_id=? AND flags!=2 ORDER BY (CASE WHEN seeding IS NULL THEN 1 ELSE 0 END),seeding';
		$allteams = $db->GetAssoc($sql,array($bracket_id));
		if($allteams == FALSE)
			return 'info_nomatch';
		$numteams = count($allteams);
//	tmtUtils()?
		list($min,$max) = $mod->GetLimits(Tourney::DETYPE);
		if($numteams < $min || $numteams > $max)
			return 'err_value';
		$numseeds = count(array_filter($allteams));
		if($numseeds > 1)
		{
			$sql = 'SELECT seedtype,fixtype FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$row = $db->GetRow($sql,array($bracket_id));
			$stype = (int)$row['seedtype'];
			$ftype = (int)$row['fixtype']; //see explanation in InitKOMatches()
		}
		else
		{
			$stype = 0; //default to random
			$ftype = 0; //and no special-case handling
		}
		//extract any seednums < 0
		$fixteams = array();
		$numfix = 0;
		foreach($allteams as $seed)
		{
			if($seed < 0)
				$numfix++;
			else
				break;
		}
		$numseeds -= $numfix; //= 'real' seeds
		if($numfix > 0)
		{
			if($ftype == 0) //ignore them all
				$mc = $numfix;
			elseif($numteams - $numfix < $numseeds*2) //need at least as many non-fixers as seeds
				$mc = $numseeds*2 - $numteams + $numfix;
			else
				$mc = 0;
			$numfix -= $mc;
			if($numfix % 2)	//need even no. of fixers
			{
				$mc++;
				$numfix--;
			}
			while($mc > 0)
			{
				//migrate current 1st member of $allteams to end, as unseeded
				reset($allteams);
				$tid = key($allteams);
				unset($allteams[$tid]);
				$allteams[$tid] = '';
				$mc--;
			}
			if($numfix)
			{
				//migrate fixers to separate array
				for($i=0; $i<$numfix; $i++)
				{
					reset($allteams);
					$tid = key($allteams);
					unset($allteams[$tid]);
					$fixteams[] = $tid;
				}
				//$allteams left with floaters only
			}
		}
	
		$numteams -= $numfix; //no. of floating teams
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
			else
				$rs = 1;
			break;
		 case 4: //random choice 2 or 3
		  $stype = rand(2,3);
		 case 3: //no seed randomising
		 case 2:
		 	break;
		 default: //ignore seeds,should never happen
			$numseeds = 0; //don't use $allteams at all
			break;
		}

		if($rs)
		{
			$exseeds = self::RandomOrder($rs,$numseeds - $rs);
			foreach($exseeds as $old=>$new)
			{
				$tmp = $allteams[$old];
				$allteams[$old] = $allteams[$new];
				$allteams[$new] = $tmp;
			}
		}

		//shuffle $fixteams per $ftype
		//0: already handled, 1: teams already in match-pair order
		//2: or 3: handled here
		if($numfix > 2 && $ftype > 1)
		{
			$h = $numfix/2;
			$l = $numfix-1; //0-based count for $ftype 3
			for($i=0; $i<$h; $i+=2)
			{
				$s = ($ftype == 2) ? $i+$h : $l-$i;
				$tmp = $fixteams[$i+1];
				$fixteams[$i+1] = $fixteams[$s];
				$fixteams[$s] = $tmp;
			} 							
		}
		$f = 0; //start with first (if any) member of $fixteams[]

		if($numteams > $numseeds)
			$randoms = self::RandomOrder($numseeds+1,$numteams-$numseeds);
		$allteams = array_keys($allteams); //team-id's now values
		$order = self::OrderMatches($numteams+$numfix,$numseeds,$stype,$numfix);
		foreach($order as $i=>$tid)
		{
			if($tid < 0)
				$order[$i] = $fixteams[$f++];
			elseif($tid <= $numseeds)
				$order[$i] = $allteams[$tid-1];
			elseif($tid <= $numteams)
				$order[$i] = $allteams[$randoms[$tid]-1];
			else
				$order[$i] = -1; //bye
		}
		$numteams = count($order); //notional i.e. teams + byes (power of 2)

		$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
		$db->Execute($sql,array($bracket_id));
		//safely 'reserve' enough match_id's : N teams means 2N-2 matches
		$idW1 = self::ReserveMatches($db,$pref,$numteams*2-2); //1st winners' draw match no.
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
		$BC = ((log($numteams,2)-0.0001)|0) + 1; //winners' semi band
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
		self::InitByes($bracket_id,$db,$pref);
		//chain up,including loser-band byes
		self::UpdateDEMatches($mod,$bracket_id);
		return TRUE;
	}

	/**
	UpdateDEMatches:
	@mod: reference to module object
	@bracket_id: the bracket identifier
	Migrate match winners and losers to corresponding next matches. Byes are propagated.
	Returns: TRUE if update done, FALSE if not
	*/
	function UpdateDEMatches($mod,$bracket_id)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql1 = 'SELECT M.*,N.teamA AS nexttA,N.teamB as nexttB
FROM '.$pref.'module_tmt_matches M JOIN '.$pref.'module_tmt_matches N ON M.nextm=N.match_id
WHERE M.bracket_id=? AND M.status>='.Tourney::MRES.' AND (N.teamA IS NULL OR N.teamB IS NULL)';
		$so = ' ORDER BY M.match_id';
		$done = FALSE;
		$skips = array();
		$updates = $db->GetArray($sql1.$so,array($bracket_id));
		while($updates)
		{
			$more = FALSE;
			foreach($updates as &$mdata)
			{
				switch($mdata['status'])
				{
				case Tourney::WONA:
				case Tourney::FORFB:
					$winner = $mdata['teamA'];
					$loser = $mdata['teamB'];
					break;
				case Tourney::WONB:
				case Tourney::FORFA:
					$winner = $mdata['teamB'];
					$loser = $mdata['teamA'];
					break;
				case Tourney::NOWIN:
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
					self::SetTeam((int)$winner,(int)$mdata['nextm'],$mid,$db,$pref);
					if($mdata['nextlm'] && $loser != NULL)
						self::SetTeam((int)$loser,(int)$mdata['nextlm'],$mid,$db,$pref,TRUE);
					$more = TRUE;
				}
				elseif($winner == -1 &&($mdata['nexttA'] == FALSE || $mdata['nexttB'] == FALSE))
				{
					self::SetTeam((int)$winner,(int)$mdata['nextm'],$mid,$db,$pref);
					if($mdata['nextlm'] && $loser != NULL)
						self::SetTeam((int)$loser,(int)$mdata['nextlm'],$mid,$db,$pref,TRUE);
					$more = TRUE;
				}
				$skips[] = $mid;
			}
			unset($mdata);
			if($more)
			{
				$done = TRUE;
				$sql = $sql1.' AND M.match_id NOT IN ('.implode(',',$skips).')'.$so;
				$updates = $db->GetArray($sql,array($bracket_id));
			}
			else
				break;
		}
		self::ScheduleMatches($mod,$bracket_id);
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
		$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2 ORDER BY displayorder';
		$allteams = $db->GetCol($sql,array($bracket_id));
		if($allteams == FALSE)
			return 'info_nomatch';
		$numteams = count($allteams);
//	tmtUtils()?
		list($min,$max) = $mod->GetLimits(Tourney::RRTYPE);
		if($numteams > $max || $numteams < $min)
			return 'err_value';
		shuffle($allteams);
		$played = array();
		foreach($allteams as $mdata)
			$played['T'.$mdata] = array(); //must be text key,to preserve in array_multisort()
		unset($allteams);

		$sql = 'SELECT * FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0';
		$stored = $db->GetArray($sql,array($bracket_id));
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
			$mid = self::ReserveMatches($db,$pref,$c);
			$sql = 'INSERT INTO '.$pref.'module_tmt_matches (match_id,bracket_id,teamA,teamB) VALUES (?,?,?,?)';
			foreach($matches as $A=>$B)
			{
				$db->Execute($sql,array($mid,$bracket_id,$A,$B));
				$mid++;
			}
			return TRUE;
		}
		self::ScheduleMatches($mod,$bracket_id);
		return 'info_nomatch';
	}

	/**
	MatchCommitted:
	@db: reference to database connection object
	@pref: database tables prefix
	@bracket_id: the bracket identifier
	@zone: bracket timezone, optional, default FALSE
	See also: tmtEditSetup::MatchExists() which is similar to this (except tests MRES)
	Returns: TRUE if any match in bracket @bracket_id is 'locked in'
	*/
	private function MatchCommitted(&$db,$pref,$bracket_id,$zone=FALSE)
	{
		$sql = 'SELECT 1 AS yes FROM '.$pref.
		'module_tmt_matches WHERE bracket_id=? AND status>='.Tourney::FIRM.' AND status!='.Tourney::ASOFT.
		' AND ((teamA IS NOT NULL AND teamA!=-1) OR (teamB IS NOT NULL AND teamB!=-1))';
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
			$dt = new DateTime('+'.Tourney::LEADHOURS.' hours',new DateTimeZone($zone));
			$sql = 'SELECT 1 AS yes FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0 AND status IN ('.Tourney::FIRM.','.Tourney::TOLD.','.Tourney::ASKED.','.Tourney::AFIRM.
			') AND playwhen IS NOT NULL AND playwhen < '.$dt->format('Y-m-d G:i:s').
			' AND ((teamA IS NOT NULL AND teamA != -1) OR (teamB IS NOT NULL AND teamB != -1))';
			$rs = $db->SelectLimit($sql,1,-1,array($bracket_id));
			if($rs && !$rs->EOF) //FIRM/TOLD/ASKED match(es) scheduled before min. leadtime from now
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

		if(self::MatchCommitted($db,$pref,$bracket_id,$zone))
		{
			if($type != Tourney::RRTYPE)
			{
				if(!is_array($tid))
					$tid = array($tid);
				foreach($tid as $one)
				{
					if(!self::RandomAdd($bracket_id,$tid))
						return FALSE;
				}
			}
			elseif(self::NextRRMatches($mod,$bracket_id) !== TRUE) //TODO add & schedule a match for $team_id
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
	
		if(self::MatchCommitted($db,$pref,$bracket_id,$zone))
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

			if($type == Tourney::RRTYPE)
			{
				$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND status<'.Tourney::MRES.' AND teamA'.$test;
				$db->Execute($sql,array($args));
				$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND status<'.Tourney::MRES.' AND teamB'.$test;
				$db->Execute($sql,array($args));
			}
			else
			{
				$args2 = $args;
				array_unshift($args2,$mod->Lang('teamgone'));
				$sql = 'UPDATE '.$pref.'module_tmt_matches SET teamA=-1,playwhen=NULL WHERE bracket_id=? AND teamB IS NULL AND teamA'.$test;
				$db->Execute($sql,array($args));
				$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=NULL,status='.Tourney::NOWIN.',score=? WHERE bracket_id=? AND status<'.Tourney::MRES.' AND teamB=-1 AND teamA'.$test;
				$db->Execute($sql,array($args2));
				$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=NULL,status='.Tourney::FORFA.',score=? WHERE bracket_id=? AND status<'.Tourney::MRES.' AND teamA'.$test;

				$db->Execute($sql,array($args2));
				$sql = 'UPDATE '.$pref.'module_tmt_matches SET teamB=-1,playwhen=NULL WHERE bracket_id=? AND teamA IS NULL AND teamB'.$test;
				$db->Execute($sql,array($args));
				$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=NULL,status='.Tourney::NOWIN.',score=? WHERE bracket_id=? AND status<'.Tourney::MRES.' AND teamA=-1 AND teamB'.$test;
				$db->Execute($sql,array($args2));
				$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=NULL,status='.Tourney::FORFB.',score=? WHERE bracket_id=? AND status<'.Tourney::MRES.' AND teamB'.$test;
				$db->Execute($sql,array($args2));
			}
			return TRUE;
		}
		else //fresh start
		{
			$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
			$db->Execute($sql,array($bracket_id));
			switch($type)
			{
			 case Tourney::DETYPE:
				return (self::InitDEMatches($mod,$bracket_id) === TRUE);
			 case Tourney::RRTYPE:
				return (self::NextRRMatches($mod,$bracket_id) === TRUE);
			 default:
			 //case Tourney::KOTYPE:
				return (self::InitKOMatches($mod,$bracket_id) === TRUE);
			}
		}
	}

	/**
	ConformNewResults:
	@bracket_id: the bracket identifier
	@type: enumerator of bracket type, KOTYPE etc
	@mid: match id, or array of match id's, to be processed
	@status: match status corresponding to non-array @mid, or array of match statuses (each member like $mid=>$status)
	For 'plan' or 'history' view match results processing.
	Adjust matches to reflect result of each match identified by @mid.
	If a status >= MRES is changed to another one, the winner (and loser
	if relevant) are used to update the teams in subsequent match(es).
	Or if a status >= MRES is changed to < MRES, the winner (and loser
	if relevant) are used to NULL relevant teams in subsequent match(es).
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
			$sql .= ' IN ('.$fillers.') AND flags=0 ORDER BY match_id';
			$args = $mid;
		}
		else
		{
			$sql .= '=? AND flags=0';
			$args = array($mid);
			$status = array($mid=>$status);
		}
		$matches = $db->GetAssoc($sql,$args);
		$sql = 'UPDATE '.$pref.'module_tmt_matches SET teamA=? WHERE bracket_id=? AND match_id>=? AND teamA=?';
		$sql2 = 'UPDATE '.$pref.'module_tmt_matches SET teamB=? WHERE bracket_id=? AND match_id>=? AND teamB=?';
		foreach($matches as $id=>$mdata)
		{
			if($mdata['status'] != $status[$id])
			{
				$newstat = (int)$status[$id];
				if($newstat >= Tourney::MRES)
				{
					switch($newstat)
					{
					 case Tourney::WONA:
					 case Tourney::FORFB:
						//ensure teamA is one of teams in nextm & beyond
						$args = array($mdata['teamA'],$bracket_id,(int)$mdata['nextm'],$mdata['teamB']);
						$db->Execute($sql,$args);
						$db->Execute($sql2,$args);
						if($type == Tourney::DETYPE)
						{
							//ensure teamB is one of teams in nextlm & beyond
							$args = array($mdata['teamB'],$bracket_id,(int)$mdata['nextlm'],$mdata['teamA']);
							$db->Execute($sql,$args);
							$db->Execute($sql2,$args);
						}
						break;
					 case Tourney::WONB:
					 case Tourney::FORFA:
						//ensure teamB is one of teams in nextm & beyond
						$args = array($mdata['teamB'],$bracket_id,(int)$mdata['nextm'],$mdata['teamA']);
						$db->Execute($sql,$args);
						$db->Execute($sql2,$args);
						if($type == Tourney::DETYPE)
						{
							//ensure teamA is one of teams in nextlm & beyond
							$args = array($mdata['teamA'],$bracket_id,(int)$mdata['nextlm'],$mdata['teamB']);
							$db->Execute($sql,$args);
							$db->Execute($sql2,$args);
						}
					break;
				 default:
					break;
					}
				}
				elseif((int)$mdata['status'] >= Tourney::MRES)
				{
					//ensure neither teamA or teamB is in nextm & beyond
					$args = array(NULL,$bracket_id,(int)$mdata['nextm'],$mdata['teamA']);
					$db->Execute($sql,$args);
					$args = array(NULL,$bracket_id,(int)$mdata['nextm'],$mdata['teamB']);
					$db->Execute($sql2,$args);
					if($type == Tourney::DETYPE)
					{
						$args = array(NULL,$bracket_id,(int)$mdata['nextlm'],$mdata['teamA']);
						$db->Execute($sql,$args);
						$args = array(NULL,$bracket_id,(int)$mdata['nextlm'],$mdata['teamB']);
						$db->Execute($sql2,$args);
					}
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
		$sql = 'SELECT COUNT(match_id) AS num FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0 AND status<'
		 .Tourney::ANON.' AND teamA IS NOT NULL AND teamB IS NOT NULL';
		return $db->GetOne($sql,array($bracket_id));
	}

}

?>
