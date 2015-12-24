<?php
/*
This file is a class for CMS Made Simple (TM).
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>

This file is free software; you can redistribute it and/or modify it under
the terms of the GNU Affero General Public License as published by the
Free Software Foundation; either version 3 of the License, or (at your option)
any later version.

This file is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details. If you don't have a copy
of that license, read it online at: www.gnu.org/licenses/licenses.html#AGPL

Class: IntervalParser
*/
/**
Interval Language

Intervals are specified in a string of the form
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
 (X,Y[,...]) which specifies a series of individual values, in any order, all of
 which are valid; or
 X..Y which specifies a sequence (an inclusive range of sequential values), all
 of which are valid, and normally Y should be chronologically after X (except
 when spanning a time-period-end e.g. 23:30..2:30 or Friday..Sunday or
 December..February).

Ordinary numeric sequences and ranges are supported, and for such ranges, Y must
be greater than X, and X and/or Y may be negative.

Any value, bracketed-sequence or range may itelf be bracketed and preceded by a
qualifier, being a single value, or scope-expander of any of the above types.
Such qualification may be nested/recursed, like S(R(Q(P))).

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

TODO Any period-descriptor may be prefaced by (translated, TODO any case) 'each'
or 'every' N, to represent fixed-interval repetition such as 'every 2nd Wednesday'

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
 week(s)-of-named-month: 2(week(March)) or or 1..3(week(July,August))
   or (-2,-1)(week(April..July))
Day descriptors
 day(s)-of-any-month: 1 or -2 or (15,18,-1) or 1..10 or 2..-1 or -3..-1 or
    1(Sunday) or -1(Wednesday..Friday) or 1..3(Friday,Saturday)
 day(s)-of-specific-week(s): Sunday(2(week)) or
   (Saturday,Wednesday)(-3..-1(week(July))) or
   Monday..Friday((-2,-1)(week(April..July)))
 day(s)-of-any-week: Monday or (Monday,Wednesday,Friday) or Wednesday..Friday
 specific day(s): 2000-9-1 or 2000-10-1..2000-12-31
Time descriptors
 9 or 2:30 or (9,12,15:15) or 12..23 or 6..15:30 or sunrise..16 or 9..sunset-3:30
*/

class IntervalParser
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
 OR  4 day(s) of any month 1,10,-2
		 5 specific year(s) 2020,2015
		 6 month(s) of specific year(s) Jan(2010..2020) OR 2015-1
		 7 week(s) of specific year(s) 1(week(Aug..Dec(2020)))
		 8 week(s) of specific month(s) 1(week(August,September))
		 9 day(s) of specific year(s) Wed((1,-1)(week(June(2015..2018))))
		10 day(s) of specific week(s)  Wed(2(week))
 OR 10 day(s) of specific month(s) 1(Aug) OR Wed((1,2)(week(June)))
		11 specfic day/date(s) 2010-6-6 OR 1(Aug(2015..2020))
	'P' => FALSE or PERIOD = structure of arrays and strings representing
		period-values and/or period-value-ranges (i.e. not series), all ordered by
		increasing value/range-start (TODO EXCEPT ROLLOVERS?) Negative values
		in ['P'] are sorted after positives, but not interpreted to corresponding
		actual values.
	'T' => FALSE or TIME = array of strings representing time-values and/or
		time-value-ranges and/or time-value-series, with sun-related ones first,
		all ordered by increasing value/range-start (TODO EXCEPT ROLLOVERS?)
		Times are not interpreted. Other than sun-related values, they can be
		converted to midnight-relative seconds, and any overlaps 'coalesced'.
		Sun-related values must of course be interpreted for each specific day
		evaluated.

	Descriptor-string parsing works LTR. Maybe sometime RTL languages will also
	be supported ?!
	$conds will be sorted, first on members' ['F']'s, then on their ['P']'s,
	then on their ['T']'s.
	*/
//	protected
	public $conds = FALSE;
//private $ob; //'(' for ltr languages, ')' for rtl
//private $cb; //')' for ltr languages, '(' for rtl
	function __construct(&$mod)
	{
		$this->mod = $mod;
	}

	/*
	_MatchBracket:
	Scan @str from offset @offset towards end, to find matching ')'. Nesting is
	supported
	@str: string
	@offset points to opening '(' in @str
	Returns: offset of matched ')' in @str, or -1 if incorrect nesting found
	*/
	private function _MatchBracket($str,$offset)
	{
		$p = $offset;
		$d = 0;
		$l = strlen($str);
		While ($p < $l)
		{
			$c = $str[$p++];
			if($c == '(')
				$d++; //should go to 1 immediately
			elseif($c == ')')
			{
				if(--$d == 0)
					return $p-1;
				elseif($d < 0)
					return -1;
			}
		}
		return -1;
	}

	/*
	_StartSequence:
	Scan @str from offset @offset towards start, until start or any char in [!(),]
	@str: string
	@offset points to initial '.' in @str
	Returns: offset in @str = 0, or after matched char, or -1 if '.' found
	*/
	private function _StartSequence($str,$offset)
	{
		$p = $offset;
		$d = 0;
		while ($p >= 0)
		{
			$c = $str[$p--];
			switch($c)
			{
			 case '.':
				if(++$d > 1)
					return -1;
				break;
			 case ')':
			 case '(':
			 case ',':
			 case '!':
				if($p+2 < $offset)
					return $p+2;
				return -1;
			}
		}
		if($offset > 0);
			return 0;
		return -1;
	}

	/*
	_EndSequence:
	Scan @str from offset @offset towards end until end, or any char in [(),@]
	@str: string
	@offset points to initial '.' in @str
	Returns: offset in @str = last char, or before matched char, or -1 if another '..' found
	*/
	private function _EndSequence($str,$offset)
	{
		$d = 0;
		$p = $offset;
		$l = strlen($str);
		While ($p < $l)
		{
			$c = $str[$p++];
			switch($c)
			{
			 case '.':
				if(++$d > 2)
					return -1;
				break;
			 case ')';
			 case '(';
			 case ',';
			 case '@';
				if($p-2 > $offset)
					return $p-2;
				return -1;
			}
		}
		if($l-1 > $offset)
			return $l-1; //offset of end
		return -1;
	}

	/*
	_StartSeries:
	Scan @str from offset @offset towards start, until start or any char in [!()]
	@str: string
	@offset points to initial ',' in @str
	Returns: offset in @str = 0, or after matched char, or -1 if error
	*/
	private function _StartSeries($str,$offset)
	{
		$p = $offset;
		while ($p >= 0)
		{
			$c = $str[$p--];
			switch($c)
			{
//			 case '.':
//TODO go forward c.f. _EndSequence($str,$offset)
//				 break;
			 case ')':
			 case '(':
			 case '!':
				if($p+2 < $offset)
					return $p+2;
				return -1;
			}
		}
		if($offset > 0);
			return 0;
		return -1;
	}

	/*
	_EndSeries:
	Scan @str from offset @offset towards end until end, or any char in [()@]
	@str: string
	@offset points to initial ',' in @str
	Returns: offset in @str = last char, or before matched char, or -1 if error
	*/
	private function _EndSeries($str,$offset)
	{
		$p = $offset;
		$l = strlen($str);
		While ($p < $l)
		{
			$c = $str[$p++];
			switch($c)
			{
//			 case '.':
//TODO go back c.f. _StartSequence($str,$offset)
//				break;
			 case ')';
			 case '(';
			 case '@';
				if($p-2 > $offset)
					return $p-2;
				return -1;
			}
		}
		if($l-1 > $offset)
			return $l-1; //offset of end
		return -1;
	}

	//Compare numbers such that -ve's last
	private function _cmp_numbers($a,$b)
	{
		if(($a >= 0 && $b < 0) || ($a < 0 && $b >= 0))
			return ($b-$a);
		return ($a-$b);
	}

	//Compare strings like D* or M*
	private function _cmp_named($a,$b)
	{
		$sa = $a[0];
		$sb = $b[0];
		if($sa != $sb)
			return ($sa - $sb); //should never happen
		$sa = (int)substr($a,1);
		$sb = (int)substr($b,1);
		return ($sa - $sb);
	}

	//Compare date-strings like YYYY[-[M]M[-[D]D]]
	private function _cmp_dates($a,$b)
	{
		//bare years don't work correctly
		$s = (strpos($a,'-')!=FALSE) ? $a:$a.'-1-1';
		//for relative times, don't need localised DateTime object
		$stA = strtotime($s);
		$s = (strpos($b,'-')!=FALSE) ? $b:$b.'-1-1';
		$stB = strtotime($s);
		return ($stA-$stB);
	}

	/*
	PeriodSegment:
	@str: tokenised PERIOD-component of an interval descriptor
	Split @str into segments which can be independently analysed. @str may be
	with or without '()' nested segments, and if with, then any depth e.g. S(R(Q(P)))
	Any segment (as represented by any of the letters in the example) may include
	one or more sequences which are '()' enclosed
	Returns: array with members which are the segment(s), or FALSE upon error
	*/
	private	function _PeriodSegment($str)
	{
		if(!$str)
			return FALSE;
		if(strpos($str,'(') === FALSE)
			return array($str);
		$ret = array();
		$r = 0;
		$parts = explode('(',$str);
		$l = count($parts) - 1;
		foreach($parts as $p=>$seg)
		{
			$o = strpos($seg,')');
			if($o === FALSE)
			{
				$ret[$r++] = $seg;
			}
			elseif($p < $l) //found ) somewhere in not the last segment
			{
				if($r > 0)
					$ret[$r-1] .= '('.$seg; //use it as-is
				else
					$ret[$r++] = '('.$seg;
			}
			else //last
			{
				$so = $o;
				$sl = strlen($seg);
				$c = 0; $x = 0;
				while($so < $sl)
				{
					if($seg[$so++] == ')')
						$c++; //count of ) chars
					else
						$x++; //something other than ) found
				}
				if($x > 0) //some other text - assume not nested
				{
					$ret[$r] = '('.$seg;
				}
				else
				{
					$ret[$r] = substr($seg,0,$o);
					//validate rest of segment
					if($c != count($ret) - 1)
						return FALSE;
				}
			}
		}
		if($ret[0] === '')
			array_shift($ret);
		return $ret;
	}

	/*
	_ParsePeriodSequence:

	This is for period-identifiers, no times are handled.
	(TODO v.2 support e.g. Sunday@10..Monday@15:30 ?)
	Depending on @getstr, this may return a 3-member array(L,'.',H) or a string
	represenation of that array 'L..H'. In either case, the return may be a
	single value L(==H) or FALSE upon error.
	The second/middle array value '.'	is flag, for further processors, that the
	array represents a range. L and/or H are not interpreted in any way, except that
	incomplete date-values will be populated.

	@str: string to be parsed, containing '..' (and no surrounding brackets, of course)
	@getstr: optional, whether to return re-constituted string, default TRUE
	*/
	private function _ParsePeriodSequence($str,$getstr=TRUE)
	{
		$parts = explode('..',$str,2);
		while($parts[1][0] == '.')
			$parts[1] = substr($parts[1],1);
		if($parts[0] === '' || $parts[1] === '')
			return FALSE;
		if($parts[0] == $parts[1])
			return $parts[0];
		//order the pair
		$swap = FALSE;
		$dateptn = '/^([12][0-9]{3})(-(1[0-2]|0?[1-9])(-(3[01]|0?[1-9]|[12][0-9]))?)?$/';
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
		if(preg_match($dateptn,$parts[0],$loparts) && preg_match($dateptn,$parts[1],$hiparts))
		{
			$swap = ($hiparts[1] < $loparts[1]);
			if($swap)
			{
				if(!isset($hiparts[3]) && isset($loparts[3]))
					$hiparts[3] = '1';
				if(!isset($hiparts[5]) && isset($loparts[5]))
					$hiparts[5] = '1';
				if(!isset($loparts[3]) && isset($hiparts[3]))
					$loparts[3] = '12';
				if(!isset($loparts[5]) && isset($hiparts[5]))
				{
					$stamp = mktime(0,0,0,(int)$loparts[3],15,(int)$loparts[1]);
					$loparts[5] = date('t',$stamp); //last day of specified month
				}
			}
			else
			{
				$swap = ($hiparts[1] == $loparts[1]) && (!isset($hiparts[3]) ||
					(isset($loparts[3]) && $hiparts[3] < $loparts[3]));
				if($swap)
				{
					if(!isset($hiparts[3]) && isset($loparts[3]))
						$hiparts[3] = '1';
					if(!isset($hiparts[5]) && isset($loparts[5]))
						$hiparts[5] = '1';
					if(!isset($loparts[3]) && isset($hiparts[3]))
						$loparts[3] = '12';
					if(!isset($loparts[5]) && isset($hiparts[5]))
					{
						$stamp = mktime(0,0,0,(int)$loparts[3],15,(int)$loparts[1]);
						$loparts[5] = date('t',$stamp); //last
					}
				}
				else
				{
					if(!isset($loparts[5]) && isset($hiparts[5]))
					{
						if($hiparts[5] != '1')
							$loparts[5] = '1';
						else
						{
							$stamp = mktime(0,0,0,(int)$hiparts[3],15,(int)$hiparts[1]);
							$loparts[5] = date('t',$stamp); //last
						}
					}
					if(!isset($hiparts[5]) && isset($loparts[5]))
					{
						$stamp = mktime(0,0,0,(int)$hiparts[3],15,(int)$hiparts[1]);
						$tmp = date('t',$stamp); //last
						if($loparts[5] != tmp)
							$hiparts[5] = $tmp;
						else
							$hiparts[5] = '1';
					}
					if(isset($hiparts[5]) && isset($loparts[5]))
					{
						if($hiparts[5] < $loparts[5])
							$swap = TRUE;
					}
				}
			}
			$parts[0] = $loparts[1];
			if(isset($loparts[3]))
				$parts[0] .= '-'.$loparts[3];
			if(isset($loparts[5]))
				$parts[0] .= '-'.$loparts[5];
			$parts[1] = $hiparts[1];
			if(isset($hiparts[3]))
				$parts[1] .= '-'.$hiparts[3];
			if(isset($hiparts[5]))
				$parts[1] .= '-'.$hiparts[5];
		}
		elseif(is_numeric($parts[0]) && is_numeric($parts[1]))
		{
			$s = (int)$parts[0];
			$e = (int)$parts[1];
			if(($s > 0 && $e > 0)||($s < 0 && $e < 0))
				$swap = ($s > $e);
			else
				$swap = ($s < $e);
		}
		else
		{
			//both should be D* or M*
			if($parts[0][0] != $parts[1][0])
				return FALSE;
			$s = (int)substr($parts[0],1);
			$e = (int)substr($parts[1],1);
			if(($s > 0 && $e > 0)||($s < 0 && $e < 0))
				$swap = ($s > $e);
			else
				$swap = ($s < $e);
		}
		if($swap)
		{
			$t = $parts[0];
			$parts[0] = $parts[1];
			$parts[1] = $t;
		}
		if($getstr)
			return $parts[0].'..'.$parts[1];
		else
			return array($parts[0],'.',$parts[1]);
	}

	/*
	_ParsePeriodSeries:

	This is for period-identifiers, no times are handled.
	Expects all values are the same type as the 1st in @str, otherwise error.
	Depending on @getstr, this may return a N-member, ascending-sorted, no-duplicates,
	contiguous-keyed array(L,...,H), or a bracket-enclosed, comma-separated string
	represenation of that array. In either case, this may also return a single value
	L(==all others) or FALSE upon error.

	@str: string to be parsed, containing 0 or more ','s and no surrounding brackets
	@getstr: optional, whether to return re-constituted string, default TRUE
	*/
	private function _ParsePeriodSeries($str,$getstr=TRUE)
	{
		$parts = explode(',',$str);
		if(!isset($parts[1])) //aka count($parts) == 1
		{
			if(strpos($str,'..') === FALSE)
				return $str;
			return self::_ParsePeriodSequence($str,$getstr);
		}
		$dateptn = '/^([12]\d{3})(-(1[0-2]|0?[1-9])(-(3[01]|0?[1-9]|[12]\d))?)?$/';
		$val = $parts[0];
		if(preg_match($dateptn,$val))
		{
			$type = 3;
			$cmp = '_cmp_dates';
		}
		elseif(is_numeric($val))
		{
			$type = 1;
			$cmp = '_cmp_numbers';
		}
		else
		{
			$type = 2;
		 	$cmp = '_cmp_named';
		}

		foreach($parts as &$val)
		{
			switch ($type)
			{
			 case 1:
				if(is_numeric($val))
				{
					break;
				}
				elseif(strpos($val,'..') !== FALSE)
				{
					$r = self::_ParsePeriodSequence($val,FALSE);
					if(is_array($r) && is_numeric($r[0]))
						$val = $r[0].'..'.$r[2]; //no sub-array here, prior to flip/de-dup
					elseif($r && is_numeric($r))
						$val = $r;
					else
					{
						$parts = FALSE;
						break 2;
					}
					break;
				}
				else
				{
					$parts = FALSE;
					break 2;
				}
			 case 2:
				if(strpos($val,'..') !== FALSE)
				{
					$r = self::_ParsePeriodSequence($val,FALSE);
					if(is_array($r)) //TODO && same type of non-numeric
						$val = $r[0].'..'.$r[2]; //no sub-array here
					elseif($r) //TODO && same type of non-numeric
						$val = $r;
					else
					{
						$parts = FALSE;
						break 2;
					}
					break;
				}
				elseif(1) //TODO same type of non-numeric
				{
					break;
				}
				else
				{
					$parts = FALSE;
					break 2;
				}
			 case 3:
				if(strpos($val,'..') !== FALSE)
				{
					$r = self::_ParsePeriodSequence($val,FALSE);
					if(is_array($r) && preg_match($dateptn,$r[0]))
						$val = $r[0].'..'.$r[2]; //no sub-array here
					elseif($r && preg_match($dateptn,$r))
						$val = $r;
					else
					{
						$parts = FALSE;
						break 2;
					}
					break;
				}
				elseif(preg_match($dateptn,$val))
				{
					break;
				}
				else
				{
					$parts = FALSE;
					break 2;
				}
			}
		}
		unset($val);
		if($parts == FALSE)
			return '';
		//remove dup's without sorting
		$parts = array_flip($parts);
		if(count($parts) > 1)
		{
			$parts = array_flip($parts);
			usort($parts,array($this,$cmp)); //keys now contiguous
			if($getstr)
				return '('.implode(',',$parts).')';
			else
				return $parts;
		}
		else
			return key($parts);
	}

	/*
	@str: tokenised PERIOD-component of an interval descriptor, like S(R(Q(P)))
	@report: whether to construct a cleaned variant of @descriptor after parsing
	Returns: according to @report, either a sanitised variant of @str, or an array,
	or in either case FALSE upon error
	The array will have one member, or more if @str has ','-separated sub-strings.
	Each member is a sanitised substr of @str, representing a singleton value or a sequence.
	No 'focus-level' interpretation here.
	*/
	private	function _PeriodClean($str,$report)
	{
		$parts = self::_PeriodSegment($str);
		if($parts == FALSE)
			return '';
		//sanitize
		foreach($parts as &$one)
		{
			$l = strlen($one);
			$clean = '';
			for($p = 0; $p < $l; $p++)
			{
				$c = $one[$p];
				switch($c)
				{
				 case '(':
					$e = self::_MatchBracket($one,$p); //matching brace
					if($e != -1)
					{
						$s = $p+1;
						$t = self::_ParsePeriodSeries(substr($one,$s,$e-$s));
						if($t !== FALSE)
						{
							$clean .= $t;
							$p = $e; //resume after closing bracket
							break;
						}
					}
					return FALSE;
				 case ')': //nested ) should never happen in this context
					return FALSE;
				 case ',':
					 $s = self::_StartSeries($one,$p);
					 $e = self::_EndSeries($one,$p);
					 if($s != -1 && $e != -1)
					 {
						$t = self::_ParsePeriodSeries(substr($one,$s,$e-$s+1));
						if($t !== FALSE)
						{
							$clean = substr($clean,0,$s) . $t;
							$p = $e+1; //resume after closing bracket
							break;
						}
					 }
					 return FALSE;
				 case '.':
					$s = self::_StartSequence($one,$p);
					$e = self::_EndSequence($one,$p);
					if($s != -1 && $e != -1)
					{
						//can't safely create range sub-array before $parts[] sort and de-dup
						$t = self::_ParsePeriodSequence(substr($one,$s,$e-$s+1),TRUE);
						if($t !== FALSE)
						{
							$clean = substr($clean,0,$s) . $t;
							$p = $e+1;
							break;
						}
					}
					return FALSE;
				 default:
					$clean .= $c;
					break;
				}
			}
			$one = $clean;
		}
		unset($one);

		if($report)
		{
			$ret = $parts[0];
			$pc = count($parts);
			if($pc > 1)
			{
				for($i=1; $i<$pc; $i++)
					$ret .= '('.$parts[$i];
				for($i=1; $i<$pc; $i++)
					$ret .= ')';
			}
			return $ret;
		}
		else
		{
			//CHECKME convert ranges to arrays (L,.,H)
			return $parts;
		}
	}

	//upstream callers all use the returned value for relative checks,
	//so no need to work with a DateTime object
	private function _RelTime($timestr)
	{
		$nums = explode(':',$timestr,3);
		$str = '';
		if(!empty($nums[0]) && $nums[0] != '00')
			$str .= ' '.$nums[0].' hours';
		if(!empty($nums[1]) && $nums[1] != '00')
			$str .= ' '.$nums[1].' minutes';
		if(!empty($nums[2]) && $nums[2] != '00')
			$str .= ' '.$nums[2].' seconds';
		if(empty($str))
			$str = '+ 0 seconds';
		return strtotime($str,0);
	}

	/**
	_ParseTimeRange:

	@str: string to be parsed, containing '..' (and no surrounding brackets, of course)
	@getstr: optional, whether to return re-constituted string, default TRUE
	*/
	private	function _ParseTimeRange($str,$getstr=TRUE)
	{
		$parts = explode('..',$str,2);
		while($parts[1][0] == '.')
			$parts[1] = substr($parts[1],1);
		if($parts[0] === '' || $parts[1] === '')
			return FALSE;
		if($parts[0] == $parts[1])
			return $parts[0];

		$timeptn = '/^([RS]([+-](1[0-2]|[0]?[0-9]):[0-5]?[0-9])?|(2[0-3]|[01]?[0-9]):[0-5]?[0-9])$/';
/* $pattern matches
e.g 'S' >> array
  0 => string 'S'
  1 => string 'S'
e.g. 'S+[H]H:[M]M' >> array
  0 => string 'S+[H]H:[M]M'
  1 => string 'S+[H]H:[M]M'
  2 => string '+[H]H:[M]M'
  3 => string '[H]H'
'[H]H:[M]M' >> array
  0 => string '[H]H:[M]M'
  1 => string '[H]H:[M]M'
  2 => string ''
  3 => string ''
  4 => string '[H]H'
*/
		if(preg_match($timeptn,$parts[0],$loparts) && preg_match($timeptn,$parts[1],$hiparts))
		{
			//order the pair
			if(strpos($parts[0],'S') !== FALSE)
			{
				if(strpos($parts[1],'S') !== FALSE)
				{
					$kl = array_key_exists(2,$loparts);
					$kh = array_key_exists(2,$hiparts);
					if($kl && $kh)
						$swap = (self::_RelTime($hiparts[2]) < self::_RelTime($loparts[2]));
					elseif($kl) //hi has no time-offset
						$swap = (self::_RelTime($loparts[2]) < 0);
					elseif($kh) //lo has no time-offset
						$swap = (self::_RelTime($hiparts[2]) > 0);
					else
						$swap = FALSE;
				}
				elseif(strpos($parts[1],'R') !== FALSE)
					$swap = TRUE; //rise before set
				else
					$swap = FALSE;
			}
			elseif(strpos($parts[0],'R') !== FALSE)
			{
				if(strpos($parts[1],'S') !== FALSE)
					$swap = FALSE;
				elseif(strpos($parts[1],'R') !== FALSE)
				{
					$kl = array_key_exists(2,$loparts);
					$kh = array_key_exists(2,$hiparts);
					if($kl && $kh)
							$swap = (self::_RelTime($hiparts[2]) < self::_RelTime($loparts[2]));
					elseif($kl) //hi has no time-offset
						$swap = (self::_RelTime($loparts[2]) < 0);
					elseif($kh) //lo has no time-offset
						$swap = (self::_RelTime($hiparts[2]) > 0);
					else
						$swap = FALSE;
				}
				else
					$swap = FALSE;
			}
			elseif(strpos($parts[1],'S') !== FALSE)
				$swap = TRUE; //sun* first
			elseif(strpos($parts[1],'R') !== FALSE)
				$swap = TRUE; //sun* first
			else
				$swap = (self::_RelTime($hiparts[1]) < self::_RelTime($loparts[1]));

			if($swap)
			{
				$t = $parts[0];
				$parts[0] = $parts[1];
				$parts[1] = $t;
			}
			if($getstr)
				return $parts[0].'..'.$parts[1];
			else
				return array($parts[0],'.',$parts[1]);
		}
		return FALSE;
	}

	private function _cmp_plaintimes($a,$b)
	{
		$sa = strpos($a,':');
		$sb = strpos($b,':');
		if($sa === FALSE)
		{
			if($sb === FALSE)
				return ($a-$b);
			else //$b has minutes
			{
				$h = substr($b,0,$sb) + 0;
				return ($a != $h) ? ($a-$h) : -1;	//$b later for same hour
			}
		}
		elseif($sb === FALSE) //and $a has minutes
		{
			$h = substr($a,0,$sa) + 0;
			return ($h != $b) ? ($h-$b) : 1; //$a later for same hour
		}
		else
		{
			$h = substr($a,0,$sa) + 0;
			$h2 = substr($b,0,$sb) + 0;
			if($h == $h2)
			{
				$h = substr($a,$sa+1) + 0;
				$h2 = substr($b,$sb+1) + 0;
			}
			return ($h-$h2);
		}
	}

	//Compare time-strings like [sun*[+-]][h]h[:[m]m]] without expensive
	//time-conversions and with all sun* before all others
	private function _cmp_times($a,$b)
	{
		$sa = strpos($a,'..');
		if($sa !== FALSE)
			$a = substr($a,0,$sa);
		$sb = strpos($b,'..');
		if($sb !== FALSE)
			$b = substr($b,0,$sb);
		$ra = strpos($a,'R');
		$rb = strpos($b,'R');
		if($ra !== FALSE)
		{
			if($rb === FALSE)
			{
				return -1;
			}
			else
			{
				$ma = (strlen($a) > $ra+1);
				if($ma) $na = $a[$ra+1];
				$mb = (strlen($b) > $rb+1);
				if($mb) $nb = $b[$rb+1];
				if($ma && $mb)
				{
					if($na != $nb)
						return (ord($nb)-ord($na)); //'+' < '-' so reverse
					elseif($na == '+')
					{
						$a = substr($a,$ra+2);
						$b = substr($b,$rb+2);
						return self::_cmp_plaintimes($a,$b);
					}
					elseif($na == '-')
					{
						$a = substr($a,$ra+2);
						$b = substr($b,$rb+2);
						return self::_cmp_plaintimes($b,$a); //swapped
					}
					else
						return FALSE;
				}
				elseif($ma && !$mb)
				{
					return ($na=='+') ? 1:-1;
				}
				elseif($mb && !$ma)
				{
					return ($nb=='+') ? -1:1;
				}
				return 0;
			}
		}
		$sa = strpos($a,'S');
		$sb = strpos($b,'S');
		if($sa !== FALSE)
		{
			if($sb === FALSE)
			{
				//want sunset after sunrise, before others
				if($rb !== FALSE) //$b includes R
					return ($ra===FALSE) ? 1:-1;
				else //no R at all
					return ($ra===FALSE) ? -1:1;
			}
			else
			{
				$ma = (strlen($a) > $sa+1);
				if($ma) $na = $a[$sa+1];
				$mb = (strlen($b) > $sb+1);
				if($mb) $nb = $b[$sb+1];
				if($ma && $mb)
				{
					if($na != $nb)
						return (ord($nb)-ord($na)); //'+' < '-' so reverse
					elseif($na == '+')
					{
						$a = substr($a,$sa+2);
						$b = substr($b,$sb+2);
						//fall through to compare $a,$b
					}
					elseif($na == '-')
					{
						$t = substr($b,$sb+2); //swap
						$b = substr($a,$sa+2);
						$a = $t;
						//fall through to compare $a,$b
					}
					else
						return FALSE;
				}
				elseif($ma && !$mb)
					return ($na=='+') ? 1:-1;
				elseif($mb && !$ma)
					return ($nb=='+') ? -1:1;
				return 0;
			}
		}
		elseif($rb !== FALSE)
			return ($sa !== FALSE) ? -1 : 1; //sunset after sunrise, before others
		elseif($sb !== FALSE)
			return ($ra !== FALSE) ? -1 : 1; //ditto
		//now just time-values
		return self::_cmp_plaintimes($a,$b);
	}

	/*
	@str is TIME component of a condition
	Depending on @report, returns sanitised variant of @str or array of timevalues,
	with sun-related values first, rest sorted ascending by value or start of range
	where relevant, or in either case FALSE upon error
	*/
	private function _TimeClean($str,$report)
	{
		//TODO handle (T1,...TN) when applying all to specified P[s]
		$parts = array();
		$one = '';
		$s = 0; $e = 0; $d = 0; $l = strlen($str);
		for($p = 0; $p < $l; $p++)
		{
			$c = $str[$p];
			switch ($c)
			{
			 case '(':
			 	if(++$d == 1)
				{
					$e = self::_MatchBracket($str,$p); //matching brace (in case nested)
					if($e != -1)
					{
						$s = $p+1;
						$t = self::_TimeClean(substr($str,$s,$e-$s),FALSE); //recurse
						if(is_array($t))
							$parts = array_merge($parts,$t);
						elseif($t !== FALSE)
							$parts[] = $t;
						else
							return FALSE;
						$d = 0;
						$one = '';
						$p = $e; //resume after closing bracket
						break;
					}
					return FALSE;
				}
				break;
 			 case ')': //nested ) should never happen in this context
			 	if(--$d < 0)
					return FALSE;
				break;
			 case '.':
				$s = self::_StartSequence($str,$p);
				$e = self::_EndSequence($str,$p);
				if($s != -1 && $e != -1)
				{
					//cannot safely create range-array before $parts[] sort and de-dup
					$t = self::_ParseTimeRange(substr($str,$s,$e-$s+1),TRUE);
					if($t !== FALSE)
					{
						$parts[] = $t;
						$one = '';
						$p = $e;
						break;
					}
				}
				return FALSE;
			 case ':':
 			  if($p > $e)
				{
					$c = $str[$p-1];
					if($c<'0' || $c>'9')
						$one .= '0';
				}
			  if($p < $l-1)
				{
					$c = $str[$p+1];
					if($c<'0' || $c>'9')
						$one .= ':0';
					else
						$one .= ':';
				}
				else
					$one .= ':0';
				break;
		  default:
				if ($c != ',')
					$one .= $c;
				elseif($one)
				{
					$parts[] = $one;
					$one = '';
				}
				break;
			}
		}
		if($one)
			$parts[] = $one; //last one
		elseif($parts == FALSE)
			return '';
		//remove dup's without sorting
		$parts = array_flip($parts);
		if(count($parts) > 1)
		{
			$parts = array_flip($parts);
			usort($parts,array($this,'_cmp_times'));
			if($report)
				return '('.implode(',',$parts).')';
			else
			{
				//CHECKME convert ranges to arrays (L,.,H)
				return $parts;
			}
		}
		else
			return key($parts);
	}

	/*
	_GetFocus:
	Interpret @parts to determine the corresponding 'F' parameter
	@parts: any-size, any-order array of parsed segments from a period-descriptor e.g.
    0 => 'D4'
    1 => '(1,2,-1)'
    2 => 'W'
    3 => 'M6'
    4 => '2015..2018'
	Returns: enum 1..11 reflecting @parts:
		 0 can't figure out anything better
		 1 no period i.e. any (time-only)
		 2 month(s) of any year June,July
		 3 week(s) of any month (1,-1)week
		 4 day(s) of any week Sun..Wed
	 OR  4 day(s) of any month 1,10,-2
		 5 specific year(s) 2020,2015
		 6 month(s) of specific year(s) Jan(2010..2020) OR 2015-1
		 7 week(s) of specific year(s) 1(week(Aug..Dec(2020)))
		 8 week(s) of specific month(s) 1(week(August,September))
		 9 day(s) of specific year(s) Wed((1,-1)(week(June(2015..2018))))
		10 day(s) of specific week(s)  Wed(2(week))
	 OR 10 day(s) of specific month(s) 1(Aug) OR Wed((1,2)(week(June)))
		11 specfic day/date(s) 2010-6-6 OR 1(Aug(2015..2020))
	*/
	private function _GetFocus($parts)
	{
		$hasday = FALSE;
		$hasweek = FALSE;
		$hasmonth = FALSE;
		$hasyear = FALSE;
		if(!is_array($parts))
			$parts = array($parts);
		foreach($parts as &$one)
		{
			if(!$hasyear && preg_match('/[12][0-9]{3}(?!(-|[0-9]))/',$one)) //includes YYYY-only
				$hasyear = TRUE;
			if(!$hasmonth && strpos($one,'M') !== FALSE) $hasmonth = TRUE;
			if(!$hasmonth && preg_match('/[12][0-9]{3}-(1[0-2]|0?[1-9])(?!(-|[0-9]))/',$one)) //includes YYYY-[M]M-only
				$hasmonth = TRUE;
			if(!$hasweek && strpos($one,'W') !== FALSE) $hasweek = TRUE;
			if(!$hasday && strpos($one,'D') !== FALSE) $hasday = TRUE;
			if(!$hasday && preg_match('/(?<!(-|[0-9]))(3[01]|0?[1-9]|[12][0-9])(?!(-|[0-9]))/',$one)) //includes 1-31
				$hasday = TRUE;
			if(!$hasday && preg_match('/(?<!(-|[0-9]))[12][0-9]{3}-(1[0-2]|0?[1-9])-(3[01]|0?[1-9]|[12][0-9])(?!(-|[0-9]))/',$one)) //includes YYYY-M[M]-[D]D
				$hasday = TRUE;
		}
		unset($one);
		if($hasyear)
		{
			if($hasmonth)
			{
				if($hasweek)
				{
/*
7 IF
1 hasday
W hasweek
M8..M12 hasmonth
2020 hasyear
OR 9 IF
D4 hasday
(1,-1) hasday
W hasweek
M6 hasmonth
2015..2018 hasyear
*/
					return (count($parts) == 4) ? 7:9;
				}
				if($hasday)
					return 11;
				return 6;
			}
			return 5;
		}
		elseif($hasmonth)
		{
			if($hasweek)
			{
				if($hasday)
				{
/*
8 IF
1
W
(August,September)
OR 10 IF
D3
2
W
M4
*/
					return (count($parts) == 3) ? 8:10;
				}
			}
/*
2 IF
M2 hasmonth
OR 6 IF
2015-6 hasmonth
*/
			return (strpos($parts[0],'-') === FALSE) ? 2:6;
		}
		elseif($hasweek)
		{
			return 3;
		}
		elseif($hasday)
		{
			return 4;
		}
		return 0;
	}

	//Compare arrays of parsed period-segments
	private function _cmp_periods($a,$b)
	{
		if($a['F'] !== $b['F'])
		{
			if($a['F'] === FALSE)
				return -1;//unspecified == more-general, so first
			if($b['F'] === FALSE)
				return 1;
			return ($a['F'] - $b['F']);
		}

		if($a['P'] !== $b['P'])
		{
			if($a['P'] === FALSE)
				return -1;
			if($b['P'] === FALSE)
				return 1;
			if(is_array($a['P']))
				$sa = $a['P'][0];
			else
				$sa = $a['P'];
			if(is_array($b['P']))
				$sb = $a['P'][0];
			else
				$sb = $b['P'];
			if(is_numeric($sa) && is_numeric($sb))
			{
				if($sa >= 0 && $sb >= 0)
					return ($sa-$sb);
				if($sa <= 0 && $sb <= 0)
					return ($sb-$sa);
				return ($sa < 0) ? 1:-1;
			}
			$ssa = substr($sa,1); //D* or M* ?
			$ssb = substr($sb,1);
			if(is_numeric($ssa) && is_numeric($ssb))
				return ($ssa-$ssb);
			return ($sa-$sb); //strcmp() BAD for months 10,11,12!
		}

		if($a['T'] !== $b['T'])
		{
			if($a['T'] === FALSE)
				return -1;
			if($b['T'] === FALSE)
				return 1;
			if($sa-$sb != 0) //lazy string-compare
				return ($sa-$sb);
		}

		if(is_array($a))
			return count($a['P']) - count($b['P']); //shorter == less-specific so first
		return 0;
	}

	/*
	_CreateConditions:

	Process condition(s) from @descriptor into $this->conds, and if @report is TRUE,
	construct a 'sanitised' variant of @descriptor.
	Depending on @report, returns TRUE or the cleaned variant, or in either case
	FALSE upon error.
	@descriptor is split on outside-bracket commas. In resultant PERIOD and/or TIME
	descriptors:
	 un-necessary brackets are excised
	 whitespace & newlines excised
	 all day-names (translated) tokenised to D1..D7
	 all month-names (translated) to M1..M12
	'sunrise' (translated) to R
	'sunset' (translated) to S
	'week' (translated) to W
	@descriptor: availability-condition string
	@locale: UNUSED locale identifier string, for correct capitalising of interval-names
	  possibly present in @descriptor
	@report: optional, whether to construct a cleaned variant of @descriptor after parsing,
		default FALSE
	*/
//	private
	function _CreateConditions($descriptor,/*$locale,*/$report=FALSE)
	{
		$this->conds = FALSE;

		$gets = range(1,7);
/*		$oldloc = FALSE;
		if($locale)
		{
			$oldloc = setlocale(LC_TIME,"0");
			if(!setlocale(LC_TIME,$locale))
				$oldloc = FALSE;
		}
*/
		//NB some of these may be wrong, due to race on threaded web-server
		$longdays = self::AdminDayNames($gets);
		$shortdays = self::AdminDayNames($gets,FALSE);
		$gets = range(1,12);
		$longmonths = self::AdminMonthNames($gets);
		$shortmonths = self::AdminMonthNames($gets,FALSE);
/*		if($oldloc)
			setlocale(LC_TIME,$oldloc);
*/
		unset($gets);

		$daycodes = array();
		for($i = 1; $i < 8; $i++)
			$daycodes[] = 'D'.$i;
		$monthcodes = array();
		for($i = 1; $i < 13; $i++)
			$monthcodes[] = 'M'.$i;
		//TODO caseless match for these, based on locale from bkrshared::GetLocale()
		$not = $this->mod->Lang('not');
		$excpt = $this->mod->Lang('except');
		$rise = $this->mod->Lang('sunrise');
		$set = $this->mod->Lang('sunset');
		$week = $this->mod->Lang('week'); //OR bkrshared::RangeNames($this->mod,1);
		//NB long-forms before short-
		$finds = array_merge($longdays,$shortdays,$longmonths,$shortmonths,
			array($not,$excpt,$rise,$set,$week,' ',"\n"));
		$repls = array_merge($daycodes,$daycodes,$monthcodes,$monthcodes,
			array('!','!','R','S','W','',''));
		$clean = str_replace($finds,$repls,$descriptor);

		if(preg_match('/[^\dDMRSW@:+-.,()]/',$clean))
			return FALSE;
		$l = strlen($clean);
		$parts = array();
		$d = 0;
		$s = 0;
		$b = -1;
		$xclean = FALSE;

		for($p=0; $p<$l; $p++)
		{
			switch ($clean[$p])
			{
			 case '(':
				if(++$d == 1)
					$b = $p;
				break;
			 case ')':
				if(--$d < 0)
					return FALSE;
				//strip inappropriate brackets
				if($d == 0)
				{
					if($p < $l-1) //before end, want pre- or post-qualifier
					{
						//check post-
						$n = $clean[$p+1];
						if($n == '@') // ')' N/A for d = 0 ?
						{
							$b = -1;
							break;
						}
					}
					//at end, or no post-qualifier, want pre-qualifier
					if($b > $s)
					{
						$n = $clean[$b-1];
						if($n == ')' || ($n >='0' && $n <= '9')) // '(' N/A for d = 0 ?
						{
							$b = -1;
							break;
						}
					}
					if($b >= $s)
					{
						$clean[$p] = ' ';
						$clean[$b] = ' ';
						$b = -1;
						$xclean = TRUE;
					}
					else
						return FALSE;
				}
				break;
			 case ',':
				if($d == 0)
				{
					if($p > $s && $clean[$p-1] != ',')
					{
						$tmp = substr($clean,$s,$p-$s);
						if ($xclean)
						{
							$parts[] = str_replace(' ','',$tmp);
							$xclean = FALSE;
						}
						else
							$parts[] = $tmp;
					}
					$s = $p+1;
				}
			 default:
				break;
			}
		}
		if($p > $s)
		{
			$tmp = substr($clean,$s,$p-$s); //last (or entire) part
			if ($xclean)
				$parts[] = str_replace(' ','',$tmp);
			else
				$parts[] = $tmp;
		}
		$repeat = FALSE;

		foreach($parts as &$one)
		{
			$parsed = array();
			$p = strpos($one,'@'); //PERIOD-TIME separator present?
			if($p !== FALSE)
			{
				$e = (strlen($one) == ($p+1)); //trailing '@'
				if($p == 0 && !$e)
				{
					$parsed['P'] = FALSE;
					$parsed['F'] = 1; //enum for only-time-specified
					$parsed['T'] = self::_TimeClean($one,$report);
				}
				else //$p > 0 || $e
					if($p > 0 && $e)
				{
					$parsed['P'] = self::_PeriodClean($one,$report);
					$parsed['F'] = self::_GetFocus($parsed['P']);
					$parsed['T'] = FALSE;
				}
				elseif($p > 0)
				{
					$parsed['P'] = self::_PeriodClean(substr($one,0,$p),$report);
					$parsed['F'] = self::_GetFocus($parsed['P']);
					$parsed['T'] = self::_TimeClean(substr($one,$p+1),$report);
				}
			}
			else //PERIOD OR TIME
			{
				if(preg_match('~[DMW]~',$one))
					$condtype = 2; //=period
				//check sunrise/set before numbers, in case have sun*-H:M
				elseif(preg_match('~[SR]~',$one))
					$condtype = 1; //=time
				else
				{
					$condtype = 0; //=undecided
					//catch many dates and numbers (<0(incl. date-separator), >24)
					$n = preg_match_all('~[-:]?\d+~',$one,$matches);
					if($n)
					{
						foreach($matches[0] as $n)
						{
							if($n[0] == ':' || $n == 0) //have minutes, day-of-month never 0
							{
								$condtype = 1;
								break;
							}
							elseif($n > 24 || $n < 0)
							{
								$condtype = 2;
								break;
							}
						}
					}
				}
				//end of analysis, for now
				if ($condtype == 1) //time
				{
					$parsed['F'] = 1;
					$parsed['P'] = FALSE;
					$parsed['T'] = self::_TimeClean($one,$report);
				}
				elseif($condtype == 2) //period
				{
					$parsed['P'] = self::_PeriodClean($one,$report);
					$parsed['F'] = self::_GetFocus($parsed['P']);
					$parsed['T'] = FALSE;
				}
				else //could be either - re-consider, after all are known
				{
					$repeat = TRUE;
					//park as a time-value, unset parsed[0] signals reconsideration needed
					$parsed['P'] = FALSE;
					$parsed['T'] = $one;
				}
			}
			$this->conds[] = $parsed;
		}
		unset($one);

		if($repeat)
		{
			//small number(s) logged, may be for hour(s) or day(s)
			$useday = FALSE;
			//if any PERIOD recorded, assume all 'bare' numbers are days-of-month
			foreach($this->conds as &$one)
			{
				if($one['P'])
				{
					$useday = TRUE;
					break;
				}
			}
			unset($one);
			//if still not sure, interpret values in $parts[]
			if(!$useday)
			{
				//calc min. non-zero difference between small numeric values
				$one = implode(' ',$parts); //'higher-quality' than $clean
				$n = preg_match_all('~(?<![-:(\d])[0-2]?\d(?![\d()])~',$one,$matches);
				if($n > 1)
				{
					$mindiff = 25.0; //> 24 hours
					$n--; //for 0-base compares
					sort($matches[0],SORT_NUMERIC);
					foreach($matches[0] as $k=>$one)
					{
						if($k < $n)
						{
							$diff = (float)($matches[0][$k+1] - $one);
							if ($diff > -0.001 && $diff < 0.001)
							{
								$useday = TRUE; //numbers repeated only in PERIOD-descriptors
								break;
							}
							elseif($diff < $mindiff)
								$mindiff = $diff;
						}
					}
					if (!$useday && $mindiff < $slothours)
						$useday = TRUE;
				}
				elseif($n)
				{
					$n = $matches[0][0];
					if($slothours >= 1.0)
						$useday = ($n < $slothours);
					else
						$useday = ($n < 7 || $n > 19); //arbitrary choice for a single number
				}
				else
					$useday = TRUE; //should never get here
			}
			//now cleanup the logged values
			foreach($this->conds as &$one)
			{
				if (!isset($one['F']))
				{
					if($useday)
					{
						//treat this as a day-value
						$one['P'] = $one['T'];
						$one['F'] = 4;
						$one['T'] = FALSE;
					}
					else //time-only
					{
						$one['F'] = 1;
					}
				}
			}
			unset($one);
		}

		if(count($this->conds) > 1)
			usort($this->conds,array($this,'_cmp_periods'));

		if($report)
		{
			//re-merge parsed array
			$p = 0;
			$s = '';
			foreach($this->conds as &$one)
			{
				if($p === 0)
					$p = 1;
				else
					$s .= ',';
				if($one['P'])
				{
					$s .= $one['P'];
					if($one['T'])
						$s .= '@'.$one['T'];
				}
				elseif($one['T'])
					$s .= $one['T'];
			}
			unset($one);
			$finds = array_merge($daycodes,$monthcodes,array('R','S','W'));
			$repls = array_merge($shortdays,$shortmonths,array($rise,$set,$week));
			return str_replace($finds,$repls,$s);
		}
		else
			return TRUE;
	}

	//========== PUBLIC FUNCS ===========

	/**
	AdminMonthNames:

	Get one, or array of, localised month-name(s).
	This is for admin use, the frontend locale may not be the same and/or supported
	by the server.

	@which: 1 (for January) .. 12 (for December), or array of such indices
	@full: optional, whether to get long-form name, default TRUE
	*/
	function AdminMonthNames($which,$full=TRUE)
	{
		$ret = array();
		//OK to use non-localised times in this context
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

	Get one, or array of, localised day-name(s).
	This is for admin use, the frontend locale may not be the same and/or supported
	by the server.

	@which: 1 (for Sunday) .. 7 (for Saturday), or array of such indices
	@full: optional whether to get long-form name default TRUE
	*/
	function AdminDayNames($which,$full=TRUE)
	{
		$ret = array();
		//OK to use non-localised times in this context
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
	ParseCondition:

	Parse @descriptor and store result in $this->conds.
	Returns TRUE upon success, or if no constraint applies, otherwise FALSE.
	This (or CheckCondition()) must be called before any poll for a suitable period,
	or check for a matching period.

	@descriptor: interval-descriptor string
	@locale: UNUSED optional, locale identifier string for correct capitalising of day/month names
	  possibly present in @descriptor, default ''
	*/
	function ParseCondition($descriptor/*,$locale=''*/)
	{
		if($descriptor)
			return self::_CreateConditions($descriptor/*,$locale*/);
		$this->conds = FALSE;
		return TRUE;
	}

	/**
	CheckCondition:

	Determine whether interval @descriptor has correct syntax.
	Returns '' if @descriptor is FALSE (no constraint applies), or a cleaned-up
	variant of @descriptor, or FALSE if @descriptor is bad.
	Also stores parsed form of @descriptor in array $this->conds. This (or ParseCondition())
	must be called before any poll for a suitable period, or check for a matching
	period.

	@descriptor: availability-condition string
	@locale: UNUSED optional, locale identifier string for correct capitalising of day/month names
	  possibly present in @descriptor, default ''
	*/
	function CheckCondition($descriptor/*,$locale=''*/)
	{
		if($descriptor)
			return self::_CreateConditions($descriptor,/*$locale,*/TRUE);
		$this->conds = FALSE;
		return '';
	}

}

?>
