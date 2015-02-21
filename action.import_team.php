<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if (!function_exists('GetSplitLine'))
{
 function ToChr($match)
 {
 	$st = ($match[0][0] == '&') ? 2:1;
	return chr(substr($match[0],$st,2));
 }

 function GetSplitLine(&$fh)
 {
	do
	{
		$fields = fgetcsv($fh,4096);
		if(is_null($fields) || $fields == FALSE)
			return FALSE;
	} while(count($fields) == 1 && is_null($fields[0])); //blank line
 	//convert any separator supported by tmtCSV::TeamsToCSV()
	foreach ($fields as &$thisfield)
		$thisfield = trim(preg_replace_callback(
			array('/&#\d\d;/','/%\d\d%/'),'ToChr',$thisfield));
	unset($thisfield);
	return $fields;
 }
}

if (isset($params['cancel']))
	$this->Redirect($id, 'addedit_comp', $returnid, $this->GetEditParms($params,'playerstab'));

if (!$this->CheckAccess('admod'))
{
	$newparms = $this->GetEditParms($params,'playerstab',$this->PrettyMessage('lackpermission',FALSE));
	$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
}

$fn = $id.'csvfile';
if (isset($_FILES) && isset($_FILES[$fn]))
{
	$file_data = $_FILES[$fn];
	$parts = explode('.',$file_data['name']);
	$ext = end($parts);
//TODO $type=;	list($min,$max) = $this->GetLimits($type);
	if ($file_data['type'] != 'text/csv'
	 || !($ext == 'csv' || $ext == 'CSV')
     || $file_data['size'] <= 0 || $file_data['size'] > 25600 //$max*1000 (teams may have lots of members)
     || $file_data['error'] != 0)
	{
		$newparms = $this->GetEditParms($params,'playerstab',$this->PrettyMessage('err_file',FALSE));
		$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
	}

	$handle = fopen($file_data['tmp_name'],'r');
	if (!$handle )
	{
		$newparms = $this->GetEditParms($params,'playerstab',$this->PrettyMessage('lackpermission',FALSE));
		$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
	}
	//some basic validation of file-content
	$haveseed = FALSE;
	$havetell = FALSE;
	$bad = FALSE;
	$firstline = GetSplitLine($handle);
	if ($firstline == FALSE)
		$bad = TRUE;
	if (!$bad)
	{
		$num = count($firstline);
		if ($num == 0)
			$bad = TRUE;
	}
	if (!$bad)
	{
		if ($firstline[0] != '##Teamname')
			$bad = TRUE;
		$haveseed = array_search('#Seeded', $firstline);
		if (!$bad && $haveseed && !($haveseed==1||$haveseed==2))
			$bad = TRUE;
		$havetell = array_search('#Tellall', $firstline);
		if (!$bad && $havetell && !($havetell==1||$havetell==2))
			$bad = TRUE;
		$mo = max((int)$haveseed, (int)$havetell);
		if ($mo > 0 && ($num-$mo-1)%2)//want even no. of player-fields
			$bad = TRUE;
		elseif ($mo == 0 && $num%2 == 0)
			$bad = TRUE;
	}
	if ($bad)
	{
		$newparms = $this->GetEditParms($params,'playerstab',$this->PrettyMessage('err_file',FALSE));
		$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
	}

	$mo++; //after last optional field, now index of 1st player-field

	$pref = cms_db_prefix();
	$sql = 'SELECT MAX(displayorder) FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2';
	$teamorder = intval($db->GetOne($sql,array($params['bracket_id']))) + 1;
	//store imported data with added-flags set
	$sql = 'INSERT INTO '.$pref.'module_tmt_teams VALUES (?,?,?,?,?,?,1)';
	$sql2 = 'INSERT INTO '.$pref.'module_tmt_people VALUES (?,?,?,?,1)';
	$added = array();
	while(!feof($handle))
	{
		$imports = GetSplitLine($handle);
		if ($imports)
		{
			//Before the bracket is first saved, $params['bracket_id'] will be some form of FALSE
			//so we fake an id (-"the next real ID") to send back, it will need to be fixed later
			if ($params['bracket_id'] == FALSE)
				$params['bracket_id'] = -$db->GenID($pref.'module_tmt_brackets_seq');
//$imports is array, 0=>teamname[,1|2=>seeding][,1|2=>tellall][,3=>player1,4=>contact1][....] for as many players as there are
			$addid = $db->GenID($pref.'module_tmt_teams_seq');
			$args = array($addid, (int)$params['bracket_id']);
			$args[] = ($imports[0]) ? $imports[0] : null;
			$args[] = ($haveseed && $imports[$haveseed]) ? (int)$imports[$haveseed] : null;
			if ($havetell && $imports[$havetell] &&
				strtolower(trim($imports[$havetell])) != 'no')
					$val = 1;
			else
				$val = 0;
			$args[] = $val;
			$args[] = $teamorder; //almost certainly adjusted later
			$db->Execute($sql,$args);
			if ($num > $mo)
			{
				$order = 1;
				for ($i=$mo; $i<$num; $i+=2)
				{
					$name = ($imports[$i]) ? $imports[$i] : null;
					$contact = ($imports[$i+1]) ? $imports[$i+1] : null;
					if ($name || $contact)
					{
						$args = array($addid,$name,$contact,$order);
						$db->Execute($sql2,$args);
						$order++;
					}
				}
			}
			$added[] = $addid;
			$teamorder++;
		}
	}
	fclose($handle);

	$newparms = $this->GetEditParms($params,'playerstab');
	if ($added)
		$newparms['added_id'] = implode(';',$added);
	$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
}

$smarty->assign('start_form',$this->CreateFormStart($id, 'import_team', $returnid, 'post','multipart/form-data'));
$smarty->assign('end_form',$this->CreateFormEnd());
$smarty->assign('hidden',$this->GetHiddenParms($id,$params,'playerstab'));
$smarty->assign('title',$this->Lang('title_teamimport',$params['tmt_name']));
$smarty->assign('chooser',$this->CreateInputFile($id, 'csvfile', 'text/csv', 25));
$smarty->assign('import', $this->CreateInputSubmitDefault($id, 'import', $this->Lang('upload')));
$smarty->assign('cancel', $this->CreateInputSubmit($id, 'cancel', $this->Lang('cancel')));
$smarty->assign('help',$this->Lang('help_teamimport'));

echo $this->ProcessTemplate('import_team.tpl');
?>
