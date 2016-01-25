<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
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

if (!$this->CheckAccess('admod'))
	exit ($this->Lang('lackpermission'));

if (isset($params['cancel']))
	$this->Redirect($id, 'addedit_comp', $returnid, $this->GetEditParms($params,'resultstab'));

$fn = $id.'csvfile';
if (isset($_FILES) && isset($_FILES[$fn]))
{
	$file_data = $_FILES[$fn];
	$parts = explode('.',$file_data['name']);
	$ext = end($parts);
//tmtUtils()?
	if ($file_data['type'] != 'text/csv'
	 || !($ext == 'csv' || $ext == 'CSV')
     || $file_data['size'] <= 0 || $file_data['size'] > 4096
     || $file_data['error'] != 0)
	{
		$newparms = $this->GetEditParms($params,'resultstab',$this->PrettyMessage('err_file',FALSE));
		$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
	}

	$handle = fopen($file_data['tmp_name'],'r');
	if (!$handle )
	{
		$newparms = $this->GetEditParms($params,'resultstab',$this->PrettyMessage('lackpermission',FALSE));
		$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
	}
	$bad = FALSE;
	//some basic validation of file-content
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
		$namecols = array(); //[0]=bracketcol or missing, [1]=matchcol or missing, [2]=teamAcol or missing
		//[3]=teamBcol or missing, [4]=resultcol or missing, [5]=scorecol or missing, [6]=finishcol or missing
		foreach (array('Bracket','Match','CompetitorA','CompetitorB','Result','Score','Finished') as $i=>$custom)
		{
			$col = array_search($custom, $firstline);
			if ($col !== FALSE)
			{
				if ($col < 7)
					$namecols[$i] = $col;
				else
				{
					$bad = TRUE;
					break;
				}
			}
		}
	}
	if ($bad)
	{
		$newparms = $this->GetEditParms($params,'playerstab',$this->PrettyMessage('err_file',FALSE));
		$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
	}


	$pref = cms_db_prefix();
	$added = array();
	while(!feof($handle))
	{
		$imports = GetSplitLine($handle);
		if ($imports)
		{
/*TODO interpret & store imported data, setup successor matches competitors
bracket-field must contain an adequate identifier - the relevant id-number, alias, or title.
match-field should contain the relevant id-number, or else if it's empty, an attempt will be made to identify the relevant match from the supplied competitor-names.
competitor-fields must identify the competitors, matching exactly the names displayed in bracket data.
result-field must identify which of the competitors (if any) prevailed, by 'win A', 'win B', 'draw', 'tie', or 'abandon' (no quotes, not translated)
finished-field may be a time (as HH:MM) in which case the day will be assumed to be the scheduled day. Otherwise a full datestamp (as YYYY-MM-DD HH:MM) may be provided. Or if empty, an approximate date/time will be determined.*/
		}
	}
	fclose($handle);

	$newparms = $this->GetEditParms($params,'resultstab');
	$this->Redirect($id, 'addedit_comp', $returnid, $newparms);
}

$tplvars = array(
	'start_form' => $this->CreateFormStart($id, 'import_result', $returnid, 'post','multipart/form-data'),
	'end_form' => $this->CreateFormEnd(),
	'hidden' => $this->GetHiddenParms($id,$params,'resultstab'),
	'title' => $this->Lang('title_resultimport',$params['tmt_name']),
	'chooser' => $this->CreateInputFile($id, 'csvfile', 'text/csv', 25),
	'apply' => $this->CreateInputSubmitDefault($id, 'import', $this->Lang('upload')),
	'cancel' => $this->CreateInputSubmit($id, 'cancel', $this->Lang('cancel')),
	'help' => $this->Lang('help_resultimport')
);

tmtTemplate::Process($this,'onepage.tpl',$tplvars);
?>
