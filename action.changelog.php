<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (isset($params['close']))
{
	$newparms = $this->GetEditParms($params,'resultstab');
	$this->Redirect($id,'addedit_comp',$returnid,$newparms);
}

$tplvars = array();
$pref = cms_db_prefix();
$sql = 'SELECT * FROM '.$pref.'module_tmt_history WHERE bracket_id=? ORDER BY changewhen DESC';
$data = $db->GetAll($sql,array($params['bracket_id']));
if ($data)
{
	$changes = array();
	$rowclass = 'row1';
	foreach ($data as $line)
	{
		$one = new stdClass();
		$one->rowclass = $rowclass;
		$one->who = $line['changer'];//TODO format
		$one->when = substr($line['changewhen'],0,-3);//strip seconds from timestamp
		$one->from = $line['olddata'];
		$one->to = $line['newdata'];
		$changes[] = $one;
		($rowclass=='row1'?$rowclass='row2':$rowclass='row1');
	}
	$tplvars += array(
		'changes' => $changes,
		'changer' => $this->Lang('title_changer'),
		'changewhen' => $this->Lang('title_changewhen'),
		'olddata' => $this->Lang('title_olddata'),
		'newdata' => $this->Lang('title_newdata')
	);
}
else
	$tplvars['nochanges'] = $this->Lang('nochanges');

$sql = 'SELECT name FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
$name = $db->GetOne($sql,array($params['bracket_id']));

$tplvars += array(
	'start_form' => $this->CreateFormStart($id,'changelog',$returnid),
	'end_form' => $this->CreateFormEnd(),
	'hidden' => $this->GetHiddenParms($id,$params,'resultstab'),
	'title' => $this->Lang('title_changelog',$name),
	'close' => $this->CreateInputSubmit($id, 'close', $this->Lang('close'))
);

tmtTemplate::Process($this,'changelog.tpl',$tplvars);
?>
