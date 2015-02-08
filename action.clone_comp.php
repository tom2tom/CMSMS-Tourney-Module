<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (! $this->CheckAccess('admod'))
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

$sid = $params['bracket_id'];
$pref = cms_db_prefix();
$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
$bdata = $db->GetRow($sql,array($sid));
if ($bdata == FALSE)
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('err_missing',FALSE)));
$bid = $db->GenID($pref.'module_tmt_brackets_seq');
$bdata['bracket_id'] = $bid;
$c = $this->Lang('clone');
$bdata['name'] .= ' '.$c;
if($bdata['alias'])
	$bdata['alias'] .= '_'.strtolower($c);
//check for date(s) > local current date
$dt = new DateTime ('now',new DateTimeZone($bdata['timezone']));
$stamp = $dt->getTimestamp();
$sdt = new DateTime ($bdata['startdate']);
$sstamp = $sdt->getTimestamp();
if ($stamp >= $sstamp)
	$bdata['startdate'] = null;
$sdt = new DateTime ($bdata['enddate']);
$sstamp = $sdt->getTimestamp();
if ($stamp >= $sstamp)
	$bdata['enddate'] = null;
$bdata['chartbuild'] = 1;
$values = array_values($bdata);
$fc = count($values);
$fillers = str_repeat('?,',$fc-1);
$sql = 'INSERT INTO '.$pref."module_tmt_brackets VALUES ($fillers?)";
$db->Execute($sql,$values);

$tpl = $this->GetTemplate('mailout_'.$sid.'_template');
if ($tpl)
	$this->SetTemplate('mailout_'.$bid.'_template',$tpl);
$tpl = $this->GetTemplate('mailin_'.$sid.'_template');
if ($tpl)
	$this->SetTemplate('mailin_'.$bid.'_template',$tpl);
$tpl = $this->GetTemplate('tweetout_'.$sid.'_template');
if ($tpl)
	$this->SetTemplate('tweetout_'.$bid.'_template',$tpl);
$tpl = $this->GetTemplate('tweetin_'.$sid.'_template');
if ($tpl)
	$this->SetTemplate('tweetin_'.$bid.'_template',$tpl);
$tpl = $this->GetTemplate('chart_'.$sid.'_template');
if ($tpl)
	$this->SetTemplate('chart_'.$bid.'_template',$tpl);

/* include this if teams are to be copied too
$sql = 'SELECT * FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2';
$teams = $db->GetAll($sql,array($sid));
if ($teams)
{
	$swaps = array();
	$fc = count($teams[0]);
	$fillers = str_repeat('?,',$fc-1);
	$sql = 'INSERT INTO '.$pref."module_tmt_teams VALUES ($fillers?)";
	foreach($teams as &$thisteam)
	{
		$tid = $db->GenID($pref.'module_tmt_teams_seq');
		$swaps[] = array($thisteam['team_id'],$tid);
		$thisteam['team_id'] = $tid;
		$thisteam['bracket_id'] = $bid;
		$thisteam['flags'] = 0;
		$values = array_values($thisteam);
		$db->Execute($sql,$values);
	}
	unset($thisteam);
	$sql = 'SELECT * FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2';
	$sql2 = 'INSERT INTO '.$pref.'module_tmt_people VALUES (?,?,?,?,?)';
	foreach($swaps as &$thisteam)
	{
		$people = $db->GetAll($sql,array($thisteam[0]));
		if ($people)
		{
			foreach ($people as &$person)
			{
				$person['id'] = $thisteam[1];
				$person['flags'] = 0;
				$db->Execute($sql2,array_values($person));
			}
			unset($person);
		}
	}
	unset($thisteam);
	//no matches are copied
}
*/

$this->Redirect($id,'addedit_comp',$returnid,array('bracket_id'=>$bid));

?>
