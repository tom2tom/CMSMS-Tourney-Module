<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
class tmtRoundsKO
{
	/**
	LevelMax:
	@teamscount: No. of teams in the bracket (assumed KOMIN..KOMAX inclusive)
	Returns: max band value for KO comp with @teamscount participants
	*/
	function LevelMax($teamscount)
	{
		return ((log($teamscount,2)-0.0001)|0)+1; //ceil-equivalent
	}

	/**
	MatchLevel:
	@matches: array of ALL matches' data for a bracket, sorted by match_id
	@mid: identifier of match being processed, a key in @matches
	*/
	function MatchLevel($teamscount,$matches,$mid)
	{
		$mids = array_keys($matches);
		$offset = array_search($mid,$mids); //0-based difference between $mid and start of matches
		$i = ((log($teamscount,2)-0.0001)|0) + 1; //ceil = no. of levels in the comp
		$bc = pow(2,$i-1); //first round matches count
		$mcount = $bc - 1; //last round-1 match
		$lvl = 1;
		while ($mcount < $offset)
		{
			$bc /= 2;
			$mcount += $bc;
			$lvl++;
		}
		return $lvl;
	}

	/**
	MatchTeamID_Team:
	@mod: reference to current Tourney module
	@matches: reference to array of all matches' data from table query (so will zap internal pointer for upstream use)
	@mid: identifier of match being processed
	@not: optional enumerator of team which is already known, so don't find this
	Get identifier for a participant in match @mid, excluding team @not (if any)
	Returns: identifier string, including prior match no. if possible
	*/
	function MatchTeamID_Team(&$mod,&$matches,$mid,$not=FALSE)
	{
		foreach($matches as $id=>$mdata)
		{
			if($mdata['nextm'] == $mid && !($not && ($mdata['teamA'] == $not || $mdata['teamB'] == $not)))
				return $mod->Lang('numwinner',$mod->Lang('matchnum',$id));
		}
		return $mod->Lang('anonwinner');
	}

	/**
	MatchTeamID_Mid:
	@mod: reference to current Tourney module
	@matches: array of all matches' data from database query
	@mid: identifier of match being processed
	@not: optional enumerator of match which is already known, so don't find this
	Get identifier for a participant in match @mid, excluding match @not (if any)
	Returns: identifier string, including prior match no. if possible
	*/
	function MatchTeamID_Mid(&$mod,$matches,$mid,$not=FALSE)
	{
		foreach($matches as $id=>$mdata)
		{
			if($mdata['nextm'] == $mid && !($not && $id == $not))
				return $mod->Lang('numwinner',$mod->Lang('matchnum',$id));
		}
		return $mod->Lang('anonwinner');
	}

	/**
	NamedTeams:
	@mod: reference to current Tourney module
	@matches: reference to array of all matches' data from database query
	@mid: identifier of match being processed
	@not: optional enumerator of team which is already known, so don't find this
	Get identifier for one or both participants in match @mid, excluding team @not (if any)
	Returns: identifier string with one or two teamnames, or FALSE
	*/
	function NamedTeams(&$mod,&$matches,$mid,$not=FALSE)
	{
		foreach($matches as $id=>$mdata)
		{
			if($mdata['nextm'] == $mid && !($not && ($mdata['teamA'] == $not || $mdata['teamB'] == $not)))
			{
				if($mdata['teamA'] == -1 && $mdata['teamB'])
					return $mod->TeamName($mdata['teamB']);
				if($mdata['teamB'] == -1 && $mdata['teamA'])
					return $mdata['teamA'];
				if($mdata['teamA'] && $mdata['teamB'])
					return sprintf($mod->Lang('or_fmt',$mod->TeamName($mdata['teamA']),$mod->TeamName($mdata['teamB'])));
				else
					break;
			}
		}
		return FALSE; //$mod->Lang('anonwinner');
	}

	/**
	LevelName:
	@mod: reference to current Tourney module
	@bdata: reference to array of bracket-table data
	@level: enumerator of competition 'round' c.f. round 1, round 2 ...
	@levelmax: enumerator of the 'final' round in the competition
	Returns: string (possibly empty) or FALSE
	*/
	function LevelName(&$mod,&$bdata,$level,$levelmax)
	{
		$diff = $levelmax - $level;
		if($diff < 4 && $diff >= 0)
		{
			$fields = array('final','semi','quarter','eighth');
			$ret = trim($bdata[$fields[$diff]]);
		}
		else
			$ret = FALSE;
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
