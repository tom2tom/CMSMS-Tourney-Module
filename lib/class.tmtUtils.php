<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2017 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

class tmtUtils
{
	/**
	MergeJS:
	@jsincs:
	@jsfuncs:
	@jsloads:
	Returns: js string
	*/
	public static function MergeJS($jsincs, $jsfuncs, $jsloads)
	{
		if (is_array($jsincs)) {
			$all = $jsincs;
		} elseif ($jsincs) {
			$all = [$jsincs];
		} else {
			$all = [];
		}
		if ($jsfuncs || $jsloads) {
			$all[] =<<<'EOS'
<script type="text/javascript">
//<![CDATA[
EOS;
			if (is_array($jsfuncs)) {
				$all = array_merge($all, $jsfuncs);
			} elseif ($jsfuncs) {
				$all[] = $jsfuncs;
			}
			if ($jsloads) {
				$all[] =<<<'EOS'
$(document).ready(function() {
EOS;
				if (is_array($jsloads)) {
					$all = array_merge($all, $jsloads);
				} else {
					$all[] = $jsloads;
				}
				$all[] =<<<'EOS'
});
EOS;
			}
			$all[] =<<<'EOS'
//]]>
</script>
EOS;
		}
		return implode(PHP_EOL, $all);
	}
}
