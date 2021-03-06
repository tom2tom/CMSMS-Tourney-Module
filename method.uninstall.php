<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (! $this->CheckAccess('admin')) return;

$pref = cms_db_prefix();
$db = cmsms()->GetDb();
// uploaded files

$fp = $config['uploads_path'];
if($fp && is_dir($fp))
{
	$ud = $this->GetPreference('uploads_dir','');
	if($ud)
	{
		$fp .= DIRECTORY_SEPARATOR.$ud;
		if($fp && is_dir($fp))
			recursive_delete($fp);
	}
	else
	{
		$files = $db->GetCol("SELECT DISTINCT chartcss FROM ".$pref."module_tmt_brackets
WHERE chartcss IS NOT NULL AND chartcss<>''");
		if($files)
		{
			foreach($files as $fn)
			{
				$fn = cms_join_path($fp,$fn);
				if(is_file($fn))
					unlink($fn);
			}
		}
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
$sql = $dict->DropTableSQL($pref.'module_tmt_groups');
$dict->ExecuteSQLArray($sql);
//tweet-table is redundant, but keep these for a while ...
$sql = $dict->DropIndexSQL('idx_tweetid', $pref.'module_tmt_tweet');
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($pref.'module_tmt_tweet');
$dict->ExecuteSQLArray($sql);

$sql = $dict->DropTableSQL($pref.'module_tmt_history');
$dict->ExecuteSQLArray($sql);

$db->DropSequence($pref.'module_tmt_brackets_seq');
$db->DropSequence($pref.'module_tmt_teams_seq');
//no sequence for people table
$db->DropSequence($pref.'module_tmt_matches_seq');
$db->DropSequence($pref.'module_tmt_groups_seq');
$db->DropSequence($pref.'module_tmt_history_seq');

// permissions
$this->RemovePermission($this->PermAdminName);
$this->RemovePermission($this->PermModName);
$this->RemovePermission($this->PermScoreName);
$this->RemovePermission($this->PermSeeName);

// preferences
$this->RemovePreference();

// templates
if ($this->oldtemplates) {
	$this->DeleteTemplate();
} else {
	$types = CmsLayoutTemplateType::load_all_by_originator($this->GetName());
	if ($types) {
		foreach ($types as $type) {
			$templates = $type->get_template_list();
			if ($templates) {
				foreach ($templates as $tpl) {
					$tpl->delete();
				}
			}
			$type->delete();
		}
	}
}

// events
//$this->RemoveEvent('ResultAdd');
//$this->RemoveEvent('MatchChange');
//$this->RemoveEventHandler('Tourney','?');
$this->RemoveEventHandler('Core','LoginPost');

?>
