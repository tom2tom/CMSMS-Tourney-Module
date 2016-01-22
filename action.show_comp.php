<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
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
 case Tourney::DETYPE:
	$sch->UpdateDEMatches ($this,$bracket_id);
	break;
 case Tourney::RRTYPE:
	$sch->NextRRMatches($this,$bracket_id);
	break;
 default:
// case Tourney::KOTYPE:
	$sch->UpdateKOMatches($this,$bracket_id);
	break;
}
unset($sch);

$tplvars = array();
$lyt = new tmtLayout();
if (!$list)
{
	$bdata['chartbuild'] = 1; //tell downstream that rebuild is needed
	list($chartfile,$errkey) = $lyt->GetChart($this,$bdata,FALSE,$titles);
	if ($chartfile)
	{
		$basename = basename($chartfile);
		list($height,$width) = $lyt->GetChartSize();
		$rooturl = (empty($_SERVER['HTTPS'])) ? $config['root_url'] : $config['ssl_url'];
		$tplvars['image'] = $this->CreateImageObject($rooturl.'/tmp/'.$basename,(int)$height+30);
		$tplname = 'admin_chart.tpl';
		if($titles == 0)
			//force refresh next time
			$db->Execute(
			'UPDATE '.$pref.'module_tmt_brackets SET chartbuild = 1 WHERE bracket_id=?',
			array($bracket_id));
	}
	else
	{
		$message = $this->PrettyMessage('err_chart',FALSE);
		if($errkey)
			$message .= '<br /><br />'.$this->Lang($errkey);
		$newparms = $this->GetEditParms($params,'charttab',$message);
		$this->Redirect($id,'addedit_comp',$returnid,$newparms);
	}
}
else
{
	$res = $lyt->GetList($this,$bdata,FALSE);
	if (is_array($res))
	{
		$tplvars['pagetitle'] = $bdata['name'];
		if (!empty($bdata['description']))
			$tplvars['pagedesc'] = $bdata['description'];
		else
			$tplvars['pagedesc'] = null;
		$tplvars['items'] = $res;
		$tplname = 'admin_list.tpl';
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

$tplvars += array(
	'startform' => $this->CreateFormStart($id,'show_comp',$returnid),
	'endform' => $this->CreateFormEnd(),
	'hidden' => $this->GetHiddenParms($id,$params),
	'close' => $this->CreateInputSubmit($id,'cancel',$this->Lang('close'))
);

tmtTemplate::Process($this,$tplname,$tplvars);
?>
