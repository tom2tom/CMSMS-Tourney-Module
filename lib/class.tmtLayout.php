<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
NOTE this class is not suited for static method-calling
*/

class tmtLayout
{
	private $chartsize = FALSE;	//array or FALSE

	/**
	GetChart:
	@mod reference to module object
	@bdata reference to array of brackets-table data
	@stylefile name of .css file to use instead of logged data (optional, default FALSE)
	@titles optional, mode enum for type of titles to include in boxes:
	 0 for (printer-ready) no labels in unplayed matches
 	 1 for normal labels in all boxes (default)
	 2 for including match numbers in 'plan' mode
	Construct bracket match status data in chart format.
	If needed, the file containing the bracket chart is created or refreshed, and saved
	Returns: array (path of chart file,'') or array(FALSE,lang key for error message)
	*/
	function GetChart(&$mod,&$bdata,$stylefile=FALSE,$titles=1)
	{
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$chartfile = $mod->ChartImageFile($bdata['bracket_id'],FALSE);
		if ($bdata['chartbuild'] || !file_exists($chartfile))
		{
			//chart needs to be reconstructed
			switch ($bdata['type'])
			{
			 case DETYPE:
				$cht = new tmtChartDE();
				break;
			 case RRTYPE:
				$cht = new tmtChartRR();
				break;
			 default:
//			 case KOTYPE:
				$cht = new tmtChartKO();
				break;
			}

			$res = $cht->MakeChart($mod,$bdata,$chartfile,$stylefile,$titles);
			if($res === TRUE)
			{
				//record sizes for upstream usage
				$this->chartsize = $cht->ChartSize();
				//no rebuilds until further notice
				$sql = 'UPDATE '.$pref.'module_tmt_brackets SET chartbuild=0 WHERE bracket_id=?';
				$db->Execute($sql,array($bdata['bracket_id']));
				$bdata['chartbuild'] = 0;
			}
			else
				return array(FALSE,$res); //send back the error-key
		}
		else
		{
			//rough approximation
		 	$sql = 'SELECT COUNT(1) AS num FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2';
			$num = $db->GetOne($sql,array($bdata['bracket_id']));
			switch ($bdata['type'])
			{
			 case RRTYPE:
				$ht = $num * 50;
				$wd = ($num-1) * 130;
				break;
			 case DETYPE:
			 	$BC = ((log($num,2)-0.0001)|0) + 1; //last band in losers' draw
				$ht = pow(2,$BC) * 1.5 * 50;
				$wd = ($BC*2 - 1) * 130; //cols no. = BF-(BC+1)+1, BF = 3BC-1
			 	break;
			 default:
			 	$BC = ((log($num,2)-0.0001)|0) + 1; //final band
				$ht = pow(2,$BC) * 50;
				$wd = $BC * 130;
			 	break;
			}
			//TODO get actual dimensions of saved $chartfile
			$this->chartsize = array('height'=>$ht+30,'width'=>$wd+30);
		}
		return array($chartfile,'');
	}

	/**
	GetChartSize:
	Get bracketchart dimensions (in points, not pixels)
	Returns: array - height,width or 0,0
	*/
	function GetChartSize()
	{
		if ($this->chartsize !== FALSE)
			return array($this->chartsize['height'],$this->chartsize['width']);
		else
			return array(0,0);
	}

	/**
	GetList:
	@mod: reference to module object
	@bdata: reference to array of brackets-table data
	@front: optional, whether list is for frontend display, default TRUE
	Get bracket match status data in list format
	Returns: array of match descriptor strings, or message-string key
		if nothing to report or error happens
	*/
	function GetList(&$mod,&$bdata,$front=TRUE)
	{
		$bracket_id = (int)$bdata['bracket_id'];
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		//get all matches, regardless of status
		$sql = 'SELECT * FROM '.$pref.
			'module_tmt_matches WHERE bracket_id=? ORDER BY match_id';
		$matches = $db->GetAssoc($sql,array($bracket_id));
		if ($matches == FALSE)
			return 'nomatch';
		if($bdata['type'] != RRTYPE)
		{
			$sql = 'SELECT COUNT(1) AS num FROM '.$pref.
				'module_tmt_teams WHERE bracket_id=? AND flags != 2';
			$tc = $db->GetOne($sql,array($bracket_id));
		}
		switch($bdata['type'])
		{
		 case RRTYPE:
			$anon = $mod->Lang('anonother');
		 	break;
		 case DETYPE:
			if ($tc < DEMIN || $tc > DEMAX)
				return 'err_value';
			$rnd = new tmtRoundsDE();
			break;
		 default:
			if ($tc < KOMIN || $tc > KOMAX)
				return 'err_value';
			$rnd = new tmtRoundsKO();
			$levelmax = $rnd->LevelMax($tc);
		 	break;
		}
		$fmt = $bdata['atformat'];
		if(!$fmt)
			$fmt = $mod->GetZoneDateFormat($bdata['timezone']).' '.$mod->GetPreference('time_format');
		$dt = new DateTime('now',new DateTimeZone($bdata['timezone']));
		$relations = $mod->ResultTemplates($bdata['bracket_id'],FALSE);
		$showrows = array();
		foreach($matches as $mid=>$mdata)
		{
			if($mdata['status'] == 0)
				continue; //filter out the matches of no interest here
			//bye(s)?
/*		if ($mdata['teamA'] == -1)
			{
				if (!($front || $mdata['teamB'] == -1))
					$showrows[] = $mod->TeamName($mdata['teamB']).' '.$bdata['bye'];
			}
			else if ($mdata['teamB'] == -1)
			{
				if (!$front)
					$showrows[] = $mod->TeamName($mdata['teamA']).' '.$bdata['bye'];
			}
			else
*/
			if ($mdata['teamA'] != -1 && $mdata['teamB'] != -1)
			{
				$tA = ($mdata['teamA']) ? $mod->TeamName($mdata['teamA']) : FALSE;
				$tB = ($mdata['teamB']) ? $mod->TeamName($mdata['teamB']) : FALSE;
				if ($mdata['status'] < MRES)
				{
					if ($mdata['playwhen'])
					{
						$dt->modify($mdata['playwhen']);
						$at = ', '.date($fmt,$dt->getTimestamp());
						if ($mdata['place'] != NULL)
							$at .= ', '.$mdata['place'];
					}
					elseif ($mdata['place'])
						$at = $mdata['place'];
					else
						$at = '';
				}

				switch ($mdata['status'])
				{
				 case NOTYET:
				 case SOFT:
				 case FIRM:
				 case TOLD:
				 	if($tA && $tB)
					{
						$str = sprintf($relations['vs'],$tA,$tB).$at;
						$showrows[] = $str;
						break;
					}
				 //no break here
				 case ASOFT:
				 case AFIRM:
				 	if(!($tA || $tB || $mdata['playwhen']))
						break;
//						$str = $mod->Lang('anonanon'); //TODO AorB vs CorD, or relevant roundname
//					else
//					{
						switch($bdata['type'])
						{
						 case KOTYPE:
							if($tA)
							{
								if($tB)
									$prev = $tB;
								else
								{
									$prev = $rnd->NamedTeams($mod,$matches,$mid,$mdata['teamA']);
									if(!$prev)
									{
									 	$level = $rnd->MatchLevel($tc,$matches,$mid);
										$prev = $mod->Lang('numwinner',$rnd->LevelName($mod,$bdata,$level-1,$levelmax));
									}
								}
							}
							elseif($tB)
							{
								$prev = $rnd->NamedTeams($mod,$matches,$mid,$mdata['teamB']);
								if(!$prev)
								{
								 	$level = $rnd->MatchLevel($tc,$matches,$mid);
									$prev = $mod->Lang('numwinner',$rnd->LevelName($mod,$bdata,$level-1,$levelmax));
								}
							}
							else
							{
							 	$level = $rnd->MatchLevel($tc,$matches,$mid);
								$name = $rnd->LevelName($mod,$bdata,$level-1,$levelmax);
							}
						 	break;
						 case DETYPE:
							if($tA)
							{
								if($tB)
									$prev = $tB;
								else
								{
								 	$level = $rnd->MatchLevel($tc,$matches,$mid);
									$prev = $rnd->NamedTeams($mod,$tc,$matches,$mid,$level,$mdata['teamA']);
									if(!$prev)
										$prev = $rnd->AnonLevelName($mod,$bdata,$tc,$level);
								}
							}
							elseif($tB)
							{
							 	$level = $rnd->MatchLevel($tc,$matches,$mid);
								$prev = $rnd->NamedTeams($mod,$tc,$matches,$mid,$level,$mdata['teamB']);
								if(!$prev)
									$prev = $rnd->AnonLevelName($mod,$bdata,$tc,$level);
							}
						 	else
							{
							 	$level = $rnd->MatchLevel($tc,$matches,$mid);
								$name = $rnd->AnonLevelName($mod,$bdata,$tc,$level);
							}
							break;
						 case RRTYPE:
							$prev = $anon;
							$name = '';
							break;
						 default:
							return 'err_value';
						}
						if($tA)
							$str = sprintf($relations['vs'],$tA,$prev);
						elseif($tB)
							$str = sprintf($relations['vs'],$prev,$tB);
						else
							$str = $name;
//					}
					$str .= $at;
					$showrows[] = $str;
				 	break;
				 case WONA:
					$showrows[] = sprintf($relations['def'],$tA,$tB).' '.$mdata['score'];
					break;
				 case WONB:
					$showrows[] = sprintf($relations['def'],$tB,$tA).' '.$mdata['score'];
					break;
				 case FORFA:
				 	$str = ($mdata['score']) ? $mdata['score'] : $bdata['forfeit']; //maybe a reason
					$showrows[] = sprintf($relations['def'],$tB,$tA).' '.$str;
					break;
				 case FORFB:
				 	$str = ($mdata['score']) ? $mdata['score'] : $bdata['forfeit']; 
					$showrows[] = sprintf($relations['def'],$tA,$tB).' '.$str;
					break;
				 case MTIED:
					$showrows[] = sprintf($relations['tied'],$tA,$tB).' '.$mdata['score'];
					break;
				 case NOWIN:
					$showrows[] = sprintf($relations['nomatch'],$tA,$tB);
					break;
				 default:
					$showrows[] = sprintf($relations['vs'],$tA,$tB).' '.$mdata['score'];
				}
			}
		}
		if($showrows)
			return $showrows;
		return 'nomatch';
	}
}

?>
