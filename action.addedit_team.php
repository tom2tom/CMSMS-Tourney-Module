<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
if(!function_exists('OrderTeamMembers'))
{
 function OrderTeamMembers(&$db,$tid)
 {
	$pref = cms_db_prefix();
	$sql = 'SELECT * FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder ASC';
	$rows = $db->GetAll($sql,array($tid));
	if($rows)
	{
		//to avoid overwrites,in the first pass stored orders are < 0,-1-based
		$tmporder = -1;
		$sql = 'UPDATE '.$pref.'module_tmt_people SET displayorder=? WHERE id=? AND displayorder=?';
		foreach($rows as &$row)
		{
			$db->Execute($sql,array($tmporder,$tid,$row['displayorder']));
			$row['displayorder'] = -$tmporder;
			$tmporder--;
			$row['id'] = (int)$row['id']; //cleanups
			$row['flags'] = (int)$row['flags'];
		}
		unset($row);
		$sql = 'UPDATE '.$pref.'module_tmt_people SET displayorder=-displayorder WHERE id=?';
		$db->Execute($sql,array($tid));
	}
	return $rows;
 }
}

if(!function_exists('OrderTeamData'))
{
 function OrderTeamData(&$db,$thistid,$before,$after,&$params)
 {
	$rows = OrderTeamMembers($db,$thistid);
	$indx = 0;
	foreach($params['plr_order'] as $k=>$oldorder)
	{
		if($oldorder == $before)
			$k = $after-1;
		elseif($oldorder == $after)
			$k = $before-1;
		$rows[$indx]['name']=$params['plr_name'][$k];
		$rows[$indx]['contact']=$params['plr_contact'][$k];
		$indx++;
	}
	return $rows;
 }
}

/*
Arrive here following competitors-tab add or edit click,or one of the actions
initiated from the page setup and displayed here.
Determine what to do,set mode-enumerator $op accordingly
NOTE: some team parameters can be edited inline,after which the update btn would be clicked
See action: save_comp
*/
if(!$this->CheckAccess('admod'))
{
	$newparms = $this->GetEditParms($params,'playerstab');
	if(!isset($params['cancel']))
		$newparms['tmt_message'] = $this->PrettyMessage('lackpermission',FALSE);
	$this->Redirect($id,'addedit_comp',$returnid,$newparms);
}

$pref = cms_db_prefix();

if(isset($params['submit']))
{
 	if(!empty($params['newteam_id']))
	 	$tid = (int)$params['newteam_id'];
	else
	 	$tid = (int)$params['team_id'];
	$teamflags = 0;
	$saver = 1;
 	$sql = 'UPDATE '.$pref.'module_tmt_people SET name=?,contact=?,displayorder=?,flags=? WHERE id=? AND displayorder=? AND flags!=2';
	foreach($params['plr_order'] as $indx=>$order)
	{
		$name = $params['plr_name'][$indx] ? trim($params['plr_name'][$indx]):NULL;
		$contact = $params['plr_contact'][$indx] ? trim($params['plr_contact'][$indx]):NULL;
		$flags = (int)$params['plr_flags'][$indx];
		if($flags)
			$teamflags = 3; //any non-0 member-flag signals team has been altered
		//1st set new orders < 0 to prevent overwrites
		$db->Execute($sql,array($name,$contact,-$saver,$flags,$tid,$params['plr_order'][$indx]));
		$saver++;
	}
	//now we can set real orders
 	$sql = 'UPDATE '.$pref.'module_tmt_people SET displayorder=-displayorder WHERE id=? AND displayorder<0';
	$db->Execute($sql,array($tid));
	$name = !empty($params['tem_name'])?trim($params['tem_name']):NULL;
	$seed = ($params['tem_seed'])?(int)$params['tem_seed']:NULL;
	$tell = $params['tem_tellall']?1:0;
	$order = $params['tem_order']?(int)$params['tem_order']:1000; //blank goes last
 	if(!empty($params['newteam_id']))
		$teamflags = 1; //force new-team flag, otherwise whatever from members-saving
 	$sql = 'UPDATE '.$pref.'module_tmt_teams SET name=?,seeding=?,contactall=?,displayorder=?,flags=? WHERE team_id=?';
	$db->Execute($sql,array($name,$seed,$tell,$order,$teamflags,$tid));
	if($teamflags == 1)
	{
		$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2 ORDER BY displayorder ASC';
		$teams = $db->GetCol($sql,array($params['bracket_id']));
		$order = 1;
		$sql = 'UPDATE '.$pref.'module_tmt_teams SET displayorder=? WHERE team_id=?';
		foreach($teams as $tid)
		{
			$db->Execute($sql,array($order,$tid));
			$order++;
		}
	}
	$this->Redirect($id,'addedit_comp',$returnid,$this->GetEditParms($params,'playerstab'));
}
elseif(isset($params['cancel']))
{
	if(!empty($params['newteam_id']))
	{
		$tid = $params['newteam_id'];
		$sql = 'DELETE FROM '.$pref.'module_tmt_teams WHERE team_id=?';
		$db->Execute($sql,array($tid));
		$sql = 'DELETE FROM '.$pref.'module_tmt_people WHERE id=?';
		$db->Execute($sql,array($tid));
	}
	else //editing an existing team
	{
		$tid = $params['team_id'];
		$sql = 'DELETE FROM '.$pref.'module_tmt_people WHERE id=? AND flags=1';
		$db->Execute($sql,array($tid));
		//TODO support content reversion where flags = 3
		$sql = 'UPDATE '.$pref.'module_tmt_people SET flags = 0 WHERE id=? AND flags IN (2,3)';
		$db->Execute($sql,array($tid));
	}
	$this->Redirect($id,'addedit_comp',$returnid,$this->GetEditParms($params,'playerstab'));
}

//simple cases
$op = FALSE;
foreach(array(
 1 => 'add',//comp add-button clicked
 3 => 'addplayer',//team add-button clicked
 5 => 'movedown',//inc player displayorder
 6 => 'moveup',//dec player displayorder
) as $i => $name)
{
	if(isset($params[$name]))
	{
		$op = $i;
//		unset($params[$name]); needed for moveup/down, at least
		break;
	}
}

if(!$op) //special cases
{
	if(isset($params['edit'])) //new session: comp edit button clicked
	{
		reset($params['edit']);
		$params['team_id'] = key($params['edit']);
		unset($params['edit']);
		$op = 2;
	}
	elseif(isset($params['delete']))
	{
		if(is_array($params['delete'])) //row-specific delete button clicked
			$op = 4;
		else
			$op = (!empty($params['psel'])) ? 11 : 99; //delete selected(if any)
	}
	elseif(isset($params['export']))
	{
		unset($params['export']);
		$op = (!empty($params['psel'])) ? 12 : 99; //export selected
	}
	else
	{
		//default to edit if possible or else add
		if(!empty($params['team_id']))
		{
			$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE team_id=?';
			$indx = $db->GetOne($sql,array($params['team_id']));
			$op = ($indx) ? 2 : 1;
		}
		else
			$op = 1;
	}
}

if($op == 99)
	$this->Redirect($id,'addedit_comp',$returnid,$this->GetEditParms($params,'playerstab'));
if($op == 1)
{
	$newtid = $db->GenID($pref.'module_tmt_teams_seq');
	$params['newteam_id'] = $newtid;
//CHECKME unset($params['add']);
}

$bracket_id = (int)$params['bracket_id'];
$name = FALSE;
$teamtitle = FALSE;
$isteam = TRUE;
$pref = cms_db_prefix();
$sql = 'SELECT name,teamsize FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
$bdata = $db->GetRow($sql,array($bracket_id));
if($bdata)
{
	if($bdata['name']) $name = $bdata['name'];
	if($bdata['teamsize'] == 1)
	{
		$teamtitle = $this->Lang('title_player');
		$isteam = FALSE;
	}
}

if($name == FALSE)
	$name = $this->Lang('tournament');
if($teamtitle == FALSE)
	$teamtitle = $this->Lang('title_team');

$pmod = $this->CheckAccess('admod');
$smarty->assign('canmod',($pmod)?1:0);

$smarty->assign('form_start',$this->CreateFormStart($id,'addedit_team',$returnid));
$smarty->assign('form_end',$this->CreateFormEnd());
//accumulator for hidden stuff
$hidden = $this->GetHiddenParms($id,$params,'playerstab');

if(!empty($params['newteam_id']))
{
	$thistid = $params['newteam_id'];
	$hidden .= $this->CreateInputHidden($id,'newteam_id',$thistid);
	$smarty->assign('pagetitle',strtoupper($this->Lang('title_add_long',$teamtitle,$name)));
}
else
{
	$thistid = $params['team_id'];
	$hidden .= $this->CreateInputHidden($id,'team_id',$thistid);
	$smarty->assign('pagetitle',strtoupper($this->Lang('title_edit_long',$teamtitle,$name)));
}

if($op == 1) //new team
{
	$sql = 'SELECT COUNT(1) AS num FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags != 2';
	$num = $db->GetOne($sql,array($bracket_id)); //start team last in order
	$num++;
	$sql = 'INSERT INTO '.$pref."module_tmt_teams VALUES (?,?,NULL,NULL,0,$num,1)";//new-team flag
	$db->Execute($sql,array($newtid,$bracket_id));
	$main = array(
	 'team_id'=>$newtid,
	 'bracket_id'=>$bracket_id,
	 'name'=>'',
	 'seeding'=>'',
	 'contactall'=>0,
	 'displayorder'=>$num,
	 'flags'=>1
	);
}
else
{
	$sql = 'SELECT * FROM '.$pref.'module_tmt_teams WHERE team_id=?';
	$main = $db->GetRow($sql,array($thistid));
}

$opts = array();
if($isteam)
	$opts[] = array(
		$this->Lang('title_teamname'),
		($pmod) ? $this->CreateInputText($id,'tem_name',$main['name'],30,64) : $main['name'],
		$this->Lang('help_teamname')
	);
$opts[] = array(
	$this->Lang('title_seed'),
	($pmod) ? $this->CreateInputText($id,'tem_seed',$main['seeding'],2,2) : $main['seeding'],
	$this->Lang('help_seednum')
);
$opts[] = array(
	$this->Lang('title_ordernum'),
	($pmod) ? $this->CreateInputText($id,'tem_order',$main['displayorder'],3,3) : $main['displayorder'],
	$this->Lang('help_order')
	);
if($isteam)
{
	if($pmod)
		$i = $this->CreateInputRadioGroup($id,'tem_tellall',
			array($this->Lang('one')=>0,$this->Lang('all')=>1),
			(int)$main['contactall'],'',' ');
	elseif ($main['contactall'] == '0')
		$i = $this->Lang('one');
	else
		$i = $this->Lang('all');
	$opts[] = array(
		$this->Lang('title_sendto'),
		$i,
		$this->Lang('help_sendto')
	);
}
elseif($pmod)
	$hidden .= $this->CreateInputHidden($id,'tem_tellall',0);

$smarty->assign('opts',$opts);
$theme = cmsms()->get_variable('admintheme');
$iconinfo = $theme->DisplayImage('icons/system/info.gif',$this->Lang('showhelp'),'','','systemicon tipper');
$smarty->assign('showtip',$iconinfo);

//table column-headings
$smarty->assign('nametext',$this->Lang('title_player'));
$smarty->assign('contacttext',$this->Lang('title_contact'));
if($pmod)
	$smarty->assign('movetext',$this->Lang('title_move'));

switch($op)
{
 case 1://no players yet in a newly-added team
	$sql = 'INSERT INTO '.$pref.'module_tmt_people VALUES (?,null,null,1,1)';//new-member flag
	$db->Execute($sql,array($newtid));
	$rows = array();
	$rows[] = array(
	 'id'=>$newtid,
	 'name'=>'',
	 'contact'=>'',
	 'displayorder'=>1,
	 'flags'=>1
	);
	break;
 case 2://starting a new edit-session
	$sql = 'SELECT * FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder ASC';
	$rows = $db->GetAll($sql,array($thistid));
	break;
 case 3://add player to team
//CHECKME unset($params['addplayer]);
 	$rows = array();
 	if(isset($params['plr_order']))
	{
		foreach($params['plr_order'] as $indx=>$order)
		{
			$rows[] = array(
			 'id'=>$thistid,
			 'name'=>$params['plr_name'][$indx],
			 'contact'=>$params['plr_contact'][$indx],
			 'displayorder'=>$order,
			 'flags'=>$params['plr_flags'][$indx]
			);
		}
		$next = count($params['plr_order'])+1; //assume no gaps in the recorded orders
	}
	else
		$next = 1;

	$sql = 'INSERT INTO '.$pref.'module_tmt_people VALUES (?,null,null,?,1)';//new-member flag
	$db->Execute($sql,array($thistid,$next));
	$rows[] = array(
	 'id'=>$thistid,
	 'name'=>'',
	 'contact'=>'',
	 'displayorder'=>$next,
	 'flags'=>1
	);
	break;
 case 4://remove player from team
 	reset($params['delete']);
	$order = key($params['delete']);
	$sql = 'UPDATE '.$pref.'module_tmt_people SET flags=2 WHERE id=? AND displayorder=? AND flags!=2';
	$res = $db->Execute($sql,array($thistid,$order));
	$keeps = array_diff($params['plr_order'],array($order));
	if($keeps)
	{
		//get values for display
		//TODO this stuff is probably borked!!
		$rows = OrderTeamMembers($db,$thistid);
		$indx = 0;
		foreach($keeps as $key=>$order)
		{
			$rows[$indx]['name'] = $params['plr_name'][$key];
			$rows[$indx]['contact'] = $params['plr_contact'][$key];
			$rows[$indx]['displayorder'] = $order;
			$rows[$indx]['flags'] = $params['plr_flags'][$key];
			$indx++;
		}
	}
	else
		$rows = array();
	break;
 case 5://bump member's displayorder
/*$tmp = array_keys($params['movedown']);
	$order = $tmp[0];
	$neworder = $order+1;
	$sql = 'UPDATE '.$pref.'module_tmt_people SET displayorder=? WHERE id=? AND flags!=2 AND displayorder=?';
	$db->Execute($sql,array(-$neworder,$thistid,$order));
	$db->Execute($sql,array($order,$thistid,$neworder));
	$db->Execute($sql,array($neworder,$thistid,-$neworder));
*/
	reset($params['movedown']);
	$o1 = key($params['movedown']);
	$k = array_search($o1,$params['plr_order']);
	if(isset($params['plr_order'][$k+1]))
	{
		$sql = 'UPDATE '.$pref.'module_tmt_people SET displayorder=? WHERE id=? AND name=?';
		if($params['plr_order'][$k+1] < $o1+2)
			$db->Execute($sql,array($o1,$thistid,$params['plr_name'][$k+1]));
		$db->Execute($sql,array($o1+1,$thistid,$params['plr_name'][$k]));
		$rows = OrderTeamData($db,$thistid,$k+1,$k+2,$params);
	}
	else
		$rows = array(); //TODO
	break;
 case 6: //dec member's displayorder
/*$tmp = array_keys($params['moveup']);
	$order = $tmp[0];
	$neworder = $order-1;
	$sql = 'UPDATE '.$pref.'module_tmt_people SET displayorder=? WHERE id=? AND flags!=2 AND displayorder=?';
	$db->Execute($sql,array(-$neworder,$thistid,$order));
	$db->Execute($sql,array($order,$thistid,$neworder));
	$db->Execute($sql,array($neworder,$thistid,-$neworder));
*/
	reset($params['moveup']);
	$o1 = key($params['moveup']);
	$k = array_search($o1,$params['plr_order']);
	if($k > 0)
	{
		$sql = 'UPDATE '.$pref.'module_tmt_people SET displayorder=? WHERE id=? AND name=?';
		if($params['plr_order'][$k-1] > $o1-2)
			$db->Execute($sql,array($o1,$thistid,$params['plr_name'][$k-1]));
		$db->Execute($sql,array($o1-1,$thistid,$params['plr_name'][$k]));
		$rows = OrderTeamData($db,$thistid,$k+1,$k,$params);
	}
	else
		$rows = array(); //TODO
	break;
 case 11://remove selected member(s)
	$args = $params['psel'];
 	$num = count($args);
	$selector = ($num > 1) ? ' IN ('.str_repeat('?,',$num-1).'?)' : '=?';
	$sql = 'UPDATE '.$pref.'module_tmt_people SET flags=2 WHERE id=? AND flags!=2 AND displayorder'.$selector;
	array_unshift($args,$thistid);
	$db->Execute($sql,$args);
	$keeps = array_diff($params['plr_order'],$params['psel']);
	if($keeps)
	{
		//get values for display
		$rows = OrderTeamMembers($db,$thistid);
		$indx = 0;
		foreach($keeps as $key=>$order)
		{
			$rows[$indx]['name'] = $params['plr_name'][$key];
			$rows[$indx]['contact'] = $params['plr_contact'][$key];
			$indx++;
		}
	}
	else
		$rows = array();
	break;
 case 12://export selected member(s)
	$rows = array();
	foreach($params['psel'] as $id)
	{
		$indx = array_search($id,$params['plr_order']);
		$rows[] = array(
		 'name'=>$params['plr_name'][$indx],
		 'contact'=>$params['plr_contact'][$indx]);
	}
	$funcs = new tmtCSV();
	$funcs->TeamsToCSV($this,$bracket_id,$thistid,$rows);
	return;
 default:
	$sql = 'SELECT * FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder ASC';
	$rows = $db->GetAll($sql,array($thistid));
	break;
}

$jsfuncs = array();	//context-specific-code accumulators
$jsloads = array();

if($rows)
{
	$pc = count($rows);
	$newo = $pc + 1;
	$players = array();
	$theme = cmsms()->get_variable('admintheme'); //CMSMS 1.9+
	$rowclass = 'row1';
	$indx = 1;
	$finds = array('/class="(.*)"/','/id=.*\[\]" /'); //for xhtml string cleanup
	$repls = array('class="plr_name $1"',''); //fails if backref first!
	if ($pmod)
	{
		$downtext = $this->Lang('down');
		$uptext = $this->Lang('up');
	}

	foreach($rows as $row)
	{
		$one = new stdClass();
		$one->rowclass = $rowclass;
		if($pmod)
		{
			$tmp = $this->CreateInputText($id,'plr_name[]',$row['name'],25,64);
			$one->input_name = preg_replace($finds,$repls,$tmp);
			$one->input_contact = $this->CreateInputText($id,'plr_contact[]',$row['contact'],30,80);
			$ord = ($row['displayorder']) ? (int)$row['displayorder'] : $newo++;
			//need input-objects that look like page-links here, to get all form parameters upon their activation
			if($indx > 1)
				$one->uplink = $this->CreateInputLinks($id,'moveup['.$ord.']','arrow-u.gif',FALSE,
					$uptext);
			else
				$one->uplink = '';
			if($indx < $pc)
				$one->downlink = $this->CreateInputLinks($id,'movedown['.$ord.']','arrow-d.gif',FALSE,
					$downtext);
			else
				$one->downlink = '';
			$one->deletelink = $this->CreateInputLinks($id,'delete['.$ord.']','delete.gif',FALSE,
				$this->Lang('deleteplayer')); //confirm via modal dialog
			//hiddens in table-row to support re-ordering and ajax manipulation
			$one->hidden = $this->CreateInputHidden($id,'plr_order[]',$ord,'class="ord"').
				$this->CreateInputHidden($id,'plr_flags[]',$row['flags']);
		}
		else
		{
			$one->input_name = $row['name'];
			$one->input_contact = $row['contact'];
		}
		$one->selected = $this->CreateInputCheckbox($id,'psel[]',$row['displayorder'],-1,'class="pagecheckbox"');
		$players[] = $one;
		($rowclass=='row1'?$rowclass='row2':$rowclass='row1');
		$indx++;
	}
	$smarty->assign('items',$players);

	if($pmod)
	{
		$jsloads[] = <<< EOS
 teamtable.find('.plr_delete').children().modalconfirm({
  overlayID: 'confirm',
  preShow: function(d){
   var name = \$(this).closest('tr').find('.plr_name').attr('value');
   var para = d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('confirm_delete','%s')}'.replace('%s',name);
  }
 });

EOS;
	}
}
else
	$pc = 0;

$smarty->assign('pc',$pc);

if($pc > 1)
{
	if($pmod)
	{
		//setup some ajax-parameters - partial data for tableDnD::onDrop
		$url = $this->CreateLink($id,'order_team',NULL,NULL,array('team_id'=>$thistid,'neworders'=>''),NULL,TRUE);
		$offs = strpos($url,'?mact=');
		$ajfirst = str_replace('amp;','',substr($url,$offs+1));
		$jsfuncs[] = <<< EOS
function ajaxData(droprow,dropcount) {
 var orders = [];
 $(droprow.parentNode).find('.ord').each(function(){
  orders[orders.length] = this.value;
 });
 var ajaxdata = '$ajfirst'+orders.join();
 return ajaxdata;
}
function dropresponse(data,status) {
 if(status == 'success' && data) {
  var i = 1;
  var teamtable = $('#team');
  teamtable.find('.ord').each(function(){\$(this).attr('value',i++);});
  var name;
  var oddclass = 'row1';
  var evenclass = 'row2';
  i = true;
  teamtable.trigger('update').find('tbody tr').each(function() {
	name = i ? oddclass : evenclass;
	\$(this).removeClass().addClass(name);
	i = !i;
  });
 } else {
  $('#page_tabs').prepend('<p style=\"font-weight:bold;color:red;\">{$this->Lang('err_ajax')}!</p><br />');
 }
}

EOS;
		$jsloads[] = <<< EOS
 teamtable.addClass('table_drag').tableDnD({
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

	$onsort = <<< EOS
function () {
 var orders = [];
 $(this).find('tbody tr td').children('.ord').each(function(){
  orders[orders.length] = this.value;
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
	$('#team').find('.ord').each(function(){\$(this).attr('value',i++);});
   } else {
    $('#page_tabs').prepend('<p style="font-weight:bold;color:red;">{$this->Lang('err_ajax')}!</p><br />');
   }
  }
 });
}
EOS;
	}
	else //!$pmod
		$onsort = 'null'; //no mods >> do nothing after sorting


	$jsloads[] = <<< EOS
 $.SSsort.addParser({
  id: 'textinput',
  is: function(s,node) {
   var n = node.childNodes[0];
   return (n && n.nodeName.toLowerCase() == 'input' && n.type.toLowerCase() == 'text');
  },
  format: function(s,node) {
   return $.trim(node.childNodes[0].value);
  },
  watch: true,
  type: 'text'
 });
 teamtable.addClass('table_sort').SSsort({
  sortClass: 'SortAble',
  ascClass: 'SortUp',
  descClass: 'SortDown',
  oddClass: 'row1',
  evenClass: 'row2',
  oddsortClass: 'row1s',
  evensortClass: 'row2s',
  onSorted: $onsort
 });

EOS;
	$jsfuncs[] = <<< EOS
function select_all_players() {
 var st = $('#playsel').attr('checked');
 if(!st) st = false;
 $('#team > tbody').find('input[type="checkbox"]').attr('checked',st);
}

EOS;
	$smarty->assign('selectall',$this->CreateInputCheckbox($id,'p',FALSE,-1,
		'id="playsel" onclick="select_all_players();"'));
} //end $pc > 1

$jsfuncs[] = <<< EOS
function player_selected() {
 var cb = $('#team > tbody').find('input:checked');
 return(cb.length > 0);
}

EOS;

if($pmod)
{
	//for popup confirmation
	$smarty->assign('no',$this->Lang('no'));
	$smarty->assign('yes',$this->Lang('yes'));

	if($pc > 1)
	{
		$smarty->assign('dndhelp',$this->Lang('help_dnd'));
		$smarty->assign('delete',$this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
			'title="'.$this->Lang('delete_tip').'"'));
		$t = ($isteam) ? $this->Lang('sel_teams') : $this->Lang('sel_players');
		$t = $this->Lang('confirm_delete',$t);
		$jsloads[] = <<< EOS
 $('#{$id}delete').modalconfirm({
  overlayID: 'confirm',
  doCheck: player_selected,
  preShow: function(d){
   var para = d.children('p:first')[0];
   para.innerHTML = '{$t}';
  }
 });

EOS;
	}
	if($isteam || $pc == 0)
	{
		//need input-object that looks like page-link, to get all form parameters upon activation
		$smarty->assign('add',$this->CreateInputLinks($id,'addplayer','newobject.gif',TRUE,
			$this->Lang('title_add',strtolower($this->Lang('title_player')))));
	}
	$smarty->assign('submit',$this->CreateInputSubmit($id,'submit',$this->Lang('save')));
	$smarty->assign('cancel',$this->CreateInputSubmit($id,'cancel',$this->Lang('cancel')));

	switch($op)
	{
	 case 2:
	 case 12: //maybe changed since export done
		$test = 'null'; //TODO check for any change e.g. ajax
		break;
	 default:
		$test = 'null'; //ask
	  	break;
	}
	//onCheckFail: true; means submit form if no check needed
	$jsloads[] = <<< EOS
 $('#{$id}cancel').modalconfirm({
  overlayID: 'confirm',
  doCheck: {$test},
  preShow: function(d){
   var para = d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('abandon')}';
  },
  onCheckFail: true
 });

EOS;
}
else
	$smarty->assign('cancel',$this->CreateInputSubmit($id,'cancel',$this->Lang('close')));

if($pc > 0)
	$smarty->assign('export',$this->CreateInputSubmit($id,'export',$this->Lang('export'),
		'title="'.$this->Lang('export_tip').'" onclick="return player_selected();"'));

$smarty->assign('hidden',$hidden);

$smarty->assign('incpath',$this->GetModuleURLPath().'/include/');

if($jsloads)
{
	$jsfuncs[] = '
$(document).ready(function() {
 var teamtable = $(\'#team\');
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$smarty->assign('jsfuncs',$jsfuncs);

echo $this->ProcessTemplate('addedit_team.tpl');

?>
