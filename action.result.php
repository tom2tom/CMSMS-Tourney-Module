<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Action to setup for reporting, and later, actually report, the status of a competition
*/
$bracket_id = $params['bracket_id'];
$pref = cms_db_prefix();
$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
$bdata = $db->GetRow($sql,array($bracket_id));
$tplvars = array();
if (!empty($params['send']))
{
	$valid = TRUE;
	if (isset($params['captcha']))
	{
		$ob = cms_utils::get_module('Captcha');
		if ($ob)
		{
			$valid = $ob->checkCaptcha($params['captcha']);
			unset($ob);
		}
	}
	if ($valid)
	{
		if (isset($params['match']))
		{
			$sql = 'SELECT teamA,teamB,playwhen,place FROM '.$pref.'module_tmt_matches WHERE match_id=? AND flags=0';
			$mdata = $db->GetRow($sql,array($params['match']));
			$tA = $this->TeamName($mdata['teamA']);
			$tB = $this->TeamName($mdata['teamB']);
//	tmtUtils()?
			$relations = $this->ResultTemplates($bracket_id,FALSE);

			$indx = array_search($params['match'],$params['shown'],false);
			$res = (int)$params['result'][$indx];
			switch ($res)
			{
			 case Tourney::WONA:
				$tres = str_replace('%s',$tA,$relations['won']);
				break;
			 case Tourney::WONB:
				$tres = str_replace('%s',$tB,$relations['won']);
				break;
			 case Tourney::FORFA:
				$tres = str_replace('%s',$tA,$relations['forf']);
				break;
			 case Tourney::FORFB:
				$tres = str_replace('%s',$tB,$relations['forf']);
				break;
			 case Tourney::MTIED:
				$tres = sprintf($relations['tied'],$tA,$tB);
				break;
			 case Tourney::NOWIN:
				$tres = sprintf($relations['nomatch'],$tA,$tB);
				break;
			 default:
			 	$valid = FALSE;
				break;
			}
			if ($valid)
			{
				$save = FALSE;
				$ob = cms_utils::get_module('FrontEndUsers');
				if ($ob)
				{
					$uid = $ob->LoggedInID();
					if ($uid !== FALSE)
					{
						$t = $bdata['feu_editgroup'];
						if ($t == 'any')
							$save = TRUE;
						elseif ($t != 'none')
						{
							$gid = $ob->GetGroupID($t);
							$save = $ob->MemberOfGroup($uid,$gid);
						}
						if($save)
							$by = $ob->GetUserName($uid); //default
					}
					unset($ob);
				}
				$dt = new DateTime($mdata['playwhen'],new DateTimeZone($bdata['timezone']));

				$name = sprintf($relations['vs'],$tA,$tB);
				$body = array($name,'',$tres,'');
				$score = $params['score'][$indx];
				if (!$score)
				{
					if ($res == Tourney::WONA || $res == Tourney::WONB)
						$score = $this->Lang('missing');
				}
				if ($score)
				{
					$body[] = $this->Lang('score').' '.$score;
					$body[] = '';
				}
				if ($res == Tourney::WONA || $res == Tourney::WONB)
				{
					$when = $params['when'][$indx];
					if ($when)
					{
						$ends = date_parse($when);
						if($ends && $ends['error_count'] == 0)
						{
							$starts = getdate($dt->getTimestamp());
							if(!$ends['year']) $ends['year'] = $starts['year'];
							if(!$ends['month']) $ends['month'] = $starts['mon'];
							if(!$ends['day']) $ends['day'] = $starts['mday'];
							if(!$ends['hour']) $ends['hour'] = $starts['hours'];
							if(!$ends['minute'])
							{
								$ends['minute'] = $starts['minutes'];
								$offs = 1800; //arbitrary 30-min increment
							}
							else
								$offs = 0;
							$dt->setDate($ends['year'],$ends['month'],$ends['day']);
							$dt->setTime($ends['hour'],$ends['minute']);
							$when = strftime('%F %R',$dt->getTimestamp()+$offs);
							$finished = $when;
						}
						else
							$when = FALSE;
					}
					if(!$when)
					{
						$when = $mdata['playwhen']; //default to scheduled start time
						$finished = $this->Lang('missing');
					}
					$body[] = $this->Lang('title_when').' '.$finished;
					$body[] = '';
				}
				if ($params['comment'])
				{
					$body[] = $params['comment']; //no added line-breaks
					$body[] = '';
				}
				if ($params['sender'])
					$body[] = $this->Lang('reporter',$params['sender']);
				if($save)
				{
					$body[] = '';
					$body[] = $this->Lang('processed');
				}

				$funcs = new tmtComm($this);
				list($res,$errmsg) = $funcs->TellOwner($bracket_id,$params['match'],$body);
				if($res)
					$msg = $this->Lang('sentok');
				else
				{
					$msg = $this->Lang('notsent');
					if($errmsg)
						$msg .= '<br />'.$errmsg;
				}

				if($save)
				{
					$sql = 'UPDATE '.$pref.'module_tmt_matches SET playwhen=?, score=?,	status=? WHERE match_id=?';
					$db->Execute($sql,array($when,$score,$res,$params['match']));
					$t = $db->GenID($pref.'module_tmt_history_seq');
					if($params['sender'])
						$by = $params['sender'];
					$dt->modify('now');
					$when = strftime('%F %T',$dt->getTimestamp());
					$sql = 'INSERT INTO '.$pref.
					'module_tmt_history (history_id,bracket_id,changer,changewhen,olddata,newdata,comment VALUES (?,?,?,?,?,?,?)';
					$db->Execute($sql,array($t,$bracket_id,$by,$when,NULL,$tres,$this->Lang('match_added',$params['match'])));

					$funcs = new tmtSchedule();
					$funcs->ScheduleMatches($this,$bracket_id);
				}

				$params = array(
					'bracket_id'=>$bracket_id,
					'view'=>$params['view'],
					'message'=>$msg
				);
				$this->RedirectForFrontEnd($id,$returnid,'default',$params);
			}
			else
				$key = 'err_noresult2';
		}
		else
			$key = 'err_nomatch';
	}
	else
		$key = 'err_captcha';

	$tplvars['message'] = $this->Lang($key);
	//fall into repeat presentation
}

$tplvars['title'] = $bdata['name'];
if ($bdata['description'])
	$desc = $bdata['description'].'<br /><br />';
else
	$desc = '';
$desc .= $this->Lang('title_scored');
$tplvars['description'] = $desc;

//script accumulators
$jsfuncs = array();
$jsloads = array();

$sql = 'SELECT match_id,teamA,teamB FROM '.$pref.'module_tmt_matches WHERE bracket_id=? AND flags=0 AND status<'.
 Tourney::ANON.' AND teamA IS NOT NULL AND teamA!=-1 AND teamB IS NOT NULL AND teamB!=-1';
$mdata = $db->GetAssoc($sql,array($bracket_id));
if ($mdata)
{
	$def = $this->Lang('chooseone');
//	tmtUtils()?
	$relations = $this->ResultTemplates($bracket_id,FALSE);
	$name = 'match'; //becomes a returned parameter
	$indx = 0;
	$matches = array();

	foreach($mdata as $mid=>$teams)
	{
		$one = new stdClass();
		$tA = $this->TeamName($teams['teamA']);
		$tB = $this->TeamName($teams['teamB']);
		$lbl = sprintf($relations['vs'],$tA,$tB);
		$indx++;
		//radio button, plus hidden shown[] (NOT 'list[]' or 'view[]'!) to return all row indices so can get index of selected row
		$one->button = '<input type="hidden" name="'.$id.'shown[]" value="'.$mid.'">'.
		'<input class="mradio" type="radio" name="'.$id.$name.'" id="'.$id.$name.$indx.'" value="'.$mid.'" /><label for="'.$id.$name.$indx.'"> '.$lbl.'</label>';
		$choices = array(
			$def=>-1,
			str_replace('%s',$tA,$relations['won'])=>Tourney::WONA,
			str_replace('%s',$tB,$relations['won'])=>Tourney::WONB,
			str_replace('%s',$tA,$relations['forf'])=>Tourney::FORFA,
			str_replace('%s',$tB,$relations['forf'])=>Tourney::FORFB,
		);
		if ($bdata['cantie'])
			$choices[$bdata['tied']]=Tourney::MTIED;
		$choices[$bdata['nomatch']]=Tourney::NOWIN;

		$one->chooser = $this->CreateInputDropdown($id,'result[]',$choices,'',-1);
		$one->score = $this->CreateInputText($id,'score[]','',10,30);
		$one->when = $this->CreateInputText($id,'when[]','',10,20);
		$matches[] = $one;
	}
	$tplvars += array(
		'matches' => $matches,
		'titleresult' => $this->Lang('title_result'),
		'titlescore' => $this->Lang('score'),
		'titlewhen' => $this->Lang('title_when'),
		'titlesender' => $this->Lang('title_sender'),
		'inputsender' => $this->CreateInputText($id,'sender','',15,30),
		'titlecomment' => $this->Lang('title_comment'),
		'inputcomment' => $this->CreateTextArea(FALSE,$id,'','comment','shortarea','','','',50,5)
	);
	$ob = cms_utils::get_module('Captcha');
	if ($ob)
	{
		$tplvars += array(
			'captcha' => $ob->getCaptcha(),
			'titlecaptcha' => $this->Lang('title_captcha'),
			'inputcaptcha' => $this->CreateInputText($id,'captcha','',5,10)
		);
	}

	$jsloads[] = <<< EOS
 $('.seeblock').css('display','none');
 $('.mradio').click(function() {
  $('.seeblock').css('display','none');
  \$(this).parent().next().css('display','block');
 });

EOS;
	$jsfuncs[] = <<< EOS
function eventCancel(ev) {
 if (!ev) {
  if (window.event) ev = window.event;
  else return;
 }
 if (ev.cancelBubble !== null) ev.cancelBubble = true;
 if (ev.stopPropagation) ev.stopPropagation();
 if (ev.preventDefault) ev.preventDefault();
 if (window.event) ev.returnValue = false;
 if (ev.cancel !== null) ev.cancel = true;
}
function showerr(msg) {
 $('#localerr').html(msg).css('display','block');
}

EOS;
	if($bdata['logic'])
	{
		$jsfuncs[] = <<< EOS
function customvalidate() {
 {$bdata['logic']}
 return true;
}

EOS;
		$xjs = <<< EOS
  else if (!customvalidate()) {
   ok = false;
  }
EOS;
	}
	else //no logic
		$xjs = '';

	$funcstr = <<< EOS
function validate(ev,btn) {
 $('#syserr').css('display','none');
 var ok = true;
 var sel = $('input:checked');
 if ($(sel).length == 0) {
  showerr('{$this->Lang('err_nomatch')}');
  ok = false;
 } else {
  var div = $(sel[0]).parent().next();
  var res = $(div).children('.cms_dropdown').val();
  var when = $(div).find('input[name="{$id}when[]"]').val();
  switch (res) {
   case '-1':
    showerr('{$this->Lang('err_noresult')}');
    ok = false;
    break;
   case '%s':
   case '%s':
   case '%s':
    if (when == '') {
     ok = false;
    } else {
     var p = /^((0?[1-9]|1[012])( *: *[0-5]\d){0,2} *[apAP][mM]|([01]\d|2[0-3])( *: *[0-5]\d){0,2})$/;
	 ok = (when.trim()).match(p);
     if (!ok) {
	  ok = !isNaN(Date.parse(when));
     }
    }
    if (!ok)
      showerr('{$this->Lang('err_notime')}');
   default:
    break;
  }
  if (ok) {
   if ($('#{$id}sender').val() == '') {
   showerr('{$this->Lang('err_nosender')}');
   ok = false;
   }
   else if ($('{$id}captcha').val() == '') {
   showerr('{$this->Lang('err_nocaptcha')}');
   ok = false;
   }
  }$xjs
 }
 if (ok) {
  set_action(btn);
  return true;
 } else {
  eventCancel(ev);
  return false;
 }
}

EOS;
	$jsfuncs[] = sprintf($funcstr,Tourney::WONA,Tourney::WONB,Tourney::MTIED);
}
else //no mdata
	$tplvars['nomatches'] = $this->Lang('info_nomatch'); //TODO better message for frontend

$tplvars['hidden'] =
	$this->CreateInputHidden($id,'bracket_id', $bracket_id).
	$this->CreateInputHidden($id,'view', $params['view']).
	$this->CreateInputHidden($id,'real_action','nosend');
$tplvars['start_form'] = $this->CreateFormStart($id,'result',$returnid);
$tplvars['end_form'] = $this->CreateFormEnd();

$tplvars['send'] =  $this->CreateInputSubmitDefault($id,'send',$this->Lang('submit'),
	'onclick="return validate(event,this)"');
//'cancel' action-name is used by other form(s)
$tplvars['cancel'] =  $this->CreateInputSubmit($id,'nosend',$this->Lang('cancel'));

if($jsloads)
{
	$jsfuncs[] = '
$(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] =  $jsfuncs;

tmtTemplate::Process($this,'result_report.tpl',$tplvars);
?>
