<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
Functions involved with CSV output
*/

class tmtCSV
{
	/**
	Save:
	@mod: reference to Tourney module
	@bdata: reference to array of brackets-table data
	@charset: character-encoding for @content
	@content: string to be saved
	*/
	private function Save(&$mod,&$bdata,$charset,$content)
	{
		$detail = ($bdata['alias']) ? preg_replace('/\W/','_',$bdata['alias']) : $bdata['bracket_id'];
		$dt = new DateTime('now',new DateTimeZone($bdata['timezone']));
		$fn = $mod->Lang('title_teams').'-'.$detail.'-'.$dt->format('Y-m-d-G-i');
		header('Pragma: public');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Cache-Control: private',FALSE);
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.$fn.'.csv"');
		header('Content-Transfer-Encoding: binary'); 

		echo $content;
	}

	/**
	TeamTitlesForCSV:
	@count: no. of players in each team
	Returns: array of strings to represent columns in 1st line of team(s) .csv file,
	  empty if $count == FALSE
	*/
	private function TeamTitles($count)
	{
		if($count)
		{
			$ret = array('##Teamname','#Seeded');
			if($count > 1)
				$ret[] = '#Tellall';
			for($i = 1; $i <= $count; $i++)
				array_push($ret,'Player'.$i,'Contact'.$i);
			return $ret;
		}
		return array();
	}

	/**
	TeamsToCSV:
	Constructs a CSV string for specified/all teams belonging to bracket @bracket_id, and exports it
	or writes it progressively to the file associated with @fp (which must be opened and closed upstream)
	To avoid field-corruption,existing separators in headings or data are converted to something else,
	generally like &#...;(except when the separator is '&','#' or ';',those become %...%)
	Parameters:
	@mod reference to current Tourney module object
	@bracket_id index of competition to be processed
	@team_id optional index of a single team to process, or array of such indices,
	 or FALSE to process the whole bracket, default=FALSE
	@members: optional array of persons in @team_id to process, each member
 	 of the array being an array('name'=>,'contact'=>) default FALSE
	@fp optional handle of open file, if writing data to disk, or FALSE
	 if saving directly, default = FALSE
	@sep optional field-separator in output data, assumed single-byte ASCII, default = ','
	Returns: TRUE, or FALSE on error
	*/
	public function TeamsToCSV(&$mod,$bracket_id,$team_id=FALSE,$members=FALSE,$fp=FALSE,$sep=',')
	{
		$config = cmsms()->GetConfig();
		if(!empty($config['default_encoding']))
			$defchars = trim($config['default_encoding']);
		else
			$defchars = 'UTF-8';
		if(ini_get('mbstring.internal_encoding') !== FALSE) //conversion is possible
		{
			$expchars = $mod->GetPreference('export_encoding','UTF-8');
			$convert = (strcasecmp($expchars,$defchars) != 0);
		}
		else
			$convert = FALSE;

		$sep2 = ($sep != ' ')?' ':',';
		switch($sep)
		{
		 case '&':
			$r = '%38%';
			break;
		 case '#':
			$r = '%35%';
			break;
		 case ';':
			$r = '%59%';
			break;
		 default:
			$r = '&#'.ord($sep).';';
			break;
		}

		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
		$bdata = $db->GetRow($sql,array($bracket_id));
		if($bdata['teamsize'])
			$count = (int)$bdata['teamsize'];
		else
			$count = 1;
		//heading line
		$names = self::TeamTitles($count);
		if($names)
			$outstr = implode($sep,$names)."\n";
		else
			$outstr = '';

		if(!is_array($team_id))
			$vals = array($team_id);
		elseif($team_id === FALSE)
		{
			$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2 ORDER BY displayorder';
			$vals = $db->GetCol($sql,array($bracket_id));
		}
		else
			$vals = $team_id;

		$sql = 'SELECT name,seeding,contactall FROM '.$pref.'module_tmt_teams WHERE team_id=?';
		if($members === FALSE)
			$sql2 = 'SELECT	name,contact FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder';
		//data line(s)
		foreach($vals as $thisval)
		{
			$tdata = $db->GetRow($sql,array($thisval));
			$outstr .= str_replace($sep,$r,$tdata['name']);
			$outstr .= $sep;
			if($tdata['seeding'])
				$outstr .= (int)$tdata['seeding'];
			if($count > 1)
			{
				if($tdata['contactall'])
					$outstr .= $sep.'yes';
				else
					$outstr .= $sep.'no';
			}
			if($members === FALSE)
				$members = $db->GetAll($sql2,array($thisval));
			//doesn't matter if no of recorded people != expected teamsize
			foreach($members as $pers)
				$outstr .= $sep.str_replace($sep,$r,trim($pers['name']))
				.$sep.str_replace($sep,$r,trim($pers['contact']));
			$outstr .= "\n";
			if($fp)
			{
				if($convert)
				{
					$conv = mb_convert_encoding($outstr,$expchars,$defchars);
					fwrite($fp,$conv);
					unset($conv);
				}
				else
					fwrite($fp,$outstr);
				$outstr = '';
			}
		}

		if(!$fp)
		{
			if($convert)
			{
				$conv = mb_convert_encoding($outstr,$expchars,$defchars);
				self::Save($mod,$bdata,$expchars,$conv);
				unset($conv);
			}
			else
				self::Save($mod,$bdata,$defchars,$outstr);
		}
		return TRUE;
	}

}

?>
