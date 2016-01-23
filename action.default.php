<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Action to display chart or list of a tournament in front end
*/

if(!function_exists('DisplayErrorPage'))
{
 function DisplayErrorPage(&$mod,&$tplvars,&$db,&$params,$err=TRUE,$message='')
 {
 	if($err)
		$tplvars['title'] =  $mod->Lang('err_system');
	if(!empty($params['bracket_id']))
	{
		$sql = "SELECT name FROM ".cms_db_prefix()."module_tmt_brackets WHERE bracket_id=?";
		$name = $db->GetOne($sql,array($params['alias']));
	}
	elseif(!empty($params['alias']))
	{
		$sql = "SELECT name FROM ".cms_db_prefix()."module_tmt_brackets WHERE alias=?";
		$name = $db->GetOne($sql,array($params['alias']));
	}
	else
		$name = false;
	if($name)
		$tplvars['name'] =  $mod->Lang('tournament').': '.$name;
	if($message)
	{
		$detail = $message;
		if($err)
			$detail .= '<br /><br />'.$mod->Lang('telladmin');
	}
	else
		$detail = '';
	$tplvars['message'] =  $detail;
	tmtTemplate::Process($this,'error.tpl',$tplvars);
 }
}

$tplvars = array();
$pref = cms_db_prefix();
if(isset($params['nosend'])) //frontend user cancelled result sumbission
{
	$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
	$val = $params['bracket_id'];
	$view = $params['view'];
	//other data from frontend not to interfere
	$params = array('view'=>$view);
}
elseif(isset($params['chart']) || isset($params['list']))
{
	$params['view'] =(isset($params['chart'])) ? 'chart':'list';
	$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
	$val = $params['bracket_id'];
}
elseif(!empty($params['alias']))
{
	$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE alias=?';
	$val = $params['alias'];
}
else
{
	DisplayErrorPage($this,$tplvars,$db,$params,TRUE,$this->Lang('err_tag'));
	return;
}
$bdata = $db->GetRow($sql,array($val));
if(!$bdata)
{
	DisplayErrorPage($this,$tplvars,$db,$params,TRUE,$this->Lang('err_missing'));
	return;
}

$bracket_id = $bdata['bracket_id'];
//refresh the matches table,if necessary
$sch = new tmtSchedule();
switch($bdata['type'])
{
 case Tourney::DETYPE:
	$res = $sch->UpdateDEMatches($this,$bracket_id);
	break;
 case Tourney::RRTYPE:
	$res = $sch->NextRRMatches($this,$bracket_id);
	break;
 default:
// case Tourney:KOTYPE:
	$res = $sch->UpdateKOMatches($this,$bracket_id);
	break;
}

$tplvars['title'] = $bdata['name'];
$tplvars['description'] = $bdata['description'];

if($res === TRUE)
	$bdata['chartbuild'] = 1; //tell downstream that rebuild is needed
$lyt = new tmtLayout();
if(empty($params['view']) || $params['view'] == 'chart')
{
	$styles =(isset($params['cssfile'])) ? $params['cssfile'] : FALSE;
	list($chartfile,$errkey) = $lyt->GetChart($this,$bdata,$styles);
	if($chartfile)
	{
		$sql = 'UPDATE '.$pref.'module_tmt_brackets SET chartbuild=0 WHERE bracket_id=?';
		$db->Execute($sql,array($bracket_id));
		//variables available for use in template(conform these with tmtEditSetup::Setup())
		$tplvars['owner'] = $bdata['owner'];
		$tplvars['contact'] = $bdata['contact'];
		$rooturl = (empty($_SERVER['HTTPS'])) ? $config['root_url'] : $config['ssl_url'];
		$basename = basename($chartfile);
		list($height,$width) = $lyt->GetChartSize();
		$tplvars['image'] = $this->CreateImageObject($rooturl.'/tmp/'.$basename,(int)$height+30);
		$tplvars['list'] = $this->CreateInputSubmit($id,'list',$this->Lang('list'));
		$dt = new DateTime('@'.filemtime($chartfile),new DateTimeZone($bdata['timezone']));
		$fmt = $this->GetPreference('date_format').' '.$this->GetPreference('time_format');
		$tplvars['imgdate'] = $dt->format($fmt);
		$tplvars['imgheight'] = $height;
		$tplvars['imgwidth'] = $width;
		$tpltxt = tmtTemplate::Get($this,'chart_'.$bracket_id.'_template');
		if($tpltxt == FALSE)
			$tpltxt = tmtTemplate::Get($this,'chart_default_template');
		//merge user-defined template into 'real' one
		$fn = cms_join_path(dirname(__FILE__),'templates','chart.tpl');
		$def = @file_get_contents($fn);
		$tpltxt = str_replace('|CUSTOM|',$tpltxt,$def);
		$hidden = $this->CreateInputHidden($id,'view','chart');
	}
	else
	{
		$sql = 'UPDATE '.$pref.'module_tmt_brackets SET chartbuild=? WHERE bracket_id=?';
		$db->Execute($sql,array((int)$bdata['chartbuild'],$bracket_id));
		if($errkey)
		{
			$err = (strpos($errkey,'err') === 0);
			if($err)
				$message = $this->Lang('err_chart').'<br /><br />'.$this->Lang($errkey);
			else
				$message = $this->Lang($errkey);
		}
		else
		{
			$err = TRUE;
			$message = $this->Lang('err_chart');
		}
		DisplayErrorPage($this,$tplvars,$db,$params,$err,$message);
		return;
	}
}
else
{
	$res = $lyt->GetList($this,$bdata);
	if(is_array($res))
	{
		$tplvars['items'] = $res;
		$tplvars['chart'] = $this->CreateInputSubmit($id,'chart',$this->Lang('chart'));
		$fn = cms_join_path(dirname(__FILE__),'templates','list.tpl');
		$tpltxt = @file_get_contents($fn);
		$hidden = $this->CreateInputHidden($id,'view','list');
	}
	else //$res (if any) is error-message key
	{
		if($res)
		{
			$err = (strpos($res,'err') === 0);
			if($err)
				$message = $this->Lang('err_list').'<br /><br />'.$this->Lang($res);
			else
				$message = $this->Lang($res);
		}
		else
		{
			$err = TRUE;
			$message = $this->Lang('err_list');
		}
		DisplayErrorPage($this,$tplvars,$db,$params,$err,$message);
		return;
	}
}

if(!empty($params['message']))
	$tplvars['message'] = urldecode($params['message']);
$tplvars['hidden'] = $this->CreateInputHidden($id,'bracket_id',$bracket_id).$hidden;
$tplvars['start_form'] = $this->CreateFormStart($id,'default',$returnid);
$tplvars['end_form'] = $this->CreateFormEnd();
$submit = null;
if($bdata['contact'])
{
	//check for match(es) scheduled & unscored
	if($sch->UnRecorded($bracket_id))
	{
		$ob = cms_utils::get_module('Notifier');
		if($ob)
		{
			unset($ob);
			//check for valid address for results
			$funcs = new MessageSender(); //TODO does this auto-load for CMSMS 1.9?
			$valid = $funcs->ValidateAddress($bdata['contact'],$bdata['smspattern']);
			if(array_filter($valid)) //any comforming address will do
				$submit = $this->CreateInputSubmitDefault($id,'result',$this->Lang('submit2'));
		}
	}
}
unset($sch);
$tplvars['submit'] = $submit;

echo tmtTemplate::ProcessfromData($this,$tpltxt,$tplvars);

?>
