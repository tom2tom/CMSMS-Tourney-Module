<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

class tmtEditSetup
{
	private $any;
	private $committed;
	private $spare;

	/**
	GetMatchExists:
	@bracket_id: bracket being processed
	Check for existence of any match for the bracket, and any 'locked in' match
	Sets self::any, self::committed
	See also: tmtSchedule::MatchCommitted() which is similar to this function
	Returns: Nothing
	*/
	function MatchExists($bracket_id)
	{
		$this->any = FALSE;
		$this->committed = FALSE;
		$pref = cms_db_prefix();
		$sql = 'SELECT 1 AS yes FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0';
		$db = cmsms()->GetDb();
		$rs = $db->SelectLimit($sql,1,-1,array($bracket_id));
		if($rs && !$rs->EOF) //any match exists
		{
			$rs->Close();
			$this->any = TRUE;
			$sql = 'SELECT 1 AS yes FROM '.$pref.
			'module_tmt_matches WHERE bracket_id=? AND status>='.Tourney::MRES.
			' AND teamA IS NOT NULL AND teamA!=-1 AND teamB IS NOT NULL AND teamB!=-1';
			$rs = $db->SelectLimit($sql,1,-1,array($bracket_id));
			if($rs && !$rs->EOF) //match(es) other than byes recorded
				$this->committed = TRUE;
			else
			{
				if($rs) $rs->Close();
				$sql = 'SELECT timezone FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
				$zone = $db->GetOne($sql,array($bracket_id));
				$dt = new DateTime('+'.Tourney::LEADHOURS.' hours',new DateTimeZone($zone));
				$sql = 'SELECT 1 AS yes FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0 AND status IN('.Tourney::FIRM.','.Tourney::TOLD.','.Tourney::ASKED.
				') AND playwhen IS NOT NULL AND playwhen < '.$dt->format('Y-m-d G:i:s').
				' AND teamA IS NOT NULL AND teamA != -1 AND teamB IS NOT NULL AND teamB != -1';
				$rs = $db->SelectLimit($sql,1,-1,array($bracket_id));
				if($rs && !$rs->EOF) //FIRM/TOLD/ASKED match(es) scheduled before min. leadtime from now
					$this->committed = TRUE;
			}
		}
		if($rs)
			$rs->Close();
	}

	/**
	SpareSlot:
	@bracket_id:
	$elim: boolean TRUE for KO/DE matches, FALSE for RR, default TRUE
	Check whether a suitable place is available for a team to be added to the bracket
	*/
	function SpareSlot($bracket_id,$elim=TRUE)
	{
		$this->spare = FALSE;
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		if($elim)
		{
			//for KO/DE bracket - 1st-round matches with a bye and corresponding 2nd round match not committed
			$sql = 'SELECT COUNT(1) AS num FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0 AND status >= '.Tourney::FIRM.' AND NOT (teamA = -1 OR teamB = -1)';
			$fc = $db->GetOne($sql,array($bracket_id));
			if(!$fc)
			{
				$this->spare = TRUE;
				return;
			}
			$sql = 'SELECT match_id,nextm FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0
AND (teamA = -1 OR teamB = -1)
AND match_id NOT IN (SELECT DISTINCT nextm FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0 AND nextm IS NOT NULL)';
			$maybe = $db->GetAssoc($sql,array($bracket_id,$bracket_id));
			if($maybe)
			{
				$sql = 'SELECT status FROM '.$pref.'module_tmt_matches WHERE match_id=? AND flags=0';
				foreach($maybe as $next)
				{
					$nstat = $db->GetOne($sql,array($next));
					if($nstat !== FALSE && $nstat < Tourney::FIRM)
					{
						$this->spare = TRUE;
						return;
					}
				}
			}
		}
		else
		{
			//for RR bracket - relatively few matches played
			$sql = 'SELECT COUNT(1) AS num FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2';
			$tc = $db->GetOne($sql,array($bracket_id));
			$sql = 'SELECT COUNT(1) AS num FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0 AND status >= '.Tourney::FIRM;
			$mc = $db->GetOne($sql,array($bracket_id));
			$this->spare = ($tc <= 5)?($tc >= $mc) :($tc >= $mc * 2);
		}
	}

	/**
	IntervalNames:
	Get one, or array of, translated time-interval-name(s).
	If @cap is TRUE, this uses ucfirst() which expects PHP locale value to be correspond to the translation of names
	@mod: reference to current module-object
	@which: index 0 (for 'none'), 1 (for 'minute') .. 6 (for 'year'), or array of
		such indices consistent with TimeIntervals() + 1
	@plural: optional, whether to get plural form of the interval name(s), default FALSE
	@cap: optional, whether to capitalise the first character of the name(s), default FALSE
	*/
	function IntervalNames(&$mod, $which, $plural=FALSE, $cap=FALSE)
	{
		$k = ($plural) ? 'multiperiods' : 'periods';
		$all = explode(',',$mod->Lang($k));
		array_unshift($all,$mod->Lang('none'));
		$c = count($all);

		if (!is_array($which)) {
			if ($which >= 0 && $which < $c) {
				if ($cap)
					return mb_convert_case($all[$which],MB_CASE_TITLE); //TODO CHECK encoding string
				else
					return $all[$which];
			}
			return '';
		}
		$ret = array();
		foreach ($which as $period) {
			if ($period >= 0 && $period < $c) {
				$ret[$period] = ($cap) ?
					mb_convert_case($all[$period],MB_CASE_TITLE):
					$all[$period];
			}
		}
		return $ret;
	}

	function Setup(&$mod,&$tplvars,&$jsfuncs,&$jsloads,&$jsincs,&$data,
		$id,$returnid,$activetab='',$message='')
	{
		$gCms = cmsms();
		$config = $gCms->GetConfig();

		if (isset($data->readonly))
		{
			unset($data->readonly);
			$pmod = FALSE;
			$pscore = FALSE;
		}
		else
		{
			$pmod = $mod->CheckAccess('admod');
			$pscore = $pmod || $mod->CheckAccess('score');
		}

		$tplvars['canmod'] = $pmod ? 1:0;
//		$tplvars['canscore'] = $pscore ? 1:0;

		if(!empty($message)) $tplvars['message'] = $message;

		if($activetab == FALSE)
			$activetab = 'maintab';
		$tplvars['tabs_start'] = $mod->StartTabHeaders().
			$mod->SetTabHeader('maintab',$mod->Lang('tab_main'),($activetab == 'maintab')).
			$mod->SetTabHeader('scheduletab',$mod->Lang('tab_schedule'),($activetab == 'scheduletab')).
			$mod->SetTabHeader('advancedtab',$mod->Lang('tab_advanced'),($activetab == 'advancedtab')).
			$mod->SetTabHeader('charttab',$mod->Lang('tab_chart'),($activetab == 'charttab')).
			$mod->SetTabHeader('playerstab',$mod->Lang('tab_players'),($activetab =='playerstab')).
			$mod->SetTabHeader('matchestab',$mod->Lang('tab_matches'),($activetab =='matchestab')).
			$mod->SetTabHeader('resultstab',$mod->Lang('tab_results'),($activetab =='resultstab')).
//			$mod->SetTabHeader('historytab',$mod->Lang('tab_history'),($activetab =='historytab')).
			$mod->EndTabHeaders() . $mod->StartTabContent();
		//workaround CMSMS2 crap 'auto-end', EndTab() & EndTabContent() before [1st] StartTab()
		$tplvars += array(
			'form_start' => $mod->CreateFormStart($id,'addedit_comp',$returnid),
			'form_end' => $mod->CreateFormEnd(),
			'tab_end' => $mod->EndTab(),
			'tabs_end' => $mod->EndTabContent(),
			'maintab_start' => $mod->StartTab('maintab'),
			'scheduletab_start' => $mod->StartTab('scheduletab'),
			'advancedtab_start' => $mod->StartTab('advancedtab'),
			'charttab_start' => $mod->StartTab('charttab'),
			'playertab_start' => $mod->StartTab('playerstab'),
			'matchtab_start' => $mod->StartTab('matchestab'),
			'resultstab_start' => $mod->StartTab('resultstab')
//			'historytab_start' => $mod->StartTab('historytab')
		);
		//accumulator for hidden items,to be parked on page
		$hidden = $mod->CreateInputHidden($id,'bracket_id',$data->bracket_id);
		if(!empty($data->added))
			$hidden .= $mod->CreateInputHidden($id,'newbracket',$data->bracket_id);
		$hidden .= $mod->CreateInputHidden($id,'active_tab').
			$mod->CreateInputHidden($id,'real_action');
		//script accumulators
		$jsfuncs = array();
		$jsloads = array();
		$jsincs = array();
		$baseurl = $mod->GetModuleURLPath();

		$jsincs[] = '<script type="text/javascript" src="'.$baseurl.'/lib/js/jquery.tmtfuncs.js"></script>';

		if($pmod)
		{
			//setup some ajax-parameters - partial data for tableDnD::onDrop
			$url = $mod->CreateLink($id,'move_team',NULL,NULL,array('bracket_id'=>$data->bracket_id,'neworders'=>''),NULL,TRUE);
			$offs = strpos($url,'?mact=');
			$ajfirst = str_replace('amp;','',substr($url,$offs+1));
			$jsfuncs[] = <<< EOS
function ajaxData(droprow,dropcount) {
 var orders = [];
 $(droprow.parentNode).find('tr td.ord').each(function(){
  orders[orders.length] = this.innerHTML;
 });
 var ajaxdata = '$ajfirst'+orders.join();
 return ajaxdata;
}
function dropresponse(data,status) {
 if(status == 'success' && data) {
  var i = 1;
  $('#tmt_players').find('.ord').each(function(){\$(this).html(i++);});
  var name;
  var oddclass = 'row1';
  var evenclass = 'row2';
  i = true;
  $('#tmt_players').trigger('update').find('tbody tr').each(function() {
   name = i ? oddclass : evenclass;
   $(this).removeClass().addClass(name);
   i = !i;
  });
 } else {
  $('#page_tabs').prepend('<p style="font-weight:bold;color:red;">{$mod->Lang('err_ajax')}!</p><br />');
 }
}
EOS;
			$onsort = <<< EOS
function () {
 var orders = [];
 $(this).find('tbody tr td.ord').each(function(){
  orders[orders.length] = this.innerHTML;
 });
 var ajaxdata = '$ajfirst'+orders.join();
 $.ajax({
  url: 'moduleinterface.php',
  type: 'POST',
  data: ajaxdata,
  dataType: 'text',
  success: function (data,status) {
   if(status == 'success' && data) {
    var i = 1;
    $('#tmt_players').find('.ord').each(function(){
     $(this).html(i++);
    });
   } else {
    $('#page_tabs').prepend('<p style="font-weight:bold;color:red;">{$mod->Lang('err_ajax')}!</p><br />');
   }
  }
 });
}
EOS;
		}
		else
			$onsort = 'null'; //no sort-processing if no mods allowed

		$jsloads[] = <<< EOS
 $.fn.SSsort.addParser({
  id: 'neglastinput',
  is: function(s,node) {
   var n = node.childNodes[0];
   if(n && n.nodeName.toLowerCase() == 'input' && n.type.toLowerCase() == 'text') {
     var v = n.value;
     return (!isNaN(parseFloat(v)) && isFinite(v));
   } else {
    return false;
   }
  },
  format: function(s,node) {
   var v = node.childNodes[0].value;
   if (v) {
    var n = Number(v);
    if (isNaN(n) || n==0) {return 0;}
    return (n>0) ? n:n+10000;
   } else if ((v+'').length > 0) {
    return 0;
   } else {
    return 9000;
   }
  },
  watch: true,
  type: 'numeric'
 });
 $.fn.SSsort.addParser({
  id: 'isoinput',
  is: function(s,node) {
   var n = node.childNodes[0];
   if(n && n.nodeName.toLowerCase() == 'input' && n.type.toLowerCase() == 'text') {
    p = /^[12]\d{3}[\/-][01]\d[\/-]\[0-3]\d +([01]\d|2[0-3])( *: *[0-5]\d){1,2}/;
    return p.test($.trim(n.value));
   } else {
    return false;
   }
  },
  format: function(s,node) {
   return Date.parse(node.childNodes[0].value);
  },
  watch: true,
  type: 'numeric'
 });
 $.fn.SSsort.addParser({
  id: 'textinput',
  is: function(s,node) {
   var n = node.childNodes[0];
   return (n && n.nodeName.toLowerCase() == 'input' && n.type.toLowerCase() == 'text');
  },
  format: function(s,node) {
   var t = node.childNodes[0].value;
   return $.trim(t);
  },
  watch: true,
  type: 'text'
 });

 var opts = {
  sortClass: 'SortAble',
  ascClass: 'SortUp',
  descClass: 'SortDown',
  oddClass: 'row1',
  evenClass: 'row2',
  oddsortClass: 'row1s',
  evensortClass: 'row2s',
  onSorted: {$onsort}
 };
 $('#tmt_players').addClass('table_sort').SSsort(opts);
 delete opts.onSorted;
 $('#tmt_matches').addClass('table_sort').SSsort(opts);
 $('#tmt_results').addClass('table_sort').SSsort(opts);
 $('.tem_name,.tem_seed,.mat_playwhen,.mat_playwhere,.res_playwhen').blur(function (ev) {
  \$(this).closest('table').trigger('update');
 });
EOS;
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.SSsort.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.alertable.min.js"></script>
EOS;

	$jsfuncs[] = <<< EOS
function eventCancel(ev) {
 if(!ev) {
  if(window.event) ev = window.event;
  else return;
 }
 if(ev.cancelBubble !== null) ev.cancelBubble = true;
 if(ev.stopPropagation) ev.stopPropagation();
 if(ev.preventDefault) ev.preventDefault();
 if(window.event) ev.returnValue = false;
 if(ev.cancel !== null) ev.cancel = true;
}
function set_action(btn) {
 $('#{$id}real_action').val(btn.name);
}
function set_tab() {
 var active = $('#page_tabs > .active');
 $('#{$id}active_tab').val(active.attr('id'));
}
function set_params(btn) {
 set_action(btn);
 set_tab();
}
EOS;

		$this->MatchExists($data->bracket_id);
		$funcs = new tmtData();

		$theme = ($mod->before20) ? cmsms()->get_variable('admintheme'):
			cms_utils::get_theme_object();
		$iconinfo = $theme->DisplayImage('icons/system/info.gif',$mod->Lang('showhelp'),'','','systemicon tipper');
		$tplvars['showtip'] = $iconinfo;

//========= MAIN OPTIONS ==========
		$main = array();
		$main[] = array(
			$mod->Lang('title_title'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_name',$data->name,50) : $data->name
		);
		$main[] = array(
			$mod->Lang('title_desc'),
			($pmod) ?
			$mod->CreateTextArea(TRUE,$id,$data->description,'tmt_description','tallarea','','','',65,8,'','','style="height:6em;"') :
			$data->description
		);
		$main[] = array(
			$mod->Lang('title_alias'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_alias',$data->alias,30) : $data->alias,
			$mod->Lang('help_alias')
		);
		$options = $mod->GetGroups();
		if($options)
		{
			foreach($options as &$row)
				$row = $row['name'];
			unset($row);
			$options = array_flip($options);
		}
		else
			$options = array($mod->Lang('groupdefault')=>0); //ensure something exists
		$main[] = array(
			$mod->Lang('title_group'),
			($pmod && !$this->committed) ?
			$mod->CreateInputDropdown($id,'tmt_groupid',$options,'',$data->groupid) :
			array_search($data->groupid,$options,TRUE).(($pmod)?$mod->CreateInputHidden($id,'tmt_groupid',$data->groupid):''),
			$mod->Lang('help_group')
		);
		$options = $funcs->GetTypeNames($mod);
		$main[] = array(
			$mod->Lang('title_type'),
			($pmod && !$this->committed) ?
			$mod->CreateInputDropdown($id,'tmt_type',$options,'',$data->type) :
			array_search($data->type,$options,TRUE).(($pmod)?$mod->CreateInputHidden($id,'tmt_type',$data->type):'')
		);
		$main[] = array(
			$mod->Lang('title_zone'),
			($pmod && !$this->committed) ?
//	tmtUtils()?
			$mod->CreateInputDropdown($id,'tmt_timezone',$mod->GetTimeZones(),'',$data->timezone) :
			$data->timezone.(($pmod)?$mod->CreateInputHidden($id,'tmt_timezone',$data->timezone):''),
			$mod->Lang('help_zone2')
		);
		$main[] = array(
			$mod->Lang('title_teamsize'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_teamsize',$data->teamsize,2,2) : $data->teamsize
		);
		$options = array(
			$mod->Lang('seed_none')=>0,
			$mod->Lang('seed_toponly')=>1,
			$mod->Lang('seed_balanced')=>2,
			$mod->Lang('seed_unbalanced')=>3,
			$mod->Lang('seed_randbalance')=>4
		);
		$main[] = array(
			$mod->Lang('title_seedtype'),
			($pmod && !$this->committed) ?
			$mod->CreateInputDropdown($id,'tmt_seedtype',$options,'',$data->seedtype):
			array_search($data->seedtype,$options).(($pmod)?$mod->CreateInputHidden($id,'tmt_seedtype',$data->seedtype):''),
			$mod->Lang('help_seedtype')
		);
		$options = array(
			$mod->Lang('fix_none')=>0,
			$mod->Lang('fix_adjacent')=>1,
			$mod->Lang('fix_balanced')=>2,
			$mod->Lang('fix_unbalanced')=>3
		);
		$main[] = array(
			$mod->Lang('title_fixtype'),
			($pmod && !$this->committed) ?
			$mod->CreateInputDropdown($id,'tmt_fixtype',$options,'',$data->fixtype):
			array_search($data->fixtype,$options).(($pmod)?$mod->CreateInputHidden($id,'tmt_fixtype',$data->fixtype):''),
			$mod->Lang('help_fixtype')
		);
		$main[] = array(
			$mod->Lang('title_owner'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_owner',$data->owner,50) : $data->owner,
			$mod->Lang('help_owner')
		);
		$main[] = array(
			$mod->Lang('title_contact2'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_contact',$data->contact,50) : $data->contact,
			$mod->Lang('help_contact')
		);

		$ob = cms_utils::get_module('Notifier');
		if($ob)
		{

			$ob = new \Notifier\MessageSender();
			$ob->Load();
			$sms = ($ob->text != FALSE);
			$mail = ($ob->mail != FALSE);
			$tweet = ($ob->tweet != FALSE);
		}
		else
		{
			$sms = FALSE;
			$mail = FALSE;
			$tweet = FALSE;
		}

		if($tweet)
		{
			$help = $mod->Lang('help_twt1');
			if($pmod)
				$help .= ' '.$mod->Lang('help_twt5');

			$options = array();
			$handles = $ob->tweet->GetHandles();
			foreach($handles as $one)
					$options[$one] = $one;
			//first one (the default) set to empty
			reset($options);
			$t = key($options);
			$options[$t] = '';

			$main[] = array(
				$mod->Lang('title_twtfrom'),
				($pmod) ?
				$mod->CreateInputDropdown($id,'tmt_twtfrom',$options,-1,$data->twtfrom)
				.' '.$mod->CreateInputSubmit($id,'connect',$mod->Lang('connect'),
					'title="'.$mod->Lang('title_auth').'"'):
				array_search($data->twtfrom,$options),
				$help
			);

			if($pmod)
			{
				$jsloads[] = <<< EOS
 $('#{$id}connect').click(function() {
  var tg = this;
  $.alertable.confirm('{$mod->Lang('allsaved')}',{
   okName: '{$this->Lang('yes')}',
   cancelName: '{$this->Lang('no')}'
  }).then(function() {
   set_params(tg);
   $(tg).trigger('click.deferred');
  });
  return false;
 });
EOS;
			}
		}
		if($sms)
		{
			if($ob->text->gateway->support_custom_sender())
				$main[] = array(
					$mod->Lang('title_smsfrom'),
					($pmod) ?
					$mod->CreateInputText($id,'tmt_smsfrom',$data->smsfrom,16):
					$data->smsfrom,
					$mod->Lang('help_smsfrom')
				);
			if($ob->text->gateway->require_country_prefix())
				$main[] = array(
					$mod->Lang('title_smsprefix'),
					($pmod) ?
					$mod->CreateInputText($id,'tmt_smsprefix',$data->smsprefix,4):
					$data->smsprefix,
					$mod->Lang('help_smsprefix')
				);
		}
		$ob = cms_utils::get_module('FrontEndUsers');
		if($ob)
		{
			//TODO if possible for feu, filter on permitted groups only c.f. MBVFaq
			if($pmod)
				$grpnames = $ob->GetGroupList();
			unset($ob);
			$main[] = array(
				$mod->Lang('title_feu_eds'),
				($pmod) ?
				$mod->CreateInputDropdown($id,'tmt_feu_editgroup',array(
					$mod->Lang('no_feuedit')=>'none',
					$mod->Lang('all_groups')=>'any')+$grpnames,'',$data->feu_editgroup) : $data->feu_editgroup,
				$mod->Lang('help_login')
			);
		}

		$tplvars['main'] = $main;

//========= ADVANCED OPTIONS ==========

		$adv = array();
		$adv[] = array(
			$mod->Lang('title_locale'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_locale',$data->locale,8,12) : $data->locale,
			$mod->Lang('help_locale')
		);
		if($sms)
		{
			$adv[] = array(
				$mod->Lang('title_phone_regex'),
				($pmod) ?
				$mod->CreateInputText($id,'tmt_smspattern',$data->smspattern,30,32):
				$data->smspattern,
				$mod->Lang('help_phone_regex')
			);
		}

		$tplhelp = array();
		$tplhelp[] = $mod->Lang('help_template');
		foreach(array(
		'title',
		'description',
		'owner',
		'contact',
		'smsfrom',
		'where',
		'when',
		'date',
		'time',
		'opponent',
		'teams',
		'recipient',
		'toall'
		) as $varname) $tplhelp[] = '&nbsp;$'.$varname.': '.$mod->Lang('desc_'.$varname);
		$tplhelp[] = $mod->Lang('help_mailout_template');
		$help = implode('<br />',$tplhelp);
		$helpabove = $mod->Lang('seeabove');

		if($mail)
		{
			if($pmod)
				$hidden .= $mod->CreateInputHidden($id,'tmt_html',0);
			$adv[] = array(
				$mod->Lang('title_emailhtml'),
				($pmod) ?
				$mod->CreateInputCheckbox($id,'tmt_html','1',$data->html) :
				(($data->html) ?  $mod->Lang('yes') : $mod->Lang('no'))
			);

			$adv[] = array(
				$mod->Lang('title_mailouttemplate'),
				($pmod) ?
				$mod->CreateTextArea(FALSE,$id,$data->motemplate,'tmt_motemplate','tallarea','','','',65,10) :
				$data->motemplate,
				$help
			);
		}
		if($sms || $tweet)
		{
			$adv[] = array(
				$mod->Lang('title_tweetouttemplate'),
				($pmod) ?
				$mod->CreateTextArea(FALSE,$id,$data->totemplate,'tmt_totemplate','shortarea','','','',65,3) :
				$data->totemplate,
				(($mail)?$helpabove:$help)
			);  //TODO maybe specific $tplhelp[]
		}
		if($mail)
		{
			$adv[] = array(
				$mod->Lang('title_mailcanceltemplate'),
				($pmod) ?
				$mod->CreateTextArea(FALSE,$id,$data->mcanctemplate,'tmt_mcanctemplate','tallarea','','','',65,10) :
				$data->mcanctemplate,
				$helpabove
			);
		}
		if($sms || $tweet)
		{
			$adv[] = array(
				$mod->Lang('title_tweetcanceltemplate'),
				($pmod) ?
				$mod->CreateTextArea(FALSE,$id,$data->tcanctemplate,'tmt_tcanctemplate','shortarea','','','',65,3) :
				$data->tcanctemplate,
				$helpabove
			);  //TODO maybe specific $tplhelp[]
		}
		if($mail)
		{
			$adv[] = array(
				$mod->Lang('title_mailrequesttemplate'),
				($pmod) ?
				$mod->CreateTextArea(FALSE,$id,$data->mreqtemplate,'tmt_mreqtemplate','tallarea','','','',65,10) :
				$data->mreqtemplate,
				$helpabove
			);
		}
		if($sms || $tweet)
		{
			$adv[] = array(
				$mod->Lang('title_tweetrequesttemplate'),
				($pmod) ?
				$mod->CreateTextArea(FALSE,$id,$data->treqtemplate,'tmt_treqtemplate','shortarea','','','',65,3) :
				$data->totemplate,
				$helpabove
			);  //TODO maybe specific $tplhelp[]
		}

		$tplhelp = array();
		$tplhelp[] = $mod->Lang('help_template');
		foreach(array(
		'title',
		'description',
		'where',
		'when',
		'date',
		'time',
		'report'
		) as $varname) $tplhelp[] = '&nbsp;$'.$varname.': '.$mod->Lang('desc_'.$varname);
		$tplhelp[] = $mod->Lang('help_mailin_template');
		$help = implode('<br />',$tplhelp);

		if($mail)
		{
			$adv[] = array(
				$mod->Lang('title_mailintemplate'),
				($pmod) ?
				$mod->CreateTextArea(FALSE,$id,$data->mitemplate,'tmt_mitemplate','tallarea','','','',65,10) :
				$data->mitemplate,
				$help
			);
		}
		if($sms || $tweet)
		{
			$adv[] = array(
				$mod->Lang('title_tweetintemplate'),
				($pmod) ?
				$mod->CreateTextArea(FALSE,$id,$data->titemplate,'tmt_titemplate','shortarea','','','',65,3) :
				$data->titemplate,
				(($mail)?$helpabove:$help)
			);  //TODO maybe specific $tplhelp[]
		}

		$adv[] = array(
			$mod->Lang('title_logic'),
			($pmod) ?
			$mod->CreateTextArea(FALSE,$id,$data->logic,'tmt_logic','tallarea','','','',65,15) :
			$data->logic,
			$mod->Lang('help_logic')
		);

		$tplvars['advanced'] = $adv;

//========= SCHEDULE ==========

		$sched = array();
		if($pmod)
		{
			if(!$this->committed)
			{
				$i = $mod->CreateInputText($id,'tmt_startdate',$data->startdate,15,20);
				$i = str_replace('class="','class="pickdate ',$i);
			}
			elseif ($data->startdate)
				$i = $data->startdate.$mod->CreateInputHidden($id,'tmt_startdate',$data->startdate);
			else
				$i = '&nbsp'.$mod->CreateInputHidden($id,'tmt_startdate','');
		}
		elseif($data->startdate)
			$i = $data->startdate;
		else
			$i = '&nbsp';
		$sched[] = array(
			$mod->Lang('title_start_date'),
			$i,
			$mod->Lang('help_date')
		);
		if($pmod)
		{
			$i = $mod->CreateInputText($id,'tmt_enddate',$data->enddate,15,20);
			$i = str_replace('class="','class="pickdate ',$i);
		}
		else
			$i = ($data->enddate) ? $data->enddate : '&nbsp';
		$sched[] = array(
			$mod->Lang('title_end_date'),
			$i,
			$mod->Lang('help_date')
		);

		if($pmod)
		{
			//for popup calendars
			$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.jquery.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/php-date-formatter.min.js"></script>
EOS;
			$nextm = $mod->Lang('nextm');
			$prevm = $mod->Lang('prevm');
			//js wants quoted period-names
			$t = $mod->Lang('longmonths');
			$mnames = "'".str_replace(",","','",$t)."'";
			$t = $mod->Lang('shortmonths');
			$smnames = "'".str_replace(",","','",$t)."'";
			$t = $mod->Lang('longdays');
			$dnames = "'".str_replace(",","','",$t)."'";
			$t = $mod->Lang('shortdays');
			$sdnames = "'".str_replace(",","','",$t)."'";
			$t = $this->Lang('meridiem');
			$meridiem = "'".str_replace(",","','",$t)."'";
			$jsloads[] = <<< EOS
 var fmt = new DateFormatter({
  longDays: [{$dnames}],
  shortDays: [{$sdnames}],
  longMonths: [{$mnames}],
  shortMonths: [{$smnames}],
  meridiem: [{$meridiem}],
  ordinal: function (number) {
   var n = number % 10, suffixes = {1:'st', 2:'nd', 3:'rd'};
   return Math.floor(number % 100 / 10) === 1 || !suffixes[n] ? 'th' : suffixes[n];
  }
 });
 $('.pickdate').each(function() {
   $(this).pikaday({
    container: this.parentNode,
    format: 'Y-m-d',
    reformat: function(target,f) {
     return fmt.formatDate(target,f);
    },
    getdate: function(target,f) {
     return fmt.parseDate(target,f);
    },
    i18n: {
     previousMonth: '{$prevm}',
     nextMonth: '{$nextm}',
     months: [{$mnames}],
     weekdays: [{$dnames}],
     weekdaysShort: [{$sdnames}]
    }
   });
 });
EOS;
		}
		if(class_exists('Booker',FALSE))
		{
			$sched[] = array(
				$mod->Lang('title_calendar').' (NOT YET WORKING)',
				($pmod) ?
				$mod->CreateInputText($id,'tmt_calendarid',$data->calendarid,15,20) : $data->calendarid,
				$mod->Lang('help_calendar')
			);
		}
		$sched[] = array(
			$mod->Lang('title_available').' (NOT YET WORKING)',
			($pmod) ?
			$mod->CreateTextArea(FALSE,$id,$data->available,'tmt_available','shortarea','','','',40,3) :
			$data->available,
			$mod->Lang('help_available')
		);
		$sched[] = array(
			$mod->Lang('title_latitude'),
			($pmod) ?
			$mod->CreateInputText($id, 'tmt_latitude', $data->latitude,8) : $data->latitude,
			$mod->Lang('help_latitude')
		);
		$sched[] = array(
			$mod->Lang('title_longitude'),
			($pmod) ?
			$mod->CreateInputText($id, 'tmt_longitude', $data->longitude,8) : $data->longitude,
			$mod->Lang('help_longitude')
		);
		$sched[] = array(
			$mod->Lang('title_same_time'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_sametime',$data->sametime,3) : $data->sametime,
			$mod->Lang('help_same_time'),
		);

		$opts = $this->IntervalNames($mod,array(0,1,2,3,4),TRUE); //choose from plural names up to weeks
		if($pmod)
			$opts = array_flip($opts); //selector needs other form of array

		$sched[] = array(
			$mod->Lang('title_place_gap'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_placegap',$data->placegap,3,5).
				'&nbsp'.
				$mod->CreateInputDropdown($id,'tmt_placegaptype',$opts,-1,$data->placegaptype):
			$data->placegap.' '.$opts[$data->placegaptype],
			$mod->Lang('help_place_gap')
			);

		$sched[] = array(
			$mod->Lang('title_play_gap'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_playgap',$data->playgap,3,5).
				'&nbsp;'.
				$mod->CreateInputDropdown($id,'tmt_playgaptype',$opts,-1,$data->playgaptype):
			$data->playgap.' '.$opts[$data->playgaptype],
			$mod->Lang('help_play_gap')
		);

		$tplvars['schedulers'] = $sched;

//========= CHART ==========

		$names = array();

		$names[] = array(
			$mod->Lang('title_final'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_final',$data->final,30) : $data->final
		);
		$names[] = array(
			$mod->Lang('title_semi'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_semi',$data->semi,30) : $data->semi
		);
		$names[] = array(
			$mod->Lang('title_quarter'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_quarter',$data->quarter,30) : $data->quarter
		);
		$names[] = array(
			$mod->Lang('title_eighth'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_eighth',$data->eighth,30) : $data->eighth
		);
		$names[] = array(
			$mod->Lang('title_roundname'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_roundname',$data->roundname,30) : $data->roundname,
			$mod->Lang('help_match_names')
		);
		$names[] = array(
			$mod->Lang('title_against'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_versus',$data->versus,30) : $data->versus
		);
		$names[] = array(
			$mod->Lang('title_defeated'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_defeated',$data->defeated,30) : $data->defeated
		);
		$names[] = array(
			$mod->Lang('title_cantie'),
			($pmod) ?
			$mod->CreateInputCheckbox($id,'tmt_cantie',1,$data->cantie) :
				($data->cantie?$mod->Lang('yesties'):$mod->Lang('noties'))
		);
		$names[] = array(
			$mod->Lang('title_tied'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_tied',$data->tied,30) : $data->tied
		);
		$names[] = array(
			$mod->Lang('title_noop'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_bye',$data->bye,30) : $data->bye
		);
		$names[] = array(
			$mod->Lang('title_forfeit'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_forfeit',$data->forfeit,30) : $data->forfeit
		);
		$names[] = array(
			$mod->Lang('title_abandoned'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_nomatch',$data->nomatch,30) : $data->nomatch
		);
		$names[] = array(
			$mod->Lang('title_atformat'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_atformat',$data->atformat,16): $data->atformat,
			$mod->Lang('help_date_format')
		);
		$names[] = array(
			$mod->Lang('title_cssfile'),
			($pmod) ?
			$mod->CreateInputText($id,'tmt_chartcss',$data->chartcss,20,128).
			' '.$mod->CreateInputSubmit($id,'upload_css',$mod->Lang('upload'),
				'title="'.$mod->Lang('upload_tip').'" onclick="set_params(this);"') :
			$data->chartcss,
			$mod->Lang('help_cssfile')
		);
		$tplhelp = array();
		$tplhelp[] = $mod->Lang('help_template');
		foreach(array(
		'title',
		'description',
		'owner',
		'contact',
		'image',
		'imgdate',
		'imgheight',
		'imgwidth',
		) as $varname) $tplhelp[] = '&nbsp;$'.$varname.': '.$mod->Lang('desc_'.$varname);
		$tplhelp[] = $mod->Lang('help_chttemplate');
		$help = implode('<br />',$tplhelp);

		$names[] = array(
			$mod->Lang('title_chttemplate'),
			($pmod) ?
			$mod->CreateTextArea(FALSE,$id,$data->chttemplate,'tmt_chttemplate','tallarea','','','',65,20) :
			$data->chttemplate,
			$help
		);

		$tplvars['names'] = $names;

		$tplvars['print'] = $mod->CreateInputSubmit($id,'print',$mod->Lang('print'),
			'title="'.$mod->Lang('print_tip').'" onclick="set_params(this);"');

//========= TEAMS ==========

		$tplvars['ordertitle'] = $mod->Lang('title_order');
		$tplvars['seedtitle'] = $mod->Lang('title_seed');
		$tplvars['contacttitle'] = $mod->Lang('title_contact');
		$tplvars['movetitle'] = $mod->Lang('title_move');
		$isteam = ((int)$data->teamsize > 1);
		$teamtitle = ($isteam) ? $mod->Lang('title_team') : $mod->Lang('title_player');
		$tplvars['teamtitle'] = $teamtitle;
		$finds = array('/class="(.*)"/','/id=.*\[\]" /'); //for xhtml string cleanup

		if($data->teams)
		{
			$tcount = count($data->teams);
			$teams = array();
			$indx = 1;
			$rowclass = 'row1'; //used to alternate row colors
			if($pmod)
			{
				$downtext = $mod->Lang('down');
				$uptext = $mod->Lang('up');
				$tmp = ($isteam) ? $mod->Lang('team') : $mod->Lang('player');
			}

			foreach($data->teams as $tid=>$tdata)
			{
				$one = new stdClass();
				$one->rowclass = $rowclass;
				if($pmod)
				{
					$one->hidden = $mod->CreateInputHidden($id,'tem_teamid[]',$tid).
						$mod->CreateInputHidden($id,'tem_contactall[]',$tdata['contactall']);
					$one->order = $tdata['displayorder'];
					$tmp = $mod->CreateInputText($id,'tem_name[]',$tdata['name'],20,64);
					$one->name = preg_replace($finds,array('class="tem_name $1"',''),$tmp);//fails if backref first!
					$tmp = $mod->CreateInputText($id,'tem_seed[]',$tdata['seeding'],3,3);
					$one->seed = preg_replace($finds,array('class="tem_seed $1"',''),$tmp);
					$tmp = $mod->CreateInputText($id,'tem_contact[]',$tdata['contact'],30,64);
					$one->contact = preg_replace($finds,array('class="tem_contact $1"',''),$tmp);
					//need input-objects that look like page-link, to get all form parameters upon activation
					if($indx > 1)
						$one->uplink = $mod->CreateInputLinks($id,'moveup['.$tid.']','arrow-u.gif',FALSE,
							$uptext,'onclick="set_params(this);"');
					else
						$one->uplink = '';
					if($indx < $tcount)
						$one->downlink = $mod->CreateInputLinks($id,'movedown['.$tid.']','arrow-d.gif',FALSE,
							$downtext,'onclick="set_params(this);"');
					else
						$one->downlink = '';
					++$indx;
					$one->editlink = $mod->CreateInputLinks($id,'edit['.$tid.']','edit.gif',FALSE,
						$mod->Lang('edit'),'onclick="set_params(this);"');
					$one->deletelink = $mod->CreateInputLinks($id,'delete_team['.$tid.']','delete.gif',FALSE,
						$mod->Lang('delete')); //confirmation via modal dialog
					$one->selected = $mod->CreateInputCheckbox($id,'tsel[]',$tid,-1);
				}
				else
				{
					$one->order = $tdata['displayorder'];
					$one->name = $tdata['name'];
					$one->seed = $tdata['seeding'];
					$one->contact = $tdata['contact'];
				}

				$teams[] = $one;
				($rowclass=='row1'?$rowclass='row2':$rowclass='row1');
			}
			$tplvars['teams'] = $teams;

			if($pmod)
			{
				if($tcount>1)
				{
					$jsincs[] = '<script type="text/javascript" src="'.$baseurl.'/lib/js/jquery.tablednd.min.js"></script>';

					$jsloads[] = <<< EOS
 $('#tmt_players').addClass('table_drag').tableDnD({
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
EOS;
				}
				$jsloads[] = <<< EOS
 $('#tmt_players').find('.tem_delete').children().click(function(ev) {
  var tg = ev.target,
    name = \$(this).closest('tr').find('.tem_name').attr('value'),
    msg;
  if (name) {
   name = name.replace(/'/g, "\\'");
   if (name.search(' ') > -1) {
    name = '"'+name+'"';
   }
   msg = '{$mod->Lang('confirm_delete','%s')}'.replace('%s',name);
  } else {
   msg = '{$mod->Lang('confirm')}';
  }
  $.alertable.confirm(msg,{
   okName: '{$this->Lang('yes')}',
   cancelName: '{$this->Lang('no')}'
  }).then(function() {
    set_params(tg);
    $(tg).trigger('click.deferred');
  });
  return false;
 });
EOS;
			}
		}
		else //no team-data
		{
			$tcount = 0;
			$tplvars['noteams'] = $mod->Lang('info_noteam');
		}
		$tplvars['teamcount'] = $tcount;

		$t = '';
		if($pmod)
		{
			$this->SpareSlot($data->bracket_id,($data->type != Tourney::RRTYPE));
			if($this->spare)
			{
				$linktext = $mod->Lang('title_add',strtolower($teamtitle));
				//need input-object that looks like page-link, to get all form parameters upon activation
				$t = $mod->CreateInputLinks($id,'addteam','newobject.gif',TRUE,
					$linktext,'onclick="set_params(this);"');
			}
		}
		$tplvars['addteam'] = $t;

		if($tcount)
		{
			if($tcount > 1)
			{
				$jsfuncs[] = <<< EOS
function select_all_teams(cb) {
 $('#tmt_players > tbody').find('input[type="checkbox"]').attr('checked',cb.checked);
}
EOS;
				$tplvars['selteams'] = $mod->CreateInputCheckbox($id,'t',FALSE,-1,
					'id="teamsel" onclick="select_all_teams(this);"');
			}
			else
				$tplvars['selteams'] = '';

			$jsfuncs[] = <<< EOS
function team_count() {
 var cb = $('#tmt_players > tbody').find('input:checked');
 return cb.length;
}
function teams_selected(ev,btn) {
 if(team_count() > 0) {
  set_params(btn);
  return true;
 } else {
  eventCancel(ev);
  return false;
 }
}
EOS;

			if($pmod)
			{
				if($data->matches || !$this->any) //not finished
				{
					$tplvars['dndhelp'] = $mod->Lang('help_dnd');
					$tplvars['update1'] = $mod->CreateInputSubmit($id,'update['.$id.'teams]',$mod->Lang('update'),
						'title="'.$mod->Lang('update_tip').'" onclick="return teams_selected(event,this);"');
					$tplvars['delete'] = $mod->CreateInputSubmit($id,'delteams',$mod->Lang('delete'),
						'title="'.$mod->Lang('delete_tip').'"');
					$t = ($isteam) ? $mod->Lang('sel_teams') : $mod->Lang('sel_players');
					$t = $mod->Lang('confirm_delete',$t);
					$jsloads[] = <<< EOS
 $('#{$id}delteams').click(function() {
  if (team_count() > 0) {
   var tg = this;
   $.alertable.confirm('$t',{
    okName: '{$this->Lang('yes')}',
    cancelName: '{$this->Lang('no')}'
   }).then(function() {
    set_params(tg);
	$(tg).trigger('click.deferred');
   });
  }
  return false;
 });
EOS;
				}
				else //finished
				{
					$tplvars['dndhelp'] = '';
					$tplvars['update1'] = '';
					$tplvars['delete'] = '';
				}
			}
			$tplvars['export'] = $mod->CreateInputSubmit($id,'export',$mod->Lang('export'),
				'title="'.$mod->Lang('export_tip').'" onclick="return teams_selected(event,this);"');
		}
		if($pmod && ($data->matches || !$this->any)) //not finished
			$t = $mod->CreateInputSubmit($id,'import_team',$mod->Lang('import'),
				'title="'.$mod->Lang('importteam_tip').'" onclick="set_params(this);"');
		else
			$t = '';
		$tplvars['import'] = $t;

//========== MATCHES ===========

		if(empty($data->matchview))
			$data->matchview = 'actual';
		$plan = ($data->matchview != 'actual');
		//these labels are shared with results tab, and may be used for history-view when all matches finished i.e. none displayed now
		$tplvars['scheduledtitle'] = $mod->Lang('scheduled');
		$tplvars['placetitle'] = $mod->Lang('title_venue');
		$tplvars['statustitle'] = $mod->Lang('title_status');

		if($data->matches)
		{
			if($plan)
			{
				switch ($data->type)
				{
				 case Tourney::RRTYPE:
					$anon = $mod->Lang('anonother');
					break;
				 case Tourney::DETYPE:
				 	$rnd = new tmtRoundsDE();
					//partial bracket data for downstream to use when naming
					$bdata = array(
					 'final'=>$data->final,
					 'semi'=>$data->semi,
					 'quarter'=>$data->quarter,
					 'eighth'=>$data->eighth,
					 'roundname'=>$data->roundname,
					 'bye'=>$data->bye
					);
				 	$tc = count($data->teams);
				 	break;
				 default:
				 	$rnd = new tmtRoundsKO();
				 	break;
				}
			}
			if($pmod)
			{
				$firmed = $mod->Lang('confirmed');
				$told = $mod->Lang('notified');
				$asked = $mod->Lang('asked');
				$items = array(
					$mod->Lang('notyet')=>Tourney::NOTYET,
					$mod->Lang('possible')=>Tourney::SOFT,
					$firmed=>Tourney::FIRM);
				$group = $mod->CreateInputRadioGroup($id,'mat_status',$items,'','','|');
				$choices = explode('|',$group);
			}

			$rowclass = 'row1';
			$matches = array();
			foreach($data->matches as $mid=>$mdata)
			{
				$one = new stdClass();
				$one->rowclass = $rowclass;
				if($plan)
				{
					$one->mid = $mid;
					$prev = FALSE;
					if($mdata['teamA'] != NULL)
					{
						$one->teamA = $mod->TeamName($mdata['teamA'],FALSE);
						if(!$one->teamA)
							$one->teamA = $data->bye;
					}
					else
					{
						switch ($data->type)
						{
						 case Tourney::RRTYPE:
							$one->teamA = $anon;
							break;
						 case Tourney::DETYPE:
							$level = $rnd->MatchLevel($tc,$data->matches,$mid);
							$one->teamA = $rnd->MatchTeamID_Team($mod,$bdata,$tc,$data->matches,$mid,$level,$mdata['teamB']); //team id may be NULL or -1
						 	break;
						 default:
							$one->teamA = $rnd->MatchTeamID_Mid($mod,$data->matches,$mid);
						 	break;
						}
					}

					if($mdata['teamB'] != NULL)
					{
						$one->teamB = $mod->TeamName($mdata['teamB'],FALSE);
						if(!$one->teamB)
						{
							if($one->teamA != $data->bye)
								$one->teamB = $data->bye;
							else
							{
								$one->teamA = $mod->Lang('nomatch');
								$one->teamB = '';
							}
						}
					}
					else
					{
						switch ($data->type)
						{
						 case Tourney::RRTYPE:
							$one->teamB = $anon;
							break;
						 case Tourney::DETYPE:
							$level = $rnd->MatchLevel($tc,$data->matches,$mid);
							if($mdata['teamA'])
								$name = $rnd->MatchTeamID_Team($mod,$bdata,$tc,$data->matches,$mid,$level,$mdata['teamA']);
							else
							{
								$excl = key($data->matches);
								if($excl)
									--$excl;
								$name = $rnd->MatchTeamID_Mid($mod,$bdata,$tc,$data->matches,$mid,$level,$excl);
							}
							if($name != $data->bye || $one->teamA != $data->bye)
								$one->teamB = $name;
							else //don't display 2 byes
							{
								$one->teamA = $mod->Lang('nomatch');
								$one->teamB = '';
							}
						 	break;
						 default:
						 	$excl = ($mdata['teamA']) ? (int)($mdata['teamA']) : key($data->matches)-1;
							$one->teamB = $rnd->MatchTeamID_Mid($mod,$data->matches,$mid,$excl);
						 	break;
						}
					}
				}
				else
				{
					$one->teamA = $mod->TeamName($mdata['teamA']);
					$one->teamB = $mod->TeamName($mdata['teamB']);
				}

				//TODO add tip(s) about checking availability, if relevant
				if($pmod)
				{
					$tmp = $mod->CreateInputText($id,'mat_playwhen[]',$mdata['playwhen'],20,48);
					$repls = array('class="mat_playwhen $1"','');
					$one->schedule = preg_replace($finds,$repls,$tmp);
					$tmp = $mod->CreateInputText($id,'mat_playwhere[]',$mdata['place'],20,64);
					$repls = array('class="mat_playwhere $1"','');
					$one->place = preg_replace($finds,$repls,$tmp);
					$one->hidden = $mod->CreateInputHidden($id,'mat_teamA[]',$mdata['teamA']).
						$mod->CreateInputHidden($id,'mat_teamB[]',$mdata['teamB']);
					if($mdata['status'] >= Tourney::MRES)
					{
						//completed, no going back
						$one->hidden .= $mod->CreateInputHidden($id,'mat_status['.$mid.']',$mdata['status']);
						if($mdata['teamA'] == -1 || $mdata['teamB'] == -1)
							$one->btn1 = '';
						else
							$one->btn1 = $mod->Lang('status_complete');
						$one->btn2 = '';
						$one->btn3 = '';
					}
					else
					{
						$r = 'mat_status['.$mid.']"'; //unique name for each radio-group,also returns match-id
						$one->btn1 = str_replace('mat_status"',$r,$choices[0]);
						$one->btn2 = str_replace('mat_status"',$r,$choices[1]);
						$one->btn3 = str_replace('mat_status"',$r,$choices[2]);
						//tailor content
						switch ((int)$mdata['status'])
						{
						 case Tourney::TOLD:
							$one->btn3 = str_replace(array($firmed,'value="'.Tourney::FIRM.'"'),array($told,'value="'.Tourney::TOLD.'"'),$one->btn3);
							break;
						 case Tourney::ASKED:
							$one->btn3 = str_replace(array($firmed,'value="'.Tourney::FIRM.'"'),array($asked,'value="'.Tourney::ASKED.'"'),$one->btn3);
							break;
						 case Tourney::ASOFT:
						 case Tourney::AFIRM:
							$one->btn2 = str_replace('value="'.Tourney::SOFT,'value="'.Tourney::ASOFT,$one->btn2);
							$one->btn3 = str_replace('value="'.Tourney::FIRM,'value="'.Tourney::AFIRM,$one->btn3);
							break;
						}
						//select relevant radio item
						switch((int)$mdata['status'])
						{
						 case Tourney::SOFT:
						 case Tourney::ASOFT:
							$one->btn2 = str_replace(' />',' checked="checked" />',$one->btn2);
							break;
						 case Tourney::FIRM:
						 case Tourney::TOLD:
	 					 case Tourney::ASKED:
						 case Tourney::AFIRM:
							$one->btn3 = str_replace(' />',' checked="checked" />',$one->btn3);
							break;
						 default:
							$one->btn1 = str_replace(' />',' checked="checked" />',$one->btn1);
							break;
						}
					}
					$one->selected = $mod->CreateInputCheckbox($id,'msel[]',$mid,-1);
				}
				else //no change allowed
				{
					$one->schedule = $mdata['playwhen'];
					$one->place = $mdata['place'];
					$one->btn1 = '';
					$one->btn2 = '';
					switch(intval($mdata['status']))
					{
					 case Tourney::SOFT:
					 case Tourney::ASOFT:
						$one->btn3 = $mod->Lang('possible');
						break;
					 case Tourney::FIRM:
					 case Tourney::AFIRM:
						$one->btn3 = $mod->Lang('confirmed');
						break;
 					 case Tourney::TOLD:
						$one->btn3 = $mod->Lang('notified');
						break;
					 case Tourney::ASKED:
						$one->btn3 = $mod->Lang('asked');
						break;
					 default:
						$one->btn3 = $mod->Lang('notyet');
						break;
					}
					$one->selected = '';
				}

				$matches[] = $one;
				($rowclass=='row1'?$rowclass='row2':$rowclass='row1');
			}
			$tplvars['matches'] = $matches;
			if($pmod && $matches)
			{
				//embedded vars here were defined for start/end-date calendars
				$jsloads[] = <<< EOS
 $('.mat_playwhen').each(function() {
   $(this).pikaday({
    container: this.parentNode,
    format: 'YYYY-MM-DD HH:mm',
    i18n: {
     previousMonth: '{$prevm}',
     nextMonth: '{$nextm}',
     months: [{$mnames}],
     weekdays: [{$dnames}],
     weekdaysShort: [{$sdnames}]
    }
   });
 });
EOS;
			}

			if($pmod && count($matches) > 1)
			{
				$jsfuncs[] = <<< EOS
function select_all_matches(cb) {
 $('#tmt_matches > tbody').find('input[type="checkbox"]').attr('checked',cb.checked);
}
EOS;
				$tplvars['selmatches'] = $mod->CreateInputCheckbox($id,'m',FALSE,-1,
					'id="matchsel" onclick="select_all_matches(this);"');
			}
			else
				$tplvars['selmatches'] = '';

			$jsfuncs[] = <<< EOS
function match_count() {
 var cb = $('#tmt_matches > tbody').find('input[type=checkbox]:checked');
 return cb.length;
}
function matches_selected(ev,btn) {
 if(match_count() > 0) {
  set_params(btn);
  return true;
 } else {
  eventCancel(ev);
  return false;
 }
}
EOS;
			if($pmod)
			{
				$tplvars['update2'] = $mod->CreateInputSubmit($id,'update['.$id.'matches]',$mod->Lang('update'),
					'title="'.$mod->Lang('update_tip').'" onclick="return matches_selected(event,this);"');
				if(!$this->committed)	//no match actually 'locked in'
				{
					$tplvars['reset'] = $mod->CreateInputSubmit($id,'reset',$mod->Lang('reset'),
						'title="'.$mod->Lang('reset_tip').'"');
					$jsloads[] = <<< EOS
 $('#{$id}reset').click(function() {
  var tg = this;
  $.alertable.confirm('{$mod->Lang('confirm_delete',$mod->Lang('match_data'))}',{
   okName: '{$this->Lang('yes')}',
   cancelName: '{$this->Lang('no')}'
  }).then(function() {
    set_params(tg);
	$(tg).trigger('click.deferred');
  });
  return false;
 });
EOS;
				}
			}

			$jsloads[] = <<< EOS
 $('#{$id}notify,#{$id}abandon').click(function() {
  if (match_count() > 0) {
   var tg = this;
   $.alertable.confirm('{$mod->Lang('allsaved')}',{
    okName: '{$this->Lang('yes')}',
    cancelName: '{$this->Lang('no')}'
   }).then(function() {
    set_params(tg);
	$(tg).trigger('click.deferred');
   });
  }
  return false;
 });
EOS;

			if($pmod && ($data->matches || !$this->any)) //not finished
				$t = $mod->CreateInputSubmit($id,'import_result',$mod->Lang('import'),
					'title="'.$mod->Lang('importresult_tip').'" onclick="set_params(this);"');
			else
				$t = '';
			$tplvars['import2'] = $t;

			$tplvars['notify'] = $mod->CreateInputSubmit($id,'notify',$mod->Lang('notify'),
				'title="'.$mod->Lang('notify_tip').'"'); //modal confirm for this
			$tplvars['abandon'] = $mod->CreateInputSubmit($id,'abandon',$mod->Lang('abandon'),
				'title="'.$mod->Lang('abandon_tip').'"'); //modal confirm for this
			if($plan)
			{
				$bdata = array(
				 'bracket_id'=>$data->bracket_id,
				 'name'=>$data->name,
				 'description'=>'',
				 'type'=>$data->type,
				 'chartcss'=>$data->chartcss,
				 'timezone'=>$data->timezone,
				 'atformat'=>$data->atformat,
				 'final'=>$data->final,
				 'semi'=>$data->semi,
				 'quarter'=>$data->quarter,
				 'eighth'=>$data->eighth,
				 'roundname'=>$data->roundname,
				 'versus'=>$data->versus,
				 'defeated'=>$data->defeated,
				 'tied'=>$data->tied,
				 'bye'=>$data->bye,
				 'forfeit'=>$data->forfeit,
				 'nomatch'=>$data->nomatch,
				 'chartbuild'=>TRUE
				);
				$lyt = new tmtLayout();
				list($chartfile,$errkey) = $lyt->GetChart($mod,$bdata,$data->chartcss,2);
				if ($chartfile)
				{
					//nobody else should see this chart
					$db = cmsms()->GetDb();
		 			$sql = 'UPDATE '.cms_db_prefix().'module_tmt_brackets SET chartbuild=1 WHERE bracket_id=?';
					$db->Execute($sql,array($data->bracket_id));
					$rooturl = (empty($_SERVER['HTTPS'])) ? $config['root_url'] : $config['ssl_url'];
					$basename = basename($chartfile);
					list($height,$width) = $lyt->GetChartSize();
					$tplvars['image'] = $mod->CreateImageObject($rooturl.'/tmp/'.$basename,(int)$height+30);
				}
				else
				{
					$message = $mod->Lang('err_chart');
						if($errkey)
							$message .= '<br /><br />'.$mod->Lang($errkey);
					$tplvars['image'] = $message;
				}
			}
			$tplvars['malldone'] = 0;
		}
		elseif($this->any) //match(es) exist
		{
			$tplvars['malldone'] = 1;
			$tplvars['nomatches'] = $mod->Lang('info_nomatch2');
		}
		else //no matches at all
		{
			$tplvars['malldone'] = 0;
			$tplvars['nomatches'] = $mod->Lang('info_nomatch');
			if($pmod && $tcount > 1)
				$tplvars['schedule'] = $mod->CreateInputSubmit($id,'schedule',$mod->Lang('schedule'),
					'onclick="set_params(this);"');
		}

		$hidden .= $mod->CreateInputHidden($id,'matchview',$data->matchview);

		$jsfuncs[] = <<< EOS
function matches_view(btn) {
 set_tab();
 $('#{$id}real_action').val('match_view');
 var newmode = (btn.name=='{$id}actual')?'actual':'plan';
 $('#{$id}matchview').val(newmode);
}
EOS;
		if($plan)
		{
			$tplvars['plan'] = 1;
			$tplvars['idtitle'] = $mod->Lang('title_mid');
			$tplvars['altmview'] = $mod->CreateInputSubmit($id,'actual',$mod->Lang('actual'),
				'title="'.$mod->Lang('actual_tip').'" onclick="matches_view(this);"');
		}
		else
		{
			$tplvars['plan'] = 0;
			$tplvars['altmview'] = $mod->CreateInputSubmit($id,'plan',$mod->Lang('plan'),
				'title="'.$mod->Lang('plan_tip').'" onclick="matches_view(this);"');
		}
		//these may be used on results tab as well or instead
		$tplvars['chart'] = $mod->CreateInputSubmit($id,'chart',$mod->Lang('chart'),
			'onclick="set_params(this);"');
		$tplvars['list'] = $mod->CreateInputSubmit($id,'list',$mod->Lang('list'),
			'onclick="set_params(this);"');

//========= RESULTS ==========

		if(empty($data->resultview))
			$data->resultview = 'future';
		$future = ($data->resultview == 'future');

		if($data->results) //matching results in the database
		{
			if($pmod)
			{
				$extras = ($future) ?
				 array($mod->Lang('chooseone')=>-1):
				 array($mod->Lang('notyet')=>Tourney::NOTYET,
						$mod->Lang('possible')=>Tourney::SOFT,
						$mod->Lang('confirmed')=>Tourney::FIRM);
			}
//	tmtUtils()?
			$relations = $mod->ResultTemplates($data->bracket_id,FALSE);
			$results = array();
			$rowclass = 'row1';
			//populate array excluding byes
			foreach($data->results as $mid=>$mdata)
			{
				if(!($mdata['teamA']=='-1'|| $mdata['teamB']=='-1'))
				{
					$one = new stdClass();
					$one->rowclass = $rowclass;
					$one->schedule = $mdata['playwhen'];
					if($pmod)
					{
						$one->hidden = $mod->CreateInputHidden($id,'res_matchid[]',$mid).
							$mod->CreateInputHidden($id,'res_teamA[]',$mdata['teamA']).
							$mod->CreateInputHidden($id,'res_teamB[]',$mdata['teamB']);
						$tmp = $mod->CreateInputText($id,'res_playwhen[]',$one->schedule,15,30);
						$repls = array('class="res_playwhen $1"','');
						$one->actual = preg_replace($finds,$repls,$tmp);
						$one->teamA = $mod->TeamName($mdata['teamA']);
						$one->teamB = $mod->TeamName($mdata['teamB']);
						$choices = $extras + array(
							str_replace('%s',$one->teamA,$relations['won'])=>Tourney::WONA,
							str_replace('%s',$one->teamB,$relations['won'])=>Tourney::WONB,
							str_replace('%s',$one->teamA,$relations['forf'])=>Tourney::FORFA,
							str_replace('%s',$one->teamB,$relations['forf'])=>Tourney::FORFB
						);
						if($data->cantie)
							$choices[$data->tied] = Tourney::MTIED;
						$choices[$data->nomatch] = Tourney::NOWIN;
						$sel = ($future) ? -1 : (int)$mdata['status'];
						$one->result = $mod->CreateInputDropdown($id,'res_status['.$mid.']',$choices,'',$sel);
						$tmp = $mod->CreateInputText($id,'res_score[]',$mdata['score'],15,30);
						$repls = array('class="res_score $1"','');
						$one->score = preg_replace($finds,$repls,$tmp);
						$one->selected = $mod->CreateInputCheckbox($id,'rsel[]',$mid,-1);
					}
					else //no changes
					{
						$one->actual = substr($mdata['playwhen'],0,-3); //without any seconds display
						$one->teamA = '';
						$one->teamB = '';
						$tA = $mod->TeamName($mdata['teamA']);
						$tB = $mod->TeamName($mdata['teamB']);
						switch(intval($mdata['status']))
						{
						 case Tourney::WONA:
							$one->result = str_replace('%s',$tA,$relations['won']);
							break;
						 case Tourney::WONB:
							$one->result = str_replace('%s',$tB,$relations['won']);
							break;
						 case Tourney::FORFA:
							$one->result = str_replace('%s',$tA,$relations['forf']);
							break;
						 case Tourney::FORFB:
							$one->result = str_replace('%s',$tB,$relations['forf']);
							break;
						 case Tourney::MTIED:
							$one->result = sprintf($relations['tied'],$tA,$tB);
							break;
						 case Tourney::NOWIN:
							$one->result = $mod->Lang('name_abandoned');
							break;
						 default:
							$one->result = $mod->Lang('notyet');
							break;
						}
						$one->score = $mdata['score'];
					}
					$results[] = $one;
					($rowclass=='row1'?$rowclass='row2':$rowclass='row1');
				}
			}
			if($results) //there's something other than a bye
			{
				$tplvars['results'] = $results;
				if($pmod)
				{
					//embedded vars here were defined for start/end-date calendars
					$jsloads[] = <<< EOS
 $('.res_playwhen').each(function() {
   $(this).pikaday({
    container: this.parentNode,
    format: 'YYYY-MM-DD HH:mm',
    i18n: {
     previousMonth: '{$prevm}',
     nextMonth: '{$nextm}',
     months: [{$mnames}],
     weekdays: [{$dnames}],
     weekdaysShort: [{$sdnames}]
    }
   });
 });
EOS;
				}
				if(count($results) > 1)
				{
					$jsfuncs[] = <<< EOS
function select_all_results(cb) {
 $('#tmt_results > tbody').find('input[type="checkbox"]').attr('checked',cb.checked);
}
EOS;
					$tplvars['selresults'] = $mod->CreateInputCheckbox($id,'r',FALSE,-1,
						'id="resultsel" onclick="select_all_results(this);"');
				}
				else
					$tplvars['selresults'] = '';

				$tplvars['playedtitle'] = $mod->Lang('played');
				$tplvars['resulttitle'] = $mod->Lang('title_result');
				$tplvars['scoretitle'] = $mod->Lang('score');

				$jsfuncs[] = <<< EOS
function result_count() {
 var cb = $('#tmt_results > tbody').find('input:checked');
 return cb.length;
}
function results_selected(ev,btn) {
 if(result_count() > 0) {
  set_params(btn);
  return true;
 } else {
  eventCancel(ev);
  return false;
 }
}
EOS;
				$tplvars['update3'] = $mod->CreateInputSubmit($id,'update['.$id.'results]',$mod->Lang('update'),
					'title="'.$mod->Lang('update_tip').'" onclick="return results_selected(event,this);"');
			}
			else //no relevant result
			{
				$tplvars['ralldone'] = 0;
				$tplvars['noresults'] = $mod->Lang('info_noresult');
			}
		}
		elseif($this->any) //any match in the database
		{
			$tplvars['ralldone'] = 1;
			$key = ($this->committed) ? 'info_noresult2':'info_noresult';
			$tplvars['noresults'] = $mod->Lang($key);
		}
		else //nothing at all
		{
			$tplvars['ralldone'] = 0;
			$tplvars['noresults'] = $mod->Lang('info_noresult');
		}

		$hidden .= $mod->CreateInputHidden($id,'resultview',$data->resultview);

		$jsfuncs[] = <<< EOS
function results_view(btn) {
 set_tab();
 $('#{$id}real_action').val('result_view');
 var newmode = (btn.name=='{$id}future')?'future':'past';
 $('#{$id}resultview').val(newmode);
}
EOS;
		if($future)
			$tplvars['altrview'] = $mod->CreateInputSubmit($id,'past',$mod->Lang('history'),
				'title="'.$mod->Lang('history_tip').'" onclick="results_view(this);"');
		else
			$tplvars['altrview'] = $mod->CreateInputSubmit($id,'future',$mod->Lang('future'),
				'title="'.$mod->Lang('future_tip').'" onclick="results_view(this);"');
		$tplvars['changes'] = $mod->CreateInputSubmit($id,'changelog',$mod->Lang('changes'),
			'title="'.$mod->Lang('changes_tip').'" onclick="set_action(this);"');
		$tplvars['getscore'] = $mod->CreateInputSubmit($id,'getscore',$mod->Lang('getscore'),
			'title="'.$mod->Lang('getscore_tip').'" onclick="return results_selected(event,this);"');

//===============================

		$tplvars['save'] = $mod->CreateInputSubmitDefault($id,'submit',$mod->Lang('save'),
			'onclick="set_action(this);"');
		$tplvars['apply'] = $mod->CreateInputSubmit($id,'apply',$mod->Lang('apply'),
			'title = "'.$mod->Lang('apply_tip').'" onclick="set_params(this);"');
		//setup cancel-confirmation popup
		if(!empty($data->added)) { //always check cancellation for new bracket
			$test = '1';
	    } else { //check if 'dirty' reported via ajax
			$url = $mod->CreateLink($id,'check_data',NULL,NULL,array('bracket_id'=>$data->bracket_id),NULL,TRUE);
			$offs = strpos($url,'?mact=');
			$ajaxdata = str_replace('amp;','',substr($url,$offs+1));
			$test = 'check_data()';
			$jfuncs[] = <<< EOS
function check_data() {
 var check = false;
 $.ajax({
   url: 'moduleinterface.php',
   async: false,
   type: 'POST',
   data: '$ajaxdata',
   dataType: 'text',
   success: function(data,status) {
	   if(status=='success') check = (data=='1');
   }
 }).fail(function() {
  $.alertable.alert('{$mod->Lang('err_ajax')}');
 });
 return check;
}
EOS;
		}

		$tplvars['cancel'] = $mod->CreateInputSubmit($id,'cancel',$mod->Lang('cancel'));
		//for popup confirmation
		$jsloads[] = <<< EOS
 $('#{$id}cancel').click(function() {
  if ({$test}) {
   var tg = this;
   $.alertable.confirm('{$mod->Lang('allabandon')}',{
    okName: '{$this->Lang('yes')}',
    cancelName: '{$this->Lang('no')}'
   }).then(function() {
    set_action(tg);
	$(tg).trigger('click.deferred');
   });
   return false;
  } else {
    set_action(this);
    return true;
  }
 });
EOS;
		//prevent immediate activation by textinput <Enter> press
		$jsloads[] = <<< EOS
 $('form input[type=text]').keypress(function(e){
  if (e.which == 13) {
// $('input[type=submit].default').focus();
   return false;
  }
 });
EOS;
		$tplvars['hidden'] = $hidden;

		if($jsloads)
		{
			$jsfuncs[] = '$(document).ready(function() {
';
			$jsfuncs = array_merge($jsfuncs,$jsloads);
			$jsfuncs[] = '});
';
		}
		$tplvars['jsfuncs'] = $jsfuncs;
		$tplvars['jsincs'] = $jsincs;
	}
}

?>
