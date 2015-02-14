<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Action to chart, list, print a tournament on a separate page in backend
*/
//setup for a full chart, or an un-labelled one, or a list
if (isset($params['chart']))
{
	unset($params['chart']);
	$titles = 1;
	$list = FALSE;
}
elseif (isset($params['list']))
{
	unset($params['list']);
	$list = TRUE;
}
elseif (isset($params['print']))
{
	unset($params['print']);
	$titles = 0;
	$list = FALSE;
}
else
{
	$newparms = $this->GetEditParms($params,$params['active_tab']);
	$this->Redirect($id,'addedit_comp',$returnid,$newparms);
}

$bracket_id = $params['bracket_id'];
$pref = cms_db_prefix();
$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
$bdata = $db->GetRow($sql,array($bracket_id));
//refresh the matches table, if necessary
$sch = new tmtSchedule();
switch ($bdata['type'])
{
 case DETYPE:
	$sch->UpdateDEMatches ($this,$bracket_id);
	break;
 case RRTYPE:
	$sch->NextRRMatches($this,$bracket_id);
	break;
 default:
// case KOTYPE:
	$sch->UpdateKOMatches($this,$bracket_id);
	break;
}
unset($sch);

$lyt = new tmtLayout();
if (!$list)
{
	$bdata['chartbuild'] = 1; //tell downstream that rebuild is needed
	list($chartfile,$errkey) = $lyt->GetChart($this,$bdata,FALSE,$titles);
	if ($chartfile)
	{
		$basename = basename($chartfile);
		list($height,$width) = $lyt->GetChartSize();
		$smarty->assign('image',$this->CreateImageObject($config['root_url'].'/tmp/'.$basename,(int)$height+30));
		$tpl = 'admin_chart.tpl';
	}
	else
	{
		$message = $this->PrettyMessage('err_chart',FALSE);
		if($errkey)
			$message .= '<br /><br />'.$mod->Lang($errkey);
		$newparms = $this->GetEditParms($params,'charttab',$message);
		$this->Redirect($id,'addedit_comp',$returnid,$newparms);
	}
}
else
{
	$res = $lyt->GetList($this,$bdata);
	if (is_array($res))
	{
		$smarty->assign('pagetitle',$bdata['name']);
		if (!empty($bdata['description']))
			$smarty->assign('pagedesc',$bdata['description']);
		else
			$smarty->assign('pagedesc',null);
		$smarty->assign('items',$res);
		$tpl = 'admin_list.tpl';
	}
	else //$res (if any) is error-message key
	{
		$message = $this->Lang('err_list');
		if($res)
			$message .= ': '.strtolower($this->Lang($res));
		$newparms = $this->GetEditParms($params,'charttab',$message);
		$this->Redirect($id,'addedit_comp',$returnid,$newparms);
	}
}
unset($lyt);

$smarty->assign('startform',$this->CreateFormStart($id,'show_comp',$returnid));
$smarty->assign('endform',$this->CreateFormEnd());
$smarty->assign('hidden',$this->GetHiddenParms($id,$params));
$smarty->assign('close',$this->CreateInputSubmit($id,'cancel',$this->Lang('close')));

echo $this->ProcessTemplate($tpl);
?>
