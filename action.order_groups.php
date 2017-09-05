<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
/* action for ajax-processing, after groups-table row(s) have been dragged and dropped
  $params[] includes
  'neworders' string with comma-separated displayorder values of all groups e.g. '2,3,6,1'
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
$sql = 'SELECT group_id FROM '.$pref.'module_tmt_groups ORDER BY displayorder';
$grps = $db->GetCol($sql);
if ($grps === FALSE || count($grps) != $nc)
{
	echo 0;
	exit;
}
$sql = 'UPDATE '.$pref.'module_tmt_groups SET displayorder = CASE group_id ';
foreach ($grps as $gid)
    $sql .= 'WHEN '.(int)$gid.' THEN ? ';
$sql .= 'ELSE displayorder END WHERE group_id IN ('.implode(',',$grps).')';
$db->Execute ($sql,$news);
if (1) //$db->Affected_Rows() not reliable after UPDATE
	echo $nc; //send back the count of updates
else
	echo 0;
exit;

?>
