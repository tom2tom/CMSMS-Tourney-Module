<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: WhenRules
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class WhenRules extends WhenRuleLexer
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
	@be: stamp for one-past-end of period being processed
	@dtw: modifiable DateTime object for use in relative calcs
	@timeparms: reference to array of parameters from self::TimeParms
	@starts: reference to array of block-start timestamps to be updated
	@ends: ditto for block-ends
	*/
	private function PeriodBlocks($cond, $bs, $be, $dtw, &$timeparms, &$starts, &$ends)
	{
		$sunny = FALSE;
		if ($cond['T']) {
			if (!is_array($cond['T']))
				$cond['T'] = array($cond['T']);
			foreach ($cond['T'] as $one) {
				if (is_array($one)) {
					foreach ($one as $t) {
						if (strpos($t,'R') !== FALSE || strpos($t,'S') !== FALSE) {
							$sunny = TRUE;
							break;
						}
					}
				} elseif (strpos($one,'R') !== FALSE || strpos($one,'S') !== FALSE) {
					$sunny = TRUE;
					break;
				}
			}
			$timeparms['sunny'] = $sunny;
			if (!$sunny) {
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

		$funcs = new PeriodInterpreter();
		if (!is_array($cond['P'])) {
			$cond['P'] = array($cond['P']);
		}
		foreach ($cond['P'] as $descriptor) {
			if ($dodays) {
				switch ($cond['F']) {
				 case 1: //whole of $bs..$be-1
					$parsed = $funcs->BlockDays($bs,$be,$dtw);
					break;
				 case 2: //months(s) in any year in $bs..$be-1
				 case 7: //months(s) in specific year(s) in $bs..$be-1
					$parsed = $funcs->SpecificMonths($descriptor,$bs,$be,$dtw);
					break;
				 case 3: //week(s) in any month in any year in $bs..$be-1
				 case 8: //week(s) in specific month(s) in $bs..$be-1
				 case 9: //week(s) in specific [month(s) and] year(s) in $bs..$be-1
					$parsed = $funcs->SpecificWeeks($descriptor,$bs,$be,$dtw);
					break;
				 case 4: //day(s) of week in any week in $bs..$be-1
				 case 5: //day(s) of month in any month in $bs..$be-1
				 case 10: //day(s) in weeks(s) in $bs..$be-1
				 case 11: //day(s) in [weeks(s) and] month(s) in $bs..$be-1
				 case 12: //day(s) in weeks(s) and specific month(s) and specific year(s) in $bs..$be-1
					$parsed = $funcs->SpecificDays($descriptor,$bs,$be,$dtw);
					break;
				 case 6: //year(s) in $bs..$be-1
					$parsed = $funcs->SpecificYears($descriptor,$bs,$be,$dtw);
					break;
				 case 13: //specific day(s) in $bs..$be-1
					$parsed = $funcs->SpecificDates($descriptor,$bs,$be,$dtw);
					break;
				 default:
					$parsed = FALSE;
					break;
				}

				if ($parsed) {
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
								$dtw->modify('+1 day');
								$ends[] = $dtw->getTimestamp()-1;
							}
						}
					}
				}
			} else { //blockwise analyis
				switch ($cond['F']) {
				 case 1: //whole block
					$starts[] = $bs;
					$ends[] = $be - 1;
					break;
				 case 2: //months(s) in any year in $bs..$be-1
				 case 7: //months(s) in specific year(s) in $bs..$be-1
					$parsed = $funcs->SpecificMonths($descriptor,$bs,$be,$dtw,TRUE);
					if ($parsed) {
						foreach ($parsed as $som) {
							foreach ($som as $st) {
								$starts[] = $st;
								$dtw->setTimestamp($st);
								$dtw->modify('+1 month');
								$st = $dtw->getTimestamp();
								if ($st < $be) {
									$ends[] = $st-1;
								} else {
									$ends[] = $be-1;
									break;
								}
							}
						}
						//TODO merge adjacent months $blocks->MergeBlocks($starts,$ends);
					}
					break;
				 case 3: //week(s) in any month in any year in $bs..$be-1
				 case 8: //week(s) in specific month(s) in $bs..$be-1
				 case 9: //week(s) in specific [month(s) and] year(s) in $bs..$be-1
					$parsed = $funcs->SpecificWeeks($descriptor,$bs,$be,$dtw,TRUE);
					if ($parsed) {
						foreach ($parsed as $sow) {
							foreach ($sow as $st) {
								$starts[] = $st;
								$dtw->setTimestamp($st);
								$dtw->modify('+7 days');
								$st = $dtw->getTimestamp();
								if ($st < $be) {
									$ends[] = $st-1;
								} else {
									$ends[] = $be-1;
									break;
								}
							}
						}
						//TODO merge adjacent weeks $blocks->MergeBlocks($starts,$ends);
					}
					break;
				 case 6: //year(s) in $bs..$be-1
					$parsed = $funcs->SpecificYears($descriptor,$bs,$be,$dtw,TRUE);
					if ($parsed) {
						foreach ($parsed as $soy) {
							foreach ($soy as $st) {
								$starts[] = $st;
								$dtw->setTimestamp($st);
								$dtw->modify('+1 year');
								$st = $dtw->getTimestamp();
								if ($st < $be) {
									$ends[] = $st-1;
								} else {
									$ends[] = $be-1;
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
			if (!empty($nums[0]) && $nums[0] != '00')
				$str .= $nums[0].' hours';
			if (!empty($nums[1]) && $nums[1] != '00')
				if ($str)
					$str .= ' ';
				$str .= $nums[1].' minutes';
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
	@bs: stamp for start of day being procesed
	@be: stamp for 1-past-end of day being procesed
	@dtw: modifiable DateTime object for use in relative calcs
	@timeparms: reference to array of parameters from self::TimeParms
	Returns: array(blockstart,blockend) or array(FALSE,FALSE)
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
			$t = '+'.$timedata['count'].' '.$timedata['type'];
			if ($timedata['count'] > 1)
				$t .= 's'; //pluralise
			$dtw->modify($t);
			$dtw->modify('-1 second');
			$parts = array($timedata,$dtw->format('G:i'));
		}
		//block-start
		if (strpos($parts[0],'R') !== FALSE) {
			/*
			Sunrise $zenith=90+50/60
			Twilights: see http://www.timeanddate.com/astronomy/about-sun-calculator.html
			Civilian twilight $zenith=96.0; <<< USE THIS FOR OUTDOORS THO' N/A >= +/-60.5° latitude
			Nautical twilight $zenith=102.0
			Astronomical twilight $zenith=108.0
			*/
			$tbase = date_sunrise($bs,SUNFUNCS_RET_TIMESTAMP,$timeparms['lat'],$timeparms['long'],96.0,$timeparms['gmtoff']);
			$parts[0] = str_replace('R','',$parts[0]);
		} elseif (strpos($parts[0],'S') !== FALSE) {
			$tbase = date_sunset($bs,SUNFUNCS_RET_TIMESTAMP,$timeparms['lat'],$timeparms['long'],96.0,$timeparms['gmtoff']);
			$parts[0] = str_replace('S','',$parts[0]);
		} else {
			$tbase = $bs;
		}
		$dtw->setTimestamp($tbase-$bs);
		self::RelTime($dtw,$parts[0]);
		$s = $dtw->getTimestamp();
		if ($s < 0 || $s >= $be-$bs) {
			$s = 0;
		}
		//block-end
		if (strpos($parts[1],'R') !== FALSE) {
			$tbase = date_sunrise($bs,SUNFUNCS_RET_TIMESTAMP,$timeparms['lat'],$timeparms['long'],96.0,$timeparms['gmtoff']);
			$parts[1] = str_replace('R','',$parts[1]);
		} elseif (strpos($parts[1],'S') !== FALSE) {
			$tbase = date_sunset($bs,SUNFUNCS_RET_TIMESTAMP,$timeparms['lat'],$timeparms['long'],96.0,$timeparms['gmtoff']);
			$parts[1] = str_replace('S','',$parts[1]);
		} else {
			$tbase = $bs;
		}
		$dtw->setTimestamp($tbase-$bs);
		self::RelTime($dtw,$parts[1]);
		$e = $dtw->getTimestamp();
		if ($e < 0 || $e >= $be-$bs) {
			$e = $be-$bs-1;
		}
		if ($e > $s)
			return array($s,$e);
		return array(FALSE,FALSE);
	}

	/*
	TimeBlocks:
	Get block-timestamps consistent with @cond and in $bs..$bs + 1 day - 1 second
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
					$dt = new \DateTime($at,$tz);
					$offs = $dt->format('Z')/3600; //DST-specific
				} catch (Exception $e) {
					$offs = 0;
				}
				break;
			}
			$timeparms['gmtoff'] = $offs;
		}

		$blocks = new Blocks(); //CHECKME pass as arg?

		$dtw->modify('+1 day');
		$be = $dtw->getTimestamp();

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
			if ($gets) {
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
			if ($gets) {
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
	Get @idata-derived parameters for context-specific time calcs
	No checks here for valid parameters in @idata - assumed done before
	@idata: reference to array of data (possibly inherited) for a resource or group
	Returns: array of parameters: latitude, longitude, zone etc
	*/
	public function TimeParms(&$idata)
	{
	 	$num = 1;
		$type = 'hour';
		if ($idata['slottype']) {
		 	$num = (int)$idata['slotcount'];
			$utils = new Utils();
			$periods = $utils->TimeIntervals();
			$t = (int)$idata['slottype'];
			if ($t > 2)
				$t = 2; //max interval-type in this context is day
			$type = $periods[$t];
		}

		$zone = $idata['timezone'];
		if (!$zone)
			$zone = $this->mod->GetPreference('pref_timezone','UTC');

		return array (
		 'count'=>$num, //part default slot length, for DateTime modification
		 'type'=>$type, //other part
		 'sunny'=>FALSE, //whether sun-related calcs are needed
		 'lat'=>(float)$idata['latitude'], //maybe 0.0
		 'long'=>(float)$idata['longitude'], //ditto
		 'zone'=>$zone
		);
	}

	/**
	GetBlocks:
	Interpret parent::conds into seconds-blocks covering the interval from
	@dts to immediately (1-sec) before @dte.
	@dts: datetime object representing resource-local start of period being
		processed, not necessarily a midnight
	@dte: datetime object representing resource-local one-past-end of the period,
		not necessarily a midnight
	@timeparms: reference to array of parameters for sun-related time calcs
	@defaultall: optional boolean, whether to return, if parent::conds is not set,
	the whole interval as one block instead of empty arrays, default FALSE
	Returns: 2-member array:
	 [0] = timestamps representing block-starts
	 [1] = timestamps for corresponding block-ends (NOT 1-past)
	BUT both arrays will be empty upon error, or if nothing applies and
		$defaultall is FALSE
	*/
	public function GetBlocks($dts, $dte, &$timeparms, $defaultall=FALSE)
	{
		$starts = array();
		$ends = array();
		if ($dts < $dte && $this->conds) {
			//stamps for period limit checks
			$bs = $dts->getTimestamp();
			$be = $dte->getTimestamp();
			$dtw = clone $dts;
			$blocks = new Blocks();
			//for all inclusion-conditions, add to $starts,$ends
			foreach ($this->conds as &$cond) {
				if ($cond['P']) {
					if ($cond['P'][0] == '!') //exclusive
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
					//merge $starts,$ends,$gets,$gete
					if ($starts) {
						list($gets,$gete) = $blocks->IntersectBlocks($starts,$ends,$gets,$gete);
					} else {
						//want something to compare with
						list($gets,$gete) = $blocks->IntersectBlocks(array($bs),array($be),$gets,$gete);
					}
					if ($gets) {
						$starts = $gets;
						$ends = $gete;
						if (count($starts) == 1 && reset($starts) <= $bs && end($ends) >= $be-1) //all of $bs..$be now covered
							break;
					}
				}
			}
//			unset($cond);
			if ($starts) {
				//for all exclusion-conditions, subtract from $starts,$ends
				foreach ($this->conds as &$cond) {
					if ($cond['P']) {
						if ($cond['P'][0] != '!') //inclusive
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
						//diff $starts,$ends,$gets,$gete
						if ($starts) {
							list($gets,$gete) = $blocks->DiffBlocks($starts,$ends,$gets,$gete);
						} else {
							//want something to compare with
							list($gets,$gete) = $blocks->DiffBlocks(array($bs),array($be),$gets,$gete);
						}
						if ($gets !== FALSE) {
							$starts = $gets;
							$ends = $gete;
							if (!$starts) //none of $bs..$be now covered
								break;
						}
					}
				}
			}
			unset($cond);
			if ($starts) {
				//sort block-pairs, merge when needed
				$blocks->MergeBlocks($starts,$ends);
			}
		} elseif ($defaultall) {
			$bs = $dts->getTimestamp();
			$be = $dte->getTimestamp() - 1;
			$starts[] = min($bs,$be);
			$ends[] = max($bs,$be);
		}
		return array($starts,$ends);
	}

	/**
	AllIntervals:
	Get array of pairs of timestamps representing conforming time-blocks in the
	 interval starting at @dts and ending 1-second before @dte
	@descriptor: interval-language string to be interpreted, or some variety of FALSE
	@dts: datetime object for UTC start (midnight) of 1st day of period being processed
	@dte: datetime object representing 1-second after the end of the period of interest
	@timeparms: reference to array of parameters from self::TimeParms, used in time calcs
	@defaultall: optional boolean, whether to return, upon some sort of problem,
		arrays representing the whole period instead of FALSE, default FALSE
	Returns: array with 2 members
	 [0] = array of UTC timestamps for starts of complying intervals during the period
	 [1] = array of corresponding stamps for interval-last-seconds (NOT 1-past)
	 OR FALSE if no descriptor, or parsing fails, and @defaultall is FALSE
	*/
	public function AllIntervals($descriptor, $dts, $dte, &$timeparms, $defaultall=FALSE)
	{
		//limiting timestamps
		$st = $dts->getTimestamp();
		$nd = $dte->getTimestamp();
		if ($descriptor) {
			if (parent::ParseDescriptor($descriptor)) {
				return self::GetBlocks($dts,$dte,$timeparms,$defaultall);
			}
		}
		//nothing to report
		if ($defaultall)
			return array(array($st),array($nd-1));
		return FALSE;
	}

	/**
	NextInterval:
	Get pair of timestamps representing the earliest conforming time-block in the
	 interval starting at @dts and ending 1-second before @dte
	@descriptor: interval-language string to be interpreted, or some variety of FALSE
	@dts: datetime object for UTC start (midnight) of 1st day of period being processed
	@dte: datetime object representing 1-second after the end of the period of interest
	@timeparms: reference to array of parameters from self::TimeParms, used in time calcs
	@slotlen: length (seconds) of wanted block
	Returns: array with 2 timestamps, or FALSE
	*/
	public function NextInterval($descriptor, $dts, $dte, &$timeparms, $slotlen)
	{
		$res = self::AllIntervals($descriptor,$dts,$dte,$timeparms,TRUE);
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
		$st = $dts->getTimestamp();
		$nd = $dte->getTimestamp();
		if ($st+$slotlen <= $nd)
			return array($st,$st+$slotlen);
		return FALSE;
	}

	/**
	IntervalComplies:
	Determine whether the time-block starting at @dts and ending 1-second
	 before @dte is consistent with @descriptor
	@descriptor: interval-language string to be interpreted, or some variety of FALSE
	@dts: datetime object for UTC start (midnight) of 1st day of period being processed
	@dte: datetime object representing 1-second after the end of the period of interest
	@timeparms: reference to array of parameters from self::TimeParms, used in time calcs
	Returns: boolean representing compliance, or TRUE if @descriptor is FALSE,
		or FALSE if @descriptor is not parsable
	*/
	public function IntervalComplies($descriptor, $dts, $dte, &$timeparms)
	{
		$res = self::AllIntervals($descriptor,$dts,$dte,$timeparms,TRUE);
		if ($res) {
			$blocks = new Blocks();
			list($starts,$ends) = $blocks->DiffBlocks(
				array($dts->getTimestamp()),array($dte->getTimestamp()), //TODO off-by-1 ?
				$res[0],$res[1]);
			return (count($starts) == 0); //none of the interval is not covered by $descriptor
		}
		return FALSE;
	}
}
