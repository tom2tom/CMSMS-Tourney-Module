<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (! $this->CheckAccess('admin')) return;

$pref = cms_db_prefix();
$taboptarray = array('mysql' => 'ENGINE MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci',
 'mysqli' => 'ENGINE MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci');
switch ($oldversion)
{
 case '0.1.0':
 case '0.1.1':
	$rel = $this->GetPreference('uploads_dir');
	if(!$rel)
		$this->SetPreference('uploads_dir',$this->GetName());
	$dict = NewDataDictionary($db);

	$flds = "
	type I(1) DEFAULT ".KOTYPE.",
	match_days C(256),
	playgap N(6.2),
	playgaptype I(1) DEFAULT 2,
	placegap N(6.2),
	placegaptype I(1) DEFAULT 2
";
	$sql = $dict->AlterColumnSQL($pref.'module_tmt_brackets',$flds);
	if(!$dict->ExecuteSQLArray($sql))
	{
		//UI error message too?
		$this->Audit(0, $this->Lang('friendlyname'), $this->Lang('upgradefail','change fields'));
		return FALSE;
	}

	$flds = "
	admin_editgroup,
	match_hours,
";
	$sql = $dict->DropColumnSQL($pref.'module_tmt_brackets',$flds);
	if(!$dict->ExecuteSQLArray($sql))
	{
		//UI error message too?
		$this->Audit(0, $this->Lang('friendlyname'), $this->Lang('upgradefail','delete field \'admin_editgroup\''));
		return FALSE;
	}
	$flds = "
	locale C(12),
	twtfrom C(18),
	calendarid C(24),
	latitude N(8.3),
	longitude N(8.3)
";
	$sql = $dict->AddColumnSQL($pref.'module_tmt_brackets',$flds);
	$dict->ExecuteSQLArray($sql,FALSE);

	$sql = $dict->RenameColumnSQL($pref.'module_tmt_brackets','match_days','available');
	$dict->ExecuteSQLArray($sql,FALSE);

	$sql = $dict->AddColumnSQL($pref.'module_tmt_matches','flags I(1) DEFAULT 0');
	$dict->ExecuteSQLArray($sql,FALSE);

	$flds = "
	bracket_id I NOTNULL DEFAULT 0,
	handle C(24),
	pubtoken C(64),
	privtoken C(80)
";
	$sql = $dict->CreateTableSQL($pref.'module_tmt_tweet', $flds, $taboptarray);
	$dict->ExecuteSQLArray($sql);
	$sql = $dict->CreateIndexSQL('idx_tweetid', $pref.'module_tmt_tweet', 'bracket_id');
	$dict->ExecuteSQLArray($sql);
	$sql = 'INSERT INTO'.$pref.'module_tmt_tweet (bracket_id,handle) VALUES (0,\'firstrow\')';
	$db->Execute($sql);

	break;
}
// put mention into the admin log
$this->Audit(0, $this->Lang('friendlyname'), $this->Lang('upgraded',$newversion));

?>
