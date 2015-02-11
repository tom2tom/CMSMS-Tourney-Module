<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Class: tmtCalendar.
*/
class tmtCalendar {

/*
	/**
	_TimeIntervals()
	Get array of characteristic time-intervals, ordinal keys & 'single-name' values
	Relevant [mainly] to slot length
	* /
	function _TimeIntervals()
	{
		return array('minute','quarterhour','halfhour','hour','halfday','day','week','month','year');
	}
	/**
	_DisplayIntervals()
	Get zoom-order array of characteristic time-intervals, ordinal keys & 'single-name' values
	Relevant [mainly] to bookings-tables display format
	* /
	function _DisplayIntervals()
	{
		return array('day','week','month','year');
	}

	/**
	AllDayTimes(&$mod)

	Assumes slot-length <= whole-day
	
	Get array of slot-start-strings for a day, suitable for a selector or table column
	* /

	function AllDayTimes(&$mod)
	{
		$slottimes = array();
		/*
		get 'slot-length' for resource
			from resource-data, revert to group-data, revert to default
		* /
		if (1) //slot length < half-day
		{
			/*
			get 'start-time' for resource
				from resource-data, revert to group-data, revert to default
				calculate if needed e.g. sunrise
			get 'end-time' for resource
				from resource-data, revert to group-data, revert to default
				calculate if needed e.g. sunset
			* /
			$tNow = mktime(0,0,0,1,1,2000,0);
			$tStart = $tNow + 'hh:mm'; //TODO
		  $tEnd = tNow + 'hh:mm'; //TODO
			for ($tNow = $tStart; $tNow <= $tEnd; $tNow += seconds-interval)
			{
				$slottimes[] = strftime('%k:%M', $tNow); //or '%l:%M %p'
			}
		}
		elseif (0)  //slot length = half-day
		{
			$slottimes[] = $mod->Lang('first');
			$slottimes[] = $mod->Lang('last');
		}
		else  //slot length = whole day
		{
			$slottimes[] = ''; //no description for whole-day in daily context
		}
		return $slottimes;
	}

	function ShowDays(&$mod)
	{
		//TODO
	}

	/**
	DaysString(&$mod,$field)
	Get UI-friendly version of $field, which is a value from a table
	'usabledays' field
	* /
	function DaysString(&$mod,$field)
	{
		//TODO
		return '';
	}

	/**
	AllHours(&$mod)

	Get array of hour-strings midnight..11pm, suitable for a selector
	* /
	function AllHours(&$mod)
	{
		$hours = array($mod->Lang('anyhour')=>24,$mod->Lang('midnight')=>0);
		$tStart = strtotime('01:00');
		$tEnd = strtotime('23:00');
		$h = 1;
		for ($tNow = $tStart; $tNow <= $tEnd; $tNow += 3600)
		{
			if ($h != 12)
				$key = date('g a',$tNow);
			else
				$key = $mod->Lang('midday');
			$hours[$key] = $h++;
		}
		return $hours;
	}

	/**
	HourNumbers($field)

	Get array of hour-values represented by $field, suitable for a selector
	$field a value from a table 'usablehours' field, normally with ';'-separated
	hour-values, any or all of 0 .. 23
	* /
	function HourNumbers($field)
	{
		if ($field == false || strpos($field, '24') !== false)
			return array(24);
		else
			return explode(';',$field,24);
	}

	/**
	HoursString(&$mod,$field)

	Get UI-friendly version of $field, which is a value from a table
	'usablehours' field, normally with ';'-separated hour-values,
	any or all of 0 .. 23
	* /
	function HoursString(&$mod,$field)
	{
		if ($field == false || strpos($field,'24') !== false)
			return $mod->Lang('anyhour');
		else
		{
			$am = date('g a',strtotime('01:00'));
			$pm = date('g a',strtotime('13:00'));
			$hours = explode(';',$field,24);
			foreach ($hours as &$h)
			{
				if ($h == '0')
					$h = $mod->Lang('midnight');
				elseif ($h == '12')
					$h = $mod->Lang('midday');
				elseif (intval($h) < 13)
					$h = str_replace('1',$h,$am);
				else
					$h = str_replace('1',$h,$pm);
			}
			return $implode(', ',hours);
		}
	}

	/**
	GetTimeOffset(&$mod,$remote_tz)
	Requires: PHP >= 5.2
	Get the offset from the origin timezone to the remote timezone, in seconds.
	$remote_tz a timezone identifier like 'Europe/London'
	* /
	function GetTimeOffset(&$mod,$remote_tz)
	{
		if ($remote_tz == false)
			return 0;
		$remote_dtz = new DateTimeZone($remote_tz);
    $offset = timezone_offset_get($remote_dtz, new DateTime());
		return $offset;
	}

	function GetOffsetTimeZone(&$mod)
	{
		$h = $mod->GetPreference('server_offset_hours');
		if ($h == 0)
			return $mod->TimeZone;

		$server_dtz = new DateTimeZone($mod->TimeZone);
		$server_dt = new DateTime('2000:01:01 00:00:00', $server_dtz);
		$server_offset = $server_dtz->getOffset($server_dt);		
		$target_offset = $server_offset + $h * 3600;
		//TODO find zone with this offet

		return $mod->TimeZone;
	}

	function Split($dtparts)
	{
		replace ' ',''
		explode ','
		foreach exploded
		{
			if has '..'...
			split on that > $rstart, $rend
		}
		return array
	}
	
	function interpret type ($dtpart)
	{
		return year/month/day/hour/hour+min
	}
	
	function back ($stamp, $dtpart, $n, $type)
	{
		back from eoy, eom, eod, eoh
		return stamp2
	}

	function suntime($stamp, $n, $type)
	{
		lat, long calc sunrise/set
		+/-
	}
	
	function range
	{
		allow - for hours weeks months
	}

	function inrange ($dt, $cond[])
	{
		optional array of conds
		while (get next interval which complies with $cond[])
		  if $dt inside
		    return true
		  next interval, from end of this interval
		return false
	}
	
	function nextrange ($dt, $cond='')
	{
		if ($cond == false)
			return 0;
		if (is_array($cond))
		{
		}
		else
		{
		}
		return array start,end where start >= dt
			& end >=  start + minlength
	}
	
	function partrange (ts, te, rstart, rend)
	{
	}
	
	
	setlocale(LC_TIME, $locale); //NB locale information is maintained per process, not per thread
	strftime("%A");


	setlocale(LC_*, 0); // = getlocale()
	$originalLocales = explode(";", setlocale(LC_ALL, 0));
	
//Do something here

    $originalLocales = array_diff ($originalLocales, array( //these will be returned by setlocale(LC_ALL, 0), but don't exist anymore.
            'LC_PAPER',
            'LC_NAME',
            'LC_ADDRESS',
            'LC_TELEPHONE',
            'LC_MEASUREMENT',
            'LC_IDENTIFICATION'
        );
	
        foreach ($originalLocales as $localeSetting) {
            if (strpos($localeSetting, "=") !== false) {
                list ($category, $locale) = explode("=", $localeSetting);
            } else {
                $category = LC_ALL;
                $locale   = $localeSetting;
            }
            setlocale(constant($category), $locale); //Using strings is deprecated.

	/**
	IntervalEnd:
	Get seconds-offset corresponding to which*count

	which index 0 (for 'none'), 1 (for 'minute') .. 6 (for 'year')
	count optional no. of periods, default 1
	* /
	function IntervalEnd($which,$count=1)
	{
			switch($which)
			{
			case 1:
				return $count*60;
			case 2:
				return $count*3600;
			case 3:
				return $count*86400;
			case 4:
				return $count*604800;
			case 5:
			//get length TODO
//				break;
			case 6:
			//get length TODO
//				break;
			default:
				return 0;
			}
	}
	
/ *
	$day = date('w');
	$week_start = date('m-d-Y', strtotime('-'.$day.' days'));
	$week_end = date('m-d-Y', strtotime('+'.(6-$day).' days'));

	$day contains a number from 0 to 6 representing the day of the week (Sunday = 0, Monday = 1, etc.).
	$week_start contains the date for Sunday of the current week as mm-dd-yyyy.
	$week_end contains the date for the Saturday of the current week as mm-dd-yyyy.
	shareimprove this answer

	$givenday = date("w", mktime(0, 0, 0, MM, dd, yyyy));

	This gives you the day of the week of the given date itself where 0 = Sunday and 6 = Saturday.
	From there you can simply calculate backwards to the day you want.
	
	date('l',int $timestamp) gives day of week
*/

	/**
	MonthNames:
	Get one, or array of, localised month-name(s)

	which: 1 (for January) .. 12 (for December), or array of such indices
	full: optional whether to get long-form name default TRUE
	*/
	function MonthNames($which,$full=TRUE)
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
	DayNames:
	Get one, or array of, localised day-name(s)

	which: 1 (for Sunday) .. 7 (for Saturday), or array of such indices
	full: optional whether to get long-form name default TRUE
	*/
	function DayNames($which,$full=TRUE)
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
