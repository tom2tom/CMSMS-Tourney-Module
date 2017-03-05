<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
class tmtWhenRules extends tmtWhenRuleLexer
{
	public function __construct(&$mod)
	{
		parent::__construct($mod);
	}

	/*
	PeriodBlocks:
	Append to @starts[] and @ends[] pair(s) of timestamps in $bs..$be and
		consistent with @cond
	@cond: member of parent::conds[] with parsed components of an interval-descriptor
		=>['P'] will be populated, =>['T'] may be populated
	@bs: stamp for start of period being processed
	@be: stamp for end of period being processed
	@dtw: modifiable DateTime object for use in relative calcs
	@timeparms: reference to array of parameters from self::TimeParms
	@starts: reference to array of block-start timestamps to be updated
	@ends: ditto for block-ends
	*/
	private function PeriodBlocks($cond, $bs, $be, $dtw, &$timeparms, &$starts, &$ends)
	{
		$funcs = new tmtPeriodInterpreter();

		$sunny = FALSE;
		if ($cond['T']) {
			if (!is_array($cond['T']))
				$cond['T'] = array($cond['T']);
			foreach ($cond['T'] as $one) {
				if (is_array($one)) {
					foreach ($one as $t) {
						if (strpos($t,'RS') !== FALSE || strpos($t,'SS') !== FALSE) {
							$sunny = TRUE;
							break;
						}
					}
				} elseif (strpos($one,'RS') !== FALSE || strpos($one,'SS') !== FALSE) {
					$sunny = TRUE;
					break;
				}
			}
			$timeparms['sunny'] = $sunny;
			if ($sunny) {
				$stimes = FALSE;
			} else {
				//no need for day-specific time(s), cache day-relative timestamps once
				list($stimes,$etimes) = self::TimeBlocks($cond['T'],$bs,$dtw,$timeparms);
			}
		} else {
			$stimes = FALSE;
		}
/* $cond['F']:
 1 no period i.e. any (time-only)
 2 month(s) of any year June,July
 3 week(s) of any month (1,-1)week
 4 day(s) of any week Sun..Wed
 5 day(s) of any month 1,10,-2 OR 1(Sunday)
 6 specific year(s) 2020,2015
 7 month(s) of specific year(s) Jan(2010..2020) OR 2015-1 OR each 3 month(2000-1..2002-12)
 8 week(s) of specific month(s) 1(week(August,September)) OR each 2 week(2000-1..2000-12)
 9 week(s) of specific [month(s) and] year(s) 1(week(Aug..Dec(2020))) OR each 4 week(2000..2001)
10 day(s) of specific week(s)  Wed(2(week)) OR (Wed..Fri)(each 2(week))
11 day(s) of specific [week(s) and] month(s) 1(Aug) OR Wed((1,2)(week(June)))
	OR each 2 day(2000-1..2000-2) OR 2(Wed(June)) OR (1,-1)(Sat(June..August))
12 day(s) of specific [week(s) and/or month(s) and] year(s) Wed((1,-1)(week(June(2015..2018))))
13 specfic day/date(s) 2010-6-6 OR 1(Aug(2015..2020))
*/
		if (($stimes || $sunny) && $timeparms['type'] != 'day') { //periods > day not used here
			$dodays = TRUE;	//daywise analyis needed
		} else {
			switch ($cond['F']) {
				case 4:
				case 5:
				case 10:
				case 11:
				case 12:
				case 13:
					$dodays = TRUE; //descriptor includes specific day(s)
					break;
				default:
					$dodays = FALSE; //blockwise analyis
			}
		}

		if ($dodays) {
			switch ($cond['F']) {
			 case 1: //whole of $bs..$be
				$parsed = $funcs->BlockDays($bs,$be,$dtw);
				break;
			 case 2: //months(s) in any year in $bs..$be
			 case 7: //months(s) in specific year(s) in $bs..$be
				$parsed = $funcs->SpecificMonths($cond['P'],$bs,$be,$dtw);
				break;
			 case 3: //week(s) in any month in any year in $bs..$be
			 case 8: //week(s) in specific month(s) in $bs..$be
			 case 9: //week(s) in specific [month(s) and] year(s) in $bs..$be
				$parsed = $funcs->SpecificWeeks($cond['P'],$bs,$be,$dtw);
				break;
			 case 4: //day(s) of week in any week in $bs..$be
			 case 5: //day(s) of month in any month in $bs..$be
			 case 10: //day(s) in weeks(s) in $bs..$be
			 case 11: //day(s) in [weeks(s) and] month(s) in $bs..$be
			 case 12: //day(s) in weeks(s) and specific month(s) and specific year(s) in $bs..$be
				$parsed = $funcs->SpecificDays($cond['P'],$bs,$be,$dtw);
				break;
			 case 6: //year(s) in $bs..$be
				$parsed = $funcs->SpecificYears($cond['P'],$bs,$be,$dtw);
				break;
			 case 13: //specific day(s) in $bs..$be
				$parsed = $funcs->SpecificDates($cond['P'],$bs,$be,$dtw);
				break;
			 default:
				$parsed = FALSE;
				break;
			}

			if ($parsed) {
				$inc = new DateInterval('P1D');
				foreach ($parsed as $doy) {
					foreach ($doy as $daystart) {
						if ($sunny) {
							list($stimes,$etimes) = self::TimeBlocks($cond['T'],$daystart,$dtw,$timeparms);
						}
						if ($stimes) {
							foreach ($stimes as $i=>$st) {
								$starts[] = $daystart + $st;
								$ends[] = $daystart + $etimes[$i];
							}
						} else {
							$starts[] = $daystart;
							$dtw->setTimestamp($daystart);
							$dtw->add($inc);
							$ends[] = $dtw->getTimestamp()-1;
						}
					}
				}
			}
		} else { //blockwise analyis
			switch ($cond['F']) {
			 case 1: //whole block
				$starts[] = $bs;
				$ends[] = $be;
				break;
			 case 2: //months(s) in any year in $bs..$be
			 case 7: //months(s) in specific year(s) in $bs..$be
				$parsed = $funcs->SpecificMonths($cond['P'],$bs,$be,$dtw,TRUE);
				if ($parsed) {
					$inc = new DateInterval('P1M');
					foreach ($parsed as $som) {
						foreach ($som as $st) {
							$starts[] = $st;
							$dtw->setTimestamp($st);
							$dtw->add($inc);
							$st = $dtw->getTimestamp();
							if ($st <= $be) {
								$ends[] = $st-1;
							} else {
								$ends[] = $be;
								break;
							}
						}
					}
					//TODO merge adjacent months $blocks->MergeBlocks($starts,$ends);
				}
				break;
			 case 3: //week(s) in any month in any year in $bs..$be
			 case 8: //week(s) in specific month(s) in $bs..$be
			 case 9: //week(s) in specific [month(s) and] year(s) in $bs..$be
				$parsed = $funcs->SpecificWeeks($cond['P'],$bs,$be,$dtw,TRUE);
				if ($parsed) {
					$inc = new DateInterval('P7D');
					foreach ($parsed as $sow) {
						foreach ($sow as $st) {
							$starts[] = $st;
							$dtw->setTimestamp($st);
							$dtw->add($inc);
							$st = $dtw->getTimestamp();
							if ($st <= $be) {
								$ends[] = $st-1;
							} else {
								$ends[] = $be;
								break;
							}
						}
					}
					//TODO merge adjacent weeks $blocks->MergeBlocks($starts,$ends);
				}
				break;
			 case 6: //year(s) in $bs..$be
				$parsed = $funcs->SpecificYears($cond['P'],$bs,$be,$dtw,TRUE);
				if ($parsed) {
					foreach ($parsed as $soy) {
						foreach ($soy as $st) {
							$starts[] = $st;
							$dtw->setTimestamp($st);
							$dtw->modify('+1 year');
							$st = $dtw->getTimestamp();
							if ($st <= $be) {
								$ends[] = $st-1;
							} else {
								$ends[] = $be;
								break;
							}
						}
					}
					//TODO merge adjacent years $blocks->MergeBlocks($starts,$ends);
				}
				break;
			 default:
				break;
			}
		}
	}

	/*
	RelTime:
	Adjust @dtbase per @timestr
	@dtw: DateTime object representing 'base' datetime
	@timestr: relative time descriptor like [+-\d][H]H:[M]M
	Returns: nothing, but @dtbase is probably changed
	*/
	private function RelTime($dtw, $timestr)
	{
		if ($timestr) {
			$str = '';
			$nums = explode(':',$timestr,3);
			if (!empty($nums[0]) && is_numeric($nums[0]) && $nums[0] != '00')
				$str .= $nums[0].' hours';
			if (!empty($nums[1]) && is_numeric($nums[1]) && $nums[1] != '00') {
				if ($str)
					$str .= ' ';
				$str .= $nums[1].' minutes';
			}
			if ($str) {
				if (!($str[0] == '+' || $str[0] == '-'))
					$str = '+'.$str;
				$dtw->modify($str);
			}
		}
	}

	/*
	GetTimeBlock:
	Get timestamps for start & end of intra-day block represented by @timedata
	@timedata: a member of a $cond['T'] i.e. a string or 3-member array
	@bs: timestamp for start of day being procesed
	@be: timestamp for end of day being procesed
	@dtw: modifiable DateTime object for use in relative calcs
	@timeparms: reference to array of parameters from self::TimeParms
	Returns: 2-member array:
		[0] = @bs-relative blockstart or FALSE
		[1] = @bs-relative blockend or FALSE
	*/
	private function GetTimeBlock($timedata, $bs, $be, $dtw, &$timeparms)
	{
		$dtw->setTimestamp($bs);
		if (is_array($timedata)) {
			if ($timedata[0][0] == '!') {
				$timedata[0] = substr($timedata[0],1);
			}
			$parts = array($timedata[0],$timedata[2]);
		} else { //use default block length
			if ($timedata[0] == '!') {
				$timedata = substr($timedata,1);
			}
			self::RelTime($dtw,$timedata);
			$t = '+'.$timeparms['count'].' '.$timeparms['type'];
			if ($timeparms['count'] > 1)
				$t .= 's'; //pluralise
			$dtw->modify($t);
			$dtw->modify('-1 second');
			$parts = array($timedata,$dtw->format('G:i'));
		}
		//block-start
		if (strpos($parts[0],'RS') !== FALSE) { //involves sunrise
			/*
			Sunrise $zenith=90+50/60
			Twilights: see http://www.timeanddate.com/astronomy/about-sun-calculator.html
			Civilian twilight $zenith=96.0; <<< USE THIS FOR OUTDOORS THO' N/A >= +/-60.5° latitude
			Nautical twilight $zenith=102.0
			Astronomical twilight $zenith=108.0
			*/
			$tbase = date_sunrise($bs,SUNFUNCS_RET_TIMESTAMP,$timeparms['lat'],$timeparms['long'],96.0,0) +
				$timeparms['gmtoff'];
			$parts[0] = str_replace('RS','',$parts[0]);
		} elseif (strpos($parts[0],'SS') !== FALSE) { //involves sunset
			$tbase = date_sunset($bs,SUNFUNCS_RET_TIMESTAMP,$timeparms['lat'],$timeparms['long'],96.0,0) +
				$timeparms['gmtoff'];
			$parts[0] = str_replace('SS','',$parts[0]);
		} else { //not sunny
			$tbase = $bs;
		}
		$dtw->setTimestamp($tbase);
		self::RelTime($dtw,$parts[0]);
		$s = $dtw->getTimestamp();
		if ($s < $bs || $s > $be) {
			$s = 0;
		} else {
			$s -= $bs;
		}
		//block-end
		if (strpos($parts[1],'RS') !== FALSE) { //sunrise
			$tbase = date_sunrise($bs,SUNFUNCS_RET_TIMESTAMP,$timeparms['lat'],$timeparms['long'],96.0,0) +
				$timeparms['gmtoff'];
			$parts[1] = str_replace('RS','',$parts[1]);
		} elseif (strpos($parts[1],'SS') !== FALSE) { //sunset
			$tbase = date_sunset($bs,SUNFUNCS_RET_TIMESTAMP,$timeparms['lat'],$timeparms['long'],96.0,0) +
				$timeparms['gmtoff'];
			$parts[1] = str_replace('SS','',$parts[1]);
		} else { //no sun
			$tbase = $bs;
		}
		$dtw->setTimestamp($tbase);
		self::RelTime($dtw,$parts[1]);
		$e = $dtw->getTimestamp();
		if ($e < $bs || $e > $be) {
			$e = $be-$bs;
		} else {
			$e -= $bs;
		}
		if ($e > $s)
			return array($s,$e);
		return array(FALSE,FALSE);
	}

	/*
	TimeBlocks:
	Get block-timestamps for the entire day which includes @bs and consistent with @cond
	@cond: reference to 'T'-member of one of parent::conds[]
	@bs: stamp somwhere in the day being processed
	@dtw: modifiable DateTime object for use in relative calcs
	@timeparms: reference to array of parameters from self::TimeParms
	Returns: 2-member array,
	 [0] = array of start-stamps for blocks
	 [1] = array of corresponding end-stamps
	 The arrays have corresponding but not necessarily contiguous numeric keys,
	 or may be empty.
	*/
	private function TimeBlocks(&$cond, $bs, $dtw, &$timeparms)
	{
		$dtw->setTimestamp($bs);
		//ensure start of day
		$dtw->setTime(0,0,0);
		$bs = $dtw->getTimestamp();

		if ($timeparms['sunny']) {
			//offset-hours for sun-related calcs
			switch ($timeparms['zone']) {
			 case 'UTC':
			 case 'GMT':
			 case FALSE:
				$offs = 0;
				break;
			 default:
				$at = $dtw->format('Y-m-d');
				try {
					$tz = new \DateTimeZone($timeparms['zone']);
					$dt2 = new \DateTime($at,$tz);
					$offs = $dt2->format('Z'); //DST-specific, unlike DateTimeZone::getOffset
				} catch (Exception $e) {
					$offs = 0;
				}
				break;
			}
			$timeparms['gmtoff'] = $offs;
		}

		$blocks = new Blocks(); //CHECKME pass as arg?

		$dtw->modify('+1 day');
		$be = $dtw->getTimestamp() - 1;

		$starts = array();
		$ends = array();
		foreach ($cond as $one) {
			if (is_array($one)) {
				if ($one[0][0] == '!') { //exclusive rule
					continue;
				}
			} elseif ($one[0] == '!') {
				continue;
			}
			list($gets,$gete) = self::GetTimeBlock($one,$bs,$be,$dtw,$timeparms);
			if ($gete) { //$gets may be 0
				$starts[] = $gets;
				$ends[] = $gete;
			}
		}
		if (count($starts) > 1)
			$blocks->MergeBlocks($starts,$ends); //cleanup

		$nots = array();
		$note = array();
		foreach ($cond as $one) {
			if (is_array($one)) {
				if ($one[0][0] != '!') { //inclusive rule
					continue;
				}
			} elseif ($one[0] != '!') {
				continue;
			}
			list($gets,$gete) = self::GetTimeBlock($one,$bs,$be,$dtw,$timeparms);
			if ($gete) {
				$nots[] = $gets;
				$note[] = $gete;
			}
		}
		if ($nots) {
			if (count($nots) > 1)
				$blocks->MergeBlocks($nots,$note); //cleanup
			$blocks->DiffBlocks($starts,$ends,$nots,$note);
		}
		return array($starts,$ends);
	}

//~~~~~~~~~~~~~~~~ PUBLIC INTERFACE ~~~~~~~~~~~~~~~~~~

	/**
	TimeParms:
	Get @bdata-derived parameters for context-specific time calcs
	No checks here for valid parameters in @bdata - assumed done before
	@bdata: reference to array of data for a bracket
	Returns: array of parameters: latitude, longitude, zone etc
	*/
	public function TimeParms(&$bdata)
	{
	 	$num = 1;
		$type = 'hour';
		if ($bdata['playgaptype']) {
		 	$num = (int)$bdata['playgap'];
			$t = (int)$bdata['playgaptype'];
			if ($t > 2)
				$t = 2; //max interval-type in this context is day
			$periods = array('minute','hour','day');
			$type = $periods[$t];
		}

		$zone = $bdata['timezone'];
		if (!$zone)
			$zone = $this->mod->GetPreference('time_zone','UTC');

		return array (
		 'count'=>$num, //part default slot length, for DateTime modification
		 'type'=>$type, //other part
		 'sunny'=>FALSE, //whether sun-related calcs are needed
		 'lat'=>(float)$bdata['latitude'], //maybe 0.0
		 'long'=>(float)$bdata['longitude'], //ditto
		 'zone'=>$zone
		);
	}

	/**
	GetBlocks:
	Interpret parent::conds into seconds-blocks covering the interval @bs..@be
	@bs: UTC timestamp for start of period being processed, not necessarily a midnight
	@be: corresponding stamp for end of period, not necessarily a 1-before-midnight
	@timeparms: reference to array of parameters for sun-related time calcs
	@defaultall: optional boolean, whether to return, if parent::conds is not set,
	the whole interval as one block instead of empty arrays, default FALSE
	Returns: 2-member array:
	 [0] = timestamps representing block-starts
	 [1] = timestamps for corresponding block-ends (NOT 1-past)
	BUT both arrays will be empty upon error, or if nothing applies and
		$defaultall is FALSE
	*/
	public function GetBlocks($bs, $be, &$timeparms, $defaultall=FALSE)
	{
		$starts = array();
		$ends = array();
		if ($bs < $be && $this->conds) {
			$dtw = new \DateTime('@0',NULL);
			//stamps for period limit checks
			$blocks = new tmtBlocks();
			//for all inclusion-conditions, add to $starts,$ends
			foreach ($this->conds as &$cond) {
				if ($cond['P']) {
					if (is_array($cond['P'])) {
						if ($cond['P'][0][0] == '!') //TODO conform to actual parsing
							continue;
					} elseif ($cond['P'][0] == '!')
						continue;
				} elseif ($cond['T']) {
					if (is_array($cond['T'])) {
						if ($cond['T'][0][0] == '!')
							continue;
					} elseif ($cond['T'][0] == '!')
						continue;
				} else {
					continue; //should never happen
				}
				$gets = array();
				$gete = array();
				self::PeriodBlocks($cond,$bs,$be,$dtw,$timeparms,$gets,$gete);
				if ($gets) {
					list($gets,$gete) = $blocks->IntersectBlocks(array($bs),array($be),$gets,$gete);
					if ($gets) {
						if ($starts) {
							$starts = array_merge($starts,$gets);
							$ends = array_merge($ends,$gete);
							$blocks->MergeBlocks($starts,$ends);
						} else {
						$starts = $gets;
						$ends = $gete;
						}
						if (count($starts) == 1 && reset($starts) <= $bs && end($ends) >= $be) //all of $bs..$be now covered
							break;
					}
				}
			}
			unset($cond);
			if ($starts) {
				//for all exclusion-conditions, subtract from $starts,$ends
				foreach ($this->conds as &$cond) {
					if ($cond['P']) {
						if (is_array($cond['P'])) {
							if ($cond['P'][0][0] != '!') //TODO conform to actual parsing
								continue;
						} elseif ($cond['P'][0] != '!')
							continue;
					} elseif ($cond['T']) {
						if (is_array($cond['T'])) {
							if ($cond['T'][0][0] != '!')
								continue;
						} elseif ($cond['T'][0] != '!')
							continue;
					} else {
						continue; //should never happen
					}
					$gets = array();
					$gete = array();
					self::PeriodBlocks($cond,$bs,$be,$dtw,$timeparms,$gets,$gete);
					if ($gets) {
						list($gets,$gete) = $blocks->IntersectBlocks(array($bs),array($be),$gets,$gete);
						if ($gets) {
							list($starts,$ends) = $blocks->DiffBlocks($starts,$ends,$gets,$gete);
							if (!$starts) //none of $bs..$be now covered
								break;
						}
					}
				}
				unset($cond);
				if ($starts) {
					//sort block-pairs, merge when needed
					$blocks->MergeBlocks($starts,$ends);
				}
			}
		} elseif ($defaultall) {
			$starts[] = min($bs,$be);
			$ends[] = max($bs,$be);
		}
		return array($starts,$ends);
	}

	/**
	AllIntervals:
	Get pair(s) of timestamps representing time-block(s) in @bs..@be that conform to @descriptor
	@descriptor: interval-language string to be interpreted, or some variety of FALSE
	@bs: UTC timestamp for start of period being processed
	@be: corresponding stamp for end-of-period (NOT 1-past)
	@timeparms: reference to array of parameters from self::TimeParms, used in time calcs
	@defaultall: optional boolean, whether to return, upon some sort of problem,
		arrays representing the whole period instead of FALSE, default FALSE
	Returns: array with 2 members
	 [0] = array of UTC timestamps for starts of complying intervals during the period
	 [1] = array of corresponding stamps for interval-last-seconds (NOT 1-past)
	 OR FALSE if no descriptor, or parsing fails, and @defaultall is FALSE
	*/
	public function AllIntervals($descriptor, $bs, $be, &$timeparms, $defaultall=FALSE)
	{
		if ($descriptor) {
			if (parent::ParseDescriptor($descriptor)) {
				return self::GetBlocks($bs,$be,$timeparms,$defaultall);
			}
		}
		//nothing to report
		if ($defaultall) {
			//limiting timestamps
			return array(array($bs),array($be));
		}
		return FALSE;
	}

	/**
	NextInterval:
	Get a pair of timestamps representing the earliest time-block in @bs..@be
	and conforming to @descriptor
	@descriptor: interval-language string to be interpreted, or some variety of FALSE
	@bs: UTC timestamp for start (midnight) of 1st day of period being processed
	@be: corresponding stamp for end-of-period (NOT 1-past)
	@timeparms: reference to array of parameters from self::TimeParms, used in time calcs
	@slotlen: length (seconds) of wanted block
	Returns: array with 2 timestamps, or FALSE
	*/
	public function NextInterval($descriptor, $bs, $be, &$timeparms, $slotlen)
	{
		$res = self::AllIntervals($descriptor,$bs,$be,$timeparms,TRUE);
		if ($res) {
			list($starts,$ends) = $res;
			foreach ($starts as $i->$st) {
				$nd = $st+$slotlen;
				if ($ends[$i] >= $nd) {
					return array($st,$nd);
				}
			}
			return FALSE;
		}
		//limiting timestamps
		if ($bs+$slotlen <= $be+1)
			return array($bs,$bs+$slotlen);
		return FALSE;
	}

	/**
	IntervalComplies:
	Determine whether the whole block @bs..@be is consistent with @descriptor
	@descriptor: when-rule string to be interpreted, or some variety of FALSE
	@bs: UTC timestamp for start of the period to be checked
	@be: corresponding stamp for end-of-period (NOT 1-past)
	@timeparms: reference to array of parameters from self::TimeParms, used in time calcs
	Returns: boolean representing compliance, or TRUE if @descriptor is FALSE,
		or FALSE if @descriptor is not parsable
	*/
	public function IntervalComplies($descriptor, $bs, $be, &$timeparms)
	{
		$res = self::AllIntervals($descriptor,$bs,$be,$timeparms,TRUE);
		if ($res) {
			$blocks = new Blocks();
			list($starts,$ends) = $blocks->DiffBlocks(array($bs),array($be),$res[0],$res[1]);
			return (count($starts) == 0); //none of the interval is not covered by $descriptor
		}
		return FALSE;
	}
}
