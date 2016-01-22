<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
if(!$this->CheckAccess('admin'))
	return $this->Lang('lackpermission');

$taboptarray = array('mysql' => 'ENGINE MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci',
 'mysqli' => 'ENGINE MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci');
$dict = NewDataDictionary($db);
$pref = cms_db_prefix();

$flds = "
	bracket_id I KEY,
	groupid I(2) DEFAULT 0,
	type I(1) DEFAULT ".Tourney::KOTYPE.",
	name C(128),
	alias C(24),
	description X,
	owner C(64),
	contact C(80),
	locale C(12),
	twtfrom C(18),
	smsfrom C(18),
	smsprefix C(6),
	smspattern C(32),
	feu_editgroup C(48),
	seedtype I(1),
	fixtype I(1) DEFAULT 0,
	teamsize I(1) DEFAULT 1,
	sametime I(1) DEFAULT 0,
	playgap N(6.2),
	playgaptype I(1) DEFAULT 2,
	available C(256),
	calendarid C(24),
	latitude N(8.3),
	longitude N(8.3),
	placegap N(6.2),
	placegaptype I(1) DEFAULT 2,
	startdate ".CMS_ADODB_DT.",
	enddate ".CMS_ADODB_DT.",
	html I(1) DEFAULT 0,
	timezone C(32),
	logic X,
	cantie I(1) DEFAULT 0,
	chartbuild I(1) DEFAULT 0,
	chartcss C(128),
	atformat C(16),
	final C(48),
	semi C(48),
	quarter C(48),
	eighth C(48),
	roundname C(48),
	versus C(48),
	bye C(48),
	defeated C(48),
	tied C(48),
	forfeit C(48),
	nomatch C(48)
";

$sql = $dict->CreateTableSQL($pref.'module_tmt_brackets', $flds, $taboptarray);
$dict->ExecuteSQLArray($sql);
$db->CreateSequence($pref.'module_tmt_brackets_seq');

//flags field to be used for undo-processing: 1=>added, 2=>deleted, 3=>changed
$flds = "
	team_id I KEY,
	bracket_id I,
	name C(64),
	seeding I(2),
	contactall I(1) DEFAULT 0,
	displayorder I(1),
	flags I(1) DEFAULT 0
";
$sql = $dict->CreateTableSQL($pref.'module_tmt_teams', $flds, $taboptarray);
$dict->ExecuteSQLArray($sql);
$db->CreateSequence($pref.'module_tmt_teams_seq');

//flags field to be used for undo-processing: 1=>added, 2=>deleted, 3=>changed
$flds = "
	id I,
	name C(64),
	contact C(80),
	displayorder I(1),
	flags I(1) DEFAULT 0
";
$sql = $dict->CreateTableSQL($pref.'module_tmt_people', $flds, $taboptarray);
$dict->ExecuteSQLArray($sql);
//no sequence for this table, but an index instead
$sql = $dict->CreateIndexSQL('idx_people', $pref.'module_tmt_people', 'id');
$dict->ExecuteSQLArray($sql);

$flds = "
	match_id I KEY,
	bracket_id I,
	nextm I,
	nextlm I,
	teamA I,
	teamB I,
	playwhen ".CMS_ADODB_DT.",
	place C(64),
	score C(64),
	status I(1) DEFAULT 0,
	flags I(1) DEFAULT 0
";
$sql = $dict->CreateTableSQL($pref.'module_tmt_matches', $flds, $taboptarray);
$dict->ExecuteSQLArray($sql);
$db->CreateSequence($pref.'module_tmt_matches_seq');

$fields = "
	group_id I(2) KEY,
	name C(128),
	displayorder I(2),
	flags I(1) DEFAULT 1
";
$sqlarray = $dict->CreateTableSQL($pref.'module_tmt_groups', $fields, $taboptarray);
$dict->ExecuteSQLArray($sqlarray);
$db->CreateSequence($pref.'module_tmt_groups_seq');
// add default group 0
$sql = 'INSERT INTO '.$pref.'module_tmt_groups (group_id,name,displayorder) VALUES (0,?,1)';
$db->Execute($sql,array($this->Lang('groupdefault')));

$flds = "
	bracket_id I NOTNULL DEFAULT 0,
	handle C(24),
	pubtoken C(64),
	privtoken C(80)
";
$sql = $dict->CreateTableSQL($pref.'module_tmt_tweet', $flds, $taboptarray);
$dict->ExecuteSQLArray($sql);
//no sequence for this table (multiple-0's allowed)
$sql = $dict->CreateIndexSQL('idx_tweetid', $pref.'module_tmt_tweet', 'bracket_id');
$dict->ExecuteSQLArray($sql);
//table always needs content
$sql = 'INSERT INTO'.$pref.'module_tmt_tweet (bracket_id,handle) VALUES (0,\'firstrow\')';
$db->Execute($sql);

$flds = "
	history_id I KEY,
	bracket_id I,
	changer C(128),
	changewhen ".CMS_ADODB_DT.",
	olddata C(128),
	newdata C(128),
	comment X
";
$sql = $dict->CreateTableSQL($pref.'module_tmt_history', $flds, $taboptarray);
$dict->ExecuteSQLArray($sql);
$db->CreateSequence($pref.'module_tmt_history_seq');

$this->CreatePermission($this->PermAdminName,$this->Lang('perm_admin'));
$this->CreatePermission($this->PermModName,$this->Lang('perm_mod'));
$this->CreatePermission($this->PermScoreName,$this->Lang('perm_score'));
$this->CreatePermission($this->PermSeeName,$this->Lang('perm_view'));

$this->SetPreference('time_zone', 'Europe/London');
$this->SetPreference('time_format', 'g:ia');
//use system setting for default date format MAYBE THIS SHOULD BE IN UTILS CLASS
$format = get_site_preference('defaultdateformat');
if ($format)
	{
	$strftokens = array(
	// Day - no strf eq : S
	'a' => 'D', 'A' => 'l', 'd' => 'd', 'e' => 'j', 'j' => 'z', 'u' => 'N', 'w' => 'w',
	// Week - no date eq : %U, %W
	'V' => 'W',
	// Month - no strf eq : n, t
	'b' => 'M', 'B' => 'F', 'm' => 'm',
	// Year - no strf eq : L; no date eq : %C, %g
	'G' => 'o', 'y' => 'y', 'Y' => 'Y',
	// Full Date / Time - no strf eq : c, r; no date eq : %c
	's' => 'U', 'D' => 'j/n/y', 'F' => 'Y-m-d', 'x' => 'j F Y'
 	);
	$format = str_replace('%','',$format);
	$parts = explode(' ',$format);
	foreach ($parts as $i => $fmt)
		{
		if(array_key_exists($fmt, $strftokens))
			$parts[$i] = $strftokens[$fmt];
		else
			unset($parts[$i]);
		}
	$format = implode(' ', $parts);
	}
else
	$format = 'd F y';
$this->SetPreference('date_format',$format);
$this->SetPreference('masterpass','OWFmNT1dGbU5FbnRlciBhdCB5b3VyIG93biByaXNrISBEYW5nZXJvdXMgZGF0YSE=');

$updir = $config['uploads_path'];
if($updir && is_dir($updir))
{
	$name = $this->GetName();
	$updir = cms_join_path($updir,$name);
	if(!is_dir($updir))
		mkdir($updir,0755);
	$this->SetPreference('uploads_dir',$name); //path relative to host uploads dir
}
else
	$this->SetPreference('uploads_dir',FALSE);
//$this->SetPreference('export_file',0);
//$this->SetPreference('strip_on_export',0);
$this->SetPreference('export_encoding','UTF-8');

$this->SetPreference('other_match_name',$this->Lang('name_other_match'));
$this->SetPreference('4thlast_match_name',$this->Lang('name_4thlast_match'));
$this->SetPreference('3rdlast_match_name',$this->Lang('name_3rdlast_match'));
$this->SetPreference('2ndlast_match_name',$this->Lang('name_2ndlast_match'));
$this->SetPreference('last_match_name',$this->Lang('name_last_match'));
$this->SetPreference('against_name',$this->Lang('name_against'));
$this->SetPreference('def_name',$this->Lang('name_defeated'));
$this->SetPreference('tied_name',$this->Lang('name_tied'));
$this->SetPreference('noop_name',$this->Lang('name_no_opponent'));
$this->SetPreference('forfeit_name',$this->Lang('name_forfeit'));
$this->SetPreference('abandon_name',$this->Lang('name_abandoned'));
$this->SetPreference('phone_regex','^(\+|\d)[0-9]{7,16}$'); //for SMS messages to cell/mobile numbers

$base = cms_join_path(dirname(__FILE__),'templates','');
if($this->before20)
{
	$s = ''.@file_get_contents($base.'chart_custom.tpl');
/*if ($s == FALSE)
		$s = '<div id="bracketchart" style="overflow:auto;">{$image}</div>';
*/
	$this->SetTemplate('chart_default_template',$s);
	$s = ''.@file_get_contents($base.'email_out.tpl');
/*if ($s == FALSE)
		$s = '{$title}'.PHP_EOL.PHP_EOL.$this->Lang('default_email','{$where}','{$when}').PHP_EOL;
*/
	$this->SetTemplate('mailout_default_template',$s);
	$s = ''.@file_get_contents($base.'email_cancelled.tpl');
/*if ($s == FALSE)
		$s = '{$title}'.PHP_EOL.PHP_EOL.$this->Lang('cancelled_email','{$when}');
*/
	$this->SetTemplate('mailcancel_default_template',$s);
	$s = ''.@file_get_contents($base.'email_request.tpl');
/*if ($s == FALSE)
		$s = '{$title}'.PHP_EOL.PHP_EOL.
		$this->Lang('title_mid').'{if $where} {$where}{/if}{if $when} {$when}{/if} {$teams}'.PHP_EOL.PHP_EOL.
		$this->Lang('tpl_mailresult','{if $contact}{$contact}{elseif $smsfrom}{$smsfrom}{elseif $owner}{$owner}{else}'.$this->Lang('organisers').'{/if}');
*/
	$this->SetTemplate('mailrequest_default_template',$s);
	$s = ''.@file_get_contents($base.'email_in.tpl');
/*if ($s == FALSE)
		$s = '{$report}';
*/
	$this->SetTemplate('mailin_default_template',$s);

	$s = ''.@file_get_contents($base.'tweet_out.tpl');
/*if ($s == FALSE)
		$s = '{$title} '.mb_strtolower($this->Lang('title_mid')).' {$where} {$when} {$teams}';
*/
	$this->SetTemplate('tweetout_default_template',$s);
	$this->SetTemplate('textout_default_template',$s);

	$s = ''.@file_get_contents($base.'tweet_cancelled.tpl');
/*if ($s == FALSE)
		$s = '{$title} '.mb_strtolower($this->Lang('title_mid')).' '.
		 mb_strtoupper($this->Lang('cancelled')).'{if $when}, '.mb_strtoupper($this->Lang('not')).' {$when}{elseif $opponent},'.$this->Lang('name_against').' {$opponent}{/if}';
*/
	$this->SetTemplate('tweetcancel_default_template',$s);
	$this->SetTemplate('textcancel_default_template',$s);

	$s = ''.@file_get_contents($base.'tweet_request.tpl');
/*if ($s == FALSE)
		$s = '{$title} '.mb_strtolower($this->Lang('title_mid')).' {$where} {$when} {$teams} '.
		 $this->Lang('tpl_tweetresult','{if $smsfrom}{$smsfrom}{elseif $contact}{$contact}{elseif $owner}{$owner}{else}'.$this->Lang('organisers').'{/if}');
*/
	$this->SetTemplate('tweetrequest_default_template',$s);
	$this->SetTemplate('textrequest_default_template',$s);

	$s = ''.@file_get_contents($base.'tweet_in.tpl');
/*if ($s == FALSE)
		$s = '{$report}';
*/
	$this->SetTemplate('tweetin_default_template',$s);
	$this->SetTemplate('textin_default_template',$s);
}
else
{
	$myname = $this->GetName();
	$me = get_userid(false);
	$files = array(
		'chart_custom.tpl',

		'tweet_in.tpl',
		'tweet_out.tpl',
		'tweet_request.tpl',
		'tweet_cancelled.tpl',

		'email_in.tpl',
		'email_out.tpl',
		'email_request.tpl',
		'email_cancelled.tpl',

		'tweet_in.tpl',
		'tweet_out.tpl',
		'tweet_request.tpl',
		'tweet_cancelled.tpl',
	);
	foreach(array(
		'chart',

		'textin',
		'textout',
		'textrequest',
		'textcancel',

		'mailin',
		'mailout',
		'mailrequest',
		'mailcancel',

		'tweetin',
		'tweetout',
		'tweetrequest',
		'tweetcancel',
	) as $i=>$name)
	{
		$ttype = new CmsLayoutTemplateType();
		$ttype->set_originator($myname);
		$ttype->set_name($name);
		$ttype->set_owner($me);
		$s = ''.@file_get_contents($base.$files[$i]);
		if($s)
		{
			$ttype->set_dflt_flag();
			$ttype->set_dflt_contents($s);
		}
		$ttype->save();
		if($s)
		{
			$tpl = new CmsLayoutTemplate();
			$tpl->set_type($ttype);
			$tpl->set_name('tmt_'.$name.'_default_content'); //must be unique!
			$tpl->set_content($s);
			$tpl->set_type_dflt(true);
//		$tpl->set_listable($flag); //CMSMS 2.1+
			$tpl->save();
		}
	}
}

//$this->CreateEvent('ResultAdd');
//$this->CreateEvent('MatchChange');
//$this->AddEventHandler('Tourney','?');
//events to trigger cleanup of chart files
$this->AddEventHandler('Core','LoginPost');

?>
