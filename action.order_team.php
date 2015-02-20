<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
/* action for ajax-processing, after team-members-table row(s) have been dragged and dropped
  $params[] includes
  'team_id'
  'neworders' string with comma-separated displayorder values of all members e.g. '2,3,6,1'
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
$id = (int)$params['team_id'];
$pref = cms_db_prefix();
$sql = 'SELECT DISTINCT displayorder FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder';
$orders = $db->GetCol($sql,array($id));
if ($orders === FALSE || count($orders) != $nc)
{
	echo 0;
	exit;
}
$sql = 'UPDATE '.$pref.'module_tmt_people SET displayorder = CASE displayorder ';
foreach ($orders as $o)
    $sql .= 'WHEN '.(int)$o.' THEN ? ';
$sql .= 'ELSE displayorder END WHERE id=? AND displayorder IN ('.implode(',',$orders).')';
foreach ($news as &$o)
	$o = -(int)$o;	//<0 to prevent overwrites
unset ($o);
$news[] = $id;
$db->Execute('BEGIN TRANSACTION');
if ($db->Execute ($sql,$news))
{
	//revert neg's
	$sql = 'UPDATE '.$pref.'module_tmt_people SET displayorder=-displayorder WHERE id=? AND displayorder<0';
	$db->Execute($sql,array($id));
	$db->Execute('COMMIT');
	echo $nc; //send back the count of updates
}
else
{
	$db->Execute('ROLLBACK');
	echo 0;
}
exit;

?>
