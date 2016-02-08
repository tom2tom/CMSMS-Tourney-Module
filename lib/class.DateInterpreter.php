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
class DateInterpreter
{
	/* *
	MonthWeekDays:
	@year: numeric year e.g. 2000
	@month: numeric month 1..12 in @year
	@dmax: 1-based index of last day in @month
	@dow: index of wanted day-of-week, 0 (for Sunday) .. 6 (for Saturday) c.f. date('w'...)
	Returns: array of integers, each a day-of-year in @year
	*/
	private function MonthWeekDays($year,$month,$dmax,$dow)
	{
		//first day in $year/$month as day-of-year
		$st = gmmktime(0,0,1,$month,1,$year);
		$base = (int)date('z',$st);
		//first day in $year/$month as: 0 = Sunday .. 6 = Saturday
		$firstdow = (int)date('w',$st);
		//0-based offset to first wanted day
		$d = $dow - $firstdow;
		if($d < 0)
			$d += 7;

		$days = array();
		for($i = $d; $i < $dmax; $i += 7) //0-based, so NOT <= $dmax
			$days[] = $base + $i;

		return $days;
	}

	/* *
	MonthDay:
	@year: numeric year e.g. 2000
	@month: numeric month 1..12 in @year
	@dmax: 1-based index of last day in @month
	@count: index of wanted day-of-month -5..-1,1..5
	@dow: index of wanted day-of-week, 0 (for Sunday) .. 6 (for Saturday) c.f. date('w'...)
	Returns: integer, a day-of-year in @year, or -1 upon error
	*/
	private function MonthDay($year,$month,$dmax,$count,$dow)
	{
		//first day in $year/$month as: 0 = Sunday .. 6 = Saturday
		$st = gmmktime(0,0,1,$month,1,$year);
		$firstdow = (int)date('w',$st);
		//offset to 1st instance of the wanted day
		$d = $dow - $firstdow;
		if($d < 0)
			$d += 7;
		if($count < 0)
		{
			$cmax = (int)(($dmax - $d)/7); //no. of wanted days in the month
			$count += 1 + $cmax;
			if($count < 0 || $count > $cmax)
				return -1;
		}
		if($count > 0)
		{
			$d += ($count -1) * 7;
			if($d >= $dmax)
				return -1;
		}
		else
			return -1;
		//first day in $year/$month as day-of-year
		$base = (int)date('z',$st);
		return $d + $base;
	}

	/* *
	WeeksDays:
	@year: numeric year e.g. 2000
	@month: numeric month 1..12 in @year
	@week: array if numeric week(s) -5..-1,1..5 in @year AND @month
	@dmax: 1-based index of last day in @month
	@dow: index of wanted day-of-week, 0 (for Sunday) .. 6 (for Saturday) c.f. date('w'...)
	Returns: array of integers, each a day-of-year in @year
	*/
	private function WeeksDays($year,$month,$week,$dmax,$dow)
	{
		//first day in $year/$month as day-of-year
		$st = gmmktime(0,0,1,$month,1,$year);
		$base = (int)date('z',$st);
		//first day in $year/$month as: 0 = Sunday .. 6 = Saturday
		$firstdow = (int)date('w',$st);
		//count of part/whole Sun..Sat weeks in $month, populated on demand
		$wmax = 0;
		$days = array();

		foreach($week as $w)
		{
			if($w < 0)
			{
				if($wmax == 0)
				{
					$d = 6 - $firstdow;	//offset to Saturday/end of 1st week
					$wmax = 1 + ceil(($dmax-$d)/7);
				}
				$w += $wmax + 1;
			}

			$d = $dow - $firstdow + ($w-1) * 7;
			if($d >= 0 && $d < $dmax) //0-based, so NOT <= $dmax
				$days[] = $base + $d;
		}
		return array_unique($days,SORT_NUMERIC);
	}

	/* *
	YearDays:
	@year: year or array of them or ','-separated series of them
	  Each year is 4-digit e.g. 2000 or 2-digit e.g. 00 or anything else that
	  can be validly processed via date('Y')
	@month: optional tokenised month(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric 1..12 or with 'M' prefix i.e. M1..M12
		FALSE means all months in @year
	@week: optional tokenised weeks(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric -5..-1,1..5 or with 'W' prefix i.e. W-5..W-1,W1..W5
		FALSE means all DAYS in @month AND @year
	@day: optional tokenised day(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric -31..-1,1..31 or with 'D' prefix i.e. D1..D7
		FALSE means all days in @week (if any) AND @month AND @year
	Returns: array of 2-member arrays, or FALSE upon error. In each member,
	 1st array member is the year (a validated, 4-digit integer),
	 2nd member is array of integers, each a 0-based day-of-year index in the year.
	*/
	private function YearDays($year,$month=FALSE,$week=FALSE,$day=FALSE)
	{
		//verify and interpret arguments
		$now = FALSE;
		if(!is_array($year) && strpos($year,',') !== FALSE)
			$year = explode(',',$year);

		if(is_array($year))
		{
			foreach($year as &$one)
			{
				$t = trim($one,' ,');
				if(is_numeric($t))
				{
					$one = (int)$t;
					if($one < 100)
					{
						if($now == FALSE)
							$now = getdate();
						$one += 100 * (int)($now['year']/100);
					}
				}
				else
				{
					$t2 = date_parse($t); //PHP 5.2+
					if($t2)
						$one = $t2['year'];
					else
						$one = FALSE;
				}
			}
			unset($one);
			$year = array_unique(array_filter($year),SORT_NUMERIC);
			$t = count($year);
			if(t == 0 || ($t > 1 && !sort($year,SORT_NUMERIC)))
				return FALSE;
		}
		elseif(is_numeric($year))
		{
			$t = (int)$year;
			if($t < 100)
			{
				if($now == FALSE)
					$now = getdate();
				$t += 100 * (int)($now['year']/100);
			}
			$year = array($t);
		}
		else
		{
			$t = date_parse($year); //PHP 5.2+
			if($t)
				$year = array($t['year']);
			else
				return FALSE;
		}

		if($month)
		{
			if(!is_array($month) && strpos($month,',') !== FALSE)
					$month = explode(',',$month);

			if(is_array($month))
			{
				foreach($month as &$one)
				{
					$t = trim($one,' M,');
					if(is_numeric($t) && $t > 0 && $t < 13)
						$one = (int)$t;
					else
						$one = FALSE;
				}
				unset($one);
				$month = array_unique(array_filter($month),SORT_NUMERIC);
				$t = count($month);
				if($t == 0 || ($t > 1 && !sort($month,SORT_NUMERIC)))
					return FALSE;
			}
			else
			{
				$t = trim($month,' M,');
				if(is_numeric($t) && $t > 0 && $t < 13)
					$month = array((int)$t);
				else
					return FALSE;
			}
		}
		else
			$month = range(1,12);

		if($week)
		{
			if(!is_array($week) && strpos($week,',') !== FALSE)
					$week = explode(',',$week);

			if(is_array($week))
			{
				foreach($week as &$one)
				{
					$t = trim($one,' W,');
					if(is_numeric($t) && $t > -6 && $t != 0 && $t < 6)
						$one = (int)$t;
					else
						$one = FALSE;
				}
				unset($one);
				$week = array_unique(array_filter($week),SORT_NUMERIC);
				$t = count($week);
				if($t == 0 || ($t > 1 && !sort($week,SORT_NUMERIC)))
					return FALSE;
				if($t > 1)
				{
					//rotate all -ve's to end
					while(($t = reset($week)) < 0)
					{
						array_shift($week);
						$week[] = $t;
					}
				}
			}
			else
			{
				$t = trim($week,' W,');
				if(is_numeric($t) && $t > -6 && $t != 0 && $t < 6)
					$week = array((int)$t);
				else
					return FALSE;
			}
		}
		else
			$week = array(); //default no-weeks i.e. use all specified days

		if($day)
		{
			if(!is_array($day) && strpos($day,',') !== FALSE)
				$day = explode(',',$day);

			if(is_array($day))
			{
				foreach($day as &$one)
				{
					$t = trim($one,' ,');
					if(is_numeric($t) && $t > -32 && $t != 0 && $t < 32)
						$one = (int)$t;
					elseif(($p = strpos($t,'D')) !== FALSE)
					{
						$t2 = substr($t,$p+1);
						if(is_numeric($t2) && $t2 > 0 && $t < 8) //SYNTAX FOR e.g. LAST Sunday: -1D1
							$one = $t;
						else
							$one = FALSE;
					}
					else
						$one = FALSE;
				}
				unset($one);
				$day = array_unique(array_filter($day));
				if(!$day) //actual days-array sorted before reutrn || !sort($day,SORT_NUMERIC))
					return FALSE;
				//rotate all -ve's to end
/*				reset($day);
				while(($t = $day[0]) < 0)
				{
					array_shift($day);
					$day[] = $t;
				}
*/
			}
			else
			{
				$t = trim($day,' ,');
				if(is_numeric($t) && $t > -32 && $t != 0 && $t < 32)
					$day = array((int)$t);
				elseif(($p = strpos($t,'D')) !== FALSE)
				{
					$t2 = substr($t,$p+1);
					if(is_numeric($t2) && $t2 > 0 && $t < 8)
						$day = array($t);
					else
						return FALSE;
				}
				else
					return FALSE;
			}
		}
		elseif($week)
			$day = array('D1','D2','D3','D4','D5','D6','D7'); //all days of the week(s)
		else
			$day = range(1,31); //all days

		$ret = array();
		foreach($year as $y)
		{
			$yeardays = array();
			foreach($month as $m)
			{
				$dmax = (int)date('t',gmmktime(0,0,1,$m,1,$y)); //days in month
				foreach($day as $d)
				{
					if(($p = strpos($d,'D')) !== FALSE)
					{
						$t = (int)substr($d,$p+1) - 1; //D1 >> 0 etc
						$c = ($p > 0) ? (int)substr($d,0,$p) : 0;
						if($c != 0)
						{
							$t2 = self::MonthDay($y,$m,$dmax,$c,$t);
							if($t2 >= 0)
								$yeardays[] = $t2;
						}
						elseif($week)
						{
							$yeardays = array_merge($yeardays,self::WeeksDays($y,$m,$week,$dmax,$t));
//							$dbg = self::WeeksDays($y,$m,$week,$dmax,$t);
//							$yeardays = array_merge($yeardays,$dbg);
						}
						else
						{
							$yeardays = array_merge($yeardays,self::MonthWeekDays($y,$m,$dmax,$t));
//							$dbg = self::MonthWeekDays($y,$m,$dmax,$t);
//							$yeardays = array_merge($yeardays,$dbg);
						}
						continue;
					}

					if(is_numeric($d) && $d < 0)
						$d += $dmax + 1;
					if($d <= $dmax)
					{
						$st = gmmktime(0,0,1,$m,$d,$y);
						$yeardays[] = (int)date('z',$st);
					}
				}
				if($yeardays)
					sort($yeardays,SORT_NUMERIC);
			}
			$ret[] = array($y,$yeardays);
		}
		return $ret;
	}

	/*
	This is used for testing only
	$dformat may include many (not all) character(s) understood by PHP date()
	If it includes 'z', the corresponding element of $dvalue must be 1-based
	$dvalue date-time string consistent with $dformat
	*/
	private function isodate_from_format($dformat,$dvalue)
	{
		$sformat = str_replace(
			array('Y' ,'M' ,'m' ,'d' ,'H' ,'h' ,'i' ,'s' ,'a' ,'A' ,'z'),
			array('%Y','%b','%m','%d','%H','%I','%M','%S','%P','%p','%j'),$dformat);
		$parts = strptime($dvalue,$sformat); //PHP 5.1+
		return sprintf('%04d-%02d-%02d %02d:%02d:%02d',
			$parts['tm_year'] + 1900,  //tm_year = relative to 1900
			$parts['tm_mon'] + 1,      //tm_mon = 0-based
			$parts['tm_mday'],
			$parts['tm_hour'],
			$parts['tm_min'],
			$parts['tm_sec']);
	}
	/*
	YearDays($year,$month=FALSE,$week=FALSE,$day=FALSE)
	@year: year or array of them or ','-separated series of them
	  Each year is 4-digit e.g. 2000 or 2-digit e.g. 00 or anything else that
	  can be validly processed via date('Y')
	@month: optional tokenised month(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric 1..12 or with 'M' prefix i.e. M1..M12
		FALSE means all months in @year
	@week: optional tokenised weeks(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric -5..-1,1..5 or with 'W' prefix i.e. W-5..W-1,W1..W5
		FALSE means all DAYS in @month AND @year
	@day: optional tokenised day(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric -31..-1,1..31 or with 'D' prefix i.e. D1..D7
		FALSE means all days in @week (if any) AND @month AND @year
	*/
	function tester($year,$month,$week,$day)
	{
		$ret = array();
		$dt = new DateTime('1-1-2000',new DateTimeZone('UTC'));
		$data = self::YearDays($year,$month,$week,$day);
		foreach($data as $row)
		{
			$yr = $row[0];
			$days = $row[1];
			foreach($days as $doy)
			{
				$d = sprintf('%03d',$doy+1);	//downstream strptime() expects padded, 1-based, day-of-year
				$newdate = self::isodate_from_format('Y z',$yr.' '.$d);
				$dt->modify($newdate);
				$ret[] = $dt->format('D j M Y');
			}
		}
		return $ret;
	}

}

?>
