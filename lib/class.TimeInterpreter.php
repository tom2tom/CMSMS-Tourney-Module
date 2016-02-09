<?php
/*
This file is a class for CMS Made Simple (TM).
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>

This file is free software; you can redistribute it and/or modify it under
the terms of the GNU Affero General Public License as published by the
Free Software Foundation; either version 3 of the License, or (at your option)
any later version.

This file is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details. If you don't have a copy
of that license, read it online at: www.gnu.org/licenses/licenses.html#AGPL
*/
class TimeInterpreter
{
	/*No checks here for valid parameters - assumed done before
		$start is bracket-local timestamp
	*/
	function GetSunData(&$bdata,$start)
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

	/* Get no. in {0.0..24.0} representing the actual or notional slot-length
		to assist interpretation of ambiguous hour-of-day or day-of-month values
	*/
	function GetIntervalHours(&$bdata)
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

	/*
	$times: reference to array of timestamp pairs, each member representing a
		period-start and period-end from the relevant interval-descriptor (TODO CHECK end+1??)
	$sameday: boolean, whether ...
	Returns: $X or FALSE
	*/
	function timecheck(&$times,$sameday)
	{
		$tstart = $TODO;
		$length = $TODO;
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

}

?>
