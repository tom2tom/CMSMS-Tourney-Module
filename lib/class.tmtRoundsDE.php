<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
class tmtRoundsDE
{
	/* *
	LevelVals:
	@teamscount: No. of teams in the bracket
	Mid is first band of losers' draw, max id band of final.
	Returns: array with mid,max band values for DE comp with @teamscount participants.
	*/
/*	function LevelVals($teamscount)
	{
		//no. of levels in the winners' draw incl. semi-final
		$i = ((log($teamscount,2)+0.0001)|0) + 1; //ceil-equivalent
		return array($i + 1, $i*3 - 1); //mid=i+1, max=3i-1
	}
*/
	/**
	MatchLevel:
	@teamscount: No. of teams in the bracket
	@matches: array of all matches' data for a bracket,
	 sorted by match_id hence increasing level-order
	@mid: identifier of match being processed, a key in @matches
	*/
	function MatchLevel($teamscount,$matches,$mid)
	{
		$ids = array_keys($matches);
		$offset = array_search($mid,$ids);
		//no. of levels in the winners' draw incl. semi-final
		$i = ((log($teamscount,2)+0.0001)|0) + 1; //ceil-equivalent
		$bc = pow(2,$i); //winners' draw matches count + 1
		if ($offset < $bc)
		{
			//winners' draw to semi-final
			$bc /= 2; //1st round matches count
			$mcount = $bc - 1; //last match in 1st round
			$lvl = 1;
			while($mcount < $offset)
			{
				$bc /= 2;
				$mcount += $bc;
				$lvl++;
			}
			return $lvl;
		}
		$mcount = $bc - 1; //offset of 1st match in 1st losers' draw round
		$bc /= 2; //1st + 2nd losers' draw matches count
		$mcount += $bc - 1; //last match in 2nd losers' draw round
		$lvl = $i + 1; //1st losers' draw round
		while($mcount < $offset)
		{
			$bc /= 2;
			$mcount += $bc;
			$lvl += 2;
		}
		if ($bc > 1 && $mcount < ($offset + $bc/2))
			$lvl++;
		return $lvl;
	}

	/**
	MatchTeamID_Team:
	@mod: reference to current Tourney module
	@bdata: reference to array of brackets-table data
	@teamscount: no. of teams in the bracket
	@matches: reference to array of all matches' data from table query (so we zap internal pointer for upstream array)
	@mid: identifier of match being processed
	@level: bracket 'level' c.f. round 1, round 2 ...
	@not: optional, enumerator of team which is already known (or NULL) so don't find this
	Returns: team identifier string, including match number if possible, otherwise generic
	*/
	function MatchTeamID_Team(&$mod,&$bdata,$teamscount,&$matches,$mid,$level,$not=FALSE)
	{
		$i = ((log($teamscount,2)+0.0001)|0) + 1; //ceil-equivalent
		$BM = $i + 1;
		$BF = $i*3 - 1;
		if($level == $BM) //first losers-draw band
		{
			//look for previous loser
			foreach($matches as $id=>$mdata)
			{
				if($mdata['nextlm'] == $mid && !($not && ($mdata['teamA'] == $not || $mdata['teamB'] == $not)))
				{
					if($mdata['teamA'] != -1 && $mdata['teamB'] != -1)
						return $mod->Lang('numloser',$mod->Lang('matchnum',$id));
					return $bdata['bye'];
				}
			}
			return $mod->Lang('anonloser');
		}
		elseif($level < $BM || $level == $BF || ($level-$BM)%2==0)
		{
		/*	winners-draw band, including semi-final
			losers-draw band where match(es) have only previous winners from losers draw
			final */
			//look for previous winner
			foreach($matches as $id=>$mdata)
			{
				if($mdata['nextm'] == $mid && !($not && ($mdata['teamA'] == $not || $mdata['teamB'] == $not)))
					return $mod->Lang('numwinner',$mod->Lang('matchnum',$id));
			}
			return $mod->Lang('anonwinner');
		}
		else //losers-draw band where match(es) have previous loser and previous winner
		{
			//look for previous loser or winner
			foreach($matches as $id=>$mdata)
			{
				if($mdata['nextlm'] == $mid && !($not && ($mdata['teamA'] == $not || $mdata['teamB'] == $not)))
				{
					if(!($mdata['teamA'] == -1 || $mdata['teamB'] == -1))
						return $mod->Lang('numloser',$mod->Lang('matchnum',$id));
					return $bdata['bye'];
				}
				if($mdata['nextm'] == $mid && !($not && ($mdata['teamA'] == $not || $mdata['teamB'] == $not)))
					return $mod->Lang('numwinner',$mod->Lang('matchnum',$id));
			}
			return $mod->Lang('anonother');
		}
	}

	/**
	MatchTeamID_Mid:
	@mod: reference to current Tourney module
	@bdata: reference to array of brackets-table data
	@teamscount: no. of teams in the bracket
	@matches: reference to array of all matches' data from table query
	@mid: identifier of match being processed
	@level: bracket 'level' c.f. round 1, round 2 ...
	@not: optional, enumerator of match which is already known, so don't find this
	Returns: team identifier string, including match number if possible, otherwise generic
	*/
	function MatchTeamID_Mid(&$mod,&$bdata,$teamscount,&$matches,$mid,$level,$not=FALSE)
	{
		$i = ((log($teamscount,2)+0.0001)|0) + 1; //ceil-equivalent
		$BM = $i + 1;
		$BF = $i*3 - 1;
		if($level == $BM) //first losers-draw band
		{
			foreach($matches as $id=>$mdata)
			{
				if($mdata['nextlm'] == $mid && (!$not || $id != $not))
				{
					if(!($mdata['teamA'] == -1 || $mdata['teamB'] == -1))
						return $mod->Lang('numloser',$mod->Lang('matchnum',$id));
					return $bdata['bye'];
				}
			}
			return $mod->Lang('anonloser');
		}
		elseif($level < $BM || $level == $BF || ($level-$BM)%2==0)
		{
			//look for previous winner
			foreach($matches as $id=>$mdata)
			{
				if($mdata['nextm'] == $mid && (!$not || $id != $not))
					return $mod->Lang('numwinner',$mod->Lang('matchnum',$id));
			}
			return $mod->Lang('anonwinner');
		}
		else //losers-draw band where match(es) have previous loser and previous winner
		{
			foreach($matches as $id=>$mdata)
			{
				if($mdata['nextlm'] == $mid && (!$not || $id != $not))
				{
					if($mdata['teamA'] != -1 || $mdata['teamB'] != -1)
						return $mod->Lang('numloser',$mod->Lang('matchnum',$id));
					return $bdata['bye'];
				}
				if($mdata['nextm'] == $mid && (!$not || $id != $not))
				{
					if($mdata['teamA'] != -1 || $mdata['teamB'] != -1)
						return $mod->Lang('numwinner',$mod->Lang('matchnum',$id));
					return $bdata['bye'];
				}
			}
			return $mod->Lang('anonother');
		}
	}

	/**
	NamedTeams:
	@mod: reference to current Tourney module
	@teamscount: no. of teams in the bracket
	@matches: reference to array of all matches' data from database query
	@mid: identifier of match being processed
	@level: bracket 'level' c.f. round 1, round 2 ...
	@not: optional enumerator of team which is already known (or NULL), so don't find this
	Get identifier for one or both participants in match @mid, excluding team @not (if any)
	Returns: identifier string with one or two teamnames, or FALSE
	*/
	function NamedTeams(&$mod,$teamscount,&$matches,$mid,$level,$not=FALSE)
	{
		$i = ((log($teamscount,2)+0.0001)|0) + 1; //ceil-equivalent
		$BM = $i + 1;
		$BF = $i*3 - 1;
		if($level == $BM) //first losers-draw band
		{
			foreach($matches as $mdata)
			{
				if($mdata['nextlm'] == $mid && !($not && ($mdata['teamA'] == $not || $mdata['teamB'] == $not)))
				{
					if($mdata['teamA'] && ($mdata['teamB'] == -1 || $mdata['teamB'] == FALSE))
						return $mod->TeamName($mdata['teamA']);
					if($mdata['teamB'] && ($mdata['teamA'] == -1 || $mdata['teamA'] == FALSE))
						return $mod->TeamName($mdata['teamB']);
					if($mdata['teamA'] && $mdata['teamB'])
						return sprintf($mod->Lang('or_fmt',$mod->TeamName($mdata['teamA']),$mod->TeamName($mdata['teamB'])));
					else
						break; //return $mod->Lang('anonwinner');
				}
			}
		}
		elseif($level < $BM || $level == $BF || ($level-$BM)%2==0)
		{
			//look for previous winner
			foreach($matches as $mdata)
			{
				if($mdata['nextm'] == $mid && !($not && ($mdata['teamA'] == $not || $mdata['teamB'] == $not)))
				{
					if($mdata['teamA'] && ($mdata['teamB'] == -1 || $mdata['teamB'] == FALSE))
						return $mod->TeamName($mdata['teamA']);
					if($mdata['teamB'] && ($mdata['teamA'] == -1 || $mdata['teamA'] == FALSE))
						return $mod->TeamName($mdata['teamB']);
					if($mdata['teamA'] && $mdata['teamB'])
						return sprintf($mod->Lang('or_fmt',$mod->TeamName($mdata['teamA']),$mod->TeamName($mdata['teamB'])));
					else
						break; //return $mod->Lang('anonwinner');
				}
			}
		}
		else //losers-draw band where match(es) have previous loser and previous winner
		{
			foreach($matches as $mdata)
			{
				if($mdata['nextlm'] == $mid || $mdata['nextm'] == $mid)
				{
					if(!($not && ($mdata['teamA'] == $not || $mdata['teamB'] == $not)))
					{
						if($mdata['teamA'] && ($mdata['teamB'] == -1 || $mdata['teamB'] == FALSE))
							return $mod->TeamName($mdata['teamA']);
						if($mdata['teamB'] && ($mdata['teamA'] == -1 || $mdata['teamA'] == FALSE))
							return $mod->TeamName($mdata['teamB']);
						if($mdata['teamA'] && $mdata['teamB'])
							return sprintf($mod->Lang('or_fmt',$mod->TeamName($mdata['teamA']),$mod->TeamName($mdata['teamB'])));
						else
							break; //return $mod->Lang('anonwinner');
					}
				}
			}
		}
		return FALSE;
	}

	/**
	AnonLevelName:
	@mod: reference to current Tourney module
	@bdata: reference to array of bracket-table data
	@teamscount: no. of teams in the bracket
	@level: bracket 'level' c.f. round 1, round 2 ...
	Returns: team identifier string like 'Round N winner' where 'Round N' represents @level - 1
	*/
	private function AnonLevelName(&$mod,&$bdata,$teamscount,$level)
	{
		$i = ((log($teamscount,2)+0.0001)|0) + 1; //ceil-equivalent
		$BM = $i + 1;
		$BF = $i*3 - 1;
		$prev = ($level == $BM) ? 1:$level-1;
		$name = $this->LevelName($mod,$bdata,$teamscount,$prev);
		if($level == $BM) //first losers'-draw band
			$key = 'numloser';
		elseif($level < $BM || $level == $BF || ($level-$BM)%2==0)
			$key = 'numwinner';
		else //losers'-draw band where match(es) have previous loser and previous winner
			$key = 'numother';

		return $mod->Lang($key,$name);
	}

	/**
	LevelName:
	@mod: reference to current Tourney module
	@bdata: reference to array of bracket-table data
	@teamscount: no. of teams in the bracket
	@level: bracket 'level' c.f. round 1, round 2 ...
	Returns: round identifier string (possibly empty) or FALSE
	*/
	function LevelName(&$mod,&$bdata,$teamscount,$level)
	{
		$i = ((log($teamscount,2)+0.0001)|0) + 1; //ceil-equivalent
		$BM = $i + 1;
		$ret = FALSE;
		if($level < $BM) //in winners draw
		{
			$diff = $BM - $level;
			if($diff == 1)
				$ret = trim($bdata['semi']);
		}
		else
		{
			$diff = $i*3 - 1 - $level; //offset from final
			if($diff < 2 && $diff >= 0)
			{
				$fields = array('final','semi'); //lower-round specific names nonsense for DE comp
				$ret = trim($bdata[$fields[$diff]]);
			}
//			$level = $level - $BM + 2;
		}
		if(!$ret && $bdata['roundname'])
		{
			$name = trim($bdata['roundname']);
			if($name)
			{
				$fmt = str_replace('%r',$name,$mod->Lang('round_fmt'));
				$ret = sprintf($fmt,$level);
			}
		}
		return $ret;
	}
}
?>
