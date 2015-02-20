<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
class tmtChartKO extends tmtChartBase
{
	private $rnd;

	function __construct()
	{
		$this->rnd = new tmtRoundsKO();
	}

	/**
	Layout:
	@params: reference to array of chart parameters set in parent::MakeChart()
	Setup box and chart layout parameters in parent::layout[] and parent::ldata[]
	*/
	function Layout(&$params)
	{
		extract($params);
/*get	$teamscount
		$gw includes margins
		$gh includes margins
		$tm chart margin
		$rm "
		$bm "
		$lm "
		$bh box content
		$bw "
		$bp box padding
		$blw box border width
		$bhm horz margin
		$bvm vert margin
*/
		$BC = ((log($teamscount,2)-0.0001)|0) + 1; //ceil-equivalent, bands count
		$R = ($BC > 2) ? $BC-1 : $BC;//reverse-direction at this level
		$TC = pow(2,$BC); //teams+byes in band 1
		$bl = $lm + $bhm; //inside margin
		$top = $tm + $bvm; //inside margin
		$xtra = ($blw+$bp)*2;
		$diffv = $bh + $xtra + $gh;
		$diffh = $bw + $xtra + $gw;
		$mid = 1;

		for($lvl=1; $lvl<=$BC; $lvl++)
		{
			$data = array();
			$start = pow(2,$lvl-1);
			$step = $start*2;
			for($i=$start; $i<$TC; $i+=$step)
			{
				$bt = $top + $diffv*($i-1)/2;
				$data[$mid] = array('bl'=>$bl,'bt'=>$bt); //inside margins
				$mid++;
			}
			$this->layout[$lvl] = $data;
			if($lvl < $R)
				$bl += $diffh;
			elseif($lvl > $R)
				$bl -= $diffh;
		}

		$this->ldata['turner'] = $R;
		$this->ldata['finalband'] = $BC;
		$item = reset($data);
		$wide = $item['bl'] + $bw + $xtra + $bhm + $rm;
		if($BC > 2)
			$wide += $gw/2;
		$this->ldata['width'] = (int)$wide;
		$item = end($this->layout[1]);
		$this->ldata['height'] = (int)($item['bt'] + $bh + $xtra + $bvm + $bm);
		$this->ldata['boxwidth'] = $bw + $xtra; //includes padding and borders, not margins
		$this->ldata['boxheight'] = $bh + $xtra; //ditto
	}

	/**
	Boxes:
	@bdata: reference to array of bracket-table data
	@db: reference to database connection object
	@titles optional, mode enum for type of titles to include in boxes:
	 0 for (printer-ready) no labels in unplayed matches
 	 1 for normal labels in all boxes (default)
	 2 for including match numbers in 'plan' mode
	Setup parent::layout[] parameters 'type' and 'text' for all chart boxes
	Returns: TRUE, or FALSE upon error
	*/
	function Boxes(&$bdata,&$db,$titles=1)
	{
		$bracket_id = (int)$bdata['bracket_id'];
		$pref = cms_db_prefix();
		//grab ALL team identifiers (including flagged deletions maybe needed for prior-match status)
		$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? ORDER BY team_id';
		$teams = $db->GetCol($sql,array($bracket_id));
		if(!$teams)
			return FALSE;
		$tc = count($teams);
		$names = array('-1'=>NULL); //byes treated specially
		foreach($teams as $tid)
			$names[$tid] = $this->mod->TeamName($tid);
		//$tc as-is not suitable for calculating levels
		$lvlmax = count($this->layout); //maximum level, for naming purposes
		$fmt = $this->mod->GetZoneDateFormat($bdata['timezone']).' '.$this->mod->GetPreference('time_format');
		$relations = $this->mod->ResultTemplates($bracket_id);

		$sql = 'SELECT * FROM '.$pref.'module_tmt_matches WHERE bracket_id=? ORDER BY match_id';
		$matches = $db->GetAssoc($sql,array($bracket_id));
		//don't use $matches internal pointer for iterating, downstream zaps that
		$allmids = array_keys($matches); //don't assume contiguous
		$mid = reset($allmids);
		//process all boxes
		foreach($this->layout as $lvl=>&$column)
		{
			foreach($column as &$row)
			{
				$mdata = &$matches[$mid]; //reference, content may be changed
				$tA = $mdata['teamA'];
				$tB = $mdata['teamB'];
				if($titles != 2) //not 'plan' mode
				{
					if($tA != FALSE && $tB != FALSE)
					{
						$nameA = (!empty($names[$tA])) ? $names[$tA] : FALSE;
						$nameB = (!empty($names[$tB])) ? $names[$tB] : FALSE;
						if($nameA && $nameB)
						{
							switch($mdata['status'])
							{
							case SOFT:
							case ASOFT:
								$type = 'nonf';
								//no break here
							case FIRM:
							case AFIRM:
								if($mdata['status'] == FIRM || $mdata['status'] == AFIRM)
									$type = 'firm';
								$rel = sprintf($relations['vs'],$nameA,$nameB);
								$at = ($mdata['place']) ? $mdata['place'] : '';
								if($mdata['playwhen'])
									$at .= ' '.date($fmt,strtotime($mdata['playwhen']));
								$text = $rel."\n".trim($at);
								break;
							case FORFB:
								if(!$mdata['score']) //no reason given
									$mdata['score'] = $bdata['forfeit'];
							case WONA:
								$type = ($lvl < $lvlmax) ? 'done' : 'final';
								$rel = sprintf($relations['def'],$nameA,$nameB);
								$text = $rel."\n".trim($mdata['score']);
								break;
							case FORFA:
								if(!$mdata['score'])
									$mdata['score'] = $bdata['forfeit'];
							case WONB:
								$type = ($lvl < $lvlmax) ? 'done' : 'final';
								$rel = sprintf($relations['def'],$nameB,$nameA);
								$text = $rel."\n".trim($mdata['score']);
								break;
							case NOWIN:
								$type = 'done';
								$rel = sprintf($relations['vs'],$nameA,$nameB);
								$text = $rel."\n".$bdata['nomatch'];
								break;
							default:
								$type = 'deflt';
								$rel = sprintf($relations['vs'],$nameA,$nameB);
								$text = $rel."\n".trim($mdata['score']);
								break;
							}
						}
						elseif($nameA)
						{
							$type = 'done';
							$text = "\n".$nameA."\n".$bdata['bye'];
						}
						elseif($nameB)
						{
							$type = 'done';
							$text = "\n".$nameB."\n".$bdata['bye'];
						}
						else //neither teamname known
						{
							$type = 'deflt';
							$text = "\n".$this->mod->Lang('err_match');
						}
					}
					else //either or both teams unknown
					{
						switch($mdata['status'])
						{
						 case SOFT:
						 case ASOFT:
							$type = 'nonf';
							//no break here
						 case FIRM:
						 case AFIRM:
							if($mdata['status'] == FIRM || $mdata['status'] == AFIRM)
								$type = 'firm';
							if(!($tA || $tB) || $tA == '-1' || $tB == '-1')
								$rel = ($titles == 1)?"\n".$this->rnd->LevelName($this->mod,$bdata,$lvl,$lvlmax):'';
							elseif($tA)
							{
								$round = $this->rnd->LevelName($this->mod,$bdata,$lvl-1,$lvlmax);
								$other = $this->mod->Lang('numwinner',$round);
								$rel = sprintf($relations['vs'],$names[$tA],$other);
							}
							else
							{
								$round = $this->rnd->LevelName($this->mod,$bdata,$lvl-1,$lvlmax);
								$other = $this->mod->Lang('numwinner',$round);
								$rel = sprintf($relations['vs'],$other,$names[$tB]);
							}
							break;
						 default:
							$type = 'deflt';
							$rel = ($titles == 1)?"\n".$this->rnd->LevelName($this->mod,$bdata,$lvl,$lvlmax):'';
							break;
						}
						$at = ($mdata['place']) ? $mdata['place'] : '';
						if($mdata['playwhen'])
							$at .= ' '.date($fmt,strtotime($mdata['playwhen']));
						$text = $rel."\n".trim($at);
					}
				}
				else //plan mode
				{
					if ($mdata['status'] < MRES)
					{
						switch($mdata['status'])
						{
						 case SOFT:
						 case ASOFT:
							$type = 'nonf';
							break;
						 case FIRM:
						 case AFIRM:
							$type = 'firm';
							break;
						 default:
							$type = 'deflt';
							break;
						}
					}
					else
						$type = 'done';
					$rel = $this->mod->Lang('matchnum',$mid)."\n";
					$anon = TRUE;
					if($tA != FALSE && $tA != -1)
					{
						$anon = FALSE;
						$rel .= $names[$tA]."\n";
						if($tB != FALSE)
							$rel .= (($tB != -1)?$names[$tB]:$bdata['bye']);
						else
							$rel .= $this->rnd->MatchTeamID_Team($this->mod,$matches,$mid,$tA);
					}
					elseif($tB != FALSE && $tB != -1)
					{
						$anon = FALSE;
						if ($tA == -1)
							$rel .= $names[$tB]."\n".$bdata['bye'];
						else
							$rel .= $this->rnd->MatchTeamID_Team($this->mod,$matches,$mid,$tB).
							"\n".$names[$tB];
					}
					if($anon) //both teams unknown or bye
					{
						$one = $this->rnd->MatchTeamID_Mid($this->mod,$matches,$mid); //sets $matches internal ptr
						$here = key($matches);
						if($here)
							$rel .= $one."\n".$this->rnd->MatchTeamID_Mid($this->mod,$matches,$mid,$here-1);
						else
							$rel .= $this->rnd->LevelName($this->mod,$bdata,$lvl,$lvlmax);
					}
					$at = ($mdata['place']) ? $mdata['place'] : '';
					if($mdata['playwhen'])
						$at .= ' '.date($fmt,strtotime($mdata['playwhen']));
					$text = $rel."\n".trim($at);
				}
				unset($mdata);
				$row['type'] = $type;
				$row['text'] = $text;
				$mid = next($allmids);
			}
			unset($row);
		}
		unset($column);
		return TRUE;
	}

	/**
	Draw:
	@params: reference to array of variables passed from parent::MakeChart(),
		members are 'pdf','gw','lw','lc','ls','blw','bp','boxstyles'
	Draw the chart
	*/
	function Draw(&$params)
	{
		extract($params);
		unset($params);
		$bh = $this->ldata['boxheight']; //includes padding and borders
		$bw = $this->ldata['boxwidth']; //ditto
		$offs = $blw + $bp;
		$xtra = $offs * 2;
		$tch = (int)(($bh-$xtra)/4); //text cell height
		$tcw = $bw - $xtra;
		$R = $this->ldata['turner'];
		$dashjoins = FALSE;
		$dojoins = TRUE;
		switch($ls)
		{
		 case 'dotted':
		 case 'dashed':
			$dashjoins = TRUE;
			break;
		 case 'none':
		 case 'hidden':
			$dojoins = FALSE;
		 default:
			break;
		}

		foreach($this->layout as $lvl=>&$column)
		{
			$first = reset($column);
			$x = floatval($first['bl']); //inside margin
			if($dojoins)
			{
				//draw joins first
				$nx = $x + $bw + $blw;
				$joins = array();
				foreach($column as &$row)
				{
					if (empty($row['type']) || $row['type'] != 'hide')
						$joins[] = array($nx,floatval($row['bt'])+$bh/2); //['bt'] inside margin
				}
				unset($row);

				$pdf->SetLineWidth($lw);
				$pdf->SetDrawColor($lc[0],$lc[1],$lc[2]);
				switch($ls)
				{
				 case 'dotted':
					$pdf->SetLineDash($this->dots,$blw*3,TRUE); //assume all lines start from box border
					break;
				 case 'dashed':
					$pdf->SetLineDash($this->dashes,$blw*3);
				 default:
					break;
				}
				if($lvl < $R)
				{
					$cv = count($joins);
					for($c=0;$c<$cv-1;$c+=2)
					{
						$x1 = $joins[$c][0];
						$y1 = $joins[$c][1];
						$y2 = $joins[$c+1][1];
						$w = $x1+$gw/2-$lw;
						$pdf->Line($x1,$y1,$w,$y1);
						$pdf->Line($w,$y1,$w,$y2);
						$pdf->Line($x1,$y2,$w,$y2);
						$y3 = ($y1+$y2)/2;
						$nx = $x1 + $gw - $blw*2;
						$pdf->Line($nx,$y3,$w,$y3);
					}
				}
				elseif($lvl == $R && $lvl < $this->ldata['finalband'])
				{
					$x1 = floatval($first['bl']+$bw+($gw-$lw)/2);
					$y1 = floatval($first['bt']+$bh/2);
					$last = end($column);
					$y2 = floatval($last['bt']+$bh/2);
					$x2 = $x1-($gw-$lw)/2 + $blw;;
					$pdf->Line($x2,$y1,$x1,$y1);
					$pdf->Line($x1,$y1,$x1,$y2);
					$pdf->Line($x2,$y2,$x1,$y2);
				}
				elseif($lvl > $R)
				{
					$x1 = floatval($first['bl']+$bw+($gw-$lw)/2);
					$y1 = floatval($first['bt']+$bh/2);
					$x2 = $x1-($gw-$lw)/2 + $blw;
					$pdf->Line($x2,$y1,$x1,$y1);
				}
				unset($joins);
				if($dashjoins)
					$pdf->SetLineDash(FALSE);
			}

			foreach($column as &$row)
			{
				$type = (!empty($row['type'])) ? $row['type'] : 'deflt';
				if($type == 'hide')
					continue;
				$style = $boxstyles[$type];
				if($style['fill'])
				{
					$c = $style['fill'];
					$pdf->SetFillColor($c[0],$c[1],$c[2]);
				}
				else
					$c = FALSE;
				//$mode default = border,no fill DF = border&fill F = no border D could be for no fill or border?
				switch($style['bs'])
				{
				 case 'dotted':
				 	$dashed = TRUE;
					$pdf->SetLineDash($this->dots,0,TRUE);
					$mode = ($c) ? 'DF':'';
					break;
				 case 'dashed':
				 	$dashed = TRUE;
					$pdf->SetLineDash($this->dashes);
					$mode = ($c) ? 'DF':'';
					break;
				 case 'none':
				 case 'hidden':
					$dashed = FALSE;
				 	$mode = 'F';
					break;
				 default:
					$dashed = FALSE;
					$mode = ($c) ? 'DF':'';
					break;
				}
				if($mode != 'F')
				{
					$pdf->SetLineWidth($blw);
					$c = $style['bc'];
					$pdf->SetDrawColor($c[0],$c[1],$c[2]);
				}
				$y = floatval($row['bt']); //inside margin
				$pdf->Rect($x,$y,$bw,$bh,$mode);
				if($dashed)
					$pdf->SetLineDash(FALSE);
				if(!empty($row['text']))
				{
					$pdf->SetFont($style['font'],$style['attr'],$style['size']);
					$c = $style['color'];
					$pdf->SetTextColor($c[0],$c[1],$c[2]);
					$pdf->SetXY($x+$offs,$y+$offs);
					$pdf->MultiCell($tcw,$tch,$row['text'],0,'C',FALSE);
				}
			}
			unset($row);
		}
		unset($column);
	}
}

?>
