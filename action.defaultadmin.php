<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
//permissions hierarchy
$pdev = $this->CheckPermission('Modify Any Page');
$padm = $this->CheckAccess('admin');
if ($padm)
{
	$pmod = TRUE;
	$pscore = TRUE;
	$pview = TRUE;
}
else
{
	$pmod = $this->CheckAccess('modify');
	if ($pmod)
	{
		$pscore = TRUE;
		$pview = TRUE;
	}
	else
	{
		$pscore = $this->CheckAccess('score');
		$pview = $this->CheckAccess('adview');
	}
}

if (isset($params['tmt_message']) && $params['tmt_message'] != '')
	$smarty->assign('message',$params['tmt_message']);

if ($padm)
{
	$smarty->assign('tab_headers',$this->starttabheaders().
		$this->settabheader('compdata',$this->lang('tab_items')).
		$this->settabheader('configuration',$this->lang('tab_config')).
		$this->endtabheaders().$this->starttabcontent());
	$smarty->assign('start_configuration_tab',$this->StartTab('configuration'));
}
else
{
	$smarty->assign('tab_headers',$this->StartTabHeaders().
		$this->SetTabHeader('compdata',$this->Lang('tab_items')).
		$this->EndTabHeaders().$this->StartTabContent());
}

$smarty->assign('start_main_tab',$this->StartTab('compdata'));
$smarty->assign('end_tab',$this->EndTab());
$smarty->assign('tab_footers',$this->EndTabContent());

$smarty->assign('title_name',$this->Lang('title_name'));

$t = ($pdev) ? $this->Lang('title_tag'):null;
$smarty->assign('title_tag',$t);

$smarty->assign('title_status',$this->Lang('title_status'));

$gCms = cmsms();
$theme = $gCms->variables['admintheme'];

$comps = array();
$jsfuncs = array();
$jsincudes = array();

$pref = cms_db_prefix();
$rows = $db->GetAll('SELECT bracket_id,name,alias FROM '.$pref.'module_tmt_brackets ORDER BY name');
if ($rows)
{
	$sql1 = 'SELECT COUNT(1) as num FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
	$sql2 = $sql1.' AND status>='.MRES;
	$sql3 = $sql1.' AND status!=0 AND status<'.ANON;
	$currow = 'row1';
	
	if($pmod || $pscore)
		$iconedit = $theme->DisplayImage('icons/system/edit.gif',$this->Lang('edit'),'','','systemicon');
	$iconview = $theme->DisplayImage('icons/system/view.gif',$this->Lang('view'),'','','systemicon');

	if($pmod)
	{
		$iconclone = $theme->DisplayImage('icons/system/copy.gif',$this->Lang('clone'),'','','systemicon');
		$icondel = $theme->DisplayImage('icons/system/delete.gif',$this->Lang('delete'),'','','systemicon');
	}
	if ($pmod || $padm)
	{
		$alt = $this->Lang('exportxml');
		$iconexport =
		'<img src="'.$this->GetModuleURLPath().'/images/xml.gif" alt="'.$alt.'" title="'.$alt.'" border="0" />';
	}
	
	foreach ($rows as $bdata)
	{
		$oneset = new stdClass();

		$total = $db->GetOne($sql1,array($bdata['bracket_id']));
		if (!$total)
			$oneset->status = $this->Lang('status_notyet');
		else
		{
			$done = $db->GetOne($sql2,array($bdata['bracket_id']));
			if ($done < $total)
			{
				$pending = $db->GetOne($sql3,array($bdata['bracket_id']));
				$mn = ($done == 1) ? $this->Lang('match'):$this->Lang('matches');
				$oneset->status = $this->Lang('status_going',$done,$mn,$pending);
			}
			else
				$oneset->status = $this->Lang('status_ended');
		}
		if ($pmod || $pscore)
		{
			$oneset->name = $this->CreateLink($id, 'addedit_comp', '',
				$bdata['name'], array('bracket_id'=>$bdata['bracket_id']));
			$oneset->editlink = $this->CreateLink($id, 'addedit_comp', '',
				$iconedit,
					array('bracket_id'=>$bdata['bracket_id']));
			if ($pdev)
				$oneset->alias = $bdata['alias']; //info for site-content developers
			if ($pmod)
			{
			$oneset->copylink = $this->CreateLink($id, 'clone_comp', '',
				$iconclone,
					array('bracket_id'=>$bdata['bracket_id']));
			$oneset->deletelink = $this->CreateLink($id, 'delete_comp', '',
				$icondel,
					array('bracket_id'=>$bdata['bracket_id']),'',false,false,
					'class="'.$id.'delete_comp"'); //confirmation by modalconfirm dialog
			}
		}
		else //no mod allowed
		{
			$oneset->name = $bdata['name'];
		}
		if ($pview || $pscore)
				$oneset->viewlink = $this->CreateLink($id, 'addedit_comp', '',
					$iconview,
						array('bracket_id'=>$bdata['bracket_id'],
						'real_action'=>'view'));
		else
			$oneset->viewlink = '';

		if ($pmod || $padm)
		{
			$oneset->exportlink = $this->CreateLink($id, 'export_comp', '',
				$iconexport,
					array('bracket_id'=>$bdata['bracket_id']));
		}
		else
			$oneset->exportlink = '';

		if ((bool)$oneset) //object isn't empty
		{
			$oneset->rowclass = $currow;
			$comps[] = $oneset;
			($currow == 'row1'?$currow='row2':$currow='row1');
		}
		else
			unset($oneset);
	}
}

if ($comps)
{
	$smarty->assign('count',count($comps));
	$smarty->assign('comps',$comps);
	$smarty->assign('modname',$this->GetName());
	$smarty->assign('candev',$pdev);
	if ($pmod)
	{
		//for popup confirmation
		$smarty->assign('no',$this->Lang('no'));
		$smarty->assign('yes',$this->Lang('yes'));
		$jsincudes[] = '<script type="text/javascript" src="'.$this->GetModuleURLPath().'/include/jquery.modalconfirm.js"></script>';
		$jsfuncs[] = <<< EOS
$(document).ready(function() {
 $('.{$id}delete_comp').modalconfirm({
  overlayID: 'confirm',
  preShow: function(d){
   var name = \$('td:first > a', $(this).closest('tr')).text();
   if (name.search(' ') > -1)
    name = '"'+name+'"';
   var para = d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('confirm_delete','%s')}'.replace('%s',name);
  }
 });
});
EOS;
	}
}
else
{
	$smarty->assign('notourn',$this->Lang('no_tourney'));
}

if ($pmod)
{
	$smarty->assign('addlink',$this->CreateLink($id,'addedit_comp', '',
		$theme->DisplayImage('icons/system/newobject.gif', $this->Lang('title_add_tourn'),'','','systemicon')));
	$smarty->assign('addlink2',$this->CreateLink($id,'addedit_comp', '',
		$this->Lang('title_add_tourn')));

	$smarty->assign('start_importform',$this->CreateFormStart($id, 'import_comp', $returnid, 'post','multipart/form-data'));
	$smarty->assign('end_importform',$this->CreateFormEnd());
	$smarty->assign('title_import',$this->Lang('title_import'));
	$smarty->assign('input_import',$this->CreateInputFile($id, 'xmlfile', 'text/xml', 25));
	$smarty->assign('submitxml', $this->CreateInputSubmit($id, 'tmt_import', $this->Lang('upload')));
}

if ($padm)
{
	$smarty->assign('canconfig',1);

	$smarty->assign('start_configform',$this->CreateFormStart($id, 'save_config', $returnid));
	$smarty->assign('end_configform',$this->CreateFormEnd());

	$smarty->assign('title_names_fieldset', $this->Lang('title_names_fieldset'));
	$smarty->assign('title_misc_fieldset', $this->Lang('title_misc_fieldset'));

	$names = array();
	$names[] = array($this->Lang('title_final'),
		$this->CreateInputText($id, 'tmt_final', $this->GetPreference('last_match_name'), 20));
	$names[] = array($this->Lang('title_semi'),
		$this->CreateInputText($id, 'tmt_semi', $this->GetPreference('2ndlast_match_name'), 20));
	$names[] = array($this->Lang('title_quarter'),
		$this->CreateInputText($id, 'tmt_quarter', $this->GetPreference('3rdlast_match_name'), 20));
	$names[] = array($this->Lang('title_eighth'),
		$this->CreateInputText($id, 'tmt_eighth', $this->GetPreference('4thlast_match_name'), 20));
	$names[] = array($this->Lang('title_roundname'),
		$this->CreateInputText($id, 'tmt_roundname', $this->GetPreference('other_match_name'), 20),
		$this->Lang('help_match_names'));
	$names[] = array($this->Lang('title_against'),
		$this->CreateInputText($id, 'tmt_versus', $this->GetPreference('against_name'), 20));
	$names[] = array($this->Lang('title_noop'),
		$this->CreateInputText($id, 'tmt_bye', $this->GetPreference('noop_name'), 20));
	$names[] = array($this->Lang('title_forfeit'),
		$this->CreateInputText($id, 'tmt_forfeit', $this->GetPreference('forfeit_name'), 20));
	$names[] = array($this->Lang('title_abandoned'),
		$this->CreateInputText($id, 'tmt_nomatch', $this->GetPreference('abandon_name'), 20));

	$smarty->assign('names',$names);

	$misc = array();
	$allzones = $this->GetTimeZones();
	$misc[] = array($this->Lang('title_zone'),
		$this->CreateInputDropdown($id, 'tmt_timezone', $allzones, '', $this->GetPreference('time_zone')),
		$this->Lang('help_zone'));
	$misc[] = array($this->Lang('title_date_format'),
		$this->CreateInputText($id, 'tmt_date_format', $this->GetPreference('date_format'), 20),
		$this->Lang('help_date_format'));
	$misc[] = array($this->Lang('title_time_format'),
		$this->CreateInputText($id, 'tmt_time_format', $this->GetPreference('time_format'), 20),
		$this->Lang('help_time_format'));
	$misc[] = array($this->Lang('title_uploads_dir'),
		$this->CreateInputText($id, 'tmt_uploads_dir', $this->GetPreference('uploads_dir',''), 30),
		$this->Lang('help_uploads_dir'));
/*	$misc[] = array($this->Lang('title_strip_on_export'),
		$this->CreateInputHidden($id,'tmt_strip_on_export','0').
		$this->CreateInputCheckbox($id, 'tmt_strip_on_export', '1', $this->GetPreference('strip_on_export','0')),
		$this->Lang('help_strip_on_export'));
	$misc[] = array($this->Lang('title_export_file'),
		$this->CreateInputHidden($id,'tmt_export_file','0').
		$this->CreateInputCheckbox($id, 'tmt_export_file', '1', $this->GetPreference('export_file','0')),
		$this->Lang('help_export_file'));
*/
	$expchars = $this->GetPreference('export_encoding','UTF-8');
	if (ini_get('mbstring.internal_encoding') !== FALSE) //PHP's encoding-conversion capability is installed
	{
		$encodings = array('utf-8'=>'UTF-8','windows-1252'=>'Windows-1252', 'iso-8859-1'=>'ISO-8859-1');
		$misc[] = array($this->Lang('title_export_file_encoding'),
			$this->CreateInputRadioGroup($id, 'tmt_export_encoding', $encodings, $expchars, '', ' '));
	}
	else
		$smarty->assign('hidden', $this->CreateInputHidden($id,'tmt_export_encoding', $expchars));

	$smarty->assign('misc',$misc);

	$smarty->assign('save',
		$this->CreateInputSubmit($id, 'tmt_submit', $this->Lang('save')));
	$smarty->assign('cancel',
		$this->CreateInputSubmit($id, 'tmt_cancel', $this->Lang('cancel')));
}
else
{
	$smarty->assign('canconfig',0);
}

if ($jsfuncs)
{
	$smarty->assign('jsincs',$jsincudes);
	$smarty->assign('jsfuncs',$jsfuncs);
}

echo $this->ProcessTemplate('adminpanel.tpl');

?>
