<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
class tmtPeriodInterpreter
{
	/*
	BlockYears:
	Get year(s) in block, also sets swaps @bs, @be if necessary, sets $dtw to @bs
	@bs: reference to block-start stamp
	@be: reference to 1-past-block-end stamp
	Returns: array, one member or a range
	*/
	private function BlockYears(&$bs, &$be, $dtw)
	{
		if ($bs > $be) {
			list($bs,$be) = array($be,$bs);
		}
		$dtw->setTimestamp($be-1);
		$e = (int)$dtw->format('Y');
		$dtw->setTimestamp($bs);
		$s = (int)$dtw->format('Y');
		if ($e > $s)
			return range($s,$e);
		else
			return array($s);
	}

	/*
	BlockMonths:
	Get months(s) in block
	@bs: block-start stamp
	@be: 1-past-block-end stamp
	Returns: array, one member or a range, not necessarily contiguous
	*/
	private function BlockMonths($bs, $be, $dtw)
	{
		$dtw->setTimestamp($be-1);
		$e = (int)$dtw->format('m');
		$dtw->setTimestamp($bs);
		$s = (int)$dtw->format('m');
		if ($e != $s) {
			if ($e > $s) {
				return range($s,$e);
			} else {
				return array_merge(range(1,$e),range($s,12));
			}
		} else
			return array($s);
	}

	/**
	BlockDays:
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day in the year
	*/
	public function BlockDays($bs, $be, $dtw)
	{
		$years = self::BlockYears($bs, $be, $dtw, FALSE);
		$dtw->setTime(0,0,0);
		$dte = clone $dtw;
		$inc = new \DateInterval('P1D');
		$ret = array();
		$yn = reset($years);

		if (count($years) > 1) {
			$ye = end($years);
			while ($yn < $ye) {
				$doy = array();
				$dte->setDate($yn+1,1,1);
				while ($dtw < $dte) {
					$doy[] = $dtw->getTimestamp();
					$dtw->add($inc);
				}
				if ($doy) {
					$ret[$yn] = $doy;
				}
				$yn++;
			}
		}
		$doy = array();
		$dte->setTimestamp($be-1);
		while ($dtw <= $dte) {
			$doy[] = $dtw->getTimestamp();
			$dtw->add($inc);
		}
		if ($doy)
			$ret[$yn] = $doy;

		return $ret;
	}

	/*
	ToNumbersRange:
	@element: string like S..E
	@prefix: 1-byte [DWMY] at start of S and/or E, or FALSE
	Returns: array of numbers, sans @prefix (if any)
	*/
	private function ToNumbersRange($elmt, $prefix)
	{
		$parts = explode('..',$elmt);
		$s = $parts[0];
		$e = $parts[1];
		if ($prefix) {
			if ($s[0] == $prefix)
				$s = substr($s,1);
			if ($e[0] == $prefix)
				$e = substr($e,1);
		}
		if ($s < $e) {
			return range($s,$e);
		}
		return array($s); //should never happen
	}

	/*
	ToMonthsRange:
	@element: string like YYYY-[M]M..YYYY-[M]M
	Returns: array of strings, each like YYYY-[M]M
	*/
	private function ToMonthsRange($elmt)
	{
		$parts = explode('..',$elmt);
		$dtw = new \DateTime('@0',NULL);
		$dtw->modify($parts[0].'-1 0:0:0');
		$dte = clone $dtw;
		$dte->modify($parts[1].'-1 0:0:0');
		if ($dtw < $dte) {
			$ret = array();
			while ($dtw <= $dte) {
				$ret[] = $dtw->format('Y-n');
				$dtw->modify('+1 month');
			}
			return $ret;
		}
		return array($parts[0]); //should never happen
	}

	/*
	ToDatesRange:
	@element: string like YYYY-[M]M-[D]D..YYYY-[M]M-[D]D
	Returns: array of strings, each like YYYY-[M]M-[D]D
	*/
	private function ToDatesRange($elmt)
	{
		$parts = explode('..',$elmt);
		$dtw = new \DateTime('@0',NULL);
		$dtw->modify($parts[0]);
		$dte = clone $dtw;
		$dte->modify($parts[1]);
		if ($dtw < $dte) {
			$ret = array();
			$inc = new \DateInterval('P1D');
			while ($dtw <= $dte) {
				$ret[] = $dtw->format('Y-n-j');
				$dtw->add($inc);
			}
			return $ret;
		}
		return array($parts[0]); //should never happen
	}

	/*
	ToArray:
	@element: string, part of a descriptor
	@prefix: 1-byte [DWMY], or FALSE
	Returns: array of numbers or strings, with any prefix other than 'D' removed
	*/
	private function ToArray($elmt, $prefix=FALSE)
	{
		if (strpos($elmt,',') !== FALSE) {
			$parts = explode(',',$elmt);
			$ret = array();
			foreach ($parts as $elmt) {
				if (strpos($elmt,'..') !== FALSE) {
					$ret[] = array_merge($ret,self::ToNumbersRange($elmt,$prefix));
				} elseif (is_numeric($elmt)) {
					$ret[] = (int)$elmt;
				} elseif ($prefix && $prefix == $elmt[0] && $prefix != 'D') {
					$ret[] = (int)substr($elmt,1);
				} else {
					$ret[] = $elmt;
				}
			}
			return array_unique($ret);
		} elseif (strpos($elmt,'..') !== FALSE) {
			return self::ToNumbersRange($elmt,$prefix);
		}
		if ($prefix && $prefix != 'D') {
			$elmt = substr($elmt,1);
		}
		if (is_numeric($elmt)) {
			$elmt = (int)$elmt;
		}
		return array($elmt);
	}

	/*
	AddEach:
	Update @found to represent a non-specific 'each n'th' substring.
	Range upper limits need to be verified in context when interpreting
	@hint: letter [DWMY] or FALSE
	@interval: interval between each wanted result
	@found: reference to results array, to be updated
	*/
	private function AddEach($hint, $interval, &$found)
	{
		//no point in specific range-upper-limits here, they may be
		//for >1 month/week, placeholders will be checked in context
		if ($hint) {
			switch ($hint) {
			 case 'D':
				$key = 'days';
				if ($found[$key][0] == '*') {
					if ($found['months'][0] != '*' || $found['years'][0] == '*') {
						$found[$key] = range(1,31);
					} elseif ($found['weeks'][0] != '*') {
						$found[$key] = range(1,7);
					} else {
						$found[$key] = range(1,366);
					}
				}
				break;
			 case 'W':
				$key = 'weeks';
				if ($found[$key][0] == '*') {
					if ($found['months'][0] != '*' || $found['years'][0] == '*') {
						$found[$key] = range(1,6);
					} else {
						$found[$key] = range(1,53);
					}
				}
				break;
			 case 'M':
				$key = 'months';
				if ($found[$key][0] == '*')
					$found[$key] = range(1,12);
				break;
			 case 'Y':
				$key = 'years';
				break;
			 default:
				return;
			}
			if ($found[$key][0] == '*')
				return;
		} else {
			foreach (array('days','weeks','months','years','-1') as $i=>$key) {
				if ($found[$key][0] != '*') {
					break;
				}
			}
			if ($i > 3)
				return;
		}

		$i = 0;
		foreach ($found[$key] as $k=>$value) {
			if ($i++ % $interval != 0) {
				unset($found[$key][$k]);
			}
		}
	}

	/*
	InterpretDescriptor:
	@descriptor: string like
	 A(B(C(D(E)))) where any/all of [ABCD] and associated '()' may be absent,
	   or may be like (P,Q,R) i.e. bracketed
	 or W,X[,Y...] where any/all may be S..E
	 or single S..E
	Returns: array with members 'years','months','weeks','days' and maybe 'dates'
	Each value is either an array of numbers or strings, or a single string.
	'days' may have members like 'D3' or 'EE2D3' or may be '*' (all),
	'weeks','months' and/or 'years' may be '*' (all) or '-' (none)
	*/
//	private
	public function InterpretDescriptor($descriptor)
	{
		if (strpos($descriptor,'(') !== FALSE) {
			$descriptor = str_replace(array('!',')','(('),array('','','('),$descriptor); //omit element-closers
			$parts = array_reverse(explode('(',$descriptor)); //prefer deeper-nested elements
			if ($descriptor[0] == '(')
				array_pop($parts); //empty last-member
			$lastkey = count($parts) - 1; //last key for comparison
		} elseif (strpos($descriptor,',') !== FALSE) {
			$parts = explode(',',$descriptor);
			$lastkey = -1; //no last-index match
		} elseif (strpos($descriptor,'..') !== FALSE) {
			if (preg_match('/([DWMY])/',$descriptor,$matches)) {
				$prefix = $matches[1];
			} else {
				$prefix = FALSE;
			}
			$parts = self::ToNumbersRange(ltrim($descriptor,'!'),$prefix);
			$lastkey = -1;
		} else {
			$parts = array(ltrim($descriptor,'!'));
			$lastkey = -1;
		}
		$ic = count($parts);
		$dc = 0;
		$found = array('years'=>'*','months'=>'*','weeks'=>'*','days'=>'*'); //no 'dates'
		for ($i=0; $i<$ic; $i++) { //NOT foreach cuz values can change on-the-fly
			$elmt = $parts[$i];
			if ($found['years'][0] == '*') { //no year-parameter recorded
				if (preg_match('/^[12]\d{3}([,.].+)?$/',$elmt)) {
					$found['years'] = self::ToArray($elmt);
					$dc++;
					continue;
				} elseif (preg_match('/^EE([2-9]|1\d+)YE$/',$elmt,$matches)) {
					$found['years'] = array(); //'eacher' but we can't know where to start/end
					$dc++;
					continue;
				}
			}
			if ($found['months'][0] == '*') { //no month-parameter recorded
				if (strpos($elmt,'M') !== FALSE) {
					if (preg_match('/^EE([2-9]|1[012])ME$/',$elmt,$matches)) { //each n'th month
						if ($found['years'][0] != '*')
							$found['months'] = range(1,12,$matches[1]);
					} elseif ($elmt != 'M') {
						$found['months'] = self::ToArray(str_replace('M','',$elmt));
					} elseif (isset($parts[$i+1])) {
						$parts[$i+1] = 'M'.$parts[$i+1];
					}
					$dc++;
					continue;
				} elseif (preg_match('/^[12]\d{3}\-(0?[1-9]|1[0-2])([,.].+)?$/',$elmt)) {
					if (strpos($elmt,',') !== FALSE) {
						$bits = explode(',',$elmt);
						$xtras = array();
						foreach ($bits as &$b) {
							if (strpos($b,'..') !== FALSE) {
								$xtras = array_merge($xtras,self::ToMonthsRange($b));
								unset($b);
							}
						}
						unset($b);
						if ($xtras) {
							$bits = array_merge($bits,$xtras);
						}
						sort($bits,SORT_STRING);
						$found['months'] = array_unique($bits,SORT_STRING);
					} elseif (strpos($elmt,'..') !== FALSE) {
						$found['months'] = self::ToMonthsRange($elmt);
					} else {
						$found['months'] = array($elmt);
					}
					$found['years'] = '-';
					$dc++;
					continue;
				}
			}
			if ($found['weeks'][0] == '*') { //no week-parameter recorded
				if (strpos($elmt,'W') !== FALSE) {
					if (preg_match('/^EE([2-9]|[1-5]\d)WE$/',$elmt,$matches)) { //each n'th week
						if ($found['months'][0] != '*')
							$d = 5; //upstream must check year/month specific max weeks
						elseif ($found['years'][0] != '*') {
							$d = 52;
							$found['months'] = '-';
						} else
							$d = 0;
						if ($d > 0)
							$found['weeks'] = range(1,$d,$matches[1]);
					} elseif ($elmt != 'W') {
						$found['weeks'] = self::ToArray($elmt,'W');
					} elseif (isset($parts[$i+1])) {
						$parts[$i+1] = 'W'.$parts[$i+1];
					}
					$dc++;
					continue;
				}
			}
			if ($found['days'][0] == '*' && !isset($found['dates'])) { //no day or date parameter recorded
				if (strpos($elmt,'D') !== FALSE) {
					if (preg_match('/^EE([2-9]|[1-3]\d{1,2}|[4-9]\d)DE$/',$elmt,$matches)) {
						if ($found['weeks'][0] != '*')
							$d = 7;
						elseif ($found['months'][0] != '*') {
							$d = 31; //upstream must check year/month-specific max days
							$found['weeks'] = '-'; //ignore weeks
						} elseif ($found['years'][0] != '*') {
							$d = 366; //upstream must check year-specific max days
							$found['weeks'] = '-';
							$found['months'] = '-';
						} else
							$d = 0;
						if ($d > 0)
							$found['days'] = range(1,$d,$matches[1]);
					} elseif ($elmt != 'D') {
						$found['days'] = self::ToArray($elmt,'D');
					} elseif (isset($parts[$i+1])) {
						$parts[$i+1] = 'D'.$parts[$i+1];
					}
					$dc++;
					continue;
				} elseif (preg_match('/^(\-)?([1-9]|[12]\d|3[01])([,.].+)?$/',$elmt)) { //day(s) of month
					$found['days'] = self::ToArray($elmt);
					$dc++;
					continue;
				} elseif (preg_match('/^[12]\d{3}\-(0?[1-9]|1[0-2])\-(0?[1-9]|[12]\d|3[01])([,.].+)?$/',$elmt)) { //date(s)
					if (strpos($elmt,',') !== FALSE) {
						$bits = explode(',',$elmt);
						$xtras = array();
						foreach ($bits as &$b) {
							if (strpos($b,'..') !== FALSE) {
								$xtras = array_merge($xtras,self::ToDatesRange($b));
								unset($b);
							}
						}
						unset($b);
						if ($xtras) {
							$bits = array_merge($bits,$xtras);
						}
						sort($bits,SORT_STRING);
						$found['dates'] = array_unique($bits,SORT_STRING);
					} elseif (strpos($elmt,'..') !== FALSE) {
						$found['dates'] = self::ToDatesRange(ltrim($elmt,'!'));
					} else {
						$found['dates'] = array(ltrim($elmt,'!'));
					}
					$dc++;
					continue;
				}
			}
			if (preg_match('/^EE([2-9]|1\d+)((.)E)?$/',$elmt,$matches)) {
				$hint = empty($matches[3]) ? FALSE:$matches[3];
				self::AddEach($hint,(int)$matches[1],$found);
				$dc++;
			}
		}

		if ($dc == $lastkey) { //1 more unparsed element
			if (preg_match('/^EE([2-9]|1\d+)((.)E)?$/',$elmt,$matches)) {
				$hint = empty($matches[3]) ? FALSE:$matches[3];
				self::AddEach($hint,(int)$matches[1],$found);
			}
		}

		if ($found['days'][0] != '*') {
			if ($found['months'][0] == '*') {
				if ($found['years'][0] != '*') {
					$found['months'] = '-';
				}
			}
			if ($found['weeks'][0] == '*') {
				if ($found['months'][0] != '*') {
					$found['weeks'] = '-';
				}
			}
		} else { //all days
			if ($found['weeks'][0] == '*') {
				$found['weeks'] = '-'; //ignore weeks
			}
		}
		if ($found['months'][0] == '*') {
			$found['months'] = range(1,12);
		}

		return $found;
	}

	/*
	AllDays:
	Get array of years and days-of-year conforming to the arguments
	This is for processing results from self::InterpretDescriptor, arguments may
	be an array of numbers or (for @days) strings or (in some cases) a single
	string '*' (all) or '-' (none/ignore)
	@years: year(s) identifier, may be '-' if specific month(s) wanted
	@months: month(s) identifier, may be '-' if specific day(s)/week(s)-of-year wanted
	@weeks: weeks(s) identifier
	@days: day(s) identifier, array member(s) may be like 'D3' or '2D3' or 'EE2D3'
	@dtw: modifiable DateTime object
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year
	*/
//	private
	public function AllDays($years, $months, $weeks, $days, $dtw)
	{
		$ret = array();
		if ($years[0] != '-') { //numeric year(s)
if ($years[0] == '*') { //DEBUG
	$this->Crash();
}
			foreach ($years as $yn) {
				$doy = self::DaysinYear($yn,$months,$weeks,$days,$dtw);
				if ($doy)
					$ret[$yn] = $doy;
			}
		} else { //specific months
			$years = array();
			foreach ($months as $one) {
				list($yn,$mn) = explode('-',$one);
				$yn = (int)$yn;
				$mn = (int)$mn;
				if (isset($years[$yn])) {
					$years[$yn][] = $mn;
				} else {
					$years[$yn] = array($mn);
				}
			}
			foreach ($years as $yn=>$one) {
				$doy = self::DaysinYear($yn,$one,$weeks,$days,$dtw);
				if ($doy)
					$ret[$yn] = $doy;
			}
		}
		return $ret;
	}

	/*
	DaysinYear:
	Get array of days-of-year conforming to the arguments
	This is for processing results from self::InterpretDescriptor, each argument
	may be an array of numbers or (for @days) strings or a single string '*' (all)
	or '-' (none/ignore)
	@year: 4-digit year
	@months: month(s) identifier
	@weeks: weeks(s) identifier
	@days: day(s) identifier, array member(s) may be like 'D3' or '2D3' or 'EE2D3'
	@dtw: modifiable DateTime object
	Returns: array, empty upon error, or integer(s), each a 0-based day-of-year in @year
	*/
	private function DaysinYear($year, $months, $weeks, $days, $dtw)
	{
		if ($months[0] != '-') { //numeric
			$doy = array();
			foreach ($months as $mn) {
				$dtw->setDate($year,$mn,1);
				$s = $dtw->format('z|t');
				list($s,$e) = explode('|',$s);
				if ($weeks[0] == '*' || $weeks[0] == '-') { //no specific week(s)
					if ($days[0] == '*') {
						$dom = range($s,$s+$e-1);
					} elseif ($days[0] == '-') {
						$dom = array((int)$s);
					} elseif (is_numeric($days[0])) {
						$dom = array();
						foreach ($days as $d) {
							if ($d <= $e)
								$dom[] = $s+$d-1;
						}
					} else {
						//$days = strings like 'D3' or '2D3' or 'EE2D3'
						$dom = self::DaysinMonth($year,$mn,$days,(int)$e,(int)$s,$dtw);
					}
				} else {
					/*
					$weeks = numbers -6..-1 1..6 (approx upper limit)
					$days = '-' or '*' or array of numbers or strings like 'D3','2D3','EE2D3','EE2[D[E]]'
					*/
					$dom = self::DaysinMonthWeeks($year,$mn,$weeks,$days,$dtw);
				}
				if ($dom)
					$doy = array_merge($doy,$dom);
			}
		} else { //week(s)/day(s) of year
			/*
			$weeks = '-' or '*' or array of numbers 1..53 (approx upper limit)
			$days = '-' or '*' or array of numbers or strings like 'D3','EE2[D[E]]'
			*/
			$doy = self::DaysinYearWeeks($year,$weeks,$days,$dtw);
		}
		if (count($doy) > 1) {
			sort($doy,SORT_NUMERIC);
			$doy = array_unique($doy,SORT_NUMERIC);
		}
		return $doy;
	}

	/*
	$days[] = strings like 'D3' or '2D3' or 'EE2D3'
	*/
	private function DaysinMonth($year, $month, $days, $dmax, $base, $dtw)
	{
		$dtw->setDate($year,$month,1);
		$firstdow = (int)$dtw->format('w'); //0..6
		$doy = array();
		foreach ($days as $one) {
			if (preg_match('/(EE)?([2-9]|[1-9]\d+)?D([1-7])/',$one,$matches)) {
				$dow = $matches[3] - 1; //0-based for Sun..Sat
				//offset to 1st instance of the wanted day
				$d = $dow - $firstdow;
				if ($d < 0)
					$d += 7;
				//count of the wanted day in the month
				$imax = ceil(($dmax - $d)/7);
				if (!empty($matches[1])) {
					$instance = range(1,$imax,$matches[2]);
				} elseif (!empty($matches[2])) {
					$instance = (int)$matches[2];
					if ($instance < 0) {
						$instance += 1 + $imax;
						if ($instance < 0 || $instance > $imax)
							return array();
					}
					$instance = array($instance);
				} else {
					$instance = range(1,$imax);
				}

				$d += $base;
				foreach ($instance as $i) {
					$doy[] = $d + ($i-1) * 7;
				}
			}
		}
		return $doy;
	}

	/*
	$weeks[] = numbers -6..-1 1..6
	$days = '-' or '*' or array of numbers or strings like 'D3','EE2[D[E]]'
	*/
	private function DaysinMonthWeeks($year, $month, $weeks, $days, $dtw)
	{
		$dtw->setDate($year,$month,1);
		$offs = (int)$dtw->format('w');
		$wmax = self::MonthWeeks($year,$month,$dtw); //part/full weeks in month
		$sort = FALSE;
		$doy = array();

		foreach ($weeks as $w) {
			if ($w < 0) {
				$w += 1 + $wmax;
				if ($w < 0 || $w > $wmax)
					return array();
				$sort = TRUE;
			}
			if ($w > 1) {
				$dtw->setDate($year,$month,1-$offs);
				$dtw->modify('+'.($w-1).' weeks');
				$d = (int)$dtw->format('z'); //doy-of-$s
				$s = 0; //0-based 1st-dow
				$e = $dtw->format('t') - $dtw->format('j');
				if ($e > 6)
					$e = 6;  //0-based last-dow
			} else {
				$dtw->setDate($year,$month,1);
				$d = (int)$dtw->format('z'); //doy-of-$s
				$s = (int)$dtw->format('w');
				$e = 6; //0-based last dow
			}

			if ($days[0] == '*') {
				$d -= $s;
				for ($dow=$s; $dow<=$e; $dow++)
					$doy[] = $d + $dow;
			} elseif ($days[0] == '-') {
				$doy[] = $d; //first-day-of-week
			} elseif (is_numeric($days[0])) {
				foreach ($days as $dow) { //1-based
					$dow--;
					if ($s+$dow <= $e)
						$doy[] = $d + $dow;
				}
			} else {
				foreach ($days as $one) {
					if (preg_match('/(EE([2-7]))?D?([1-7])?/',$one,$matches)) {
						if (empty($matches[1])) {
							$dow = $matches[3] - 1; //0-based for Sun..Sat
							if ($dow >= $s && $dow <= $e)
								$doy[] = $d + $dow;
						} else {
							for ($dow=$s; $dow<=$e; $dow+=$matches[2])
								$doy[] = $d + $dow;
						}
					}
				}
			}
		}
		if ($sort) {
			sort($doy,SORT_NUMERIC);
			$doy = array_unique($doy,SORT_NUMERIC);
		}
		return $doy;
	}

	/*
	$weeks = '-' or '*' or array of numbers 1..53
	$days = '-' or '*' or array of numbers or strings like 'D3','EE2[D[E]]'
	*/
	private function DaysinYearWeeks($year, $weeks, $days, $dtw)
	{
		$dtw->setDate($year,1,1);
		$offs = (int)$dtw->format('w');
		$dtw->setDate($year,12,31);
		$dmax = (int)$dtw->format('z');
		$wmax = self::YearWeeks($year,$dtw); //part/full weeks in year
		$sort = FALSE;
		$doy = array();

		if ($weeks[0] == '*') {
			$weeks = range(1,$wmax);
		} elseif ($weeks[0] == '-') {
			$weeks = array(1);	//CHECKME or return array();
		}
		foreach ($weeks as $w) {
			if ($w < 0) {
				$w += 1 + $wmax;
				if ($w < 0 || $w > $wmax)
					return array();
				$sort = TRUE;
			}
			if ($w > 1) {
				$dtw->setDate($year,1,1-$offs);
				$dtw->modify('+'.($w-1).' weeks');
				$d = (int)$dtw->format('z'); //doy-of-$s
				$s = 0; //0-based 1st-dow
				$e = $dmax - $d;
				if ($e > 6)
					$e = 6;  //0-based last-dow
			} else {
				$dtw->setDate($year,1,1);
				$d = 0; //doy-of-$s
				$s = (int)$dtw->format('w'); //0-based 1st-dow
				$e = 6; //0-based last dow
			}

			if ($days[0] == '*') {
				for ($dow=$s; $dow<=$e; $dow++)
					$doy[] = $d + $dow;
			} elseif ($days[0] == '-') {
				$doy[] = $d + $s; //first-day-of-week
			} elseif (is_numeric($days[0])) {
				foreach ($days as $dow) {
					$dow--; //0-based, relative to $s
					if ($s+$dow <= $e)
						$doy[] = $d + $dow;
				}
			} else {
				foreach ($days as $one) {
					if (preg_match('/(EE([2-7]))?D?([1-7])?/',$one,$matches)) {
						if (empty($matches[1])) {
							$dow = $matches[3] - 1; //0-based for Sun..Sat
							if ($dow >= $s && $dow <= $e)
								$doy[] = $d + $dow;
						} else {
							for ($dow=$s; $dow<=$e; $dow+=$matches[2])
								$doy[] = $d + $dow;
						}
					}
				}
			}
		}
		if ($sort) {
			sort($doy,SORT_NUMERIC);
			$doy = array_unique($doy,SORT_NUMERIC);
		}
		return $doy;
	}

	private function YearWeeks($year, $dtw=NULL)
	{
		if (!$dtw)
			$dtw = new \DateTime('@0',NULL);
		$dtw->setDate($year,1,1);
		$d = $dtw->format('w');
		$dtw->setDate($year,12,31);
		$e = $dtw->format('z');
		return ceil(($d+$e+1)/7);
	}

	private function MonthWeeks($year, $month, $dtw=NULL)
	{
		if (!$dtw)
			$dtw = new \DateTime('@0',NULL);
		$dtw->setDate($year,$month,1);
		$d = $dtw->format('w');
		$e = $dtw->format('t');
		return ceil(($d+$e)/7);
	}

	/**
	SpecificYears:
	@descriptor: string including 4-digit year, or sequence of those, or
		','-separated series of any of the former
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	@merge: optional boolean, whether to report year-starts instead of day-starts
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day or year in the year
	*/
	public function SpecificYears($descriptor, $bs, $be, $dtw, $merge=FALSE)
	{
		$parsed = self::InterpretDescriptor($descriptor);
		if (!$parsed || $parsed['years'][0] == '-')
			return array();

		$years = self::BlockYears($bs,$be,$dtw); //get year(s) in block, clean $bs,$be
		if ($parsed['years'][0] != '*') {
			$years = array_intersect($years,$parsed['years']);
			if (!$years)
				return array();
		}

		$dtw->setTime(0,0,0);
		$dte = clone $dtw;
		$inc = new \DateInterval('P1D');
		$ret = array();
		$yn = reset($years);

		if (count($years) > 1) {
			$ye = end($years);
			while ($yn < $ye) {
				$doy = array();
				$t = ($yn+1).'-1-1';
				$dte->modify($t);
				while ($dtw < $dte) {
					$st = $dtw->getTimestamp();
					if ($st >= $bs && $st < $be) {
						if (!$merge || $dtw->format('z') == 0) {
							$doy[] = $st;
						}
					}
					$dtw->add($inc);
				}
				if ($doy) {
					$ret[$yn] = $doy;
				}
				$dtw = $dte;
				$yn++;
			}
		}
		$doy = array();
		$dte->setTimestamp($be-1);
		while ($dtw <= $dte) {
			$st = $dtw->getTimestamp();
			if ($st >= $bs && $st < $be) {
				if (!$merge || $dtw->format('z') == 0) {
					$doy[] = $st;
				}
			}
			$dtw->add($inc);
		}
		if ($doy)
			$ret[$yn] = $doy;

		return $ret;
	}

	/**
	SpecificMonths:
	@descriptor: string including token M1..M12, or sequence(s) of those,
		or ','-separated series of any of the former
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	@merge: optional boolean, whether to report month-starts instead of day-starts
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day or month in the year
	*/
	public function SpecificMonths($descriptor, $bs, $be, $dtw, $merge=FALSE)
	{
		$parsed = self::InterpretDescriptor($descriptor);
		if (!$parsed || $parsed['months'][0] == '-') {
			return array();
		}
		$years = self::BlockYears($bs, $be, $dtw); //get year(s) in block, clean $bs,$be
		if (!($parsed['years'][0] == '*' || $parsed['years'][0] == '-')) {
			$years = array_intersect($years,$parsed['years']);
			if (!$years)
				return array();
		}
		$months = self::BlockMonths($bs,$be,$dtw);
		if ($parsed['months'][0] != '*') {
			$months = array_intersect($months,$parsed['months']);
			if (!$months)
				return array();
		}

		$wanted = self::AllDays($years,$months,'-','*',$dtw);
		if (!$wanted)
			return array();

		$ret = array();
		$dtw->setTime(0,0,0); //just in case
		//convert offsets to stamps - daily or monthly
		foreach ($wanted as $yn=>$offs) {
			$doy = array();
			foreach ($offs as $d) {
				$dtw->modify($yn.'-1-1 +'.$d.' days');
				if (!$merge || $dtw->format('j') == 1)
					$doy[] = $dtw->getTimestamp();
			}
			if ($doy)
				$ret[$yn] = $doy;
		}
		return $ret;
	}

	/**
	SpecificWeeks:
	@descriptor: string including token W1..W5 or W-5..W-1, or sequence of those,
		or ','-separated series of any of the former
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	@merge: optional boolean, whether to report week-starts instead of day-starts
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day or week in the year
	*/
	public function SpecificWeeks($descriptor, $bs, $be, $dtw, $merge=FALSE)
	{
		$parsed = self::InterpretDescriptor($descriptor);
		if (!$parsed || $parsed['weeks'][0] == '-' || $parsed['years'][0] == '-') {
			return array();
		}
		$years = self::BlockYears($bs,$be,$dtw); //get year(s) in block
		if ($parsed['years'][0] != '*') {
			$years = array_intersect($years,$parsed['years']); //each n'th year N/A
			if (!$years)
				return array();
		}
		if (!($parsed['months'][0] == '*' || $parsed['months'][0] == '-')) {
			$months = self::BlockMonths($bs,$be,$dtw);
			$months = array_intersect($months,$parsed['months']);
			if (!$months)
				return array();
		} else {
			$months = $parsed['months']; //* or -
		}

		$wanted = self::AllDays($years,$months,$parsed['weeks'],'*',$dtw);
		if (!$wanted)
			return array();

		$ret = array();
		//convert offsets to stamps - daily or monthly
		$dtw->setTime(0,0,0); //just in case
		foreach ($wanted as $yn=>$offs) {
			$doy = array();
			foreach ($offs as $d) {
				$dtw->modify($yn.'-1-1 +'.$d.' days');
				if (!$merge || $dtw->format('w') == 0)
					$doy[] = $dtw->getTimestamp();
			}
			if ($doy)
				$ret[$yn] = $doy;
		}
		return $ret;
	}

	/**
	SpecificDays:
	@descriptor: string including token D1..D7, or number 1..31 or -31..-1, or
		a sequence of those, or ','-separated series of any of the former
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day in the year
	*/
	public function SpecificDays($descriptor, $bs, $be, $dtw)
	{
		$parsed = self::InterpretDescriptor($descriptor);
		if (!$parsed || $parsed['days'][0] == '-' || $parsed['years'][0] == '-') {
			return array();
		}
		$years = self::BlockYears($bs,$be,$dtw); //get year(s) in block
		if ($parsed['years'][0] != '*') {
			$years = array_intersect($years,$parsed['years']); //each n'th year N/A
			if (!$years)
				return array();
		}
		if (!($parsed['months'][0] == '*' || $parsed['months'][0] == '-')) {
			$months = self::BlockMonths($bs,$be,$dtw);
			$months = array_intersect($months,$parsed['months']);
			if (!$months)
				return array();
		} else {
			$months = $parsed['months']; //* or -
		}

		$wanted = self::AllDays($years,$months,$parsed['weeks'],$parsed['days'],$dtw); //must get something
		$ret = array();
		//convert offsets to stamps - daily or monthly
		$dtw->setTime(0,0,0); //just in case
		foreach ($wanted as $yn=>$offs) {
			$doy = array();
			foreach ($offs as $d) {
				$dtw->modify($yn.'-1-1 +'.$d.' days');
				$doy[] = $dtw->getTimestamp();
			}
			if ($doy)
				$ret[$yn] = $doy;
		}
		return $ret;
	}

	/**
	SpecificDates:
	@descriptor: ISO-format date, or array like ['ISOstart','.','ISOend']
		representing a sequence
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day in the year
*/
	public function SpecificDates($descriptor, $bs, $be, $dtw)
	{
		$parsed = self::InterpretDescriptor($descriptor);
		if (!$parsed || empty($parsed['dates'])) {
			return array();
		}

		$ret = array();
		$dtw->setTime(0,0,0);
		foreach ($parsed['dates'] as $s) {
			$dtw->modify($s);
			$st = $dtw->getTimestamp();
			if ($st >= $bs && $st < $be) {
				$yn = (int)$dtw->format('Y');
				if (isset($ret[$yn])) {
					$ret[$yn][] = $st;
				} else
					$ret[$yn] = array($st);
			}
		}

		if ($ret) {
			ksort($ret,SORT_NUMERIC);
			foreach ($ret as &$doy) {
				sort($doy,SORT_NUMERIC);
				$doy = array_unique($doy,SORT_NUMERIC);
			}
			unset($doy);
		}
		return $ret;
	}
}
