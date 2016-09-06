<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: WhenRuleLexer
# Uses CMSMS-specific string-translation method
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/**
Repetition Language

Repeated date/times are specified in a string of the form
 condition[,condition2...]
Whitespace and newlines in the string are ignored. If more than one condition is
specified, their order is irrelevant.

Each condition in the string is of the form
 [PERIOD][@][TIME]
The separator '@' is mandatory if both PERIOD and TIME are specified. It may
otherwise be used i.e. PERIOD@ or @TIME, to clarify ambiguous data.  For now at
least, the interpreter supports LTR languages only.

PERIOD and/or TIME may be
 a 'singleton' value; or
 (X[,...]) which specifies a series of individual values, in any order.

 Any member(s) of such a series may be like X..Y, which specifies a sequence
 (an inclusive range of sequential values), and normally Y should be
 chronologically after X (except when spanning a time-period-end
 e.g. 23:30..2:30 or Friday..Sunday or December..February).

Ordinary numeric series and sequences are supported, and for such sequences,
Y must be greater than X, and X, or both X and Y, may be negative.

Any value or bracketed-series may itelf be bracketed and preceded by a qualifier,
being a single value, or scope-expander of any of the above types. Such
qualification may be nested/recursed, like S(R(Q(P))).

A value which is a date is expected to be formatted as yyyy[-[m]m[-[d]d]] i.e.
month and day are optional, and leading 0 is not needed for month or day value.

A value which is a month- or day-name is expected to be in accord with the default,
or a specified, translation/locale. Month- and/or day-names may be abbreviated.
The term 'week' isn't system-pollable, and must be translated manually. Weeks
always start on Sunday (and so 'week' can be considered equivalent to
'Sunday..Saturday').

TODO Any period-descriptor may be prefaced by (translated, TODO any case) 'not'
or 'except' to specify a period to be excluded from the days otherwise covered
by other period-descriptors.

TODO Any period-descriptor may be qualifed by (translated, TODO any case) 'each'
or 'every' N, to represent fixed-interval repetition such as 'every 2nd Wednesday'.
And in turn must qualify (PS..PE) where PS and PE are period-descriptors repsectively
representing the 1st of the repeats, and the maximum for the last of the repeats
(which may actually be before PE if that's how the repetition turns out)
e.g. 'each 2(Wednesday(2000-1..2000-3-15))'

A value which is a time is expected to be formatted as [h]h[:[m]m] i.e. 24-hour
format, leading '0' optional, minutes optional but if present, the separator is
':'. In some contexts, [h]h:00 may be needed instead of just [h]h, to reliably
distinguish between hours-of-day and days-of-month. Sunrise/sunset times are
supported, if relevant latitude and longitude data are provided. Times like
sunrise/sunset +/- interval are also supported. A time-value which is not a
range is assumed to represent a range of 1-hour.

Negative-value parameters count backwards from next-longest-interval-end.

If multiple conditions are specified, and any of them overlap, then less-explicit/
more-liberal conditions prevail over more-explicit/less-liberal ones. This means
that, for any particular date/time, it will be sufficient for any combination of
conditions to be satisfied.

Examples:
Date descriptors
 year(s): 2000 or (2020,2024) or 2000..2005
 month(s)-of-any-year: January or (March,September) or July..December
 month(s)-of-specfic-year: January(2000) or 2000-6
   or (March,September)(2000) or July..December(2000) or 2000-10..2001-3
Week descriptors
 week(s)-of-any-month (some of which may not be 7-days): 2(week) or -1(week)
   or 2..3(week)
 week(s)-of-named-month: 2(week(March)) or 1..3(week(July,August))
   or (-2,-1)(week(April..July))
Day descriptors
 day(s)-of-specific-month(s): 1(June) or -2(June..August) or (6..9,15,18,-1)(January,July)
	 or 2(Wednesday(June)) or (1,-1)(Saturday(June..August))
 day(s)-of-any-month: 1 or -2 or (15,18,-1) or 1..10 or 2..-1 or -3..-1 or
1(Sunday) or -1(Wednesday..Friday) or 1..3(Friday,Saturday)
 day(s)-of-specific-week(s): Sunday(2(week)) or
   (Saturday,Wednesday)(-3..-1(week(July))) or
   Monday..Friday((-2,-1)(week(April..July))) or
 day(s)-of-any-week: Monday or (Monday,Wednesday,Friday) or Wednesday..Friday
 specific day(s): 2000-9-1 or 2000-10-1..2000-12-31
Time descriptors
 9 or 2:30 or (9,12,15:15) or 12..23 or 6..15:30 or sunrise..16 or 9..sunset-3:30
*/
namespace Booker;

class WhenRuleLexer
{
	protected $mod; //reference to current module-object
	/*
	$conds will be array of parsed descriptors, or FALSE

	Each member of $conds is an array, with members:
	'F' => 'focus'-enum (as below) to assist interpeting any or all applicable
	PERIOD value(s)
		 0 can't decide
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
	'P' => FALSE or PERIOD = structure of arrays and strings representing
		period-values and/or period-value-ranges (i.e. not series), all ordered by
		increasing value/range-start (TODO EXCEPT ROLLOVERS?) Negative values
		in ['P'] are sorted after positives, but not interpreted to corresponding
		actual values.
	'T' => FALSE or TIME = string or array of strings representing time-value(s),
		with '!'-prefixed exclusions grouped last, sun-related ones ordered first
		among grouped includes and/or excludes, all ordered by increasing
		value/range-start (TODO EXCEPT ROLLOVERS?)
		For time-ranges, instead of a string, the value will be a 3-member array,
		range-start-string,'.',range-end-string.
		Times are not interpreted. Other than sun-related values, they can be
		converted to midnight-relative seconds, and any overlaps 'coalesced'.
		Sun-related values must of course be interpreted for each specific day
		evaluated.
	perhaps - cached interpretation data:
	'S' => resource-local date which is the earliest for (currently) interpreted data in ['A']
	'E' => resource-local date which is the latest for (currently) interpreted data in ['A']
	'A' => array of arrays, each with a pair of members:
		[0] = 4-digit year, maybe with -ve sign indicating this is data for a
			'except/not' interval
		[1] = array of 0-based day-of-year indices for the year in [0] and within
			the bounds of ['S'] to ['E'] inclusive

	Descriptor-string parsing works LTR. Maybe sometime RTL languages will also
	be supported ?!
	$conds will be sorted, first on members' ['F']'s, then on their ['P']'s
	(with '!'-prefixed exclusions last), then on their ['T']'s.
	*/
//	protected
	public $conds = FALSE;
//	private $ob; //'(' for ltr languages, ')' for rtl
//	private $cb; //')' for ltr languages, '(' for rtl
	//regex for ISO dates like YYYY[-[M]M[-[D]D]] see https://regex101.com/r/xL4oY5/1
	private $dateptn = '/!?([12]\d{3}(?!\d))(-(1[0-2]|0?[1-9])(?!\d)(-(3[01]|0?[1-9]|[12]\d)(?!\d))?)?/';
	//regex for trailing [+-][H]H[:[M]M] see https://regex101.com/r/vU2bR5/2
	private $timeptn = '/([+-])?\b([01]?\d|2[0-3])(:[0-5]?\d)?$/';

	public function __construct(&$mod)
	{
		$this->mod = $mod;
	}

	/*
	EndScan:
	Scan @str from offset @offset towards end until end, or any char in [()@]
	'(' is a special-case, cuz' searching e.g. '(()' from the first '(' is not valid
	@str: string
	@offset points to initial ',' in @str
	Returns: offset in @str of last char in series i.e. before matched char, or last char
	 or -1 if end not found, or -2 if nested '(' found
	*/
	private function EndScan($str, $offset)
	{
		$l = strlen($str);
		for ($p=$offset; $p<$l; $p++) {
			switch ($str[$p]) {
			 case ')':
			 case '@':
				if ($p > $offset)
					return $p-1;
				return -1;
			 case '(': //if processing a non-bracketed series, this might be the terminator
				if ($p == $offset)
					break; //ignore series-terminator at scan-start
				elseif ($p-1 > $offset)
					return $p-1;
				return -2;
			}
		}
		if ($l-1 > $offset)
			return $l-1; //offset of end
		return -1;
	}

	//Compare numbers such that -ve's last.
	//Either or both numbers may be a sequence, in which case sorted on first part
	private function cmp_numbers($a, $b)
	{
		if (($p = strpos($a,'..')) !== FALSE)
			$a = substr($a,0,$p);
		if (($p = strpos($b,'..')) !== FALSE)
			$b = substr($b,0,$p);
		if (($a >= 0 && $b < 0) || ($a < 0 && $b >= 0))
			return ($b-$a);
		return ($a-$b);
	}

	//Compare strings like D* or M*.
	//Either or both strings may be a sequence, in which case sorted on first part
	private function cmp_named($a, $b)
	{
		if (($p = strpos($a,'..')) !== FALSE)
			$a = substr($a,0,$p);
		if (($p = strpos($b,'..')) !== FALSE)
			$b = substr($b,0,$p);
		$sa = $a[0];
		$sb = $b[0];
		if ($sa != $sb)
			return ($sa - $sb); //should never happen
		$sa = (int)substr($a,1);
		$sb = (int)substr($b,1);
		return ($sa - $sb);
	}

	//Compare date-strings like YYYY[-[M]M[-[D]D]].
	//Either or both dates may be a sequence, in which case sorted on first part
	private function cmp_dates($a, $b)
	{
		if (($p = strpos($a,'..')) !== FALSE)
			$a = substr($a,0,$p);
		if (($p = strpos($b,'..')) !== FALSE)
			$b = substr($b,0,$p);
		if ($a[0] == '!') { //except-dates sorted last
			if ($b[0] == '!') {
				$a = substr($a,1);
				$b = substr($b,1);
			} else {
				return 1; }
		} elseif ($b[0] == '!') {
			return -1; }
		//bare years don't work correctly
		$s = (strpos($a,'-') !== FALSE) ? $a:$a.'-1-1';
		//for relative times, don't need localised DateTime object
		$stA = strtotime($s);
		$s = (strpos($b,'-') !== FALSE) ? $b:$b.'-1-1';
		$stB = strtotime($s);
		return ($stA-$stB);
	}

	/*
	ParsePeriodSequence:

	Ensure @str is ordered according to increasing number or date/time order
	but with -ve numbers (i.e. countbacks) ordered after +ve's
	This is for period-identifiers, no times are handled.
	(TODO v.2 support e.g. Sunday@10..Monday@15:30 ?)

	@str: string to be parsed, like 'A..B' (and no surrounding brackets)
	 where A,B are both if the same type (as represented by A) -
	 numbers, or day/month tokens, or dates
	@getstr: optional, whether to return re-constituted string, default TRUE
	Returns: depending on @getstr, either a 3-member array(L,'.',H) or a string
	represenation of that array 'L..H'. In either case, the return may be a
	single value L(==H) or FALSE upon error.
	The second/middle array value '.' is a flag, for further processors, that the
	array represents a range. L and/or H are not interpreted in any way, except
	that incomplete date-values will be populated.
	*/
	private function ParsePeriodSequence($str, $getstr=TRUE)
	{
		$parts = explode('..',$str,2);
		while ($parts[1][0] == '.')
			$parts[1] = substr($parts[1],1);
		if ($parts[0] === '' || $parts[1] === '')
			return FALSE;
		if ($parts[0] == $parts[1])
			return $parts[0];
		//order the pair
		$swap = FALSE;
/* $pattern matches
'2001-10-12' >> array
 0 => string '2001-10-12'
 1 => string '2001'
 2 => string '-10-12'
 3 => string '10'
 4 => string '-12'
 5 => string '12'
'2001-10' >> array
 0 => string '2001-10'
 1 => string '2001'
 2 => string '-10'
 3 => string '10'
'2001' >> array
 0 => string '2001'
 1 => string '2001'
*/
		if (preg_match($this->dateptn,$parts[0],$loparts)
		&& preg_match($this->dateptn,$parts[1],$hiparts))
		{
			$swap = ($hiparts[1] < $loparts[1]);
			if ($swap) {
				if (!isset($hiparts[3]) && isset($loparts[3]))
					$hiparts[3] = '1';
				if (!isset($hiparts[5]) && isset($loparts[5]))
					$hiparts[5] = '1';
				if (!isset($loparts[3]) && isset($hiparts[3]))
					$loparts[3] = '12';
				if (!isset($loparts[5]) && isset($hiparts[5])) {
					$stamp = mktime(0,0,0,(int)$loparts[3],15,(int)$loparts[1]);
					$loparts[5] = date('t',$stamp); //last day of specified month
				}
			} else {
				$swap = ($hiparts[1] == $loparts[1]) && (!isset($hiparts[3]) ||
					(isset($loparts[3]) && $hiparts[3] < $loparts[3]));
				if ($swap) {
					if (!isset($hiparts[3]) && isset($loparts[3]))
						$hiparts[3] = '1';
					if (!isset($hiparts[5]) && isset($loparts[5]))
						$hiparts[5] = '1';
					if (!isset($loparts[3]) && isset($hiparts[3]))
						$loparts[3] = '12';
					if (!isset($loparts[5]) && isset($hiparts[5])) {
						$stamp = mktime(0,0,0,(int)$loparts[3],15,(int)$loparts[1]);
						$loparts[5] = date('t',$stamp); //last
					}
				} else {
					if (!isset($loparts[5]) && isset($hiparts[5])) {
						if ($hiparts[5] != '1')
							$loparts[5] = '1';
						else {
							$stamp = mktime(0,0,0,(int)$hiparts[3],15,(int)$hiparts[1]);
							$loparts[5] = date('t',$stamp); //last
						}
					}
					if (!isset($hiparts[5]) && isset($loparts[5])) {
						$stamp = mktime(0,0,0,(int)$hiparts[3],15,(int)$hiparts[1]);
						$tmp = date('t',$stamp); //last
						if ($loparts[5] != tmp)
							$hiparts[5] = $tmp;
						else
							$hiparts[5] = '1';
					}
					if (isset($hiparts[5]) && isset($loparts[5])) {
						if ($hiparts[5] < $loparts[5])
							$swap = TRUE;
					}
				}
			}
			$parts[0] = $loparts[1];
			if (isset($loparts[3]))
				$parts[0] .= '-'.$loparts[3];
			if (isset($loparts[5]))
				$parts[0] .= '-'.$loparts[5];
			$parts[1] = $hiparts[1];
			if (isset($hiparts[3]))
				$parts[1] .= '-'.$hiparts[3];
			if (isset($hiparts[5]))
				$parts[1] .= '-'.$hiparts[5];
		} elseif (is_numeric($parts[0]) && is_numeric($parts[1])) {
			$s = (int)$parts[0];
			$e = (int)$parts[1];
			if (($s > 0 && $e > 0)||($s < 0 && $e < 0))
				$swap = ($s > $e);
			else
				$swap = ($s < $e);
		} else {
			//both should be tokenised days D[1-7] or months M[1-12]
			if ($parts[0][0] != $parts[1][0])
				return FALSE;
			$s = (int)substr($parts[0],1);
			$e = (int)substr($parts[1],1);
			if (($s > 0 && $e > 0)||($s < 0 && $e < 0))
				$swap = ($s > $e);
			else
				$swap = ($s < $e);
		}
		if ($swap) {
			$t = $parts[0];
			$parts[0] = $parts[1];
			$parts[1] = $t;
		}
		if ($getstr)
			return $parts[0].'..'.$parts[1];
		else
			return array($parts[0],'.',$parts[1]);
	}

	/*
	CleanPeriod:

	Ensure @str is ordered according to increasing number or date/time order
	-ve numbers (i.e. countbacks) are ordered after +ve's
	This is for period-identifiers, no times are handled.

	@str: string '(A[,B...])' to be parsed, possibly containing one or more
	  (and if so, comma-separated) singleton and/or sequence values, and
	  optionally, surrounding brackets. Elements A,B etc are all of the same type
	  (as represented by A) - numbers, or day/month tokens, or dates.
	  May be empty.
	@getstr: optional, whether to return re-constituted string, default TRUE
	Returns: depending on @getstr, either a N-member, ascending-sorted, no-duplicates,
	contiguous-keyed array (L,...,H), or comma-separated string represenation of
	that array. A single-member series will return either that single value
	L(==all others) or its string-equivalent 'L'.
	Returns FALSE upon error e.g. different element types.
	*/
	private function CleanPeriod($str, $getstr=TRUE)
	{
		$work = trim($str,' ()'); //strip surrounding brackets
		if (!$work)
			return ($getstr) ? '':array('');
		$parts = explode(',',$work);
		if (!isset($parts[1])) { //aka count($parts) == 1 i.e.singleton
			if (strpos($work,'..') !== FALSE)
				$work = self::ParsePeriodSequence($work,$getstr); //reorder if appropriate
			return ($getstr) ? $work:array($work);
		}
		$val = $parts[0];
		if (preg_match($this->dateptn,$val)) {
			$type = 3;
			$cmp = 'cmp_dates';
		} elseif (is_numeric($val)) {
			$type = 1;
			$cmp = 'cmp_numbers';
		} else {
			if (($p = strpos($val,'..')) !== FALSE) {
				$n = substr($val,0,$p);
				if (is_numeric($n)) {
					$type = 1;
					$cmp = 'cmp_numbers';
				} else {
					$type = 2;
					$cmp = 'cmp_named';
				}
			} else {
				$type = 2;
				$cmp = 'cmp_named';
			}
		}

		foreach ($parts as &$val) {
			switch ($type) {
			 case 1:
				if (is_numeric($val)) {
					break;
				} elseif (strpos($val,'..') !== FALSE) {
					$r = self::ParsePeriodSequence($val,FALSE); //reorder if appropriate
					if (is_array($r) && is_numeric($r[0]))
						$val = $r[0].'..'.$r[2]; //no sub-array here, prior to flip/de-dup
					elseif ($r && is_numeric($r))
						$val = $r;
					else {
						$parts = FALSE;
						break 2;
					}
					break;
				} else {
					$parts = FALSE;
					break 2;
				}
			 case 2:
				if (strpos($val,'..') !== FALSE) {
					$r = self::ParsePeriodSequence($val,FALSE); //reorder if appropriate
					if (is_array($r)) //TODO && same type of non-numeric
						$val = $r[0].'..'.$r[2]; //no sub-array here
					elseif ($r) //TODO && same type of non-numeric
						$val = $r;
					else {
						$parts = FALSE;
						break 2;
					}
					break;
				} elseif (1) { //TODO same type of non-numeric
					break;
				} else {
					$parts = FALSE;
					break 2;
				}
			 case 3:
				if (strpos($val,'..') !== FALSE) {
					$r = self::ParsePeriodSequence($val,FALSE); //reorder if appropriate
					if (is_array($r) && preg_match($this->dateptn,$r[0]))
						$val = $r[0].'..'.$r[2]; //no sub-array here
					elseif ($r && preg_match($this->dateptn,$r))
						$val = $r;
					else {
						$parts = FALSE;
						break 2;
					}
					break;
				} elseif (preg_match($this->dateptn,$val)) {
					break;
				} elseif ($val[0] == '!') {
					break;
				} else {
					$parts = FALSE;
					break 2;
				}
			}
		}
		unset($val);
		if ($parts == FALSE)
			return '';
		//remove dup's without sorting
		$parts = array_flip($parts);
		$parts = array_flip($parts);
		if (count($parts) > 1)
			usort($parts,array($this,$cmp)); //keys now contiguous
		if ($getstr)
			return implode(',',$parts);
		else
			return $parts;
	}

	/*
	SplitPeriod:

	@str: tokenised PERIOD-component of an interval descriptor, like S(R(Q(P)))
	Returns: array, with one member, or more if @str has ','-separated sub-strings.
	*/
	private function SplitPeriod($str)
	{
		$arr = explode('(',$str);
		foreach ($arr as &$one) {
			if ($one)
				$one = str_replace(')','',$one);
		}
		unset($one);
		return array_filter($arr);
	}

	//upstream callers all use the returned value for relative checks,
	//so no need to work with a DateTime object
	private function TimeofDay($timestr)
	{
		$nums = explode(':',$timestr,3);
		$str = '';
		if (!empty($nums[0]) && $nums[0] != '00')
			$str .= ' '.$nums[0].' hours';
		if (!empty($nums[1]) && $nums[1] != '00')
			$str .= ' '.$nums[1].' minutes';
		if (!empty($nums[2]) && $nums[2] != '00')
			$str .= ' '.$nums[2].' seconds';
		if (empty($str))
			$str = '+ 0 seconds';
		return strtotime($str,0);
	}

	private function cmp_plaintimes($a, $b)
	{
		$sa = strpos($a,':');
		$sb = strpos($b,':');
		if ($sa === FALSE) {
			if ($sb === FALSE)
				return ($a-$b);
			else { //$b has minutes
				$h = substr($b,0,$sb) + 0;
				return ($a != $h) ? ($a-$h) : -1;	//$b later for same hour
			}
		} elseif ($sb === FALSE) { //and $a has minutes
			$h = substr($a,0,$sa) + 0;
			return ($h != $b) ? ($h-$b) : 1; //$a later for same hour
		} else {
			$h = substr($a,0,$sa) + 0;
			$h2 = substr($b,0,$sb) + 0;
			if ($h == $h2) {
				$h = substr($a,$sa+1) + 0;
				$h2 = substr($b,$sb+1) + 0;
			}
			return ($h-$h2);
		}
	}

	/*Compare time-strings without expensive time-conversions for any
	[sun*[+-]][h]h[:[m]m]] and putting all '!'-prefixed strings last,
	and grouping all sun* before others in includes and/or excludes.
	Either or both time args may be a sequence
	*/
	private function cmp_times($a, $b)
	{
		if ($a[0] == '!') {
			if ($b[0] != '!') {
				return 1;
			}
			$a = substr($a,1);
			$b = substr($b,1);
		} elseif ($b[0] == '!') {
			return -1;
		}
		$ra = strpos($a,'RS');
		$rb = strpos($b,'RS');
		$sa = strpos($a,'SS');
		$sb = strpos($b,'SS');
		if (($p = strpos($a,'..')) !== FALSE) {
			$a = substr($a,0,$p);
			$ae = substr($a,$p+2); //sequence-end-descriptor
		}
		if (($p = strpos($b,'..')) !== FALSE) {
			$b = substr($b,0,$p);
			$be = substr($b,$p+2);
		}
		if ($ra !== FALSE) {
			if ($rb === FALSE) {
				return -1; } else {
				$ma = (strlen($a) > $ra+2);
				if ($ma) {
					$na = $a[$ra+2]; }
				$mb = (strlen($b) > $rb+2);
				if ($mb) {
					$nb = $b[$rb+2]; }
				if ($ma && $mb) {
					if ($na != $nb)
						return (ord($nb)-ord($na)); //'+' < '-' so reverse
					elseif ($na == '+') {
						$a = substr($a,$ra+2);
						$b = substr($b,$rb+2);
						return self::cmp_plaintimes($a,$b);
					} elseif ($na == '-') {
						$a = substr($a,$ra+2);
						$b = substr($b,$rb+2);
						return self::cmp_plaintimes($b,$a); //swapped
					} else {
						return FALSE; }
				} elseif ($ma && !$mb) {
					return ($na=='+') ? 1:-1;
				} elseif ($mb && !$ma) {
					return ($nb=='+') ? -1:1;
				}
				return 0;
			}
		}
		if ($sa !== FALSE) {
			if ($sb === FALSE) {
				//want sunset after sunrise, before others
				if ($rb !== FALSE) //$b includes R
					return ($ra===FALSE) ? 1:-1;
				else //no R at all
					return ($ra===FALSE) ? -1:1;
			} else {
				$ma = (strlen($a) > $sa+1);
				if ($ma) $na = $a[$sa+1];
				$mb = (strlen($b) > $sb+1);
				if ($mb) $nb = $b[$sb+1];
				if ($ma && $mb) {
					if ($na != $nb)
						return (ord($nb)-ord($na)); //'+' < '-' so reverse
					elseif ($na == '+') {
						$a = substr($a,$sa+2);
						$b = substr($b,$sb+2);
						//fall through to compare $a,$b
					} elseif ($na == '-') {
						$t = substr($b,$sb+2); //swap
						$b = substr($a,$sa+2);
						$a = $t;
						//fall through to compare $a,$b
					} else
						return FALSE;
				} elseif ($ma && !$mb)
					return ($na=='+') ? 1:-1;
				elseif ($mb && !$ma)
					return ($nb=='+') ? -1:1;
				return 0;
			}
		} elseif ($rb !== FALSE) {
			return ($sa !== FALSE) ? -1 : 1; } //sunset after sunrise, before others
		elseif ($sb !== FALSE) {
			return ($ra !== FALSE) ? -1 : 1; }//ditto
		//now just time-values
		return self::cmp_plaintimes($a,$b);
	}

	//for use in ParseTimeSequence()
	private function MergeTime($parts)
	{
		if ($parts) {
			$t = ($parts[2]) ? $parts[2]:'0';
			$t .= (isset($parts[3])) ? $parts[3]:':0';
			return $t;
		} else
			return FALSE;
	}

	/**
	ParseTimeSequence:

	@str: string to be parsed, containing '..' and no surrounding brackets
	@getstr: optional, whether to return re-constituted string, default TRUE
	Returns: depending on @getstr, either a 3-member array(L,'.',H) or a string
	represenation of that array 'L..H'. In either case, the return may be a
	single value L(==H) or FALSE upon error.
	The second/middle array value '.' is a flag, for further processors, that the
	array represents a range. L and/or H are not interpreted in any way, except
	that incomplete time-values will be populated.
	*/
	private function ParseTimeSequence($str, $getstr=TRUE)
	{
		if ($str[0] != '!') {
			$not = '';
		} else {
			$not = '!';
			$str = substr($str,1);
		}
		$parts = explode('..',$str,2);
		while ($parts[1][0] == '.')
			$parts[1] = substr($parts[1],1);
		if ($parts[0] === '' || $parts[1] === '') {
			return FALSE;
		}
		if ($parts[0] == $parts[1]) {
			return ($getstr) ? $not.$parts[0]:array($not.$parts[0]);
		}

		$lorise = (strpos($parts[0],'RS') !== FALSE);
		$loset = (strpos($parts[0],'SS') !== FALSE);
		$hirise = (strpos($parts[1],'RS') !== FALSE);
		$hiset = (strpos($parts[1],'SS') !== FALSE);
		preg_match($this->timeptn,$parts[0],$loparts);
		preg_match($this->timeptn,$parts[1],$hiparts);
/*
match-array(s) have
[0] whole i.e. +/-hours[:minutes]
[1] +/- if string is valid
[2] hours
[3] :minutes (if provided)
*/
		if (($lorise || $loset || isset($loparts[2])) && ($hirise || $hiset || isset($hiparts[2]))) {
			if (($lorise || $loset) && $loparts && !$loparts[1]) {
				$loparts[1] = '+';
				$parts[0] = preg_replace('/([SR]S)/','$1+',$parts[0]);
			}
			if (($hirise || $hiset) && $hiparts && !$hiparts[1]) {
				$hiparts[1] = '+';
				$parts[1] = preg_replace('/([SR]S)/','$1+',$parts[1]);
			}
			//order the pair
			if ($loset) {
				if ($hiset) {
					//order by +- offset
					if ($loparts[1] == '+' && $hiparts[1] == '-') {
						$swap = TRUE; } elseif ($loparts[1] == '-' && $hiparts[1] == '+') {
						$swap = FALSE; } else {
						$kl = self::MergeTime($loparts);
						$kh = self::MergeTime($hiparts);
						if ($kl && $kh) {
							$swap = (self::TimeofDay($kh) < self::TimeofDay($kl));
							if ($loparts[1] == '-' && $hiparts[1] == '-')
								$swap = !$swap;
						} elseif ($kl) { //hi has no time-offset
							$swap = ($loparts[1] == '+');
						} elseif ($kh) { //lo has no time-offset
							$swap = ($hiparts[1] == '-');
						} else {
							$swap = FALSE; }
						}
				} elseif ($hirise) {
					$swap = TRUE; } //rise before set
				else {
					$swap = FALSE; }
			} elseif ($lorise) {
				if ($hiset) {
					$swap = FALSE; } elseif ($hirise) {
					//order by +- offset
					$kl = self::MergeTime($loparts);
					$kh = self::MergeTime($hiparts);
					if ($kl && $kh) {
						$swap = (self::TimeofDay($kh) < self::TimeofDay($kl));
						if ($loparts[1] == '-' && $hiparts[1] == '-')
							$swap = !$swap;
					} elseif ($kl) { //hi has no time-offset
						$swap = ($loparts[1] == '+');
					} elseif ($kh) { //lo has no time-offset
						$swap = ($hiparts[1] == '-');
					} else {
						$swap = FALSE; }
				} else {
					$swap = FALSE;}
			} elseif ($hiset) {
				$swap = FALSE;  } //stet if only one has sun*
			elseif ($hirise) {
				$swap = FALSE; } //ditto
			else {
				//TODO
				$swap = (self::TimeofDay($hiparts[1]) < self::TimeofDay($loparts[1])); }

			if ($swap) {
				$t = $parts[0];
				$parts[0] = $parts[1];
				$parts[1] = $t;
			}
			if ($getstr)
				return $not.$parts[0].'..'.$parts[1];
			else
				return array($not.$parts[0],'.',$parts[1]);
		}
		return FALSE;
	}

	/**
	CleanTime:

	Ensure @str is ordered according to increasing time-order
	Excluded values are grouped last. Within respective in/excludes, sun-related values
	are ordered first, other values ordered ascending by value or start of sequence
	where relevant.

	@str: TIME component of a repetition descriptor '(A[,B...])', containing
	one or more (and if so, comma-separated) singleton and/or sequence values,
	and optionally, surrounding brackets and/or preceeding '@'.
	May be empty.
	@getstr: optional, whether to return re-constituted string, default TRUE
	Returns: depending on @getstr, either a N-member, ascending-sorted, no-duplicates,
	contiguous-keyed array(L,...,H), or comma-separated string represenation of
	that array. A single-member series will return either that single value
	L(==all others) or its string-equivalent 'L'.
	FALSE upon error.
	*/
	private function CleanTime($str, $getstr=TRUE)
	{
		$work = trim($str,' @()'); //strip surrounding brackets etc
		if (!$work)
			return ($getstr) ? '':array('');
		$parts = explode(',',$work);
		if (!isset($parts[1])) { //i.e.singleton
			if (strpos($work,'..') !== FALSE)
				$work = self::ParseTimeSequence($work,$getstr); //reorder if appropriate
			if ($getstr)
				return $work;
			return array($work);
		}

		foreach ($parts as &$val) {
			if (strpos($val,'..') === FALSE) {
				//check for valid time or rise/set-relation
				$r = preg_match($this->timeptn,$val,$matches);
				$p = preg_match('/^[SR]S/',$val);
				if ($p && $matches && !$matches['1']) {
					$val = preg_replace('/([SR]S)/','$1+',$val);
				}
				$r = $r || $p;
			} else {
				$r = self::ParseTimeSequence($val); //reorder if appropriate
				if ($r !== FALSE) {
					$val = $r;
				}
			}
			if (!$r) {
				unset($val);
				return FALSE;
			}
		}
		unset($val);
		if ($parts == FALSE)
			return '';
		//remove dup's without sorting
		$parts = array_flip($parts);
		$parts = array_flip($parts); //keys now contiguous
		if (count($parts) > 1)
			usort($parts,array($this,'cmp_times'));
		if ($getstr)
			return implode(',',$parts);
		else
			return $parts;
	}

	/*
	GetFocus:

	Determine the 'F' parameter for @str
	@str: period-descriptor like P(Q(R(S)))
	Returns: enum 0..13:
		 0 can't figure out anything better
		 1 no period i.e. any (time-only)
		 2 month(s) of any year June,July
		 3 week(s) of any month (1,-1)week
		 4 day(s) of any week Sun..Wed
 		 5 day(s) of any month 1,10,-2 OR 1(Sunday)
		 6 specific year(s) 2020,2015
		 7 month(s) of specific year(s) Jan(2010..2020) OR 2015-1
		 8 week(s) of specific month(s) 1(week(August,September))
		 9 week(s) of specific [month(s) and] year(s) 1(week(Aug..Dec(2020)))
		10 day(s) of specific week(s)  Wed(2(week)) OR (Wed..Fri)(each 2(week))
		11 day(s) of specific [week(s) and] month(s) 1(Aug) OR Wed((1,2)(week(June)))
			OR each 2 day(2000-1..2000-2) OR 2(Wed(June)) OR (1,-1)(Sat(June..August))
		12 day(s) of specific [week(s) and/or month(s) and] year(s) Wed((1,-1)(week(June(2015..2018))))
		13 specfic day/date(s) 2010-6-6 OR 1(Aug(2015..2020))
	*/
	private function GetFocus($str)
	{
		$longdate = FALSE;
		$hasday = FALSE;
		$hasweek = FALSE;
		$hasmonth = FALSE;
		$hasyear = FALSE;

		if (preg_match('/[12]\d{3}(?![-\d])/',$str)) { //includes YYYY-only
			$hasyear = TRUE; }
		if (strpos($str,'M') !== FALSE) {
			$hasmonth = TRUE; }
		if (!$hasmonth && preg_match('/[12]\d{3}-(1[0-2]|0?[1-9])(?![-\d])/',$str)) { //includes YYYY-[M]M-only
			$hasyear = TRUE;
			$hasmonth = TRUE;
		}
		if (strpos($str,'W') !== FALSE) {
			$hasweek = TRUE; }
		if (strpos($str,'D') !== FALSE) {
			$hasday = TRUE; }
		if (!($hasday || $hasweek)) {
			if (preg_match('/^-(0?[1-9]|[12]\d|3[01])(?![-\d])(?![-:\d])/',$str)) { //begins with -[1-31]
				$hasday = TRUE;
			}
			elseif (preg_match('/(?<![-+:\dDWME])(0?[1-9]|[12]\d|3[01])(?![-\d])(?![-:\d])/',$str)) { //includes 1-31
				$hasday = TRUE;
			}
		}
		$longdate = preg_match('/(?<!(-|\d))[12]\d{3}-(1[0-2]|0?[1-9])-(3[01]|0?[1-9]|[12]\d)(?![-\d])/',$str);
		if ($longdate && !$hasday) { //includes YYYY-M[M]-[D]D
			$hasday = TRUE;
		}

		if ($hasyear) {
			if ($hasday) {
				return ($hasmonth) ? 13:12;
			}
			if ($hasweek) {
				return 9;
			}
			return ($hasmonth) ? 7:6;
		} elseif ($hasmonth) {
			if ($hasday) {
				return 11;
			}
			return ($hasweek) ? 8:2;
		} elseif ($hasweek) {
			if ($hasday) {
				return 10;
			}
			return 3;
		} elseif ($hasday) {
			if ($longdate) {
				return 13;
			}
			return (strpos($str,'(') === FALSE && strpos($str,'D') !== FALSE) ? 4:5;
		}
		return 0;
	}

	//Compare members of arrays of parsed period-segments
	private function cmp_periods($a, $b)
	{
		if ($a['F'] !== $b['F']) {
			if ($a['F'] === FALSE)
				return -1;//unspecified == more-general, so first
			if ($b['F'] === FALSE)
				return 1;
			return ($a['F'] - $b['F']);
		}

		if ($a['P'] !== $b['P']) {
			if ($a['P'] === FALSE)
				return -1;
			if ($b['P'] === FALSE)
				return 1;
			if (is_array($a['P']))
				$sa = $a['P'][0];
			else
				$sa = $a['P'];
			if (is_array($b['P']))
				$sb = $a['P'][0];
			else
				$sb = $b['P'];
			if ($sa[0] == '!') {
				if ($sb[0] != '!') {
					return 1;
				}
				$sa = substr($sa,1);
				$sb = substr($sb,1);
			} elseif ($sb[0] == '!') {
				return -1;
			}
			if (is_numeric($sa) && is_numeric($sb)) {
				if ($sa >= 0 && $sb >= 0)
					return ($sa-$sb);
				if ($sa <= 0 && $sb <= 0)
					return ($sb-$sa);
				return ($sa < 0) ? 1:-1;
			}
			$ssa = substr($sa,1); //D* or M* ?
			$ssb = substr($sb,1);
			if (is_numeric($ssa) && is_numeric($ssb))
				return ($ssa-$ssb);
			return ($sa-$sb); //strcmp() BAD for months 10,11,12!
		}

		if ($a['T'] !== $b['T']) {
			if ($a['T'] === FALSE)
				return -1;
			if ($b['T'] === FALSE)
				return 1;
			if (is_array($a['T']))
				$sa = $a['T'][0];
			else
				$sa = $a['T'];
			if (is_array($sa))
				$sa = $sa[0];
			if (is_array($b['T']))
				$sb = $b['T'][0];
			else
				$sb = $b['T'];
			if (is_array($sb))
				$sb = $sb[0];
			if ($sa[0] == '!') {
				if ($sb[0] != '!') {
					return 1;
				}
				$sa = substr($sa,1);
				$sb = substr($sb,1);
			} elseif ($sb[0] == '!') {
				return -1;
			}
			if ($sa-$sb != 0) //lazy string-compare
				return ($sa-$sb);
		}

		if (is_array($a))
			return count($a['P']) - count($b['P']); //shorter == less-specific so first
		return 0;
	}

	/*
	DayNames:

	Get one, or array of, localised day-name(s).
	This is for admin use, the frontend locale may not be the same and/or supported
	by the server.

	@which: 1 (for Sunday) .. 7 (for Saturday), or array of such indices
	@full: optional whether to get long-form name default TRUE
	*/
	private function DayNames($which, $full=TRUE)
	{
		$ret = array();
		//OK to use non-localised times in this context
		$stamp = time();
		$today = date('w',$stamp); //0 to 6 representing the day of the week Sunday = 0 .. Saturday = 6
		if (!is_array($which))
			$which = array($which);
		$f = ($full) ? 'l' : 'D';
		foreach ($which as $day) {
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

	/*
	MonthNames:

	Get one, or array of, localised month-name(s).
	This is for admin use, the frontend locale may not be the same and/or supported
	by the server.

	@which: 1 (for January) .. 12 (for December), or array of such indices
	@full: optional, whether to get long-form name, default TRUE
	*/
	private function MonthNames($which, $full=TRUE)
	{
		$ret = array();
		//OK to use non-localised times in this context
		$stamp = time();
		$thism = date('n',$stamp); //1 to 12 representing January .. December
		if (!is_array($which))
			$which = array($which);
		$f = ($full) ? 'F' : 'M';
		foreach ($which as $month) {
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

	/*
	Lex:

	Parse repeition-descriptor @descriptor into $this->conds.

	@descriptor is split on outside-bracket commas. In resultant PERIOD and/or TIME
	descriptors:
	 un-necessary brackets are excised
	 whitespace & newlines excised
	 all day-names (translated) tokenised to D1..D7
	 all month-names (translated) to M1..M12
	'sunrise' (as translated) to R
	'sunset' (as translated) to S
	'week' (as translated) to W
	'not' and 'except' (as translated) to !
	@descriptor: availability-condition string
	@locale: UNUSED locale identifier string, for correct capitalising of
	  interval-names possibly present in @descriptor
	@report: optional, whether to construct a cleaned variant of @descriptor after parsing,
	  default FALSE
	@slothours: optional, minimum accepted hour-length for repeated items,
	  for decoding small anonymous numbers, default 1.0
	Returns: if @report is TRUE, a 'sanitised' variant of @descriptor, like
	  A(B(C(D(E)))), where ABCD and related brackets may be absent, each of ABCDE
	  may be a singleton value or ','-separated series, any of the former may be
	  a sequence, or any of ABCD may be 'each N'
	  Or if @report is FALSE, returns TRUE. In either case, FALSE upon error.
	*/
	private function Lex($descriptor,/* $locale,*/ $report=FALSE, $slothours=1.0)
	{
		$this->conds = FALSE;

		$gets = range(1,7);
	/*		$oldloc = FALSE;
		if ($locale) {
			$oldloc = setlocale(LC_TIME,"0");
			if (!setlocale(LC_TIME,$locale))
				$oldloc = FALSE;
		}
	*/
		//NB some of these may be wrong, due to locale race on threaded web-server
		$longdays = self::DayNames($gets);
		$shortdays = self::DayNames($gets,FALSE);
		$gets = range(1,12);
		$longmonths = self::MonthNames($gets);
		$shortmonths = self::MonthNames($gets,FALSE);
		unset($gets);
		//TODO caseless match for these, based on locale from somewhere
		$specials = array(
			$this->mod->Lang('to'),
			$this->mod->Lang('not'),
			$this->mod->Lang('except'),
			$this->mod->Lang('each'),
			$this->mod->Lang('every'),
			$this->mod->Lang('sunrise'),
			$this->mod->Lang('sunset'),
			$this->mod->Lang('day'), //for use with 'each' OR Booker\Utils::RangeNames($this->mod,0)
			$this->mod->Lang('week'), //OR Booker\Utils::RangeNames($this->mod,1)
			$this->mod->Lang('month'), //for use with 'each' OR Booker\Utils::RangeNames($this->mod,2)
			$this->mod->Lang('year'), //for use with 'each'
		);
/*		if ($oldloc)
			setlocale(LC_TIME,$oldloc);
*/
		//replacement tokens
		$daytokes = array();
		for ($i = 1; $i < 8; $i++)
			$daytokes[] = 'D'.$i;
		$monthtokes = array();
		for ($i = 1; $i < 13; $i++)
			$monthtokes[] = 'M'.$i;
		//2-char text-tokens to avoid name-conflicts e.g. sunrise-Sun,  extra 'S' or 'E' for recognition
		$spectokes = array('..','!','!','EE','EE','RS','SS','DE','WE','ME','YE');
		//long-forms before short- & specials last, for effective str_replace()
		$finds = array_merge($longdays,$shortdays,$longmonths,$shortmonths,
			$specials,array(' ',PHP_EOL));
		$repls = array_merge($daytokes,$daytokes,$monthtokes,$monthtokes,
			$spectokes,array('',''));
		$descriptor = str_replace($finds,$repls,$descriptor);

		//allowed content check
		if (preg_match('/[^\dDMRSWEY@:+-.,()!]/',$descriptor))
			return FALSE;
		//check overall consistency (i.e. no unrecognised content, all brackets are valid)
		//and separate into distinct comma-separated 'parts'
		$parts = array();
		$storeseg = 0; //index of 1st array-element to merge & store
		$clean = '';
		$depth = 0;

		$segs = explode('(',$descriptor);
		$cs = count($segs);
		foreach ($segs as $i=>&$one) {
			$depth++;
			if ($one) {
				$segl = strlen($one);
				$segat = strrpos($one,'@',-1);
				$e = self::EndScan($one,0); //no need for success-check
				$t = substr($one,0,$e+1);
				//process as period[@time] or time alone
				if ($segat === FALSE && (strpos($t,':') !== FALSE || strpos($t,'S') !== FALSE)) {
					$t = self::CleanTime($t);
				} elseif (strpos($t,',') !== FALSE || strpos($t,'..') !== FALSE) {
					$t = self::CleanPeriod($t);
				} elseif (strpos($t,'EE1') === 0 && (strlen($t) == 3 || !is_numeric($t[3]))) { //ignore 1-separated 'eachers'
					$segs[$i] = '';
					continue;
				}
				//process in-seg bracket(s)
				$p = ($segat === FALSE) ? $segl:$segat;
				if ($p > $e+1) {
					$cb = substr_count($one,')',$e+1,$p-$e-1); //right-bracket(s) following the processed sub-string
				} else {
					$cb = 0;
				}
				if ($cb > 0) {
					$depth -= $cb;
					if ($depth < 0) {//CHECKME or 1?
						return FALSE; }
					if ($cs == 2 && $i > 0 && $segs[$i-1] == '') { //special case (stuff)
						if ($p >= $segl) {
							unset($segs[$i-1]);
						} else {
							$t .= str_repeat(')',$cb);
						}
					} else {
						$t .= str_repeat(')',$cb);
					}
				} else { //no bracket
					if ($t && strpos($t,',') !== FALSE) {
						if ($i > 0) {
							$t .= ')'; //correction
						}
					} elseif ($i > 0 && $segs[$i-1] == '') {
						unset($segs[$i-1]);
					}
				}
				//skip ')' in source-string
				while (++$e < $segl && $one[$e] == ')');
				if ($e >= $segl) {
					$one = $t;
				} else {
					$rest = substr($one,$e); //more stuff to end of segment
					//may be singleton e.g. @14:00 or include part-separator e.g. ,M1 or @9:00,1
					$p = strpos($rest,',');
					if ($p === FALSE) { //no part-separator in $rest
						if ($rest[0] == '@') {
							$t .= '@';
							if ($e+1 < $segl) { //after @ might have '..' sequence or time
								$t .= self::CleanTime($rest);
							}
							$one = $t;
						} else {
							$one = $t.self::CleanPeriod($rest); //$rest might contain '..' sequence only?
						}
					} else {
						//we're done with the current part
						$s = substr($rest,0,$p);
						if ($s[0] == '@') {
							$t .= '@';
							if ($e+1 < $segl) {
								$t .= self::CleanTime($s); } //after @ might have '..' sequence or time
							$one = $t;
						} else
							$one = $t.self::CleanPeriod($s); //TODO or CleanTime() ?
						$clean .= implode('(',array_slice($segs,$storeseg,$i-$storeseg+1));
						$c = substr_count($clean,'(');
						if (substr_count($clean,')') != $c) {
							return FALSE; }
						$parts[] = $clean;
						$storeseg = $i+1; //next merge begins after this segment
						$t = substr($rest,$p+1);
						if (strpos($t,',') !== FALSE || strpos($t,'..') !== FALSE)
							$t = self::CleanPeriod($t);
						//if more seg(s), next implode() won't know about this bit
						if ($i < $cs-1) {
							$t .= '('; }
						$clean = $t;
						$depth = 0;
					}
				}
			}
		}
		unset($one);
		//last (or entire) part
		$clean .= implode('(',array_slice($segs,$storeseg,$i-$storeseg+1));
		if ($clean) {
			$p = substr_count($clean,'(');
			if (substr_count($clean,')') != $p) {
				return FALSE;
			}
			$parts[] = $clean;
		}

		//interpretation
		$repeat = FALSE;
		foreach ($parts as &$one) {
			$parsed = array();
			$p = strpos($one,'@'); //PERIOD-TIME separator present?
			if ($p !== FALSE) {
				$e = (strlen($one) == ($p+1)); //trailing '@'
				if ($p == 0 && !$e) {
					$parsed['P'] = FALSE;
					$parsed['F'] = 1; //enum for only-time-specified
					$parsed['T'] = $report ? $one : self::CleanTime($one,FALSE);
				} else //$p > 0 || $e
					if ($p > 0 && $e) {
					$parsed['P'] = $report ? $one : self::SplitPeriod($one);
					$parsed['F'] = self::GetFocus($one);
					$parsed['T'] = FALSE;
				} elseif ($p > 0) {
					$t = substr($one,0,$p);
					$parsed['P'] = $report ? $t : self::SplitPeriod($t);
					$parsed['F'] = self::GetFocus($t);
					$t = substr($one,$p+1);
					$parsed['T'] = $report ? $t : self::CleanTime($t,FALSE);
				}
			} else { //PERIOD OR TIME
				if (preg_match('~[DMW]~',$one))
					$condtype = 2; //=period
				//check sunrise/set before numbers, in case have sun*-H:M
				elseif (preg_match('~[SR]~',$one))
					$condtype = 1; //=time
				else {
					$condtype = 0; //=undecided
					//catch many dates and numbers (<0(incl. date-separator), >24)
					$n = preg_match_all('~[-:]?\d+~',$one,$matches);
					if ($n) {
						foreach ($matches[0] as $n) {
							if ($n[0] == ':' || $n == 0) { //have minutes, day-of-month never 0
								$condtype = 1;
								break;
							} elseif ($n > 24 || $n < 0) {
								$condtype = 2;
								break;
							}
						}
/*						if ($condtype == 0)	{
							$s = $matches[0][0];
							$e = $matches[0]....;
							if ($s <= 5 && $e <= 31)
							   $condtype = 2; //guess period
						}
*/
					}
				}
				//end of analysis, for now
				if ($condtype == 1) { //time
					$parsed['P'] = FALSE;
					$parsed['F'] = 1;
					$parsed['T'] = $report ? $one : self::CleanTime($one,FALSE);
				} elseif ($condtype == 2) { //period
					$parsed['P'] = $report ? $one : self::SplitPeriod($one);
					$parsed['F'] = self::GetFocus($one);
					$parsed['T'] = FALSE;
				} else { //could be either - re-consider, after all are known
					$repeat = TRUE;
					//park as a time-value, lack of parsed[F] signals reconsideration needed
					$parsed['P'] = FALSE;
					$parsed['T'] = $one;
				}
			}
			$this->conds[] = $parsed;
		}
		unset($one);

		if ($repeat) {
			//small number(s) logged, may be for hour(s) or day(s)
			$useday = FALSE;
			//if any PERIOD recorded, assume all 'bare' numbers are days-of-month
			foreach ($this->conds as &$one) {
				if ($one['P']) {
					$useday = TRUE;
					break;
				}
			}
			unset($one);
			//if still not sure, interpret values in $parts[]
			if (!$useday) {
				//calc min. non-zero difference between small numeric values
				$one = implode(' ',$parts); //'higher-quality' than $descriptor
				$n = preg_match_all('~(?<![-:(\d])[0-2]?\d(?![\d()])~',$one,$matches);
				if ($n > 1) {
					$mindiff = 25.0; //> 24 hours
					$n--; //for 0-base compares
					sort($matches[0],SORT_NUMERIC);
					foreach ($matches[0] as $k=>$one) {
						if ($k < $n) {
							$diff = (float)($matches[0][$k+1] - $one);
							if ($diff > -0.001 && $diff < 0.001) {
								$useday = TRUE; //numbers repeated only in PERIOD-descriptors
								break;
							} elseif ($diff < $mindiff)
								$mindiff = $diff;
						}
					}
					if (!$useday && $mindiff < $slothours)
						$useday = TRUE;
				} elseif ($n) {
					$n = $matches[0][0];
					if ($slothours >= 1.0)
						$useday = ($n < $slothours);
					else
						$useday = ($n < 7 || $n > 19); //arbitrary choice for a single number
				} else
					$useday = TRUE; //should never get here
			}
			//now cleanup the logged values
			foreach ($this->conds as &$one) {
				if (!isset($one['F'])) {
					if ($useday) {
						//treat this as a day-value
						$one['P'] = $one['T'];
						$one['F'] = 5;
						$one['T'] = FALSE;
					} else { //time-only
						$one['F'] = 1;
					}
				}
			}
			unset($one);
		}

		$n = count($this->conds);
		if ($n > 1) {
			usort($this->conds,array($this,'cmp_periods'));
			//re-merge parts of same type
			$p = 0;
			while ($p < $n) {
				$parsed = $this->conds[$p];
				$i = $p+1;
				while ($i < $n) {
					$one = $this->conds[$i];
					if ($one['F'] == $parsed['F'] && $one['T'] == $parsed['T']) {
						if (!(preg_match('/^[!E]/',$one['P']) || preg_match('/^[!E]/',$parsed['P']))) {
							$parsed['P'] .= ','.$one['P'];
							unset($this->conds[$i]);
							$i++;
							continue;
						}
					}
					break;
				}
				$p = $i;
			}
		}

		if ($report) {
			//re-merge parsed array
			$p = 0;
			$s = '';
			foreach ($this->conds as &$one) {
				if ($p === 0)
					$p = 1;
				else
					$s .= ',';
				if ($one['P']) {
					$s .= $one['P'];
					if ($one['T'])
						$s .= '@'.$one['T'];
				} elseif ($one['T'])
					$s .= $one['T'];
			}
			unset($one);
			//keep any replaced 'to'
			array_shift($spectokes);
			array_shift($specials);
			//$specials last, to prevent dayname conflicts e.g. month(M) vs M1(Jan)
			//reverse month-arrays to match M10-M12 before M1
			$finds = array_merge($daytokes,array_reverse($monthtokes),$spectokes);
			$repls = array_merge($shortdays,array_reverse($shortmonths),$specials);
			return str_replace($finds,$repls,$s);
		} else
			return TRUE;
	}

	//========== PUBLIC FUNCS ===========

	/**
	ParseDescriptor:
	Parse @descriptor and store result in $this->conds.
	This (or CheckDescriptor()) must be called before any poll for a suitable period,
	or check for a matching period.
	@descriptor: interval-descriptor string
	@locale: UNUSED optional, locale identifier string for correct capitalising of day/month names
	  possibly present in @descriptor, default ''
	Returns: TRUE upon success or if @descriptor is FALSE, otherwise FALSE.
	*/
	public function ParseDescriptor($descriptor/*, $locale=''*/)
	{
		if ($descriptor) {
			return self::Lex($descriptor/*,$locale*/); }
		$this->conds = FALSE;
		return TRUE;
	}

	/**
	CheckDescriptor:
	Determine whether @descriptor has correct syntax.
	Stores parsed form of @descriptor in array $this->conds.
	This (or ParseDescriptor()) must be called before any poll for a suitable period,
	or check for a matching period.
	@descriptor: availability-condition string
	@locale: UNUSED optional, locale identifier string for correct capitalising of day/month names
	  possibly present in @descriptor, default ''
	Returns: '' if @descriptor is FALSE, or a cleaned-up variant of @descriptor,
	or FALSE if @descriptor is bad.
	*/
	public function CheckDescriptor($descriptor/*, $locale=''*/)
	{
		if ($descriptor) {
			return self::Lex($descriptor,/*$locale,*/TRUE); }
		$this->conds = FALSE;
		return '';
	}
}
