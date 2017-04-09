<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

function rmdir_recursive($dir)
{
	foreach(scandir($dir) as $file) {
		if (!($file === '.' || $file === '..')) {
			$fp = $dir.DIRECTORY_SEPARATOR.$file;
			if (is_dir($fp)) {
				rmdir_recursive($fp);
			} else {
 				@unlink($fp);
			}
		}
	}
	rmdir($dir);
}

if (!$this->CheckAccess('admin'))
	return $this->Lang('lackpermission');

$pref = cms_db_prefix();
$taboptarray = array('mysql' => 'ENGINE MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci',
 'mysqli' => 'ENGINE MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci');
$dict = NewDataDictionary($db);
switch ($oldversion) {
 case '0.1.0':
 case '0.1.1':
	$rel = $this->GetPreference('uploads_dir');
	if(!$rel)
		$this->SetPreference('uploads_dir',$this->GetName());
	$this->SetPreference('phone_regex','^(\+|\d)[0-9]{7,16}$');

	$flds = '
type I(1) DEFAULT '.Tourney::KOTYPE.',
match_days C(256),
playgap N(6.2),
playgaptype I(1) DEFAULT 2,
placegap N(6.2),
placegaptype I(1) DEFAULT 2
';
	$sql = $dict->AlterColumnSQL($pref.'module_tmt_brackets',$flds);
	if(!$dict->ExecuteSQLArray($sql))
	{
		$msg = $this->Lang('err_upgrade','change fields');
		$this->Audit(0, $this->Lang('friendlyname'), $msg);
		return $msg;
	}

	$flds = '
admin_editgroup,
match_hours,
';
	$sql = $dict->DropColumnSQL($pref.'module_tmt_brackets',$flds);
	if(!$dict->ExecuteSQLArray($sql))
	{
		$msg = $this->Lang('err_upgrade','delete field \'admin_editgroup\'');
		$this->Audit(0, $this->Lang('friendlyname'), $msg);
		return $msg;
	}
	$flds = '
groupid I(2) DEFAULT 0,
fixtype I(1) DEFAULT 0,
locale C(12),
twtfrom C(18),
smsfrom C(18),
smsprefix C(6),
smspattern C(32),
calendarid C(24),
latitude N(8.3),
longitude N(8.3),
atformat C(16)
';
	$sql = $dict->AddColumnSQL($pref.'module_tmt_brackets',$flds);
	$dict->ExecuteSQLArray($sql,FALSE);

	$sql = $dict->RenameColumnSQL($pref.'module_tmt_brackets','match_days','available');
	$dict->ExecuteSQLArray($sql,FALSE);

	$sql = $dict->AddColumnSQL($pref.'module_tmt_matches','flags I(1) DEFAULT 0');
	$dict->ExecuteSQLArray($sql,FALSE);

	$sql = $dict->AlterColumnSQL($pref.'module_tmt_history','bracket_id I');
	$dict->ExecuteSQLArray($sql,FALSE);
	$sql = $dict->AddColumnSQL($pref.'module_tmt_history','history_id I KEY');
	$dict->ExecuteSQLArray($sql,FALSE);

	$flds = '
bracket_id I NOTNULL DEFAULT 0,
handle C(24),
pubtoken C(64),
privtoken C(80)
';
	$sql = $dict->CreateTableSQL($pref.'module_tmt_tweet', $flds, $taboptarray);
	$dict->ExecuteSQLArray($sql);
	$sql = $dict->CreateIndexSQL('idx_tweetid', $pref.'module_tmt_tweet', 'bracket_id');
	$dict->ExecuteSQLArray($sql);
	$sql = 'INSERT INTO'.$pref.'module_tmt_tweet (bracket_id,handle) VALUES (0,\'firstrow\')';
	$db->Execute($sql);

 case '0.1.2':
	$fp = cms_join_path(dirname(__FILE__),'templates','email_cancelled.tpl');
	$s = @file_get_contents($fp);
	if ($s == FALSE)
		$s = '{$title}'.PHP_EOL.PHP_EOL.$this->Lang('cancelled_email','{$when}');
	tmtTemplate::Set($this,'mailcancel_default_template',$s);

	$fp = cms_join_path(dirname(__FILE__),'templates','email_request.tpl');
	$s = @file_get_contents($fp);
	if ($s == FALSE)
		$s = '{$title}'.PHP_EOL.PHP_EOL.
		$this->Lang('title_mid').'{if $where} {$where}{/if}{if $when} {$when}{/if} {$teams}'.PHP_EOL.PHP_EOL.
		$this->Lang('tpl_mailresult','{if $contact}{$contact}{elseif $smsfrom}{$smsfrom}{elseif $owner}{$owner}{else}'.$this->Lang('organisers').'{/if}');
	tmtTemplate::Set($this,'mailrequest_default_template',$s);

	$fp = cms_join_path(dirname(__FILE__),'templates','tweet_cancelled.tpl');
	$s = @file_get_contents($fp);
	if ($s == FALSE)
		$s = '{$title} '.mb_strtolower($this->Lang('title_mid')).' '.
		 mb_strtoupper($this->Lang('cancelled')).'{if $when}, '.mb_strtoupper($this->Lang('not')).' {$when}{elseif $opponent},'.$this->Lang('name_against').' {$opponent}{/if}';
	tmtTemplate::Set($this,'tweetcancel_default_template',$s);

	$fp = cms_join_path(dirname(__FILE__),'templates','tweet_request.tpl');
	$s = @file_get_contents($fp);
	if ($s == FALSE)
		$s = '{$title} '.mb_strtolower($this->Lang('title_mid')).' {$where} {$when} {$teams} '.
		 $this->Lang('tpl_tweetresult','{if $smsfrom}{$smsfrom}{elseif $contact}{$contact}{elseif $owner}{$owner}{else}'.$this->Lang('organisers').'{/if}');
	tmtTemplate::Set($this,'tweetrequest_default_template',$s);

	$flds = '
group_id I(2) KEY,
name C(128),
displayorder I(2),
flags I(1) DEFAULT 1
';
	$sqlarray = $dict->CreateTableSQL($pref.'module_tmt_groups', $flds, $taboptarray);
	$dict->ExecuteSQLArray($sqlarray);
	$db->CreateSequence($pref.'module_tmt_groups_seq');
	// add default group 0
	$sql = 'INSERT INTO '.$pref.'module_tmt_groups (group_id,name,displayorder) VALUES (0,?,1)';
	$db->Execute($sql,array($this->Lang('groupdefault')));

 case '0.2.0':
 case '0.2.1':
 case '0.2.2':
 	$fp = cms_join_path(dirname(__FILE__),'lib','class.Calendar.php');
 	if(is_file($fp))
		unlink($fp);
 	$fp = cms_join_path(dirname(__FILE__),'lib','OAuth.php');
 	if(is_file($fp))
		unlink($fp);
 	$fp = cms_join_path(dirname(__FILE__),'lib','class.TTwitter.php');
 	if(is_file($fp))
		unlink($fp);
 	$fp = cms_join_path(dirname(__FILE__),'lib','class.TwitterCredential.php');
 	if(is_file($fp))
		unlink($fp);
 	$fp = cms_join_path(dirname(__FILE__),'templates','tweet_auth.tpl');
 	if(is_file($fp))
		unlink($fp);
 	$fp = cms_join_path(dirname(__FILE__),'action.twtauth.php');
 	if(is_file($fp))
		unlink($fp);

	$sql = $dict->DropIndexSQL('idx_tweetid', $pref.'module_tmt_tweet');
	$dict->ExecuteSQLArray($sql);
	$sql = $dict->DropTableSQL($pref.'module_tmt_tweet');
	$dict->ExecuteSQLArray($sql);

	$flds = '
available C(128)
';
	$sql = $dict->AddColumnSQL($pref.'module_tmt_people',$flds);
	$dict->ExecuteSQLArray($sql,FALSE);

	//redundant directory
	$fp = cms_join_path(dirname(__FILE__), 'include');
	if (is_dir($fp)) {
		rmdir_recursive($fp);
	}
}
