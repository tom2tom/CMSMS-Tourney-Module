<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Action to display chart or list of a tournament in front end
*/

$pref = cms_db_prefix();
if(isset($params['nosend'])) //fronted user cancelled result sumbission
{
	$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
	$match = $params['bracket_id'];
	$view = $params['view'];
	unset($params); //other data from frontend not to interfere
	$params['view'] = $view;
}
elseif(isset($params['tweetauth']))
{
	if(empty($returnid)) //no $returnid upon return from Twitter
	{
		$returnid = $params['returnid']; //frontend-page-display needs this var
		if(isset($_REQUEST['oauth_token'])) //authorisation was completed
		{
			//preserve values across redirection
			$params['oauth_token'] = $_REQUEST['oauth_token'];
			$params['oauth_verifier'] = $_REQUEST['oauth_verifier'];
		}
	}
	$this->Redirect($id,'twtauth',$returnid,$params,TRUE);
}
elseif(isset($params['chart']) || isset($params['list']))
{
	$params['view'] =(isset($params['chart'])) ? 'chart':'list';
	$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
	$match = $params['bracket_id'];
}
elseif(!empty($params['alias']))
{
	$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE alias=?';
	$match = $params['alias'];
}
else
{
	$this->DisplayErrorPage($id,$params,$returnid);
	return;
}
$bdata = $db->GetRow($sql,array($match));
if(!$bdata)
{
	$this->DisplayErrorPage($id,$params,$returnid,$this->Lang('err_missing'));
	return;
}

$bracket_id = $bdata['bracket_id'];
//refresh the matches table,if necessary
$sch = new tmtSchedule();
switch($bdata['type'])
{
 case DETYPE:
	$fresh = $sch->UpdateDEMatches($bracket_id);
	break;
 case RRTYPE:
	$fresh = $sch->NextRRMatches($this,$bracket_id);
	break;
 default:
// case KOTYPE:
	$fresh = $sch->UpdateKOMatches($bracket_id);
	break;
}

if($fresh)
	$bdata['chartbuild'] = 1; //tell downstream that rebuild is needed
$lyt = new tmtLayout();
if(empty($params['view']) || $params['view'] == 'chart')
{
	$styles =(isset($params['cssfile'])) ? $params['cssfile'] : FALSE;
	$chartfile = $lyt->GetChart($this,$bdata,$styles);
	if($chartfile)
	{
		$sql = 'UPDATE '.$pref.'module_tmt_brackets SET chartbuild=0 WHERE bracket_id=?';
		$db->Execute($sql,array($bracket_id));
		//variables available for use in template(conform these with tmtEditSetup::Setup())
		$smarty->assign('title',$bdata['name']);
		$smarty->assign('description',$bdata['description']);
		$smarty->assign('owner',$bdata['owner']);
		$smarty->assign('contact',$bdata['contact']);
		$basename = basename($chartfile);
		list($height,$width) = $lyt->GetChartSize();
		$smarty->assign('image',$this->CreateImageObject($config['root_url'].'/tmp/'.$basename,(int)$height+30));
		$smarty->assign('list',$this->CreateInputSubmit($id,'list',$this->Lang('list')));
		$dt = new DateTime('@'.filemtime($chartfile),new DateTimeZone($bdata['timezone']));
		$fmt = $this->GetPreference('date_format').' '.$this->GetPreference('time_format');
		$smarty->assign('imgdate',$dt->format($fmt));
		$smarty->assign('imgheight',$height);
		$smarty->assign('imgwidth',$width);
		$tpl = $this->GetTemplate('chart_'.$bracket_id.'_template');
		if($tpl == FALSE)
			$tpl = $this->GetTemplate('chart_default_template');
		//merge user-defined template into 'real' one
		$fn = cms_join_path(dirname(__FILE__),'templates','chart.tpl');
		$def = @file_get_contents($fn);
		$tpl = str_replace('|CUSTOM|',$tpl,$def);
		$hidden = $this->CreateInputHidden($id,'view','chart');
	}
	else
	{
		$sql = 'UPDATE '.$pref.'module_tmt_brackets SET chartbuild=? WHERE bracket_id=?';
		$db->Execute($sql,array((int)$bdata['chartbuild'],$bracket_id));
		$this->DisplayErrorPage($id,$params,$returnid,$this->Lang('err_chart'));
		return;
	}
}
else
{
	$listrows = $lyt->GetList($this,$bdata);
	if($listrows !== FALSE)
	{
		$smarty->assign('items',$listrows);
		$smarty->assign('chart',$this->CreateInputSubmit($id,'chart',$this->Lang('chart')));
		$fn = cms_join_path(dirname(__FILE__),'templates','list.tpl');
		$tpl = @file_get_contents($fn);
		$hidden = $this->CreateInputHidden($id,'view','list');
	}
	else
	{
		$this->DisplayErrorPage($id,$params,$returnid,$this->Lang('error'));
		return;
	}
}

if(!empty($params['message']))
	$smarty->assign('message',urldecode($params['message']));
$smarty->assign('hidden',$this->CreateInputHidden($id,'bracket_id',$bracket_id).$hidden);
$smarty->assign('start_form',$this->CreateFormStart($id,'default',$returnid));
$smarty->assign('end_form',$this->CreateFormEnd());
$submit = null;
if($bdata['contact'])
{
	//check for match(es) scheduled & unscored
	if($sch->UnRecorded($bracket_id))
	{
		//check for valid address for results
		$funcs = new tmtComm($this);
		if($funcs->ValidateAddress($bdata['contact']))
			$submit = $this->CreateInputSubmit($id,'result',$this->Lang('submit2'));
	}
}
unset($sch);
$smarty->assign('submit',$submit);

$this->ProcessDataTemplate($tpl,TRUE);

?>
