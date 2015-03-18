<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
class tmtChartRR extends tmtChartBase
{
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
		$LC = $teamscount-1; //no. of columns to display
		$bl = (int)($lm + $gw/2 + $bhm); //inside margin
		$top = $tm + $bvm; //inside margin
		$xtra = ($blw+$bp)*2;
		$diffv = $bh + $xtra + $gh;
		$diffh = $bw + $xtra + $gw;

		for($col=1;$col<=$LC;$col++)
		{
			$data = array();
			$stop = $teamscount-$col+1;
			for($i=1; $i<$stop; $i++)
			{
				$bt = $top + $diffv*($i-1);
				$data[$col+$i] = array('bl'=>$bl,'bt'=>$bt); //inside margins
			}
			$this->layout[$col] = $data;
			$bl += $diffh;
		}

		$item = reset($data);
		$this->ldata['width'] = (int)($item['bl'] + $bw + $xtra + $bhm + $rm);
		$item = end($this->layout[1]);
		$this->ldata['height'] = (int)($item['bt'] + $bh + $xtra + $bvm + $bm);
		$this->ldata['boxwidth'] = $bw + $xtra; //includes padding and borders, not margins
		$this->ldata['boxheight'] = $bh + $xtra; //ditto
	}

	/**
	Boxes:
	@bdata: reference to array of bracket-table data for the bracket being processed
	@db: reference to database connection object
	@titles optional, mode enum for type of titles to include in boxes:
	 0 for (printer-ready) no labels in unplayed matches
 	 1 for normal labels in all boxes (default)
	 2 for including (real and fake) match numbers in 'plan' mode
	Setup parent::layout[] parameters 'type' and 'text' for all chart boxes
	Returns: TRUE,or FALSE if no teams in the bracket
	*/
	function Boxes(&$bdata,&$db,$titles=1)
	{
		$pref = cms_db_prefix();
		//grab all team identifiers (including flagged deletions maybe needed for prior-match status)
		$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? ORDER BY displayorder';
		$teams = $db->GetCol($sql,array($bdata['bracket_id']));
		if($teams == FALSE)
			return FALSE;
		$tc = count($teams);
		$order = 1;
		$names = array();
		foreach($teams as $tid)
		{
			$names[$order] = $this->mod->TeamName($tid);
			$order++;
		}
		$fmt = $bdata['atformat'];
		if(!$fmt)
			$fmt = $this->mod->GetZoneDateFormat($bdata['timezone']).' '.$this->mod->GetPreference('time_format');
		$dt = new DateTime('1900-01-01 00:00:00',new DateTimeZone($bdata['timezone']));
		$relations = $this->mod->ResultTemplates($bdata['bracket_id']);
		$anon = $this->mod->Lang('anonother');
		//process all recorded matches
		$sql = 'SELECT M.*,T1.displayorder AS tAorder,T2.displayorder AS tBorder FROM '
.$pref.'module_tmt_matches M JOIN '
.$pref.'module_tmt_teams T1 ON M.teamA = T1.team_id JOIN '
.$pref.'module_tmt_teams T2 ON M.teamB = T2.team_id
WHERE M.bracket_id=? AND T1.flags!=2 AND T2.flags!=2
ORDER BY T1.displayorder';
		$matches = $db->GetAll($sql,array($bdata['bracket_id']));
		foreach($matches as &$mdata)
		{
			$OA = (int)$mdata['tAorder'];
			$OB = (int)$mdata['tBorder'];
			if ($OA > $OB)
			{
				$tmp = $OA;
				$OA = $OB;
				$OB = $tmp;
			}
			$tA = $names[$OA];
			$tB = $names[$OB];
			if($titles != 2) //not 'plan' mode
			{
				switch($mdata['status'])
				{
				case 0:
					$type = 'deflt';
					goto firm2;
					//no break here
				case SOFT:
					$type = 'nonf';
					goto firm2;
					//no break here
				case FIRM:
				case TOLD:
					$type = 'firm';
firm2:
					$rel = sprintf($relations['vs'],$tA,$tB);
					$at = ($mdata['place']) ? $mdata['place'] : '';
					if($mdata['playwhen'])
					{
						$dt->modify($mdata['playwhen']);
						$at .= ' '.date($fmt,$dt->getTimestamp());
					}
					$text = $rel."\n".trim($at);
					break;
				case ASOFT:
					$type = 'nonf';
					//no break here
				case AFIRM:
					if($mdata['status'] == AFIRM)
						$type = 'firm';
					if(!($mdata['teamA'] || $mdata['teamB'])) //should never happen for planned RR match
						$rel = ($titles==1)?"\n".$this->mod->Lang('anonanon'):''; //TODO better descriptor
					elseif($mdata['teamA'])
						$rel = sprintf($relations['vs'],$tA,$anon);
					else
						$rel = sprintf($relations['vs'],$anon,$tB);
					$at = ($mdata['place']) ? $mdata['place'] : '';
					if($mdata['playwhen'])
					{
						$dt->modify($mdata['playwhen']);
						$at .= ' '.date($fmt,$dt->getTimestamp());
					}
					$text = $rel."\n".trim($at);
					break;
				case FORFB:
					if(!$mdata['score']) //no reason given
						$mdata['score'] = $bdata['forfeit'];
				case WONA:
					$type = 'done';
					$rel = sprintf($relations['def'],$tA,$tB);
					$text = $rel."\n".trim($mdata['score']);
					break;
				case FORFA:
					if(!$mdata['score'])
						$mdata['score'] = $bdata['forfeit'];
				case WONB:
					$type = 'done';
					$rel = sprintf($relations['def'],$tB,$tA);
					$text = $rel."\n".trim($mdata['score']);
					break;
				case MTIED:
					$type = 'done';
					$rel = sprintf($relations['tied'],$tA,$tB);
					$text = $rel."\n".trim($mdata['score']);
					break;
				case NOWIN:
					$type = 'done';
					$text = sprintf($relations['nomatch'],$tA,$tB);
					$rel = sprintf($relations['vs'],$tA,$tB);
					$text = $rel."\n".$bdata['nomatch'];
					break;
				default:
					$type = 'deflt';
					$rel = sprintf($relations['vs'],$tA,$tB);
					$text = $rel."\n".trim($mdata['score']);
					break;
				}
			}
			else //plan mode
			{
				switch($mdata['status'])
				{
				 case 0:
					$type = 'deflt';
					break;
				 case SOFT:
				 case ASOFT:
					$type = 'nonf';
					break;
				 case FIRM:
				 case TOLD:
				 case AFIRM:
					$type = 'firm';
					break;
				 default:
					$type = ($tA != FALSE && $tB != FALSE) ? 'done':'deflt';
					break;
				}
				$at = ($mdata['place']) ? $mdata['place'] : '';
				if($mdata['playwhen'])
				{
					$dt->modify($mdata['playwhen']);
					$at .= ' '.date($fmt,$dt->getTimestamp());
				}
				if($at)
				{
					$rel = ''; //$this->mod->Lang('matchnum',(int)$mdata['match_id'])."\n";
					if($tA != FALSE)
						$rel .= $tA."\n";
					if($tB != FALSE)
						$rel .= $tB."\n";
					if($tA == FALSE || $tB == FALSE)
						$rel .= $anon."\n";
					$text = $rel.trim($at);
				}
				else
				{
					if($tA == FALSE)
						$tA = $anon;
					if($tB == FALSE)
						$tB = $anon;
					$text = sprintf($relations['vs'],$tA,$tB);
				}
			}
			$this->layout[$OA][$OB]['type'] = $type;
			$this->layout[$OA][$OB]['text'] = $text;
		}
		unset($mdata);
		//process 'not-yet-created' matches
		foreach($this->layout as $OA=>&$column)
		{
			foreach($column as $OB=>&$row)
			{
				if(!isset($row['type']))
				{
					$row['type'] = 'deflt';
					$tA = $names[$OA];
					if($tA == FALSE)
						$tA = $anon;
					$tB = $names[$OB];
					if($tB == FALSE)
						$tB = $anon;
					$row['text'] = sprintf($relations['vs'],$tA,$tB);
				}
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

		foreach($this->layout as &$column)
		{
			$first = reset($column);
			$x = floatval($first['bl']); //inside margin
			if(count($column) > 1 && $dojoins)
			{
				//draw joins first
				$pdf->SetLineWidth($lw);
				if($lc)
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
				$y = floatval($first['bt'] + $bh/2);
				$last = end($column);
				$y2 = floatval($last['bt'] + $bh/2);
				$m = $x - $gw/2;
				if($y2 != $y)
					$pdf->Line($m,$y,$m,$y2);
				foreach($column as &$row)
				{
					if(empty($row['type']) || $row['type'] != 'hide')
					{
						$y = floatval($row['bt']) + $bh/2;
						$pdf->Line($x,$y,$m,$y);
					}
				}
				unset($row);
				if($dashjoins)
					$pdf->SetLineDash(FALSE);
			}

			foreach($column as &$row)
			{
				$type = (!empty($row['type'])) ? $row['type'] : 'deflt';
				if($type == 'hide')
					continue;
				$style = $boxstyles[$type];
				$c = $style['fill'];
				if($c)
					$pdf->SetFillColor($c[0],$c[1],$c[2]);
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
					if($c)
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
					if($c)
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
