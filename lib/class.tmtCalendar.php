<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Class: tmtCalendar
*/

class tmtCalendar extends Calendar
{
	function __construct(&$mod)
	{
		parent::__construct($mod);
	}
	
	//No checks here for valid parameters - assumed done before
	private function _GetSunData(&$bdata,$start)
	{
		$cond = $this->cleaned;
		if($cond)
		{
			$rise = 'R'; //alias for $this->mod->Lang('sunrise');
			$set = 'S'; //alias for $this->mod->Lang('sunset');
			if(strpos($cond,$rise) !== FALSE || strpos($cond,$set) !== FALSE)
			{
				if($bdata['timezone'])
					$zone = $bdata['timezone'];
				else
				{
					$zone = $this->mod->GetPreference('time_zone','');
					if(!zone)
						$zone = 'Europe/London'; //TODO BETTER e.g. func($bdata['locale'])
				}
				$lat = $bdata['latitude']; //maybe 0.0
				$long = $bdata['longitude']; //ditto
				$daystamp = floor($start/84600)*84600; //midnight
				return array (
				 'rise'=>$rise,
				 'set'=>$set,
				 'day'=>$daystamp,
				 'lat'=>$lat,
				 'long'=>$long,
				 'zone'=>$zone
				);
			}
		}
		return FALSE;
	}

	//Get no. in {0.0..24.0} representing the actual or notional slot-length
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

	/**
	SlotComplies:

	Determine whether the interval @start to @start + @length satisfies constraints
	specified in relevant fields in @bdata. Also returns FALSE if the
	availability-descriptor string is malformed.
	ParseCondition() must be called before this func.

	@bdata: reference to array of data for current bracket 	
	@start: preferred start time (bracket-local timestamp)
	@length: optional length (seconds) of time period to be checked, default 0
	@laterdays: optional, no. of extra days to check after the one including @start, default 365
	*/
	function SlotComplies(&$bdata,$start,$length=0,$laterdays=365)
	{
		if($this->conds == FALSE)
			return FALSE;
		$sunstuff = $this->_GetSunData($bdata,$start);
		$maxhours = $this->_GetSlotHours($bdata);
		foreach ($this->conds as $cond)
		{
			if(0) //TODO
				return TRUE;
		}
		return FALSE;
	}

	/**
	SlotStart:
	
	Get start-time (timestamp) matching constraints specified in relevant fields in
	@bdata, and	starting no sooner than @start, or ASAP within @later days after
	the one including @start, and where the available time is at least @length.
	Returns FALSE if no such time is available within the specified interval, or
	the availability-descriptor string is malformed.
	ParseCondition() must be called before this func.

	@bdata: reference to array of data for current bracket
	@start: preferred/first start time (bracket-local timestamp)
	@length: optional length (seconds) of time period to be discovered, default 0
	@laterdays: optional, no. of extra days to check after the one including @start, default 365
	*/
	function NextSlotStart(&$bdata,$start,$length=0,$laterdays=365)
	{
		if($this->conds == FALSE)
			return FALSE;
		$sunstuff = $this->_GetSunData($bdata,$start);
		$maxhours = $this->_GetSlotHours($bdata);
		foreach ($this->conds as $cond)
		{
			if(0) //TODO
				return TRUE;
		}
		return FALSE;
	}

}

?>
