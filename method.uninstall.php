<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (! $this->CheckAccess('admin')) return;

$pref = cms_db_prefix();
$db = cmsms()->GetDb();
// uploaded files
$config = cmsms()->GetConfig();
$updir = $config['uploads_path'];
if($updir) //careful, we're going to delete stuff wildly
{
	$rel = $this->GetPreference('uploads_dir',$this->GetName());
	if ($rel)
	{
		$updir .= DIRECTORY_SEPARATOR.$rel;
		$mask = $updir.DIRECTORY_SEPARATOR.'*';
		array_map("unlink",glob($mask,GLOB_NOSORT)); //assumes no populated subdir
		unlink($updir);
	}
}

$dict = NewDataDictionary($db);

$sql = $dict->DropTableSQL($pref.'module_tmt_brackets');
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($pref.'module_tmt_teams');
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropIndexSQL('idx_people', $pref.'module_tmt_people');
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($pref.'module_tmt_people');
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($pref.'module_tmt_matches');
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropIndexSQL('idx_tweetid', $pref.'module_tmt_tweet');
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($pref.'module_tmt_tweet');
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($pref.'module_tmt_history');
$dict->ExecuteSQLArray($sql);

$db->DropSequence($pref.'module_tmt_brackets_seq');
$db->DropSequence($pref.'module_tmt_teams_seq');
//no sequence for people,tweet tables
$db->DropSequence($pref.'module_tmt_matches_seq');
$db->DropSequence($pref.'module_tmt_history_seq');

// permissions
$this->RemovePermission($this->PermAdminName);
$this->RemovePermission($this->PermModName);
$this->RemovePermission($this->PermScoreName);
$this->RemovePermission($this->PermSeeName);

// preferences
$this->RemovePreference();

// templates
$this->DeleteTemplate();

// events
//$this->RemoveEvent('ResultAdd');
//$this->RemoveEvent('MatchChange');
//$this->RemoveEventHandler('Tourney','?');
$this->RemoveEventHandler('Core','LoginPost');

$this->Audit(0, $this->Lang('friendlyname'), $this->Lang('uninstalled'));

?>
