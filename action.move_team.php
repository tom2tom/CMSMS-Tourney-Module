<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
/* action for ajax-processing, after teams-table row(s) have been dragged and dropped
  $params[] includes
  'bracket_id'
  'neworders' string with comma-separated displayorder values of all teams e.g. '2,3,6,1'
*/
if (!$this->CheckAccess('admod'))
{
	echo 0;
	exit;
}
$news = explode(',',$params['neworders']);
$nc = count($news);
if ($nc < 2)
	exit;	//a single row, nothing to do
$pref = cms_db_prefix();
$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2 ORDER BY displayorder ASC';
$teams = $db->GetCol($sql, array($params['bracket_id']));
if ($teams === FALSE || count($teams) != $nc)
{
	echo 0;
	exit;
}
$sql = 'UPDATE '.$pref.'module_tmt_teams SET displayorder = CASE team_id ';
foreach ($teams as $i=>$tid)
    $sql .= 'WHEN '.(int)$tid.' THEN ? ';
$sql .= 'ELSE displayorder END WHERE team_id IN ('.implode(',',$teams).')';
if ($db->Execute ($sql,$news))
	echo $nc; //send back the count of updates
else
	echo 0;
exit;

?>
