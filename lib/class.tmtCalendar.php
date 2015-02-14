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

Availability constraints are specified in a string of the form condition[,condition2...]
If more than 1 condition is specified, their order is irrelevant. Whitespace and
newlines in the string are ignored.

Each condition in the string is of the form [period][@][time]
The separator '@' is mandatory if both period and time are specified.

The period and/or time may be
 a 'singleton' value; or
 x,y[,...] which specifies a sequence of individual values, in any order, all of
 which are valid; or
 x..y which specifies an inclusive range of values, all of which are valid, and
 normally y should be chronologically after x (except when spanning a period-end
 e.g. 23:30..2:30 or Friday..Sunday or December..February).

Merely numeric sequences and ranges are supported, and in that case, y must be
greater than x.

Any singleton value may be preceded by a bracketed scope-expander of any of the
above types.
Any sequence or range of values may itelf be bracketed and preceded by a bracketed
scope-expander of any of the above types.

A value which is a date is expected to be formatted as yyyy[-[m]m[-[d]d]] i.e.
month and day are optional, and leading 0 is not needed for month or day value.
A value which is a month- or day-name is expected to be formatted in accord with
a specified, or the default, locale. Month- and/or day-names may be abbreviated
if the relevant option is specified.

The term 'week' isn't system-pollable, and must be translated manually.

A value which is a time is expected to be formatted as [h]h[:[m]m] i.e. 24-hour
format, leading '0' optional, minutes optional but if present, the separator is ':'.
A time-value which is not a range is assumed to represent a range of 1-hour less 1-second.
Sunrise/sunset-related times are supported, if relevant latitude and longitude
data are provided. Time-values like sunrise/sunset +/- time-interval are also supported.

Negative-value parameters count backwards from next-longest-period-end.

If multiple conditions are specified, specific date(s) prevail over everything else,
named month(s) prevail over unqualified years, named week(s) prevail over
unqualified months, and so on down the time scale.

It will be sufficient for any combination of conditions to be satisfied.
If no condition is specified, no constraint applies.

Examples:
Date descriptors
 2000 or 2020,2024 or 2000..2005 or 2000-6 or 2000-10..2001-3 or 2000-9-1
   or 2000-10-1..2000-12-31
 for month(s)-of-any-year: January or March,September or July..December
Week descriptors
 for week(s)-of-any-month (some of which may not be 7-days): 2(week) or -1(week)
   or (2..3)(week)
 for week(s)-of-named-month: 2(week(March)) or or (1..3)(week(July,August))
   or (-2,-1)(week(April..July))
Day descriptors
 for day(s)-of-any-month: 1 or -2 or 15,18,-1 or 1..10 or 2..-1 or -3..-1 or
    1(Sunday) or -1(Wednesday..Friday) or (1..3)(Friday,Saturday)
 for day(s)-of-any-week: Monday or Monday,Wednesday,Friday or Wednesday..Friday
Time descriptors
 9 or 2:30 or 9,12,15:15 or 12..23 or 6..15:30 or sunrise..16 or 9..sunset-3:30

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
	private static function _cmp_dblocks($a,$b)
	{
		if(is_array($a))
		{
			if(is_array($b))
				return func($a[0],$b[0]);
			elseif($a[0] === $b)
				return 1; //range-arrays last
			return func($a[0],$b);
		}
		elseif(is_array($b))
		{
			if($a === $b[0])
				return -1; //range-arrays last
			return func($a,$b[0]);
		}
		else
			return func($a,$b);
	}

	/**
	_ParseDayCondition:
	
	Get array of day-range(s), each value therein an integer relative to start of
	current year. Assumes no whitespace in @str.

	@str: the string to be parsed
	*/
	private function _ParseDayCondition($str)
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
		if($count($parts) > 1)
			usort($parts, array('tmtCalendar','_cmp_dblocks'));
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
	private static function _cmp_blocks($a,$b)
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
	 'lat'=>latitude for calc
	 'long'=>longitude for calc
	 'zone'=>name of timezone (for calc GMT offset)
	 'day'=>timestamp for day being processed
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
					$offs = 0; //TODO calc timezone offset func($sunstuff['zone']) +/- custom offset
					return date_sunrise($sunstuff['day'],SUNFUNCS_RET_STRING,
						$sunstuff['lat'],$sunstuff['long'],90+(50/60),$offs);
			}
			if(strpos($str,$sunstuff['rise']) === 0)
			{
					$offs = 0; //TODO calc timezone offset func($sunstuff['zone']) +/- custom offset
					return date_sunrise($sunstuff['day'],SUNFUNCS_RET_STRING,
						$sunstuff['lat'],$sunstuff['long'],90+(50/60),$offs);
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
		'lat'=>latitude for calc
		'long'=>longitude for calc
		'zone'=>name of timezone (for calc GMT offset)
		'day'=>timestamp for day being processed
	)
	*/
	private function _ParseHourCondition($str,$sunstuff=FALSE)
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
				elseif(strcmp($s,$e) > 0)
				{
					if (1)
						$one = array($e,$s);
					else
					{
						//TODO support midnight-span
					}
				}
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
		if($count($parts) > 1)
			usort($parts, array('tmtCalendar','_cmp_blocks'));
		//coalesce
//	$tvals = array(0,0,0,1,1,date("Y")); //start of current year (CHECKME or 1970?)
//	$base = mktime($tvals);
		$base = mktime(0,0,0,1,1);
		$blocks = array(array(-1,-1)); //always have something to compare
		foreach($parts as &$one)
		{
			if(is_array($one))
			{
/*			$tvals[0] = func($one[0]); //hour
				$tvals[1] = func($one[0]); //minute
				$stamp = mktime($tvals) - $base;
				$tvals[0] = func($one[1]);
				$tvals[1] = func($one[1]);
				$stend = mktime($tvals) - 1 - $base;
*/
				$stamp = strtotime($one[0],$base); //no need for time localisation?
				$stend = strtotime($one[1],$base);
			}
			else
			{
/*			$tvals[0] = func($one); //hour
				$tvals[1] = func($one); //minute
				$stamp = mktime($tvals) - $base;
*/
				$stamp = strtotime($one,$base);
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
	SlotComplies:

	Determine whether the (timestamp) interval @start to @start + @length satisfies
	constaints specified in	relevant fields in @bdata.

	@mod: reference to current module-object
	@bdata: reference to array of data for current bracket 	
	@start: preferred start time (timestamp)
	@length: optional length (seconds) of time period to be checked, default 0
	*/
	function SlotComplies(&$mod,&$bdata,$start,$length=0)
	{
		$calcond = $bdata['match_days'];
		if($calcond == FALSE)
			return TRUE;
		if(0)
		{
			//TODO use cached $this->dayblocks if appropriate ?
			$candays = self::_ParseDayCondition($daycond);
			foreach ($candays as $block)
			{
				if($timecond)
				{
					//TODO use cached $this->timeblocks if appropriate ?
					$sunstuff = FALSE; //TODO
					$cantimes = self::_ParseHourCondition($timecond,$sunstuff);
				}
				//TODO find whether start..start+length fits within $candays() + $cantimes()
			}
		}
		return FALSE;
	}

	/**
	SlotStart:
	
	Get start-time (timestamp) matching constraints specified in relevant fields in
	@bdata, and	starting no sooner than @start, or ASAP within @later days after
	the one including @start, and where the available time is at least @length.
	Returns 0 if no such time is available within the requested interval.

	@mod: reference to current module-object
	@bdata: reference to array of data for current bracket
	@start: preferred start time (timestamp)
	@length: optional length (seconds) of time period to be discovered, default 0
	@later: optional, no. of extra days to check after the one including @start, default 365
	*/
	function SlotStart(&$mod,&$bdata,$start,$length=0,$later=365)
	{
		$calcond = $bdata['match_days'];
		if($calcond == FALSE)
			return $start;
		if(0)
		{
			//TODO use cached $this->dayblocks if appropriate ?
			$candays = self::_ParseDayCondition($daycond);
			foreach ($candays as $block)
			{
				if($timecond)
				{
					//TODO use cached $this->timeblocks if appropriate ?
					$sunstuff = FALSE; //TODO
					$cantimes = self::_ParseHourCondition($timecond,$sunstuff);
				}
				//TODO find in candays + cantimes next interval matching start..start+length 
			}
		}
		return 0;
	}

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
	*/
	function MonthNames(&$mod,$which)
	{
		$ret = array();
		if (!is_array($which))
			$which = array($which);
		foreach($which as $month)
		{
			switch($month)
			{
				//TODO
//				case 1:
//				 $k = ;
//				 break;
				default:
				 $k = FALSE;
				 break;
			}
			if($k)
				$ret[$month] = $mod->Lang($k);
		}
		if (count($which) > 1)
			return $ret;
		return(reset($ret));
	}

	/**
	DayNames:

	Get one, or array of, translated day-name(s)

	@mod: reference to current module object
	@which: 1 (for Sunday) .. 7 (for Saturday), or array of such indices
	*/
	function DayNames(&$mod,$which)
	{
		$ret = array();
		if (!is_array($which))
			$which = array($which);
		foreach($which as $day)
		{
			switch($day)
			{
				//TODO
//				case 1:
//				 $k = ;
//				 break;
				default:
				 $k = FALSE;
				 break;
			}
			if($k)
				$ret[$day] = $mod->Lang($k);
		}
		if (count($which) > 1)
			return $ret;
		return(reset($ret));
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
		if (!is_array($which))
			$which = array($which);
		$ret = array();
		foreach($which as $period)
		{
			switch($period)
			{
			case 0:
				$k = 'none'; //Lang() key
				break;
			case 1:
				$k = 'minute';
				break;
			case 2:
				$k = 'hour';
				break;
			case 3:
				$k = 'day';
				break;
			case 4:
				$k = 'week';
				break;
			case 5:
				$k = 'month';
				break;
			case 6:
				$k = 'year';
				break;
			default:
				continue;
			}
			if($plural && ($period > 0))
				$k .= 's';
			$v = $mod->Lang($k);
			if($cap)
				$ret[$k] = ucfirst($v); //for current locale
			else
				$ret[$k] = $v;
		}
		if (count($which) > 1)
			return $ret;
		return(reset($ret));
	}

	/**
	CheckConstraint:

	Determine whether @str has correct syntax for a calendar-constraint.

	@mod: reference to current module-object
	@bdata: reference to array of data for current bracket
	@str: condition-string to be processed
	@type: enumerator - 1 for time, 2 for day or greater
	*/
	function CheckCondition(&$mod,&$bdata,$str,$type)
	{
		if($str == FALSE)
			return TRUE;
		//TODO
		if($type == 1)
		{
		}
		else
		{
		}
		return FALSE;
	}
}

?>
