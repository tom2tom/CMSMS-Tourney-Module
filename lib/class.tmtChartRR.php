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
	 2 for including match numbers in 'plan' mode
	Setup parent::layout[] parameters 'type' and 'text' for all chart boxes
	Returns: TRUE,or FALSE if no teams in the bracket
	*/
	function Boxes(&$bdata,&$db,$titles=1)
	{
		$pref = cms_db_prefix();
		//grab all team identifiers (including flagged deletions maybe needed for prior-match status)
		$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? ORDER BY displayorder ASC';
		$teams = $db->GetCol($sql,array($bdata['bracket_id']));
		if($teams == FALSE) return FALSE;
		$tc = count($teams);
		$order = 1;
		$names = array();
		foreach($teams as $tid)
		{
			$names[$order] = $this->mod->TeamName($tid);
			$order++;
		}
		$fmt = $this->mod->GetZoneDateFormat($bdata['timezone']).' '.$this->mod->GetPreference('time_format');
		$relations = $this->mod->ResultTemplates($bdata['bracket_id']);
		$anon = $this->mod->Lang('anonother');
		//process 'non-default' matches
		$sql = 'SELECT M.*,T1.displayorder AS tAorder,T2.displayorder AS tBorder FROM '
.$pref.'module_tmt_matches M JOIN '
.$pref.'module_tmt_teams T1 ON M.teamA = T1.team_id JOIN '
.$pref.'module_tmt_teams T2 ON M.teamB = T2.team_id
WHERE M.bracket_id=? AND M.status != 0 AND T1.flags!=2 AND T2.flags!=2
ORDER BY T1.displayorder ASC';
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
				case SOFT:
					$type = 'nonf';
					//no break here
				case FIRM:
				if($mdata['status'] == FIRM)
						$type = 'firm';
					$rel = sprintf($relations['vs'],$tA,$tB);
					$at = ($mdata['place']) ? $mdata['place'] : '';
					if($mdata['playwhen'])
						$at .= ' '.date($fmt,strtotime($mdata['playwhen']));
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
						$at .= ' '.date($fmt,strtotime($mdata['playwhen']));
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
				 case SOFT:
				 case ASOFT:
					$type = 'nonf';
					break;
				 case FIRM:
				 case AFIRM:
					$type = 'firm';
					break;
				 default:
					$type = ($tA != NULL && $tB != NULL) ? 'done':'deflt';
					break;
				}
				$rel = $this->mod->Lang('matchnum',(int)$mdata['match_id'])."\n";
				if($tA != null)
					$rel .= $tA."\n";
				if($tB != null)
					$rel .= $tB."\n";
				if($tA == NULL || $tB == NULL)
					$rel .= $anon."\n";
				$at = ($mdata['place']) ? $mdata['place'] : '';
				if($mdata['playwhen'])
					$at .= ' '.date($fmt,strtotime($mdata['playwhen']));
				$text = $rel.trim($at);
			}
			$this->layout[$OA][$OB]['type'] = $type;
			$this->layout[$OA][$OB]['text'] = $text;
		}
		unset($mdata);
		//process 'default' matches
		$indx = count($matches); //TODO no valid match number applies, want count $matches::processed above + 1
		foreach($this->layout as $OA=>&$boxes)
		{
			foreach($boxes as $OB=>&$item)
			{
				if(!isset($item['type']))
				{
					$item['type'] = 'deflt';
					if ($titles != 2)
						$item['text'] = sprintf($relations['vs'],$names[$OA],$names[$OB]);
					else
					{
						$id = $this->mod->Lang('matchnum',$indx);
						$at = ($mdata['place']) ? $mdata['place'] : '';
						if($mdata['playwhen'])
							$at .= ' '.date($fmt,strtotime($mdata['playwhen']));
						$item['text'] = $id."\n".$names[$OA]."\n".$names[$OB]."\n".trim($at);
					}
				}
				$indx++; //CRAPOLA!!
			}
			unset($item);
		}
		unset($boxes);
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

		foreach($this->layout as &$boxes)
		{
			if(count($boxes) > 1 && $dojoins)
			{
				//draw joins first
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
				$first = reset($boxes);
				$x = floatval($first['bl']); //inside margin
				$y = floatval($first['bt'] + $bh/2);
				$last = end($boxes);
				$y2 = floatval($last['bt'] + $bh/2);
				$m = $x - $gw/2;
				if($y2 != $y)
					$pdf->Line($m,$y,$m,$y2);
				foreach($boxes as &$item)
				{
					if(empty($item['type']) || $item['type'] != 'hide')
					{
						$y = floatval($item['bt']) + $bh/2;
						$pdf->Line($x,$y,$m,$y);
					}
				}
				unset($item);
				if($dashjoins)
					$pdf->SetLineDash(FALSE);
			}

			foreach($boxes as &$item)
			{
				$type = (!empty($item['type'])) ? $item['type'] : 'deflt';
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
				$y = floatval($item['bt']); //inside margin
				$pdf->Rect($x,$y,$bw,$bh,$mode);
				if($dashed)
					$pdf->SetLineDash(FALSE);
				if(!empty($item['text']))
				{
					$pdf->SetFont($style['font'],$style['attr'],$style['size']);
					$c = $style['color'];
					$pdf->SetTextColor($c[0],$c[1],$c[2]);
					$pdf->SetXY($x+$offs,$y+$offs);
					$pdf->MultiCell($tcw,$tch,$item['text'],0,'C',FALSE);
				}
			}
			unset($item);
		}
		unset($boxes);
	}
}

?>
