<?php
/*
This file is a class for CMS Made Simple (TM).
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>

This file is free software; you can redistribute it and/or modify it under
the terms of the GNU Affero General Public License as published by the
Free Software Foundation; either version 3 of the License, or (at your option)
any later version.

This file is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details. If you don't have a copy
of that license, read it online at: www.gnu.org/licenses/licenses.html#AGPL

Class: Calendar
*/
/**
Calendar Availability Language

Availability constraints are specified in a string of the form
 condition[,condition2...]
Whitespace and newlines in the string are ignored. If more than one condition is
specified, their order is irrelevant. 

Each condition in the string is of the form
 [PERIOD][@][TIME]
The separator '@' is mandatory if both PERIOD and TIME are specified. It may
otherwise be used i.e. PERIOD@ or @TIME, to clarify ambiguous data.  For now at
least, the interpreter supports LTR languages only.

PERIOD and/or TIME may be
 a 'singleton' value; or
 (X,Y[,...]) which specifies a sequence of individual values, in any order, all
 of which are valid; or
 X..Y which specifies an inclusive range of sequential values, all of which are
 valid, and normally Y should be chronologically after X (except when spanning a
 time-period-end e.g. 23:30..2:30 or Friday..Sunday or December..February).

Ordinary numeric sequences and ranges are supported, and for such ranges, Y must
be greater than X, and X and/or Y may be negative.

Any value, bracketed-sequence or range may itelf be bracketed and preceded by a
qualifier, being a single value, or scope-expander of any of the above types.
Such qualification may be nested/recursed.

A value which is a date is expected to be formatted as yyyy[-[m]m[-[d]d]] i.e.
month and day are optional, and leading 0 is not needed for month or day value.

A value which is a month- or day-name is expected to be in accord with the default,
or a specified, locale. Month- and/or day-names may be abbreviated. The term
'week' isn't system-pollable, and must be translated manually. Weeks always start
on Sunday (and so 'week' can be considered equivalent to 'Sunday..Saturday').

A value which is a time is expected to be formatted as [h]h[:[m]m] i.e. 24-hour
format, leading '0' optional, minutes optional but if present, the separator is
':'. In some contexts, [h]h:00 may be needed instead of just [h]h, to reliably
distinguish between hours-of-day and days-of-month. Sunrise/sunset times are
supported, if relevant latitude and longitude data are provided. Times like
sunrise/sunset +/- interval are also supported.  A time-value which is not a
range is assumed to represent a range of 1-hour less 1-second.

Negative-value parameters count backwards from next-longest-interval-end.

If multiple conditions are specified, and any of them overlap, then less-explicit/
more-liberal conditions prevail over more-explicit/less-liberal ones. This means
that, for any particular date/time, it will be sufficient for any combination of
conditions to be satisfied.

If no condition is specified, no constraint applies.

Examples:
Date descriptors
 2000 or (2020,2024) or 2000..2005 or 2000-6 or 2000-10..2001-3 or 2000-9-1
   or 2000-10-1..2000-12-31
 for month(s)-of-any-year: January or (March,September) or July..December
 for month(s)-of-specfic-year: January(2000) (otherwise as 2000-1)
   or (March,September)(2000) or July..December(2000)
Week descriptors
 for week(s)-of-any-month (some of which may not be 7-days): 2(week) or -1(week)
   or 2..3(week)
 for week(s)-of-named-month: 2(week(March)) or or 1..3(week(July,August))
   or (-2,-1)(week(April..July))
Day descriptors
 for day(s)-of-any-month: 1 or -2 or (15,18,-1) or 1..10 or 2..-1 or -3..-1 or
    1(Sunday) or -1(Wednesday..Friday) or 1..3(Friday,Saturday)
 for day(s)-of-specific-week(s): Sunday(2(week)) or
   (Saturday,Wednesday)(-3..-1(week(July))) or
   Monday..Friday((-2,-1)(week(April..July)))
 for day(s)-of-any-week: Monday or (Monday,Wednesday,Friday) or Wednesday..Friday
Time descriptors
 9 or 2:30 or (9,12,15:15) or 12..23 or 6..15:30 or sunrise..16 or 9..sunset-3:30
*/

class Calendar
{
	protected $mod; //reference to current module-object
	protected $cleaned;	//cached clean version of string which $conds represents
	/*
	$conds will be sorted, first on ascending priority-level (see below) and
	within each level, on ascending date, or first-date, in the case of sequences
	and ranges. Negative values are sorted after positives, but not interpreted
	to corresponding actual values.
	Priority levels:
	1 no period (time-only)
	2 unqualified year
	3 unqualified month
	4 unqualified week
	5 unqualified day
	6 qualified month
	7 qualified week
	8 qualified month
	9 qualified month
	10 specfic day
	*/
	protected $conds; //cached array of interpreted calendar-conditions, or FALSE
//private $ob; //'(' for ltr, ')' for rtl
//private $cb; //')' for ltr, '(' for rtl
	function __construct(&$mod)
	{
		$this->mod = $mod;
		$this->cleaned = '';
		$this->conds = FALSE;
	}

	private function _SplitCondition($str)
	{
		$parts = explode(',',$str);
		foreach($parts as &$one)
		{
			if (strpos($one,'..') !== FALSE)
			{
				$one = explode('..',$one,2);
				//in case the user mistakenly added more dots ....
				while($one[1][0] == '.')
					$one[1] = substr($one[1],1);
			}
		}
		unset($one);
		return $parts;
	}

	//No brackets around $str
	//Returns 3-member array(L,'.',H) or single value L(==H) or FALSE
	private function _ParseRange($str)
	{
		$parts = explode('..',$str,2);
		while($parts[1][0] == '.')
			$parts[1] = substr($parts[1],1);
		if($parts[0] === '' || $parts[1] === '')
			return FALSE;
		if($parts[0] == $parts[1])
			return $parts[0];
		//order the pair
		$swap = FALSE;
		$dateptn = '/^([12][0-9]{3})-(1[0-2]|0?[1-9])(-(3[01]|0?[1-9]|[12][0-9]))?$/';
/* $pattern matches
'2001-10-12' >> array
  0 => string '2001-10-12'
	1 => string '2001'
  2 => string '10'
  3 => string '-12'
  4 => string '12'
'2001-10' >> array
  0 => string '2001-10'
	1 => string '2001'
  2 => string '10'
'2001' >> empty array() 	
*/
		if(preg_match($dateptn,$parts[0],$loparts) && preg_match($dateptn,$parts[1],$hiparts))
		{
			$swap = (($hiparts[1] < $loparts[1])
			|| ($hiparts[1] == $loparts[1] && $hiparts[2] < $loparts[2]));
			if($swap)
			{
				if(!isset($hiparts[4]))
					$hiparts[4] = '1';
				if(!isset($loparts[4]))
				{
					$stamp = mktime(0,0,0,(int)$loparts[2],15,(int)$loparts[1]);				
					$loparts[4] = date('t',$stamp); //last day of specified month
				}
			}
			else
			{
				if(!isset($loparts[4]))
					$loparts[4] = '1';
				if(!isset($hiparts[4]))
				{
					$stamp = mktime(0,0,0,(int)$hiparts[2],15,(int)$hiparts[1]);				
					$hiparts[4] = date('t',$stamp); //last day of specified month
				}
				if($hiparts[4] < $loparts[4])
					$swap = TRUE;
			}
			$parts[0] = $loparts[1].'-'.$loparts[2].'-'.$loparts[4];
			$parts[1] = $hiparts[1].'-'.$hiparts[2].'-'.$hiparts[4];
		}
		elseif(is_numeric($parts[0]) && is_numeric($parts[1]))
		{
			$s = (int)$parts[0];
			$e = (int)$parts[1];
			$swap = ($s > $e && $e > 0);
		}
		else
		{
			//both should be D* or M*
			if($parts[0][0] != $parts[1][0])
				return FALSE;
			$s = (int)substr($parts[0],1);
			$e = (int)substr($parts[1],1);
			$swap = ($s > $e && $e > 0);
		}
		if($swap)
		{
			$t = $parts[0];
			$parts[0] = $parts[1];
			$parts[1] = $t;
		}
		return array($parts[0],'.',$parts[1]);
	}

	//Compare numbers such that -ve's last and increasing
	private static function _cmp_numbers($a,$b)
	{
		if(($a >= 0 && $b < 0) || ($a < 0 && $b >= 0)) 
			return ($b-$a);
		return ($a-$b);
	}

	//Compare date-strings like YYYY[-[M]M[-[D]D]]
	private static function _cmp_dates($a,$b)
	{
		//bare years don't work correctly
		$s = (strpos($a,'-')!=FALSE) ? $a:$a.'-1-1';
		$stA = strtotime($s);
		$s = (strpos($b,'-')!=FALSE) ? $b:$b.'-1-1';
		$stB = strtotime($s);
		return ($stA-$stB);
	}

	//No brackets around $str
	//Returns N-member, no-duplicates, array(L,..,H) or single value L(==all others) or FALSE
	//Any trailing @T is stripped
	private function _ParseSequence($str)
	{
		$parts = explode(',',$str);
		if(count($parts) == 1)
			return $str;
		//assume all values are the same type as the 1st
		$val = $parts[0];
		//trim any @T
		$p = strpos($val,'@');
		if($p !== FALSE)
			$val = substr($val,0,$p);
		$dateptn = '/^([12][0-9]{3})(-(1[0-2]|0?[1-9])(-(3[01]|0?[1-9]|[12][0-9]))?)?$/';
		if(preg_match($dateptn,$val))
			$type = 3;
		elseif(is_numeric($val))
			$type = 1;
		else
			$type = 2;

		foreach($parts as &$val)
		{
			//trim any @T
			$p = strpos($val,'@');
			if($p !== FALSE)
				$val = substr($val,0,$p);
			switch ($type)
			{
			 case 1:
				if(is_numeric($val))
				{
					break;
				}
				else
				{
					$parts = FALSE;
					break 2;
				}
			 case 2:
				if(1) //TODO same type of non-numeric
				{
					break;
				}
				else
				{
					$parts = FALSE;
					break 2;
				}
			 case 3:
				if(preg_match($dateptn,$val,$matches))
				{
					//populate any missing part(s)? NO
					break;
				}
				else
				{
					$parts = FALSE;
					break 2;
				}
			}
		}
		//sort
		switch ($type)
		{
		 case 1:
			usort($parts,array('tmtCalendar','_cmp_numbers'));
		 case 2:
		 	sort($parts,SORT_STRING);
			break;
		 case 3:
			usort($parts,array('tmtCalendar','_cmp_dates'));
			break;
		}
		//remove dups
		$parts = array_flip($parts);
		return array_flip($parts);
	}

	/**
	_CleanPeriod:

	Get suitably-adjusted form of @str.

	@str: the string to be parsed
	*/
	private function _CleanPeriod($str)
	{
		if($str[0] == '-')
		{
			preg_match('/ TODO /',$str,$matches,0,1);
			if($matches[0])
			{
				//TODO
			}
			return substr($str,1); //TODO better error handling

		}
		//TODO
		return $str;
	}

	//Sort array in which any member(s) may be a range-array. All strings, caseless.
	private static function _cmp_periods($a,$b)
	{
		if(is_array($a))
		{
			if(is_array($b))
				return funcTODO($a[0],$b[0]);
			elseif($a[0] === $b)
				return 1; //range-arrays last
			return funcTODO($a[0],$b);
		}
		elseif(is_array($b))
		{
			if($a === $b[0])
				return -1; //range-arrays last
			return funcTODO($a,$b[0]);
		}
		else
			return funcTODO($a,$b);
	}

	/**
	_ParsePeriod:
	
	Get array of [midnight timestamps CHECKME] for each day in the range day-of- @start to
	that day plus @later days, inclusive, and which complies with @str.
	Assumes no whitespace in @str.

	@str: the string to be parsed
	@start: timestamp for period being matched
	@laterdays: no. of days to process after the day of @start
	*/
	private function _ParsePeriod($str,$start,$laterdays)
	{
		$parts = $this->_SplitCondition($str);
		//clean
		foreach($parts as &$one)
		{
			if(is_array($one))
			{
			}
			else
			{
			}
		}
		unset($one);
		//sort
		if(count($parts) > 1)
			usort($parts, array('tmtCalendar','_cmp_periods'));
		//coalesce
		$blocks = array(array(-1,-1)); //always have something to compare
		foreach($parts as &$one)
		{
			if(is_array($one))
			{
			}
			else
			{
			}
/*		$blend = end($blocks);
			if ($stamp > $blend[1] + 1)
				$blocks[] = array($stamp,$stend);
			elseif($stend > $blend[1])
				$blend[1] = $stend;
*/
		}
		unset($one);
		array_shift($blocks);
		return $blocks;
	}

	//Sort array in which any member(s) may be a range-array. All strings, case-specific.
	private static function _cmp_times($a,$b)
	{
		if(is_array($a))
		{
			if(is_array($b))
				return strcmp($a[0],$b[0]);
			elseif($a[0] === $b)
				return 1; //range-arrays last
			return strcmp($a[0],$b);
		}
		elseif(is_array($b))
		{
			if($a === $b[0])
				return -1; //range-arrays last
			return strcmp($a,$b[0]);
		}
		else
			return strcmp($a,$b);
	}

	/**
	_CleanTime:

	Get suitably-adjusted form of @str.

	@str: the string to be parsed
	@sunstuff =	array(
	 'rise'=>translated 'sunrise' string
	 'set'=>translated 'sunset' string
	 'day'=>timestamp for day being processed
	 'lat'=>latitude for calc
	 'long'=>longitude for calc
	 'zone'=>name of timezone (for calc GMT offset)
	)
	or FALSE
	*/
	private function _CleanTime($str,$sunstuff)
	{
		if($str[0] == '-')
		{
			preg_match('/(2[0-3]|[0-1]?[0-9])(:[0-5]?[0-9])?/',$str,$matches,0,1);
			if($matches[0])
			{
				$adj = 24 - $matches[0];
				while ($adj < 0)
					$adj += 24;
				if($matches[1])
					$adj .= $matches[1];
				return $adj;
			}
			return substr($str,1); //TODO better error handling
		}
		if($sunstuff)
		{
			if(strpos($str,$sunstuff['rise']) === 0)
			{
				$time = new DateTime($TODO, new DateTimeZone($sunstuff['zone']));
				$stamp = date_sunrise(
					$time->getTimestamp(),
					SUNFUNCS_RET_TIMESTAMP,$sunstuff['lat'],$sunstuff['long'],90+(50/60),
					$time->getOffset() / 3600
				);
				//TODO calc any offset
				return date('G:i',$stamp);
			}
			if(strpos($str,$sunstuff['set']) === 0)
			{
				$time = new DateTime($TODO, new DateTimeZone($sunstuff['zone']));
				$stamp = date_sunset(
					$time->getTimestamp(),
					SUNFUNCS_RET_TIMESTAMP,$sunstuff['lat'],$sunstuff['long'],90+(50/60),
					$time->getOffset() / 3600
				);
				//TODO calc any offset
				return date('G:i',$stamp);
			}
		}
		return $str; //TODO better error handling
	}

	/**
	_ParseHourCondition:

	Get array of seconds-range(s), each value therein a timestamp relative to start
	of current year. Assumes no whitespace in @str.

	@str: the string to be parsed
	@sunstuff: optional array of sunrise/set calculation parameters as below, default FALSE
	array(
		'rise'=>translated 'sunrise' string as would appear in module parameter value
		'set'=>translated 'sunset' string as would appear in module parameter value
		'day'=>timestamp for day being processed
		'lat'=>latitude for calc
		'long'=>longitude for calc
		'zone'=>name of timezone (for calc GMT offset)
	)
	*/
	private function _ParseTime($str,$sunstuff=FALSE)
	{
		$parts = $this->_SplitCondition($str);
		//cleanup
		foreach($parts as &$one)
		{
			if(is_array($one))
			{
				$s = $one[0];
				$f = $s[0];
				if($f < '0' || $f > '9')
					$s = $this->_CleanTime($s,$sunstuff);
				$e = $one[1];
				$f = $e[0];
				if($f < '0' || $f > '9')
					$e = $this->_CleanTime($e,$sunstuff);
				if(strcmp($s,$e) < 0)
					$one = array($s,$e);
				elseif(0) //TODO midnight-span
					$one = array($s,$e);
				elseif(strcmp($s,$e) > 0)
					$one = array($e,$s);
				else
					$one = $s;
			}
			else
			{
				$f = $one[0];
				if($f < '0' || $f > '9')
					$one = $this->_CleanTime($one,$sunstuff);
			}
		}
		unset($one);
		if(count($parts) > 1)
			usort($parts, array('tmtCalendar','_cmp_times'));
		//coalesce
		$blocks = array(array(-1,-1)); //always have something to compare
		foreach($parts as &$one)
		{
			if(is_array($one))
			{
				$stamp = strtotime($one[0],0); //time-localisation not supported
				$stend = strtotime($one[1],0);
			}
			else
			{
				$stamp = strtotime($one,0);
				$stend = $stamp + 3599;
			}
			$blend = end($blocks);
			if ($stamp > $blend[1] + 1)
				$blocks[] = array($stamp,$stend);
			elseif($stend > $blend[1])
				$blend[1] = $stend;
		}
		unset($one);
		array_shift($blocks);
		return $blocks;
	}

	/**
	_ParseConditions:
	
	Parse each member of @conds[] (as [p][@][t]) into arrays of days and related
	times.

	@conds: reference to array of availability-descriptor strings
	@sunstuff: reference to array of sunrise/sunset calc parameters, or FALSE
	@slothours: no. in {0.0..24.0}, representing the actual or notional slot-length,
	 	to help distinguish ambiguous hour- and day-numbers
	@start: preferred start time (bracket-local timestamp)
	@laterdays: no. of extra days to check after the one including @start
	*/
	private function _ParseConditions(&$conds,&$sunstuff,$slothours,$start,$laterdays)
	{
		$dstart = floor($start/86400); //1970/0-based index of $start's day 
		$days = array();
		$daytimes = array();
		$repeat = FALSE;
		foreach($conds as $one)
		{
			$p = strpos($one,'@'); //PERIOD-TIME separator present?
			if($p !== FALSE)
			{
				$e = (strlen($one) == ($p+1)); //trailing '@'
				if($p == 0 && !$e)
				{
					$days[] = ''; //distinguish from FALSE, cuz corresponding $daytimes[] is real
					$daytimes[] = $this->_ParseTime(substr($one,$p+1),$sunstuff); //seconds-blocks rel. 0 (midnight 1/1/1970 GMT)
				}
				else //$p > 0 || $e
					if($p > 0 && $e)
				{
					$days[] = $this->_ParsePeriod(substr($one,0,$p),$start,$laterdays); //day-indices rel. 0 (1/1/1970)
					$daytimes[] = FALSE;
				}
				elseif($p > 0)
				{
					$days[] = $this->_ParsePeriod(substr($one,0,$p),$start,$laterdays);
					$daytimes[] = $this->_ParseTime(substr($one,$p+1),$sunstuff);
				}
			}
			else //PERIOD OR TIME
			{
				if(preg_match('~[DMW]~',$one))
					$condtype = 2; //=period
				//check sunrise/set before numbers, in case have sun*-H:M
				elseif(preg_match('~[SR]~',$one))
					$condtype = 1; //=time
				else
				{
					$condtype = 0; //=undecided
					//catch many dates and numbers (<0(incl. date-separator), >24)
					$n = preg_match_all('~[-:]?\d+~',$one,$matches);
					if($n)
					{
						foreach($matches[0] as $n)
						{
							if($n[0] == ':' || $n == 0) //have minutes, day-of-month never 0
							{
								$condtype = 1;
								break;
							}
							elseif($n > 24 || $n < 0)
							{
								$condtype = 2;
								break;
							}
						}
					}
				}
				//end of analysis, for now
				if ($condtype == 1) //time
				{
					$days[] = array($dstart,$dstart+$laterdays);
					$daytimes[] = $this->_ParseTime($one,$sunstuff);
				}
				elseif($condtype == 2) //period
				{
					$days[] = $this->_ParsePeriod($one,$start,$laterdays);
					$daytimes[] = FALSE;
				}
				else //could be either - re-consider, after all are known
				{
					$repeat = TRUE;
					$days[] = FALSE;
					$daytimes[] = $one;
				}
			}
		}
		if($repeat)
		{
			//small number(s) logged, may be for hour(s) or day(s)
			$useday = FALSE;
			//if any PERIOD recorded, assume all 'bare' numbers are days-of-month
			foreach($days as &$one)
			{
				if($one) //NOT !== FALSE, match FALSE or ''
				{
					$useday = TRUE;
					break;
				}
			}
			unset($one);
			//if still not sure, interpret values in $conds[]
			if(!$useday)
			{
				//calc min. non-zero difference between small numeric values
				$one = implode(' ',$conds);
				$n = preg_match_all('~(?<![-:(\d])[0-2]?\d(?![\d()])~',$one,$matches);
				if($n > 1)
				{
					$mindiff = 25.0; //> 24 hours
					$n--; //for 0-base compares
					sort($matches[0],SORT_NUMERIC);
					foreach($matches[0] as $k=>$one)
					{
						if($k < $n)
						{
							$diff = (float)($matches[0][$k+1] - $one);
							if ($diff > -0.001 && $diff < 0.001)
							{
								$useday = TRUE; //numbers repeated only in PERIOD-descriptors
								break;
							}
							elseif($diff < $mindiff)
								$mindiff = $diff;
						}
					}
					if (!$useday && $mindiff < $slothours)
						$useday = TRUE;
				}
				elseif($n)
				{
					$n = $matches[0][0];
					if($slothours >= 1.0)
						$useday = ($n < $slothours);
					else
					 	$useday = ($n < 7 || $n > 19); //arbitrary choice for a single number
				}
				else
					$useday = TRUE; //should never get here
			}
			//now cleanup the logged values
			foreach($days as $k=>&$one)
			{
				if ($one === FALSE) //NOT ==
				{
					if($useday)
					{
						//treat this as a day-value
						$one = $this->_ParsePeriod($daytimes[$k],$start,$laterdays);
						$daytimes[$k] = FALSE;
					}
					else
					{
						//$n(s) <7 or >19 more likely to be a day-value ?
						$one = array($dstart,$dstart+$laterdays);
						$daytimes[$k] = $this->_ParseTime($daytimes[$k],$sunstuff);
					}
				}
				elseif ($one === '')
					$one = FALSE; //now we can clear that distinction
			}
			unset($one);
		}
		if($days)
			return array($days,$daytimes);
		return array(FALSE,FALSE);
	}

	/**
	_GetConditions:

	Get array of 'cleaned' condition(s) from @avail. Returns FALSE upon error.
	Un-necessary brackets are excised. Split on outside-bracket commas.
	All day-names aliased to D1..D7, all month-names aliased to M1..M12,
	'sunrise' to R, 'sunset' to S, 'week' to W, whitespace & newlines gone.

	@available: availability-condition string
	@locale: locale identifier string, for localising day/month names
	  possibly present in @available
	*/
	private function _GetConditions($avail,$locale)
	{
		$gets = range(1,7);
		$oldloc = FALSE;
		if($locale)
		{
			$oldloc = setlocale(LC_TIME,"0");
			if(!setlocale(LC_TIME,$locale))
				$oldloc = FALSE;
		}
		//NB some of these may be wrong, due to race on threaded web-server
		$longdays = $this->AdminDayNames($gets);
		$shortdays = $this->AdminDayNames($gets,FALSE);
		$gets = range(1,12);
		$longmonths = $this->AdminMonthNames($gets);
		$shortmonths = $this->AdminMonthNames($gets,FALSE);
		if($oldloc)
			setlocale(LC_TIME,$oldloc);
		unset($gets);

		$daycodes = array();
		for($i = 1; $i < 8; $i++)
			$daycodes[] = 'D'.$i;
		$monthcodes = array();
		for($i = 1; $i < 13; $i++)
			$monthcodes[] = 'M'.$i;
		//NB long before short
		$finds = array_merge($longdays,$shortdays,$longmonths,$shortmonths,
			array($this->mod->Lang('sunrise'),$this->mod->Lang('sunset'),$this->mod->Lang('week'),' ',"\n"));
		$repls = array_merge($daycodes,$daycodes,$monthcodes,$monthcodes,
			array('R','S','W','',''));
		$clean = str_replace($finds,$repls,$avail);
		if(preg_match('/[^\dDMRSW@:+-.,()]/',$clean))
			return FALSE;
		$l = strlen($clean);
		$parts = array();
		$d = 0;
		$s = 0;
		$b = -1;
		$xclean = FALSE;
		for($p=0; $p<$l; $p++)
		{
			switch ($clean[$p])
			{
			 case '(':
				if(++$d == 1)
					$b = $p;
				break;
			 case ')':
				if(--$d < 0)
					return FALSE;
				//strip inappropriate brackets
				if($d == 0)
				{
					if($p < $l-1) //before end, want pre- or post-qualifier
					{
						//check post-
						$n = $clean[$p+1];
						if($n == '@') // ')' N/A for d = 0 ?
						{
							$b = -1;
							break;
						}
					}
					//at end, or no post-qualifier, want pre-qualifier
					if($b > $s)
					{
						$n = $clean[$b-1];
						if($n == ')' || ($n >='0' && $n <= '9')) // '(' N/A for d = 0 ?
						{
							$b = -1;
							break;
						}
					}
					if($b >= $s)
					{
						$clean[$p] = ' ';
						$clean[$b] = ' ';
						$b = -1;
						$xclean = TRUE;
					}
					else
						return FALSE;
				}
				break;
			 case ',':
				if($d == 0)
				{
					if($p > $s && $clean[$p-1] != ',')
					{
						$tmp = substr($clean,$s,$p-$s);
						if ($xclean)
						{
							$parts[] = str_replace(' ','',$tmp);
							$xclean = FALSE;
						}
						else
							$parts[] = $tmp;
					}
					$s = $p+1;
				}
			 default:
				break;
			}
		}
		if($p > $s)
		{
			$tmp = substr($clean,$s,$p-$s); //last (or entire) part
			if ($xclean)
				$parts[] = str_replace(' ','',$tmp);
			else
				$parts[] = $tmp;
		}
		return $parts;
	}

	//========== PUBLIC FUNCS ===========
 
	/**
	AdminMonthNames:

	Get one, or array of, localised month-name(s).
	This is for admin use, the frontend locale may not be the same and/or supported
	by the server.

	@which: 1 (for January) .. 12 (for December), or array of such indices
	@full: optional, whether to get long-form name, default TRUE
	*/
	function AdminMonthNames($which,$full=TRUE)
	{
		$ret = array();
		$stamp = time();
		$thism = date('n',$stamp); //1 to 12 representing January .. December
		if (!is_array($which))
			$which = array($which);
		$f = ($full) ? 'F' : 'M';
		foreach($which as $month)
		{
			$offs = $month-$thism;
			if ($offs < 0)
				$ret[] = date($f,strtotime($offs.' months',$stamp));
			elseif ($offs > 0)
				$ret[] = date($f,strtotime('+'.$offs.' months',$stamp));
			else
				$ret[] = date($f,$stamp);
		}
		if (count($which) > 1)
			return $ret;
		return(reset($ret));
	}

	/**
	AdminDayNames:

	Get one, or array of, localised day-name(s).
	This is for admin use, the frontend locale may not be the same and/or supported
	by the server.

	@which: 1 (for Sunday) .. 7 (for Saturday), or array of such indices
	@full: optional whether to get long-form name default TRUE
	*/
	function AdminDayNames($which,$full=TRUE)
	{
		$ret = array();
		$stamp = time();
		$today = date('w',$stamp); //0 to 6 representing the day of the week Sunday = 0 .. Saturday = 6
		if (!is_array($which))
			$which = array($which);
		$f = ($full) ? 'l' : 'D';
		foreach($which as $day)
		{
			$offs = $day-$today-1;
			if ($offs < 0)
				$ret[] = date($f,strtotime($offs.' days',$stamp));
			elseif ($offs > 0)
				$ret[] = date($f,strtotime('+'.$offs.' days',$stamp));
			else
				$ret[] = date($f,$stamp);
		}
		if (count($which) > 1)
			return $ret;
		return(reset($ret));
	}

	/**
	MonthNames:

	Get one, or array of, translated month-name(s)

	@which: 1 (for January) .. 12 (for December), or array of such indices
	@short: optional, whether to get short-form name, default FALSE
	*/
	function MonthNames($which,$short=FALSE)
	{
		$k = ($short) ? 'shortmonths' : 'longmonths';
		$all = explode(',',$this->mod->Lang($k));

		if (!is_array($which))
		{
			if ($which > 0 && $which < 13)
				return $all[$which-1];
			return '';
		}
		$ret = array();
		foreach ($which as $month)
		{
			if ($month > 0 && $month < 13)
				$ret[$month] = $all[$month-1];
		}
		return $ret;
	}

	/**
	DayNames:

	Get one, or array of, translated day-name(s)

	@which: 1 (for Sunday) .. 7 (for Saturday), or array of such indices
	@short: optional, whether to get short-form name, default FALSE
	*/
	function DayNames($which,$short=FALSE)
	{
		$k = ($short) ? 'shortdays' : 'longdays';
		$all = explode(',',$this->mod->Lang($k));

		if (!is_array($which))
		{
			if ($which > 0 && $which < 8)
				return $all[$which-1];
			return '';
		}
		$ret = array();
		foreach ($which as $day)
		{
			if ($day > 0 && $day < 8)
				$ret[$day] = $all[$day-1];
		}
		return $ret;
	}

	/**
	IntervalNames:

	Get one, or array of, translated time-interval-name(s)

	@which: index 0 (for 'none'), 1 (for 'minute') .. 6 (for 'year'), or array of such indices
	@plural: optional, whether to get plural form of the interval name(s), default FALSE
	@cap: optional, whether to capitalise the first character of the name(s), default FALSE
	*/
	function IntervalNames($which,$plural=FALSE,$cap=FALSE)
	{
		$k = ($plural) ? 'multiperiods' : 'periods';
		$all = explode(',',$this->mod->Lang($k));
		array_unshift($all,$this->mod->Lang('none'));

		if (!is_array($which))
		{
			if ($which >= 0 && $which < 7)
				return $all[$which];
			return '';
		}
		$ret = array();
		foreach($which as $period)
		{
			if ($period >= 0 && $period < 7)
			{
				$ret[$period] = ($cap) ? ucfirst($all[$period]): //for current locale
					$all[$period];
			}
		}
		return $ret;
	}

	/**
	ParseCondition:

	Parse calendar-constraint @available and store result in $this->conds.
	Returns TRUE upon success (or if no constraint applies).
	This must be called before any poll for a period or check for a match.

	@available: availability-condition string
	@locale: optional, locale identifier string for localising day/month names
	  possibly present in @available, default ''
	*/
	function ParseCondition($available,$locale='')
	{
		if($available == FALSE)
			return TRUE;
		$this->conds = FALSE;
		$conds = $this->_GetConditions($available,$locale);
		if(!$conds)
			return FALSE;
		//TODO
		return FALSE;
	}

	/**
	CheckCondition:

	Determine whether calendar-constraint @available has correct syntax.
	Returns TRUE if no constraint applies, or else a 'cleaned' version of @available,
	or FALSE if @available is bad.

	@available: availability-condition string
	@locale: optional, locale identifier string for localising day/month names
	  possibly present in @available, default ''
	*/
	function CheckCondition($available,$locale='')
	{
		if($available == FALSE)
			return TRUE;
		$s1 = $this->conds;
		$s2 = $this->cleaned;
		if($this->ParseCondition($available,$locale))
			$ret = $this->cleaned;
		else
			$ret = FALSE;
		$this->conds = $s1;
		$this->cleaned = $s2;
		return $ret;
	}

}

?>
