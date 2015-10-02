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
Calendar Availability Language

Availability constraints are specified in a string of the form
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
 (X,Y[,...]) which specifies a sequence of individual values, in any order, all
 of which are valid; or
 X..Y which specifies an inclusive range of sequential values, all of which are
 valid, and normally Y should be chronologically after X (except when spanning a
 time-period-end e.g. 23:30..2:30 or Friday..Sunday or December..February).

Ordinary numeric sequences and ranges are supported, and for such ranges, Y must
be greater than X, and X and/or Y may be negative.

Any value, bracketed-sequence or range may itelf be bracketed and preceded by a
qualifier, being a single value, or scope-expander of any of the above types.
Such qualification may be nested/recursed.

A value which is a date is expected to be formatted as yyyy[-[m]m[-[d]d]] i.e.
month and day are optional, and leading 0 is not needed for month or day value.

A value which is a month- or day-name is expected to be in accord with the default,
or a specified, locale. Month- and/or day-names may be abbreviated. The term
'week' isn't system-pollable, and must be translated manually. Weeks always start
on Sunday (and so 'week' can be considered equivalent to 'Sunday..Saturday').

A value which is a time is expected to be formatted as [h]h[:[m]m] i.e. 24-hour
format, leading '0' optional, minutes optional but if present, the separator is
':'. In some contexts, [h]h:00 may be needed instead of just [h]h, to reliably
distinguish between hours-of-day and days-of-month. Sunrise/sunset times are
supported, if relevant latitude and longitude data are provided. Times like
sunrise/sunset +/- interval are also supported.  A time-value which is not a
range is assumed to represent a range of 1-hour less 1-second.

Negative-value parameters count backwards from next-longest-interval-end.

If multiple conditions are specified, and any of them overlap, then less-explicit/
more-liberal conditions prevail over more-explicit/less-liberal ones. This means
that, for any particular date/time, it will be sufficient for any combination of
conditions to be satisfied.

If no condition is specified, no constraint applies.

Examples:
Date descriptors
 2000 or (2020,2024) or 2000..2005 or 2000-6 or 2000-10..2001-3 or 2000-9-1
   or 2000-10-1..2000-12-31
 for month(s)-of-any-year: January or (March,September) or July..December
 for month(s)-of-specfic-year: January(2000) (otherwise as 2000-1)
   or (March,September)(2000) or July..December(2000)
Week descriptors
 for week(s)-of-any-month (some of which may not be 7-days): 2(week) or -1(week)
   or 2..3(week)
 for week(s)-of-named-month: 2(week(March)) or or 1..3(week(July,August))
   or (-2,-1)(week(April..July))
Day descriptors
 for day(s)-of-any-month: 1 or -2 or (15,18,-1) or 1..10 or 2..-1 or -3..-1 or
    1(Sunday) or -1(Wednesday..Friday) or 1..3(Friday,Saturday)
 for day(s)-of-specific-week(s): Sunday(2(week)) or
   (Saturday,Wednesday)(-3..-1(week(July))) or
   Monday..Friday((-2,-1)(week(April..July)))
 for day(s)-of-any-week: Monday or (Monday,Wednesday,Friday) or Wednesday..Friday
Time descriptors
 9 or 2:30 or (9,12,15:15) or 12..23 or 6..15:30 or sunrise..16 or 9..sunset-3:30
*/

class IntervalParser
{
	protected $mod; //reference to current module-object
	/*
	Each member of $conds is an array (
	0 => 'focus'-level (as below) to assist interpeting PERIOD value
		1 no period (time-only)
		2 unqualified year
		3 unqualified month
		4 unqualified week
		5 unqualified day
		6 qualified month
		7 qualified week
		8 qualified month
		9 qualified month
		10 specfic day
	1 => FALSE or PERIOD = structure of arrays and strings representing period-values and/or
		period-value-ranges
	2 => FALSE or TIME = array of strings representing time-values and/or time-value-ranges,
		with sun-related ones first, all ordered by increasing value/range-start
	);
	$conds will be sorted, first on [0] ascending, then on [1] TODO, then on
	[2][?][0] ascending. Negative values in [1] are sorted after positives, but not
	interpreted to corresponding actual values.
	Times in [2] are not interpreted. Other than sun-related values, they can be
	converted to midnight-relative seconds, and any overlaps 'coalesced'. Sun-related
	values must of course be interpreted for each specific day evaluated.
	*/
	protected $conds;
//private $ob; //'(' for ltr, ')' for rtl
//private $cb; //')' for ltr, '(' for rtl
	function __construct(&$mod)
	{
		$this->mod = $mod;
		$this->conds = FALSE;
	}

	//$offset points to opening bracket in $str
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

	//$offset points to initial '.' in $str
	private function _StartRange($str,$offset)
	{
		$d = 0;
		$p = $offset;
		While ($p >= 0)
		{
			$c = $str[$p--];
			switch($c)
			{
			 case '.':
				if(++$d > 2)	//should never happen
					return -1;
				break;
			 case ')':
			 case '(':
			 case ',':
				return $p+2;
			}
		}
		return 0;
	}

	//$offset points to initial '.' in $str
	private function _EndRange($str,$offset)
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
				return $p-2;
			}
		}
		return $l-1; //offset of end
	}

	/**
	_ParseRange:

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
//	private
	function _ParseRange($str,$getstr=TRUE)
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
					if($hiparts[5] < $loparts[5])
						$swap = TRUE;
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

	//Compare numbers such that -ve's last
	private static function _cmp_numbers($a,$b)
	{
		if(($a >= 0 && $b < 0) || ($a < 0 && $b >= 0)) 
			return ($b-$a);
		return ($a-$b);
	}

	//Compare strings like D* or M*
	private static function _cmp_named($a,$b)
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
	private static function _cmp_dates($a,$b)
	{
		//bare years don't work correctly
		$s = (strpos($a,'-')!=FALSE) ? $a:$a.'-1-1';
		$stA = strtotime($s);
		$s = (strpos($b,'-')!=FALSE) ? $b:$b.'-1-1';
		$stB = strtotime($s);
		return ($stA-$stB);
	}
	
	/**
	_ParseSequence:

	This is for period-identifiers, no times are handled.
	Expects all values are the same type as the 1st in @str, otherwise error.
	Depending on @getstr, this may return a N-member, ascending-sorted, no-duplicates,
	contiguous-keyed array(L,...,H), or a bracket-enclosed, comma-separated string
	represenation of that array. In either case, this may also return a single value
	L(==all others) or FALSE upon error.

	@str: string to be parsed, containing 0 or more ','s and no surrounding brackets
	@getstr: optional, whether to return re-constituted string, default TRUE
	*/
	private function _ParseSequence($str,$getstr=TRUE)
	{
		$parts = explode(',',$str);
		if(count($parts) == 1)
		{
			if(strpos($str,'..') === FALSE)
				return $str;
			return self::_ParseRange($str,$getstr);
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
					$r = self::_ParseRange($val,FALSE);
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
					$r = self::_ParseRange($val,FALSE);
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
					$r = self::_ParseRange($val,FALSE);
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
			usort($parts,array('Calendar',$cmp)); //keys now contiguous
			if($getstr)
				return '('.implode(',',$parts).')';
			else
				return $parts;
		}
		else
			return key($parts);
	}

	private static function _cmp_periods($a,$b)
	{
		$sa = $a['focus'];
		$sb = $b['focus'];
//		if($sa != $sb)
			return ($sa-$sb);
		//TODO
//		return 0;
	}

	/**
	@str is PERIOD component of a condition
	Depending on @report, returns sanitised variant of @str or array, or in either
	case FALSE upon error;
	*/
//	private 
	function _PeriodClean($str,$report)
	{
		$parts = array();
		$one = '';
		$s = 0; $e = 0; $d = 0; $l = strlen($str);
		for($p = 0; $p < $l; $p++)
		{
			$c = $str[$p];
			switch ($c)
			{
			 case '(':
			 	if(++$d == 1) //CHECKME no nesting-support?
				{
					$e = self::_MatchBracket($str,$p); //matching brace (in case nested)
					if($e != -1)
					{
						if(0)
						{
							//TODO handle 'of' brackets
							//TODO $one['of'] = 
						}
						else
						{
							//TODO $one['of'] = 
							$s = $p+1;
							$t = self::_ParseSequence(substr($str,$s,$e-$s),FALSE); //no nesting supported
							if(is_array($t))
								$parts = array_merge($parts,$t);
							elseif($t !== FALSE)
								$parts[] = $t;
							else
								return FALSE;
							$d = 0;
							$one = '';
						}
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
				$s = self::_StartRange($str,$p);
				$e = self::_EndRange($str,$p);
				if($s != -1 && $e != -1)
				{
					//can't safely create range sub-array before $parts[] sort and de-dup 
					$t = self::_ParseRange(substr($str,$s,$e-$s+1),TRUE);
					if($t !== FALSE)
					{
						$parts[] = $t;
						$one = '';
						$p = $e;
						break;
					}
				}
				return FALSE;
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
		{
//			$one['of'] = ; TODO
			$parts[] = $one; //last one
		}
		elseif($parts == FALSE)
			return '';
		//TODO remove dup's
		if(count($parts) > 1)
		{
//		usort($parts,array('Calendar','_cmp_periods')); //keys now contiguous
			if($report)
			{
				return $str; //TODO reconstruct from $parts
			}
			else
			{
				//CHECKME convert ranges to arrays (L,.,H)
				return $parts;
			}
		}
		else
			return $parts;
	}

	//Compare time-strings like [sun*[+-]][h]h[:[m]m]] without expensive
	//time-conversions and with all sun* before all others
	private static function _cmp_times($a,$b)
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
						goto plaintimes;
					}
					elseif($na == '-')
					{
						$t = substr($b,$rb+2); //swap
						$b = substr($a,$ra+2);
						$a = $t;
						goto plaintimes;
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
plaintimes:
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

	/**
	@str is TIME component of a condition
	Depending on @report, returns sanitised variant of @str or array of timevalues,
	with sun-related values first, rest sorted ascending by value or start of range
	where relevant, or in either case FALSE upon error
	*/
	private function _TimeClean($str,$report)
	{
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
				$s = self::_StartRange($str,$p);
				$e = self::_EndRange($str,$p);
				if($s != -1 && $e != -1)
				{
					//cannot safely create range-array before $parts[] sort and de-dup 
					$t = self::_ParseRange(substr($str,$s,$e-$s+1),TRUE);
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
			usort($parts,array('Calendar','_cmp_times')); //keys now contiguous
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

	/**
	_CreateConditions:

	Process condition(s) from @avail into $this->conds, and if @report is TRUE,
	construct a 'sanitised' variant of @avail.
	Depending on @report, returns TRUE or the cleaned variant, or in either case
	FALSE upon error.
	Un-necessary brackets are excised. @avail split on outside-bracket commas.
	All day-names aliased to D1..D7, all month-names aliased to M1..M12,
	'sunrise' to R, 'sunset' to S, 'week' to W, whitespace & newlines gone.

	@available: availability-condition string
	@locale: locale identifier string, for localising day/month names
	  possibly present in @available
	@report: optional, whether to construct a cleaned variant of @avail after parsing,
		default FALSE
	*/
//	private 
	function _CreateConditions($avail,$locale,$report=FALSE)
	{
		$this->conds = FALSE;
	
		$gets = range(1,7);
		$oldloc = FALSE;
		if($locale)
		{
			$oldloc = setlocale(LC_TIME,"0");
			if(!setlocale(LC_TIME,$locale))
				$oldloc = FALSE;
		}
		//NB some of these may be wrong, due to race on threaded web-server
		$longdays = self::AdminDayNames($gets);
		$shortdays = self::AdminDayNames($gets,FALSE);
		$gets = range(1,12);
		$longmonths = self::AdminMonthNames($gets);
		$shortmonths = self::AdminMonthNames($gets,FALSE);
		if($oldloc)
			setlocale(LC_TIME,$oldloc);
		unset($gets);

		$daycodes = array();
		for($i = 1; $i < 8; $i++)
			$daycodes[] = 'D'.$i;
		$monthcodes = array();
		for($i = 1; $i < 13; $i++)
			$monthcodes[] = 'M'.$i;
		//NB long before short
		$rise = $this->mod->Lang('sunrise');
		$set = $this->mod->Lang('sunset');
		$week = $this->mod->Lang('week');
		$finds = array_merge($longdays,$shortdays,$longmonths,$shortmonths,
			array($rise,$set,$week,' ',"\n"));
		$repls = array_merge($daycodes,$daycodes,$monthcodes,$monthcodes,
			array('R','S','W','',''));
		$clean = str_replace($finds,$repls,$avail);
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
					$parsed[0] = 1; //enum for only-time-specified
					$parsed[1] = FALSE;
					$parsed[2] = self::_TimeClean($one,$report);
				}
				else //$p > 0 || $e
					if($p > 0 && $e)
				{
					$parsed[1] = self::_PeriodClean($one,$report);
					if(!$report)
						$parsed[0] = $parsed[1]['focus'];
					$parsed[2] = FALSE;
				}
				elseif($p > 0)
				{
					$parsed[1] = self::_PeriodClean(substr($one,0,$p),$report);
					if(!$report)
						$parsed[0] = $parsed[1]['focus'];
					$parsed[2] = self::_TimeClean(substr($one,$p+1),$report);
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
					$parsed[0] = 1;
					$parsed[1] = FALSE;
					$parsed[2] = self::_TimeClean($one,$report);
				}
				elseif($condtype == 2) //period
				{
					$parsed[1] = self::_PeriodClean($one,$report);
					if(!$report)
						$parsed[0] = $parsed[1]['focus'];
					$parsed[2] = FALSE;
				}
				else //could be either - re-consider, after all are known
				{
					$repeat = TRUE;
					//park as a time-value, unset parsed[0] signals reconsideration needed
					$parsed[1] = FALSE;
					$parsed[2] = $one;
				}
			}
			$this->conds[] = $parsed;
		}
		unset($one);
//
		if($repeat)
		{
			//small number(s) logged, may be for hour(s) or day(s)
			$useday = FALSE;
			//if any PERIOD recorded, assume all 'bare' numbers are days-of-month
			foreach($this->conds as &$one)
			{
				if($one[1])
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
				if (!isset($one[0]))
				{
					if($useday)
					{
						//treat this as a day-value
						$one[0] = 5;
						$one[1] = $one[2];
						$one[2] = FALSE;
					}
					else //time-only
					{
						$one[0] = 1;
					}
				}
			}
			unset($one);
		}

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
				if($one[1])
				{
					$s .= $one[1];
					if($one[2])
						$s .= '@'.$one[2];
				}
				elseif($one[2])
					$s .= $one[2];
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

	Parse calendar-constraint @available and store result in $this->conds.
	Returns TRUE upon success, or if no constraint applies, otherwise FALSE.
	This (or CheckCondition()) must be called before any poll for a suitable period,
	or check for a matching period.

	@available: availability-condition string
	@locale: optional, locale identifier string for localising day/month names
	  possibly present in @available, default ''
	*/
	function ParseCondition($available,$locale='')
	{
		if(!$available)
		{
			$this->conds = FALSE;
			return TRUE;
		}
//	return self::_CreateConditions($available,$locale);
		return TRUE;
	}

	/**
	CheckCondition:

	Determine whether calendar-constraint @available has correct syntax.
	Returns '' if @available is FALSE (no constraint applies), or a cleaned-up
	variant of @available, or FALSE if @available is bad.
	Also stores the parsed @available in $this->conds. This (or ParseCondition())
	must be called before any poll for a suitable period, or check for a matching
	period.

	@available: availability-condition string
	@locale: optional, locale identifier string for localising day/month names
	  possibly present in @available, default ''
	*/
	function CheckCondition($available,$locale='')
	{
		if(!$available)
		{
			$this->conds = FALSE;
			return '';
		}
//		return self::_CreateConditions($available,$locale,TRUE);
		return trim($available);
	}

}

?>
