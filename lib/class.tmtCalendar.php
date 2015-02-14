<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Class: tmtCalendar.
*/
class tmtCalendar
{
	private $dayblocks;
	private $timeblocks;

	/**
	CheckCondition:

	Determine whether $str has correct syntax for a calendar-condition

	str condition-string to be processed
	type enumerator - 1 for hours, 2 for days, 3 for longer
	*/
	function CheckCondition($str,$type)
	{
		if($str == FALSE)
			return TRUE;
		//TODO
		return FALSE;
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
				//TODO ensure [0] is before [1]
			}
		}
		unset($one);
		return $parts;
	}

	/**
	_ParseDayCondition:
	$str: the string to be parsed

	Populate self::dayblocks with days-range array(s), each value therein an
	integer relative to start of current year

	Assumes no whitespace in $str.
	*/
	private function _ParseDayCondition($str)
	{
		$parts = self::_SplitCondition($str);
	}

	//Sort array in which any member(s) may be a range-array. All strings, caseless.
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

/*
	$sunstuff = FALSE, or
	array(
		'rise'=>translated 'sunrise' string
		'set'=>translated 'sunset' string
		'lat'=>latitude for calc
		'long'=>longitude for calc
		'zone'=>name of timezone (for calc GMT offset)
		'day'=>timestamp for day being processed
	)
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
	$str: the string to be parsed
	$sunstuff: optional array of sunrise/set calculation parameters as below, default FALSE
	array(
		'rise'=>translated 'sunrise' string as would appear in module parameter value
		'set'=>translated 'sunset' string as would appear in module parameter value
		'lat'=>latitude for calc
		'long'=>longitude for calc
		'zone'=>name of timezone (for calc GMT offset)
		'day'=>timestamp for day being processed
	)

	Populate self::timeblocks with seconds-range array(s), each value therein a
	timestamp relative to start of current year

	Assumes no whitespace in $str.
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
		$this->timeblocks = $blocks;
	}

	/**
	SlotComplies:

	Determine whether the (timestamp) interval $start to $start+$length satisfies
	conditions specified in	$daycond and $hourcond.

	daycond: calendar-condition string
	hourcond: calendar-condition string
	start: preferred start time (timestamp)
	length: optional length (seconds) of time period to be checked, default 0
	*/
	function SlotComplies($daycond,$hourcond,$start,$length=0)
	{
		if($daycond == FALSE && $hourcond == FALSE)
			return TRUE;
		if($daycond)
		{
			//TODO [re]init $this->dayblocks?
			self::_ParseDayCondition($daycond);
		}
		elseif ($this->dayblocks)
		{
			unset($this->dayblocks);
			$this->dayblocks = FALSE;
		}
		if($hourcond)
		{
			//TODO [re]init $this->timeblocks?
			$sunstuff = FALSE; //TODO
			self::_ParseHourCondition($hourcond,$sunstuff);
		}
		elseif ($this->timeblocks)
		{
			unset($this->timeblocks);
			$this->timeblocks = FALSE;
		}
		//TODO find whether start..start+length fits within dayblocks + timeblocks
		return FALSE;
	}

	/**
	SlotStart:
	
	Get start-time (timestamp) matching conditions specified in $daycond, $hourcond
	and	starting no sooner than $start, or optionally ASAP after $start if
	$later = TRUE, and where the available time is at least $length.
	Returns 0 if no such time is available.
	
	daycond: calendar-condition string
	hourcond: calendar-condition string
	start: preferred start time (timestamp)
	length: optional length (seconds) of time period to be discovered, default 0
	later: optional whether a start-time after @start is acceptable, default TRUE	
	*/
	function SlotStart($daycond,$hourcond,$start,$length=0,$later=TRUE)
	{
		if($daycond == FALSE && $hourcond == FALSE)
			return $start;
		self::_ParseDayCondition($daycond);
		$sunstuff = FALSE; //TODO
		self::_ParseHourCondition($hourcond,$sunstuff);
		//TODO find in dayblocks + timeblocks next interval matching start..start+length 
		return 0;
	}

	/**
	AdminMonthNames:
	Get one, or array of, localised month-name(s)
	This is for admin use, the frontend locale may not be the same and/or supported
	by the server.

	which: 1 (for January) .. 12 (for December), or array of such indices
	full: optional whether to get long-form name default TRUE
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
	Get one, or array of, localised day-name(s)
	This is for admin use, the frontend locale may not be the same and/or supported
	by the server.

	which: 1 (for Sunday) .. 7 (for Saturday), or array of such indices
	full: optional whether to get long-form name default TRUE
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
	
	mod: reference to current module object
	which: 1 (for January) .. 12 (for December), or array of such indices
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

	mod: reference to current module object
	which: 1 (for Sunday) .. 7 (for Saturday), or array of such indices
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

	mod: reference to current module object
	which: index 0 (for 'none'), 1 (for 'minute') .. 6 (for 'year'), or array of such indices
	plural: optional, whether to get plural form of the interval name(s), default FALSE
	cap: optional, whether to capitalise the first character of the name(s), default FALSE
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
	
}

?>
