<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
 
Processes form-submission from admin brackets tab
*/

if(isset($params['cancel']) || empty($params['selitems']))
	$this->Redirect($id,'defaultadmin');
elseif(isset($params['clone']))
{
	if (!$this->CheckAccess('admod'))
		$this->Redirect($id,'defaultadmin','',
			array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

	$vals = array_flip($params['selitems']); //convert strings
	$vals = array_flip($vals);
	if($vals)
	{
		$funcs = new tmtClone();
		$res = $funcs->CloneBracket($this,$vals);
		if($res !== TRUE)
			$this->Redirect($id,'defaultadmin','',
				array('tmt_message'=>$this->PrettyMessage($res,FALSE)));
	}
}
elseif(isset($params['group'])) //begin grouping process
{
	$smarty->assign('start_form',$this->CreateFormStart($id,'process_items',$returnid));
	$smarty->assign('end_form',$this->CreateFormEnd());
	$smarty->assign('hidden',$this->CreateInputHidden($id,'selitems',implode(';',$params['selitems'])));
	$smarty->assign('title',$this->Lang('title_bracketsgroup'));
	$options = $this->GetGroups();
	if($options)
	{
		foreach($options as &$row)
			$row = $row['name'];
		unset($row);
		$options = array_flip($options);
	}
	else
		$options = array($this->Lang('groupdefault')=>0); //ensure something exists
	$options = array($this->Lang('select_one')=>-1) + $options;
	$smarty->assign('chooser',$this->CreateInputDropdown($id,'togroup',$options,-1,-1));
	$smarty->assign('apply',$this->CreateInputSubmitDefault($id,'apply',$this->Lang('apply')));
	$smarty->assign('cancel',$this->CreateInputSubmit($id,'cancel', $this->Lang('cancel')));
	$smarty->assign('help',''); //$this->Lang('help_bracketsgroup'));
	echo $this->ProcessTemplate('onepage.tpl');
	return;
}
elseif(isset($params['apply'])) //selected group
{
	$gid = (int)$params['togroup']; //new group choice
	if($gid != -1)
	{
		$vals = array_flip(explode(';',$params['selitems'])); //convert strings
		$vals = array_flip($vals);
		$vc = count($vals);
		$fillers = str_repeat('?,',$vc-1).'?';
		$pref = cms_db_prefix();
		$sql = 'UPDATE '.$pref.'module_tmt_brackets SET groupid=? WHERE bracket_id IN ('.$fillers.')';
		array_unshift($vals,$gid);
		$db->Execute($sql,$vals);
	}
}
elseif(isset($params['delete_item'])) //delete selected bracket(s)
{
	if (!$this->CheckAccess('admod'))
		$this->Redirect($id,'defaultadmin','',
			array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

	$vals = array_flip($params['selitems']); //convert strings
	$vals = array_flip($vals);
	if($vals)
	{
		$funcs = new tmtDelete();
		$funcs->DeleteBracket($this,$vals);
	}
}
elseif(isset($params['export']))
{
	$vals = array_flip($params['selitems']); //convert strings
	$vals = array_flip($vals);
	if($vals)
	{
		$funcs = new tmtXML();
		$res = $funcs->ExportXML($this,$params['selitems']);
		if ($res === TRUE)
			exit;
		$this->Redirect($id,'defaultadmin','',
				array('tmt_message'=>$this->PrettyMessage($res,FALSE)));
	}
}

$this->Redirect($id,'defaultadmin');

?>
