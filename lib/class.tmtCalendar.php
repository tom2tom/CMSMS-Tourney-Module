<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Class: tmtCalendar.
*/
class tmtCalendar {

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
			}
		}
		unset($one);
		return $parts;
	}

	//assumes no whitespace in $str
	private function _ParseDayCondition($str)
	{
		$parts = self::_SplitCondition($str);
	}

	//assumes no whitespace in $str
	private function _ParseHourCondition($str)
	{
		$parts = self::_SplitCondition($str);
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
		//TODO
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
		//TODO
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
