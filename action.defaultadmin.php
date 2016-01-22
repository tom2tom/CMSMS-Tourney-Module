<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
$tplvars = array();
//permissions hierarchy
$pdev = $this->CheckPermission('Modify Any Page');
$padm = $this->CheckAccess('admin');
if ($padm)
{
	$pmod = TRUE;
	$pscore = TRUE;
	$pview = TRUE;
	$tplvars['config'] = 1;
	$tplvars['canmod'] = 1;
}
else
{
	$tplvars['config'] = 0;
	$pmod = $this->CheckAccess('modify');
	if ($pmod)
	{
		$pscore = TRUE;
		$pview = TRUE;
		$tplvars['canmod'] = 1;
	}
	else
	{
		$pscore = $this->CheckAccess('score');
		$pview = $this->CheckAccess('adview');
		$tplvars['canmod'] = 0;
	}
}

if (!empty($params['tmt_message']))
	$tplvars['message'] = $params['tmt_message'];

if(isset($params['showtab']))
	$showtab = (int)$params['showtab'];
else
	$showtab = 0; //default
$seetab1 = ($showtab==1);
$seetab2 = ($showtab==2);

$t = ($padm) ? $this->SetTabHeader('configuration',$this->lang('tab_config'),$seetab2) : '';
$tplvars['tab_headers'] = $this->StartTabHeaders().
	$this->SetTabHeader('itmdata',$this->lang('tab_items')).
	$this->SetTabHeader('grpdata',$this->lang('tab_groups'),$seetab1).
	$t.
	$this->EndTabHeaders().$this->StartTabContent();
$tplvars['tab_footers'] = $this->EndTabContent();

//need diversion for 'import_comp' action
$tplvars['start_itemsform'] = $this->CreateFormStart($id, 'process_items', $returnid, 'post','multipart/form-data');
//$tplvars['start_itemsform'] = $this->CreateFormStart($id, 'process_items',$returnid);
$tplvars['end_form'] = $this->CreateFormEnd();

$tplvars['start_items_tab'] = $this->StartTab('itmdata');
$tplvars['end_tab'] = $this->EndTab();

$tplvars['title_name'] = $this->Lang('title_name');
$tplvars['title_tag'] = ($pdev) ? $this->Lang('title_tag'):null;
$tplvars['title_group'] = $this->Lang('title_group');
$tplvars['title_status'] = $this->Lang('title_status');

$gCms = cmsms();
$theme = ($this->before20) ? $gCms->get_variable('admintheme'):
	cms_utils::get_theme_object();

$jsfuncs = array();
$jsincs = array();
$jsloads = array();
$baseurl = $this->GetModuleURLPath();

$comps = array();
$groups = $this->GetGroups();
$selgrp = ($groups && count($groups) > 1);
if($selgrp)
	$inactive = $this->Lang('inactive');
else
	$ungrouped = $this->Lang('groupdefault');
$pref = cms_db_prefix();
$rows = $db->GetAll('SELECT bracket_id,groupid,name,alias FROM '.$pref.'module_tmt_brackets ORDER BY name');
if ($rows)
{
	$sql1 = 'SELECT COUNT(1) as num FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0';
	$sql2 = $sql1.' AND status>='.Tourney::MRES;
	$sql3 = $sql2.' AND teamA>-1 AND teamB>-1';
	$sql4 = $sql1.' AND status!=0 AND status<'.Tourney::ANON;

	if($pmod || $pscore)
		$iconedit = $theme->DisplayImage('icons/system/edit.gif',$this->Lang('edit'),'','','systemicon');
	$iconview = $theme->DisplayImage('icons/system/view.gif',$this->Lang('view'),'','','systemicon');

	if($pmod)
	{
		$iconclone = $theme->DisplayImage('icons/system/copy.gif',$this->Lang('clone'),'','','systemicon');
		$icondel = $theme->DisplayImage('icons/system/delete.gif',$this->Lang('delete'),'','','systemicon delitmlink'); //extra class for confirm-dialog usage
	}
	if ($pmod || $padm)
	{
		$alt = $this->Lang('exportxml');
		$iconexport =
		'<img src="'.$baseurl.'/images/xml.gif" alt="'.$alt.'" title="'.$alt.'" border="0" />';
	}

	foreach ($rows as $bdata)
	{
		$one = new stdClass();
		$thisid = (int)$bdata['bracket_id'];
		$total = $db->GetOne($sql1,array($thisid));
		if (!$total)
			$one->status = $this->Lang('status_notyet');
		else
		{
			$done = $db->GetOne($sql2,array($thisid));
			if ($done < $total)
			{
				$done = $db->GetOne($sql3,array($thisid));
				$pending = $db->GetOne($sql4,array($thisid));
				$mn = ($done == 1) ? $this->Lang('match'):$this->Lang('matches');
				$one->status = $this->Lang('status_going',$done,$mn,$pending);
			}
			else
				$one->status = $this->Lang('status_ended');
		}
		if ($pmod || $pscore)
		{
			$one->name = $this->CreateLink($id,'addedit_comp','',
				$bdata['name'],array('bracket_id'=>$thisid));
			$one->editlink = $this->CreateLink($id,'addedit_comp','',
				$iconedit,array('bracket_id'=>$thisid));
			if ($pdev)
				$one->alias = $bdata['alias']; //info for site-content developers
			if ($pmod)
			{
				$one->copylink = $this->CreateLink($id,'clone_comp','',
					$iconclone,array('bracket_id'=>$thisid));
				$one->deletelink = $this->CreateLink($id,'delete_comp','',
					$icondel,array('bracket_id'=>$thisid)); //$(.delitmlink) modalconfirm
			}
		}
		else //no mod allowed
		{
			$one->name = $bdata['name'];
		}
		if($selgrp)
		{
			$gid = $bdata['groupid'];
			$t = $groups[$gid]['name'];
			if ((int)$groups[$gid]['flags'] & 1 === 0)
				$t .= ':'.$inactive;
			$one->group = $t;
		}
		else
			$one->group = $ungrouped;
		if ($pview || $pscore)
			$one->viewlink = $this->CreateLink($id,'addedit_comp','',
				$iconview,array('bracket_id'=>$thisid,'real_action'=>'view'));
		else
			$one->viewlink = '';

		if ($pmod || $padm)
		{
			$one->exportlink = $this->CreateLink($id, 'export_comp', '',
				$iconexport,
					array('bracket_id'=>$thisid));
		}
		else
			$one->exportlink = '';
		$one->selected = $this->CreateInputCheckbox($id,'selitems[]',$thisid,-1);

		if ((bool)$one) //object isn't empty
			$comps[] = $one;
		else
			unset($one);
	}
}

if ($comps)
{
	$ic = count($comps);
	$tplvars += array(
		'icount' => $ic,
		'comps' => $comps,
		'modname' => $this->GetName(),
		'candev' => $pdev
	);
	if ($ic > 1)
	{
		$jsfuncs[] = <<< EOS
function select_all_items(cb)
{
 $('input[name="{$id}selitems[]"][type="checkbox"]').attr('checked',cb.checked);
}
EOS;
		$t = $this->CreateInputCheckbox($id,'item',TRUE,FALSE,'onclick="select_all_items(this)"');
	}
	else
		$t = '';
	$tplvars['selectall_items'] = $t;

	$tplvars['printbtn'] = $this->CreateInputSubmit($id,'print',$this->Lang('print'),
		'title="'.$this->Lang('printsel_tip').'" onclick="return confirm_selitm_count();"');
	$tplvars['exportbtn'] = $this->CreateInputSubmit($id,'export',$this->Lang('export'),
		'title="'.$this->Lang('exportsel_tip').'" onclick="return confirm_selitm_count();"');
	$jsfuncs[] = <<< EOS
function selitm_count()
{
 var cb = $('input[name="{$id}selitems[]"]:checked');
 return cb.length;
}
function confirm_selitm_count()
{
 return (selitm_count() > 0);
}
EOS;
	if ($pmod)
	{
		$tplvars['notifybtn'] = $this->CreateInputSubmit($id,'notify',$this->Lang('notify'),
			'title="'.$this->Lang('notifysel_tip').'" onclick="return confirm_selitm_count();"');
		if ($selgrp)
			$t = $this->CreateInputSubmit($id,'group',$this->Lang('title_group'),
			'title="'.$this->Lang('groupsel_tip').'" onclick="return confirm_selitm_count();"');
		else
			$t = '';
		$tplvars['groupbtn'] = $t;
		$tplvars['clonebtn'] = $this->CreateInputSubmit($id,'clone',$this->Lang('clone'),
			'title="'.$this->Lang('clonesel_tip').'" onclick="return confirm_selitm_count();"');
		$tplvars['deletebtn'] = $this->CreateInputSubmit($id,'delete_item',$this->Lang('delete'),
			'title="'.$this->Lang('deletesel_tip').'"'); //$(#$id.delete_item) modalconfirm
		//for popup confirmation
		$tplvars['no'] = $this->Lang('no');
		$tplvars['yes'] = $this->Lang('yes');
		$jsincs[] = '<script type="text/javascript" src="'.$baseurl.'/include/jquery.modalconfirm.min.js"></script>';
		$jsloads[] = <<<EOS
 $('.delitmlink').modalconfirm({
  overlayID: 'confirm',
  preShow: function(tg,dlg){
   var name = \$('td:first > a', $(tg).closest('tr')).text(),
    msg;
   if (name) {
    if (name.search(' ') > -1){
     name = '"'+name+'"';
    }
    msg = '{$this->Lang('confirm_delete','%s')}'.replace('%s',name);
   } else {
    msg = '{$this->Lang('confirm')}';
   }
   var para = dlg.children('p:first')[0];
   para.innerHTML = msg;
  }
 });
 $('#{$id}delete_item').modalconfirm({
  overlayID: 'confirm',
  doCheck: confirm_selitm_count,
  preShow: function(tg,dlg){
   var para = dlg.children('p:first')[0];
   para.innerHTML = '{$this->Lang('confirm_delete',$this->Lang('sel_items'))}';
  }
 });
 $('form input[type=text]').keypress(function(e){
  if (e.which == 13){
   $('input[type=submit].default').focus();
   return false;
  }
 });

EOS;
	}
}
else //no tournament
{
	$tplvars['icount'] = 0;
	$tplvars['notourn'] = $this->Lang('no_tourney');
}

if ($pmod)
{
	$tplvars['addlink'] = $this->CreateLink($id,'addedit_comp', '',
		$theme->DisplayImage('icons/system/newobject.gif', $this->Lang('title_add_tourn'),'','','systemicon'));
	$tplvars['addlink2'] = $this->CreateLink($id,'addedit_comp', '',
		$this->Lang('title_add_tourn'));

	$tplvars['title_import'] = $this->Lang('title_import');
	$tplvars['input_import'] = $this->CreateInputFile($id, 'xmlfile', 'text/xml', 25);
	$tplvars['submitxml'] =  $this->CreateInputSubmit($id, 'import', $this->Lang('upload'));
}

$tplvars['start_grps_tab'] = $this->StartTab('grpdata');
$tplvars['start_groupsform'] = $this->CreateFormStart($id, 'process_groups', $returnid);

if(!empty($params['addgroup']))
{
	//setup to append empry row
	if($groups)
	{
		$gc = count($groups);
		$groups = $groups + array(-1 => array('name'=>'','displayorder'=>$gc,'flags'=>1));
	}
	else
		$groups = array(-1 => array('name'=>'','displayorder'=>1,'flags'=>1));
}

if($groups)
{
	if($pmod)
	{
		$mc = 0;
		$previd	= -10;
		$iconup = $theme->DisplayImage('icons/system/arrow-u.gif',$this->Lang('up'),'','','systemicon');
		$icondn = $theme->DisplayImage('icons/system/arrow-d.gif',$this->Lang('down'),'','','systemicon');
		$icondel = $theme->DisplayImage('icons/system/delete.gif',$this->Lang('delete'),'','','systemicon delgrplink'); //extra class for confirm-dialog usage
	}
	else
	{
		$no = $this->Lang('no');
		$yes = $this->Lang('yes');
	}
	$showgrps = array();
	foreach ($groups as $gid=>&$gdata)
	{
		$one = new stdClass();
		$active = ((int)$gdata['flags'] & 1) ? TRUE:FALSE;
		if($pmod)
		{
			$one->name = $this->CreateInputText($id,'group_name[]',$gdata['name'],50,128);
			$one->order = (int)$gdata['displayorder']; //hidden, for DnD
			$one->active = $this->CreateInputCheckbox($id,'group_active[]',$gid,(($active)?$gid:-10));
			$one->downlink = '';
			if ($mc)
			{
				//there's a previous item,create the appropriate links
				$one->uplink = $this->CreateLink($id,'swap_groups',$returnid,
					$iconup,array('group_id'=>$gid,'prev_id'=>$previd));
				$showgrps[($mc-1)]->downlink = $this->CreateLink($id,'swap_groups',$returnid,
					$icondn,array('group_id'=>$previd,'next_id'=>$gid));
			}
			else
				$one->uplink = '';
			$mc++;
			$previd = $gid;
			if ($gid > 0) //preserve the default group
				$one->deletelink = $this->CreateLink($id,'delete_group','',
					$icondel,array('group_id'=>$gid)); //$(.delgrplink) modalconfirm
			else
				$one->deletelink = '';
		}
		else //no mod allowed
		{
			$one->name = $gdata['name'];
			$one->active = ($active) ? $yes : $no;
		}
		$one->selected = $this->CreateInputCheckbox($id,'selgroups[]',$gid,-1);
		$showgrps[] = $one;
	}
	unset($gdata);
	$gc = count($showgrps);
	$tplvars['gcount'] = $gc;
	$tplvars['groups'] = $showgrps;
	if ($gc > 0)
	{
		//buttons
		if ($pmod)
		{
			if($selgrp)
				$t = $this->CreateInputSubmit($id,'delete_group',
					$this->Lang('delete'),
					'title="'.$this->Lang('deleteselgrp').'"'); //$(#$id.delete_group) modalconfirm
			else
				$t = '';
			$tplvars['deletebtn2'] = $t;
			if($selgrp)
				$t = $this->CreateInputSubmit($id,'activate',
					$this->Lang('activate'),
					'title="'.$this->Lang('activeselgrp').'" onclick="return confirm_selgrp_count();"');
			else
				$t = '';
			$tplvars['activebtn2'] = $t;
			$tplvars['cancelbtn2'] = $this->CreateInputSubmit($id,'cancel',
				$this->Lang('cancel'));
			$tplvars['submitbtn2'] = $this->CreateInputSubmit($id,'update',
				$this->Lang('update'),
				'title="'.$this->Lang('updateselgrp').'" onclick="return confirm_selgrp_count();"');
			$jsfuncs[] = <<< EOS
function selgrp_count()
{
 var cb = $('input[name="{$id}selgroups[]"]:checked');
 return cb.length;
}
function confirm_selgrp_count()
{
 return (selgrp_count() > 0);
}
EOS;
		}
	}

	if ($gc > 1)
	{
		if ($pmod)
		{
			//setup some ajax-parameters - partial data for tableDnD::onDrop
			$url = $this->CreateLink($id,'order_groups',NULL,NULL,array('neworders'=>''),NULL,TRUE);
			$offs = strpos($url,'?mact=');
			$ajfirst = str_replace('amp;','',substr($url,$offs+1));
			$jsfuncs[] = <<< EOS
function select_all_groups(cb)
{
 $('input[name="{$id}selgroups[]"][type="checkbox"]').attr('checked',cb.checked);
}
function ajaxData(droprow,dropcount)
{
 var orders = [];
 $(droprow.parentNode).find('tr td.ord').each(function(){
  orders[orders.length] = this.innerHTML;
 });
 var ajaxdata = '$ajfirst'+orders.join();
 return ajaxdata;
}
function dropresponse(data,status)
{
 if(status == 'success' && data) {
  var i = 1;
  $('#groups').find('.ord').each(function(){\$(this).html(i++);});
  var name;
  var oddclass = 'row1';
  var evenclass = 'row2';
  i = true;
  $('#groups').trigger('update').find('tbody tr').each(function() {
	name = i ? oddclass : evenclass;
	\$(this).removeClass().addClass(name);
	i = !i;
  });
 } else {
  $('#page_tabs').prepend('<p style="font-weight:bold;color:red;">{$this->Lang('err_ajax')}!</p><br />');
 }
}
$(document).ready(function(){
 $('#groups').addClass('table_drag').tableDnD({
	dragClass: 'row1hover',
	onDrop: function(table, droprows) {
		var odd = true;
		var oddclass = 'row1';
		var evenclass = 'row2';
		var droprow = $(droprows)[0];
		$(table).find('tbody tr').each(function() {
			var name = odd ? oddclass : evenclass;
			if (this === droprow) {
				name = name+'hover';
			}
			$(this).removeClass().addClass(name);
			odd = !odd;
		});
		if (typeof ajaxData !== 'undefined' && $.isFunction(ajaxData)) {
			var ajaxdata = ajaxData(droprow,droprows.length);
			if (ajaxdata) {
				$.ajax({
				 url: 'moduleinterface.php',
				 type: 'POST',
				 data: ajaxdata,
				 dataType: 'text',
				 success: dropresponse
				});
			}
		}
	}
 }).find('tbody tr').removeAttr('onmouseover').removeAttr('onmouseout').mouseover(function() {
		var now = $(this).attr('class');
		$(this).attr('class', now+'hover');
 }).mouseout(function() {
		var now = $(this).attr('class');
		var to = now.indexOf('hover');
		$(this).attr('class', now.substring(0,to));
 });
 $('.updown').hide();
 $('.dndhelp').css('display','block');
 $('.delgrplink').modalconfirm({
  overlayID: 'confirm',
  preShow: function(tg,dlg){
   var name = \$('td > input:text', $(tg).closest('tr')).val(),
    msg;
   if (name) {
    if (name.search(' ') > -1){
     name = '"'+name+'"';
    }
    msg = '{$this->Lang('confirm_delete','%s')}'.replace('%s',name);
   } else {
    msg = '{$this->Lang('confirm')}';
   }
   var para = dlg.children('p:first')[0];
   para.innerHTML = msg;
  }
 });
 $('#{$id}delete_group').modalconfirm({
  overlayID: 'confirm',
  doCheck: confirm_selgrp_count,
  preShow: function(tg,dlg){
   var para = dlg.children('p:first')[0];
   para.innerHTML = '{$this->Lang('confirm_delete',$this->Lang('sel_groups'))}';
  }
 });
});
EOS;
			$jsincs[] = '<script type="text/javascript" src="'.$baseurl.'/include/jquery.tablednd.min.js"></script>';
			$tplvars['dndhelp'] = $this->Lang('help_dnd');
			$tplvars['sortbtn2'] = $this->CreateInputSubmit($id,'sort',
				$this->Lang('sort'));
		}
		$t = $this->Lang('title_move');
		$cb = $this->CreateInputCheckbox($id,'group',TRUE,FALSE,'onclick="select_all_groups(this)"');
	}
	else
	{
		if ($pmod)
			$tplvars['sortbtn2'] = '';
		$t = '';
		$cb = '';
	}
	$tplvars['title_gname'] = $this->Lang('title_name');
	$tplvars['title_active'] = $this->Lang('title_active');
	$tplvars['title_move'] = $t;
	$tplvars['selectall_groups'] = $cb;
}
else //no group
{
	$tplvars['nogroups'] = $this->Lang('no_groups');
	$tplvars['gcount'] = 0;
}

if ($padm)
{
	$tplvars['addgrplink'] = $this->CreateLink($id,'addgroup',$returnid,
		$theme->DisplayImage('icons/system/newobject.gif',$this->Lang('addgroup'),'','','systemicon'),
			array(),'',false,false,'')
		.' '.
		$this->CreateLink($id,'addgroup',$returnid,
			$this->Lang('addgroup'),
			array(),'',false,false,'class="pageoptions"');
}

if ($padm)
{
	$tplvars['start_config_tab'] = $this->StartTab('configuration');
	$tplvars['start_configform'] = $this->CreateFormStart($id, 'save_config', $returnid);

	$tplvars['title_names_fieldset'] =  $this->Lang('title_names_fieldset');
	$tplvars['title_misc_fieldset'] =  $this->Lang('title_misc_fieldset');

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

	$tplvars['names'] = $names;

	$misc = array();
//	tmtUtils()?
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
	if(class_exists('Notifier',FALSE))
	{
		$misc[] = array($this->Lang('title_phone_regex'),
			$this->CreateInputText($id, 'tmt_phoneid', $this->GetPreference('phone_regex'), 30),
			$this->Lang('help_phone_regex'));
	}
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
		$tplvars['hidden'] =  $this->CreateInputHidden($id,'tmt_export_encoding', $expchars);

	$utils = new tmtUtils(); //TODO other uses

	$pw = $this->GetPreference('masterpass');
	if($pw)
		$pw = $utils->unfusc($pw);
	$misc[] = array($this->Lang('title_password'),
		$this->CreateTextArea(false,$id,$pw,'tmt_masterpass','cloaked',
			$id.'tmt_passwd','','',40,2));
	$jsincs[] = '<script type="text/javascript" src="'.$baseurl.'/include/jquery-inputCloak.min.js"></script>';
	$jsloads[] =<<<EOS
 $('#{$id}tmt_passwd').inputCloak({
  type:'see4',
  symbol:'\u2022'
 });

EOS;

	$tplvars['misc'] = $misc;

	$tplvars['save'] =
		$this->CreateInputSubmitDefault($id, 'submit', $this->Lang('save'));
	$tplvars['cancel'] =
		$this->CreateInputSubmit($id, 'cancel', $this->Lang('cancel'));
}
else
{
	$tplvars['canconfig'] = 0;
}

if($jsloads)
{
	$jsfuncs[] = '
$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;

tmtTemplate::Process($this,'adminpanel.tpl',$tplvars);

?>
