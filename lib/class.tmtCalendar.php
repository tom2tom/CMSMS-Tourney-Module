<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Class: tmtCalendar
*/

class tmtCalendar extends IntervalParser
{
	function __construct(&$mod)
	{
		parent::__construct($mod);
	}

	//No checks here for valid parameters - assumed done before
	//$start is bracket-local timestamp
	private function _GetSunData(&$bdata,$start)
	{
		$daystamp = floor($start/84600)*84600; //midnight
		$lat = $bdata['latitude']; //maybe 0.0
		$long = $bdata['longitude']; //ditto
		$zone = $bdata['timezone'];
		if(!$zone)
			$zone = $this->mod->GetPreference('time_zone','Europe/London'); //TODO valid pref?

		return array (
		 'day'=>$daystamp,
		 'lat'=>$lat,
		 'long'=>$long,
		 'zone'=>$zone
		);
	}

	//Get no. in {0.0..24.0} representing the actual or notional slot-length
	//to assist interpretation of ambiguous hour-of-day or day-of-month values
	private function _GetSlotHours(&$bdata)
	{
		if($bdata['placegap'])
		{
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
					break;
			}
		}
		//TODO if $bdata['startdate'] to $bdata['enddate'] short/< N days 
		// assume nominated values are hours, return appropriate value
		return 0.0;
	}

	private function _timecheck(&$times,$sameday)
	{
		foreach($times as &$range)
		{
			//TODO interpet any sun-related times
			$s = ($sameday) ? MAX($range[0],$tstart) : $range[0];
			//TODO support roll-over to contiguous day(s) & time(s)
			if($range[1] >= $s+$length)
			{
				unset($range);
				return $s;
			}
		}
		unset($range);
		return FALSE;
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

	@which: 0 (for Sunday) .. 6 (for Saturday), or array of such indices
	@short: optional, whether to get short-form name, default FALSE
	*/
	function DayNames($which,$short=FALSE)
	{
		$k = ($short) ? 'shortdays' : 'longdays';
		$all = explode(',',$this->mod->Lang($k));
		$c = count($all);

		if (!is_array($which))
		{
			if ($which >= 0 && $which < $c)
				return $all[$which];
			return '';
		}
		$ret = array();
		foreach ($which as $day)
		{
			if ($day >= 0 && $day < $c)
				$ret[$day] = $all[$day];
		}
		return $ret;
	}

	/**
	IntervalNames:

	Get one, or array of, translated time-interval-name(s)

	@which: index 0 (for 'none'), 1 (for 'minute') .. 6 (for 'year'), or array
		of such indices consistent with _TimeIntervals()
	@plural: optional, whether to get plural form of the interval name(s), default FALSE
	@cap: optional, whether to capitalise the first character of the name(s), default FALSE
	*/
	function IntervalNames($which,$plural=FALSE,$cap=FALSE)
	{
		$k = ($plural) ? 'multiperiods' : 'periods';
		$all = explode(',',$this->mod->Lang($k));
		array_unshift($all,$this->mod->Lang('none'));
		$c = count($all);

		if(!is_array($which))
		{
			if($which >= 0 && $which < $c)
			{
				if($cap)
					return ucfirst($all[$which]);
				else
					return $all[$which];
			}
			return '';
		}
		$ret = array();
		foreach($which as $period)
		{
			if($period >= 0 && $period < $c)
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
	specified in relevant fields in @bdata. Also returns FALSE if the
	interval-descriptor string is malformed.
	parent::CheckCondition() or ::ParseCondition() must be called before this func.

	@bdata: reference to array of data for current bracket 	
	@start: preferred start time (bracket-local timestamp)
	@length: optional length (seconds) of time period to be checked, default 0
	@laterdays: optional, no. of extra days to check after the one including @start, default 365
	*/
	function SlotComplies(&$bdata,$start,$length=0,$laterdays=365)
	{
		if($this->conds == FALSE)
			return FALSE;
/*
		$sunstuff = self::_GetSunData($bdata,$start);
		$maxhours = self::_GetSlotHours($bdata);
		$dstart = floor($start/86400);
		$dend = $dstart + $laterdays;
		$tstart = $start - $dstart;
		foreach($this->conds as &$cond)
		{
			//TODO
		}
		unset($cond);
*/
		return FALSE;
	}

	/**
	SlotStart:

	Get start-time (timestamp) matching constraints specified in relevant fields in
	@bdata, and	starting no sooner than @start, or ASAP within @later days after
	the one including @start, and where the available time is at least @length.
	Returns FALSE if no such time is available within the specified interval (or
	the availability-descriptor string is malformed).
	parent::CheckCondition() or ::ParseCondition() must be called before this func.

	@bdata: reference to array of data for current bracket
	@start: preferred/first start time (bracket-local timestamp)
	@length: optional length (seconds) of time period to be discovered, default 0
	@laterdays: optional, no. of extra days to check after the one including @start, default 365
	*/
	function NextSlotStart(&$bdata,$start,$length=0,$laterdays=365)
	{
		if($this->conds == FALSE)
			return FALSE;
/*
		$sunstuff = self::_GetSunData($bdata,$start);
		$maxhours = self::_GetSlotHours($bdata);
		$dstart = floor($start/86400);
		$dend = $dstart + $laterdays;
		$tstart = $start - $dstart;
		foreach($this->conds as &$cond)
		{
			$times = $cond[2];
			if(!$times)
				$times = array(0=>array(0,86399)); //whole day's worth of seconds
			if($cond[1] == FALSE) //time(s) on any day
			{
				$X = self::_timecheck($times,TRUE);
				if($X !== FALSE)
				{
					uset($cond);
					return $X; //TODO + $cond[1]>day-index * 86400 + zone offset seconds
				}
				if($laterdays > 0)
				{
					$X = self::_timecheck($times,FALSE);
					if($X !== FALSE)
					{
						uset($cond);
						return $X; //TODO + $cond[1]>day-index * 86400 + zone offset seconds
					}
				}
			}
			else
			{
				/*interpret $cond[1]
				  foreach day of interpreted
				    get day-index
						if IN $dstart to $dend inclusive
							$sameday = (day-index == $dstart);
							$X = self::_timecheck($times,$sameday);
							if($X !== FALSE)
							{
								uset($cond);
								return $X; //TODO + $cond[1]>day-index * 86400 + zone offset seconds
							}
				* /
			}
		}
		unset($cond);
*/
		return FALSE;
	}

}

?>
