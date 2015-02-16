<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Class: tmtCalendar
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
otherwise be used i.e. PERIOD@ or @TIME, to clarify ambiguous data.

PERIOD and/or TIME may be
 a 'singleton' value; or
 (X,Y[,...]) which specifies a sequence of individual values, in any order, all
 of which are valid; or
 X..Y which specifies an inclusive range of sequential values, all of which are
 valid, and normally Y should be chronologically after X (except when spanning a
 time-period-end e.g. 23:30..2:30 or Friday..Sunday or December..February).

Ordinary numeric sequences and ranges are supported, and for such ranges, Y must
be greater than X.

Any value, sequence or range may itelf be bracketed and preceded by a qualifier,
being a single value, or scope-expander of any of the above types. Such
qualification may be nested/recursed.

A value which is a date is expected to be formatted as yyyy[-[m]m[-[d]d]] i.e.
month and day are optional, and leading 0 is not needed for month or day value.

A value which is a month- or day-name is expected to be in accord with the default,
or a specified, locale. Month- and/or day-names may be abbreviated. The term
'week' isn't system-pollable, and must be translated manually. Weeks always start
on Sunday.

A value which is a time is expected to be formatted as [h]h[:[m]m] i.e. 24-hour
format, leading '0' optional, minutes optional but if present, the separator is
':'. In some contexts, [h]h:00 may be needed instead of just [h]h, to reliably
distinguish between hours-of-day and days-of-month. Sunrise/sunset times are
supported, if relevant latitude and longitude data are provided. Times like
sunrise/sunset +/- interval are also supported.  A time-value which is not a
range is assumed to represent a range of 1-hour less 1-second.

Negative-value parameters count backwards from next-longest-interval-end.

If multiple conditions are specified, specific date(s) prevail over everything
else, named month(s) prevail over unqualified years, named week(s) prevail over
unqualified months, and so on down the time-scale.

It will be sufficient for any combination of conditions to be satisfied.
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
 for day(s)-of-any-week: Monday or (Monday,Wednesday,Friday) or Wednesday..Friday
Time descriptors
 9 or 2:30 or (9,12,15:15) or 12..23 or 6..15:30 or sunrise..16 or 9..sunset-3:30
*/

class tmtCalendar
{
	private $dayblocks = FALSE; //cached array of day-ranges, or FALSE
	private $timeblocks = FALSE; //cached array of time-ranges, or FALSE
/*
	__construct()
	{
	}
*/
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
	@later: no. of days to process after the day of @start
	*/
	private function _ParsePeriod($str,$start,$later)
	{
		$parts = self::_SplitCondition($str);
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
		$parts = self::_SplitCondition($str);
		//cleanup
		foreach($parts as &$one)
		{
			if(is_array($one))
			{
				$s = $one[0];
				$f = $s[0];
				if($f < '0' || $f > '9')
					$s = self::_CleanTime($s,$sunstuff);
				$e = $one[1];
				$f = $e[0];
				if($f < '0' || $f > '9')
					$e = self::_CleanTime($e,$sunstuff);
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
					$one = self::_CleanTime($one,$sunstuff);
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
	@later: no. of extra days to check after the one including @start
	*/
	private function _ParseConditions(&$conds,&$sunstuff,$slothours,$start,$later)
	{
		$days = array();
		$daytimes = array();
		$repeat = FALSE;
		$shortdays = FALSE;
		$longdays = FALSE;
		$shortmonths = FALSE;
		$longmonths = FALSE;
		$week = FALSE;
		$suns = FALSE;
		foreach($conds as $one)
		{
			$p = strpos($one,'@'); //PERIOD-TIME separator present?
			if($p !== FALSE)
			{
				$e = (strlen($one) == ($p+1)); //trailing '@'
				if($p == 0 && !$e)
				{
					$days[] = ''; //distinguish from FALSE, cuz corresponding $daytimes[] is real
					$daytimes[] = self::_ParseTime(substr($one,$p+1),$sunstuff); //seconds-blocks rel. 0 (midnight 1/1/1970 GMT)
				}
				else //$p > 0 || $e
					if($p > 0 && $e)
				{
					$days[] = self::_ParsePeriod(substr($one,0,$p),$start,$later); //day-indices rel. 0 (1/1/1970)
					$daytimes[] = FALSE;
				}
				elseif($p > 0)
				{
					$days[] = self::_ParsePeriod(substr($one,0,$p),$start,$later);
					$daytimes[] = self::_ParseTime(substr($one,$p+1),$sunstuff);
				}
			}
			else //PERIOD OR TIME
			{
				$condtype = 0; //0=undecided 1=time 2=time
				if(!$shortdays) $shortdays = self::AdminDayNames(range(1,7),FALSE);
				foreach($shortdays as &$n)
				{
					if(strpos($one,$n) !== FALSE)
					{
						$condtype = 2;
						break;
					}
				}
				unset($n);
				if($condtype == 0)
				{
					if(!$shortmonths) $shortmonths = self::AdminMonthNames(range(1,12),FALSE);
					foreach($shortmonths as &$n)
					{
						if(strpos($one,$n) !== FALSE)
						{
							$condtype = 2;
							break;
						}
					}
					unset($n);
				}
				if($condtype == 0)
				{
					if(!$longdays) $longdays = self::AdminDayNames(range(1,7));
					foreach($longdays as &$n)
					{
						if(strpos($one,$n) !== FALSE)
						{
							$condtype = 2;
							break;
						}
					}
					unset($n);
				}
				if($condtype == 0)
				{
					if(!$longmonths) $longmonths = self::AdminMonthNames(range(1,12));
					foreach($longmonths as &$n)
					{
						if(strpos($one,$n) !== FALSE)
						{
							$condtype = 2;
							break;
						}
					}
					unset($n);
				}
				if($condtype == 0)
				{
					if (!$week) $week = $mod->Lang('week');
					if(strpos($one,$week) !== FALSE)
							$condtype = 2;
				}
				if($condtype == 0)
				{
					//check these before numbers, in case have sun*-H:M
					if(!$suns) $suns = array($mod->Lang('sunrise'),$mod->Lang('sunset'));
					foreach($suns as &$n)
					{
						if(strpos($one,$n) !== FALSE)
						{
							$condtype = 1;
							break;
						}
					}
					unset($n);
				}
				if($condtype == 0)
				{
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
					$days[] = array($dstart,$dstart+$later);
					$daytimes[] = self::_ParseTime($one,$sunstuff);
				}
				elseif($condtype == 2) //period
				{
					$days[] = self::_ParsePeriod($one,$start,$later);
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
				$one = implode(' ',$cond);
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
							$diff = (float)($matches[$k+1] - $one);
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
						$one = self::_ParsePeriod($daytimes[$k],$start,$later);
						$daytimes[$k] = FALSE;
					}
					else
					{
						//$n(s) <7 or >19 more likely to be a day-value ?
						$one = array($dstart,$dstart+$later);
						$daytimes[$k] = self::_ParseTime($daytimes[$k],$sunstuff);
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

	//Get array of 'cleaned' condition(s) from $avail (i.e. split on outside-bracket commas)
	private function _GetConditions($avail)
	{
		$clean = str_replace(array(' ',"\n"),array('',''),$avail);
		$l = strlen($clean);
		$parts = array();
		$d = 0; $s = 0;
		for($p=0; $p<$l; $p++)
		{
			switch ($clean[$p])
			{
			 case '(':
				$d++;
				break;
			 case ')':
				$d--;
				if($d < 0)
					$d = 0;
				break;
			 case ',':
				if($d == 0)
				{
					if($p > $s && $clean[$p-1] != ',')
						$parts[] = substr($clean,$s,$p-$s);
					$s = $p+1;
				}
			 default:
				break;
			}
		}
		if($p > $s)
			$parts[] = substr($clean,$s,$p-$s); //last (or entire) part
		return $parts;
	}

	//No checks here for valid parameters - assumed done before
	private function _GetSunData(&$mod,&$bdata)
	{
		$cond = $bdata['available'];
		$rise = $mod->Lang('sunrise');
		$set = $mod->Lang('sunset');
		if(strpos($cond,$rise) !== FALSE || strpos($cond,$set) !== FALSE)
		{
			if($bdata['timezone'])
				$zone = $bdata['timezone'];
			else
			{
				$zone = $mod->GetPreference('time_zone','');
				if(!zone)
					$zone = 'Europe/London'; //TODO BETTER e.g. func($bdata['locale'])
			}
			$lat = $bdata['latitude']; //maybe 0.0
			$long = $bdata['longitude']; //ditto
/* TODO not the hour c.f.
			$time = new DateTime('now', new DateTimeZone($sunstuff['zone']));
			$stamp = date_sunset($time->getTimestamp(),
*/
			$day = floor($start/3600)*3600;
			return array (
			 'rise'=>$rise,
			 'set'=>$set,
			 'day'=>$day,
			 'lat'=>$lat,
			 'long'=>$long,
			 'zone'=>$zone
			);
		}
		return FALSE;
	}

	//Get no. in {0.0..24.0} representing the actual or notional slot-length
	private function _GetSlotHours(&$bdata)
	{
		if(!$bdata['placegap'])
			return 0.0;
		switch($bdata['placegaptype'])
		{
			case 1: //minute
				return MIN($bdata['placegap']/60,24.0);
			case 2: //hour
				return MIN((float)$bdata['placegap'],24.0);
			case 3: //>= day
			case 4:
			case 5:
			case 6:
				return 24.0;
			default:
				return 0.0;
		}
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
	
	@mod: reference to current module object
	@which: 1 (for January) .. 12 (for December), or array of such indices
	@short: optional, whether to get short-form name, default FALSE
	*/
	function MonthNames(&$mod,$which,$short=FALSE)
	{
		$k = ($short) ? 'shortmonths' : 'longmonths';
		$all = explode(',',$mod->Lang($k));

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

	@mod: reference to current module object
	@which: 1 (for Sunday) .. 7 (for Saturday), or array of such indices
	@short: optional, whether to get short-form name, default FALSE
	*/
	function DayNames(&$mod,$which,$short=FALSE)
	{
		$k = ($short) ? 'shortdays' : 'longdays';
		$all = explode(',',$mod->Lang($k));

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

	@mod: reference to current module object
	@which: index 0 (for 'none'), 1 (for 'minute') .. 6 (for 'year'), or array of such indices
	@plural: optional, whether to get plural form of the interval name(s), default FALSE
	@cap: optional, whether to capitalise the first character of the name(s), default FALSE
	*/
	function IntervalNames(&$mod,$which,$plural=FALSE,$cap=FALSE)
	{
		$k = ($plural) ? 'multiperiods' : 'periods';
		$all = explode(',',$mod->Lang($k));
		array_unshift($all,$mod->Lang('none'));

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
	SlotComplies:

	Determine whether the interval @start to @start + @length satisfies constraints
	specified in	relevant fields in @bdata.

	@mod: reference to current module-object
	@bdata: reference to array of data for current bracket 	
	@start: preferred start time (bracket-local timestamp)
	@length: optional length (seconds) of time period to be checked, default 0
	*/
	function SlotComplies(&$mod,&$bdata,$start,$length=0)
	{
		if($bdata['available'] == FALSE)
			return TRUE;
		$conds = self::_GetConditions($bdata['available']);
		$sunstuff = self::_GetSunData($mod,$bdata);
		$maxhours = self::_GetSlotHours($bdata);
		list($days,$daytimes) = self::_ParseConditions($conds,$sunstuff,$maxhours,$start,365); //check up to a year ahead
		if($days)
		{
			foreach($days as $k=>&$one)
			{
				if($one)
				{
					$times = (isset($daytimes[$k]))?$daytimes[$k]:FALSE;
					if(!$times)
						$times = array(0,86399); //whole day's worth of seconds
					foreach($times as &$range)
					{
						$base = stampfuncTODO($one);
						$from = MAX($start,($base+$range[0]));
						if(($base+$range[1]) >= ($from+$length))
						{
							unset($one);
							unset($range);
							return TRUE;
						}
					}
					unset($range);
				}
			}
			unset($one);
		}
		return FALSE;
	}

	/**
	SlotStart:
	
	Get start-time (timestamp) matching constraints specified in relevant fields in
	@bdata, and	starting no sooner than @start, or ASAP within @later days after
	the one including @start, and where the available time is at least @length.
	Returns FALSE if no such time is available within the specified interval.

	@mod: reference to current module-object
	@bdata: reference to array of data for current bracket
	@start: preferred/first start time (bracket-local timestamp)
	@length: optional length (seconds) of time period to be discovered, default 0
	@later: optional, no. of extra days to check after the one including @start, default 365
	*/
	function SlotStart(&$mod,&$bdata,$start,$length=0,$later=365)
	{
		if($bdata['available'] == FALSE)
			return $start;
		$conds = self::_GetConditions($bdata['available']);
		$sunstuff = self::_GetSunData($mod,$bdata);
		$maxhours = self::_GetSlotHours($bdata);
		list($days,$daytimes) = self::_ParseConditions($conds,$sunstuff,$maxhours,$start,$later);
		if($days)
		{
			foreach($days as $k=>&$one)
			{
				if($one)
				{
					$times = (isset($daytimes[$k]))?$daytimes[$k]:FALSE;
					if(!$times)
						$times = array(0,86399); //whole day's worth of seconds
					foreach($times as &$range)
					{
						$base = stampfuncTODO($one);
						$ret = MAX($start,($base+$range[0]));
						if(($base+$range[1]) >= ($ret+$length))
						{
							unset($one);
							unset($range);
							return $ret;
						}
					}
					unset($range);
				}
			}
			unset($one);
		}
		return FALSE;
	}

	/**
	CheckConstraint:

	Determine whether bracket calendar-constraint has correct syntax.
	Returns TRUE if no constraint exists.

	@mod: reference to current module-object
	@bdata: reference to array of data for current bracket
	*/
	function CheckCondition(&$mod,&$bdata)
	{
		if($bdata['available'] == FALSE)
			return TRUE;
		$conds = self::_GetConditions($bdata['available']);
		//TODO PARSE CONDS
		return FALSE;
	}
}

?>
