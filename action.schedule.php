<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
if (!$this->CheckAccess('admod'))
	$this->Redirect($id, 'defaultadmin', '',
		array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

$bid = $params['bracket_id'];
$pref = cms_db_prefix();
$sql = 'SELECT type FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
$type = $db->GetOne($sql,array($bid));
if ($type !== FALSE)
{
	$sch = new tmtSchedule();
	switch (intval($type))
	{
	 case DETYPE:
		$res = $sch->InitDEMatches($this,$bid);
		break;
	 case RRTYPE:
		$res = $sch->NextRRMatches($this,$bid);
		break;
	 default:
	 //case KOTYPE:
		$res = $sch->InitKOMatches($this,$bid);
		break;
	}
	unset($sch);
	$newparms = $this->GetEditParms($params,'matchestab');
	if ($res === TRUE)
	{
		$sql = 'UPDATE '.$pref.'module_tmt_brackets SET chartbuild=1 WHERE bracket_id=?';
		$db->Execute($sql,array($bid));
		$newparms['matchview'] = 'plan';
	}
	else
		$newparms['tmt_message'] = $this->PrettyMessage($res,FALSE);

	$this->Redirect($id,'addedit_comp',$returnid,$newparms);
}

$this->Redirect($id, 'defaultadmin', '',
	array('tmt_message'=>$this->PrettyMessage('err_missing',FALSE)));

?>
