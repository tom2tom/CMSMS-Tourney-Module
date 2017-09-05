<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

action for ajax-processing, after cancel-button click when editing a comp
new-bracket check is handled otherwise (see $data->added in tmtEditSetup::Setup)
*/
$ret = 0; //default clean
$bracket_id = (int)$params['bracket_id'];
$pref = cms_db_prefix();
$sql = 'SELECT match_id FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags!=0';
$rst = $db->Execute($sql, array($bracket_id));
if ($rst) {
	if (!$rst->EOF) {
		$ret = 1; //dirty
	}
	$rst->Close();
}
if ($ret == 0) {
	$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=0';
	$rst = $db->Execute($sql, array($bracket_id));
	if ($rst) {
		if (!$rst->EOF) {
			$ret = 1;
		}
		$rst->Close();
	}
}
if ($ret == 0) {
	$sql = 'SELECT id FROM '.$pref.'module_tmt_people WHERE id IN (SELECT team_id FROM '.
	$pref.'module_tmt_teams WHERE bracket_id=?) AND flags!=0';
	$rst = $db->Execute($sql, array($bracket_id));
	if ($rst) {
		if (!$rst->EOF) {
			$ret = 1;
		}
		$rst->Close();
	}
}

echo $ret;
?>
