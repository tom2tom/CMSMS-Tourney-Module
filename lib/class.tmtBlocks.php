<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
class tmtBlocks
{
	/**
	DiffBlocks:
	@starts1: array of first-block start-stamps, sorted ascending
	@ends1: array of corresponding end-stamps
	@starts2: array of other-block start-stamps, sorted ascending
	@ends2: array of corresponding end-stamps
	Returns: 2-member array,
	 [0] = array of start-stamps for subblocks in first-block and not in other-block
	 [1] = array of corresponding end-stamps
	 The arrays have corresponding but not necessarily contiguous numeric keys, or may be empty.
	 1-second blocks are omitted, so care needed for off-by-1 in supplied arrrays.
	*/
	public function DiffBlocks($starts1, $ends1, $starts2, $ends2)
	{
		$i = 0;
		$ic = count($starts1);
		$j = 0;
		$jc = count($starts2);
		while ($i < $ic && $j < $jc) {
			$a = $starts1[$i];
			$b = $ends1[$i];
			$c = $starts2[$j];
			$d = $ends2[$j];
			if ($d > $a && $c < $b) { //there's overlap
				if ($a >= $c && $b <= $d) {
					unset($starts1[$i]);
					unset($ends1[$i]);
					$i++;
					continue;
				} elseif ($a < $c && $d < $b) {
					$t = array_search($i,array_keys($starts1)); //current array-offset
					array_splice($ends1,$t,0,$c-1); //insert before $ends1[$i]
					$t++;
					array_splice($starts1,$t,0,$d+1); //insert after $starts1[$i]
					$i = $t; //arrays have been re-keyed
					$ic++;
					continue;
				} elseif ($d < $b) {
					$starts1[$i] = $d+1;
				} elseif ($c > $a) {
					$ends1[$i] = $c-1;
				}
			}
			$t = $j;
			if ($ends2[$j] <= $ends1[$i]) {
				$j++;
			}
			if ($ends1[$i] <= $ends2[$t]) {
				$i++;
			}
		}
		foreach ($starts1 as $i=>$t) {
			if ($ends1[$i] < $t) {
				$starts1[$i] = $ends1[$i];
				$ends1[$i] = $t;
			}
		}
		return array($starts1,$ends1);
	}

	/**
	IntersectBlocks:
	@starts1: array of first-block start-stamps, sorted ascending
	@ends1: array of corresponding end-stamps
	@starts2: array of other-block start-stamps, sorted ascending
	@ends2: array of corresponding end-stamps
	Returns: 2-member array,
	 [0] = array of start-stamps for subblocks in both first-block and other-block
	 [1] = array of corresponding end-stamps
	 The arrays have corresponding numeric keys
	OR if nothing applies
	 [0] = FALSE
	 [1] = FALSE
	*/
	public function IntersectBlocks($starts1, $ends1, $starts2, $ends2)
	{
		$starts = array();
		$ends = array();
		$i = 0;
		$ic = count($starts1);
		$j = 0;
		$jc = count($starts2);
		while ($i < $ic || $j < $jc) {
			$st1 = ($i < $ic) ? $starts1[$i] : ~PHP_INT_MAX; //aka PHP_INT_MIN
			$nd1 = ($i < $ic) ? $ends1[$i] : PHP_INT_MAX;
			$st2 = ($j < $jc) ? $starts2[$j] : ~PHP_INT_MAX;
			$nd2 = ($j < $jc) ? $ends2[$j] : PHP_INT_MAX;
			if (($st1 >= $st2 && $st1 < $nd2) || ($st2 >= $st1 && $st2 < $nd1)) { //$st1..$nd1 overlaps with $st2..$nd2
				$bst = max($st1,$st2);
				$bnd = min($nd1,$nd2);
				$starts[] = $bst;
				$ends[] = $bnd;
				if ($bnd == $ends1[$i]) { //1-block is ended
					if (++$i == $ic) {
						$j++;
						break;
					}
				}
				if ($bnd == $ends2[$j]) { //2-block is ended
					if (++$j == $jc) {
						$i++;
						break;
					}
				}
			} elseif ($ends1[$i] <= $starts2[$j]) { //2-block starts at or after 1-block end
				if (++$i == $ic) {
					break;
				}
			} elseif ($starts1[$i] >= $ends2[$j]) { //1-block starts at or after 2-block end
				if (++$j == $jc) {
					break;
				}
			}
		}

		$ic = count($starts);
		if ($ic > 0) {
			if ($ic > 1) {
				//merge adjacent blocks
				$ic--;
				for ($i=0; $i<$ic; $i++) {
					$j = $i+1;
					if ($ends[$i] >= $starts[$j]-1) {
						$starts[$j] = $starts[$i];
						unset($starts[$i]);
						unset($ends[$i]);
					}
				}
				$starts = array_values($starts);
				$ends = array_values($ends);
			}
			return array($starts,$ends);
		}
		return array(FALSE,FALSE);
	}

	/**
	MergeBlocks:
	Coalesce and sort-ascending the timestamp-blocks in @starts and @ends.
	The arrays must be equal-sized, have numeric keys. Resultant array keys may
	be non-contiguous.
	@starts: reference to array of block-start stamps, any order
	@ends: reference to array of corresponding block-end stamps, no FALSE value(s)
	*/
	public function MergeBlocks(&$starts, &$ends)
	{
		$ic = count($starts);
		if ($ic > 1) {
			array_multisort($starts,SORT_ASC,SORT_NUMERIC,$ends);
			$i = 0;
			while ($i < $ic) {
				$e1 = $ends[$i];
				for ($j=$i+1; $j<$ic; $j++) {
					if (isset($starts[$j])) {
						if ($starts[$j] > $e1) {
							break;
						}
						$e2 = $ends[$j];
						if ($e2 > $e1) {
							$ends[$i] = $e2;
						}
						unset($starts[$j]);
						unset($ends[$j]);
					}
				}
				$i = $j;
			}
		}
	}
}
