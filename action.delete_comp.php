<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (! $this->CheckAccess('admod')) exit;
if (empty($params['bracket_id']))
	$this->Redirect($id, 'defaultadmin');

$bid = (int)$params['bracket_id'];
$pref = cms_db_prefix();
$sql = 'SELECT chartcss FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
$file = $db->GetOne($sql,array($bid));
if ($file)
{
	$sql = 'SELECT COUNT(*) AS sharers FROM '.$pref.'module_tmt_brackets WHERE chartcss=?';
	$num = $db->GetOne($sql,array($file));
	if ($num < 2)
	{
		if($this->GetPreference('uploads_dir'))
			$path = cms_join_path($config['uploads_path'],
				$this->GetPreference('uploads_dir'),$file);
		else
			$path = cms_join_path($config['uploads_path'],$file);
		if(is_file($path))
			unlink($path);
	}
}
$file = $this->ChartImageFile($bid);
if ($file)
	unlink($file);

$sql = 'DELETE FROM '.$pref.'module_tmt_tweet WHERE bracket_id=?';
$db->Execute($sql,array($bid));
$sql = 'DELETE FROM '.$pref.'module_tmt_people WHERE id IN
 (SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=?)';
$db->Execute($sql,array($bid));
$sql = 'DELETE FROM '.$pref.'module_tmt_teams WHERE bracket_id=?';
$db->Execute($sql,array($bid));
$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
$db->Execute($sql,array($bid));
$sql = 'DELETE FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
$db->Execute($sql,array($bid));

$bid = $params['bracket_id'];
$this->DeleteTemplate('mailout_'.$bid.'_template');
$this->DeleteTemplate('mailin_'.$bid.'_template');
$this->DeleteTemplate('tweetout_'.$bid.'_template');
$this->DeleteTemplate('tweetin_'.$bid.'_template');
$this->DeleteTemplate('chart_'.$bid.'_template');

unset($params);
$this->Redirect($id, 'defaultadmin', '', array('tmt_message'=>$this->PrettyMessage('comp_deleted')));

?>
