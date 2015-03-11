<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
Functions involved with XML output
*/

class tmtXML
{
	/**
	SaveXML:
	@mod: reference to Tourney module
	@detail: identifier-detail for inclusion in name of saved file
	@date: for inclusion in name of saved file
	@charset: character-encoding for @content
	@content: string to be saved
	*/
	private function SaveXML(&$mod,$detail,$date,$charset,$content)
	{
		$fn = $mod->Lang('tournament').'-'.$detail.'-'.$date;
		@ob_clean();
		header('Pragma: public');
		header('Expires: 0');
		header('Cache-Control: must-revalidate,post-check=0,pre-check=0');
		header('Cache-Control: private',FALSE);
		header('Content-Description: File Transfer');
		//note: some older HTTP/1.0 clients did not deal properly with an explicit charset parameter
		header('Content-Type: text/xml'.$charset);
		header('Content-Length: '.strlen($content));
		header('Content-Disposition: attachment; filename="'.$fn.'.xml"');
		
		echo $content;
	}

	/**
	CreateXML:
	@mod: reference to Tourney module
	@bracket_id: single bracket identifier, or array of them
	@date: date string for inclusion in the content
	@charset: optional, name of content encoding, default = FALSE
	*/
	private function CreateXML(&$mod,$bracket_id,$date,$charset = FALSE)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
		if (!is_array($bracket_id))
		{
			$properties = $db->GetRow($sql,array($bracket_id));
			$bracket_id = array($bracket_id);
		}
		else
		{
			//use bracket-data from first-found
			foreach ($bracket_id as $thisid)
			{
				$properties = $db->GetRow($sql,array($thisid));
				if($properties != FALSE)
					break;
			}
		}
		if($properties == FALSE)
			return FALSE;

		$header = TRUE; //onetime flag for header construction
		$xml = array();
		$sql = 'SELECT * FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2 ORDER BY team_id';
		$sql2 = 'SELECT P.* FROM '.$pref.'module_tmt_people P JOIN '.$pref.
			'module_tmt_teams T ON P.id = T.team_id WHERE T.bracket_id=? AND T.flags!=2 AND P.flags!=2 ORDER BY P.id,P.displayorder';
		$sql3 = 'SELECT * FROM '.$pref.'module_tmt_matches WHERE bracket_id=? ORDER BY match_id';

		foreach($bracket_id as $thisid)
		{
			$teams = $db->GetAll($sql,array($thisid));
			if($teams)
			{
				$people = $db->GetAll($sql2,array($thisid));
				$matches = $db->GetAll($sql3,array($thisid));
			}
			else
			{
				$people = FALSE;
				$matches = FALSE;
			}

			if($header)
			{
				if($charset)
					$xml[] = '<?xml version="1.0" encoding="'.strtoupper($charset).'"?>';
				else
					$xml[] = '<?xml version="1.0"?>';
				$xml[] = '<!DOCTYPE tourney [';
				$xml[] = '<!ELEMENT tourney (version,date,count,bracket)>';
				$xml[] = '<!ELEMENT version (#PCDATA)>';
				$xml[] = '<!ELEMENT date (#PCDATA)>';
				$xml[] = '<!ELEMENT count (#PCDATA)>';
				$xml[] = '<!ELEMENT bracket (properties,teams,people,matches)>';
				$fields1 = array_keys($properties);
				$xml[] = '<!ELEMENT properties ('.implode(',',$fields1).')>';
				foreach($fields1 as $thisfield)
					$xml[] = "<!ELEMENT $thisfield (#PCDATA)>";
				if($teams)
				{
					$fields2 = array_keys($teams[0]);
					$xml[] = '<!ELEMENT teams (team)>';
					$xml[] = '<!ELEMENT team ('.implode(',',$fields2).')>';
					foreach($fields2 as $thisfield)
						$xml[] = "<!ELEMENT $thisfield (#PCDATA)>";
					if($people)
					{
						$fields3 = array_keys($people[0]);
						$xml[] = '<!ELEMENT people (person)>';
						$xml[] = '<!ELEMENT person ('.implode(',',$fields3).')>';
						foreach($fields3 as $thisfield)
							$xml[] = "<!ELEMENT $thisfield (#PCDATA)>";
					}
					if($matches)
					{
						$fields4 = array_keys($matches[0]);
						$xml[] = '<!ELEMENT matches (match)>';
						$xml[] = '<!ELEMENT match ('.implode(',',$fields4).')>';
						foreach($fields4 as $thisfield)
							$xml[] = "<!ELEMENT $thisfield (#PCDATA)>";
					}
				}
				$xml[] = ']>';
				$xml[] = '<tourney>';
				$xml[] = "\t<version>".$mod->GetVersion().'</version>';
				$xml[] = "\t<date>".$date.'</date>';
				$xml[] = "\t<count>".count($bracket_id).'</count>';
				$header = FALSE;
			}

			$xml[] = "\t<bracket>";
			//main-table fieldnames are not namespaced
			$xml[] = "\t\t<properties>";
			foreach($fields1 as $thisfield)
				$xml[] = "\t\t\t<$thisfield>".$properties[$thisfield]."</$thisfield>";
			$xml[] = "\t\t</properties>";

			//to avoid conflicts, other-table fieldnames are namespaced
			$xml[] = "\t\t<t:teams xmlns:t=\"file:///teams\">";
			if($teams)
			{
				foreach($teams as $thisteam)
				{
					$xml[] = "\t\t\t<team>";
						foreach($fields2 as $thisfield)
							$xml[] = "\t\t\t\t<t:$thisfield>".$thisteam[$thisfield]."</t:$thisfield>";
					$xml[] = "\t\t\t</team>";
				}
			}
			$xml[] = "\t\t</t:teams>";
			$xml[] = "\t\t<h:people xmlns:h=\"file:///humans\">";
			if($people)
			{
				foreach($people as $thisone)
				{
					$xml[] = "\t\t\t<person>";
						foreach($fields3 as $thisfield)
							$xml[] = "\t\t\t\t<h:$thisfield>".$thisone[$thisfield]."</h:$thisfield>";
					$xml[] = "\t\t\t</person>";
				}
			}
			$xml[] = "\t\t</h:people>";
			$xml[] = "\t\t<m:matches xmlns:m=\"file:///matches\">";
			if($matches)
			{
				foreach($matches as $thismatch)
				{
					$xml[] = "\t\t\t<match>";
						foreach($fields4 as $thisfield)
							$xml[] = "\t\t\t\t<m:$thisfield>".$thismatch[$thisfield]."</m:$thisfield>";
					$xml[] = "\t\t\t</match>";
				}
			}
			$xml[] = "\t\t</m:matches>";
			$xml[] = "\t</bracket>";
		}
		$xml[] = '</tourney>';

		return implode("\n",$xml);
	}

	/**
	ExportXML:
	@mod: reference to Tourney module
	@bracket_id: single bracket identifier, or array of them
	Returns TRUE if successful, otherwise a Lang-key related to the error
	*/
	function ExportXML(&$mod,$bracket_id)
	{
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		$sql = 'SELECT alias,timezone FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
		if (!is_array($bracket_id))
		{
			$bdata = $db->GetRow($sql,array($bracket_id));
		}
		else
		{
			//use data from first-found
			foreach ($bracket_id as $thisid)
			{
				$bdata = $db->GetRow($sql,array($thisid));
				if($bdata != FALSE)
					break;
			}
		}
		if($bdata == FALSE)
			return 'err_missing';
		//if necessary and possible, we will convert exported data from site-encoding
		//to UTF-8, cuz php's xml-parser doesn't support many codings
		$config = cmsms()->GetConfig();
		if(!empty($config['default_encoding']))
		{
			$defchars = trim($config['default_encoding']);
			$convert = ($defchars && strcasecmp($defchars,'UTF-8') != 0);
		}
		else
		{
			$defchars = FALSE; //we will assume this means UTF-8
			$convert = FALSE;
		}
		if($convert && ini_get('mbstring.internal_encoding') === FALSE) //conversion not possible
		{
			if($defchars)
				$defchars = FALSE;
			$convert = FALSE;
		}

		$dt = new DateTime('now',new DateTimeZone($bdata['timezone']));
		$xmlstr = self::CreateXML($mod,$bracket_id,$dt->format('Y-m-d'),$defchars);

		if($xmlstr)
		{
			if (!is_array($bracket_id) || count($bracket_id) == 1)
				$detail = ($bdata['alias']) ? preg_replace('/\W/','_',$bdata['alias']) : $bracket_id;
			else
				$detail = 'brackets~'.implode('~',$bracket_id);

			if($convert)
			{
				ini_set('mbstring.substitute_character','none'); //strip illegal chars
				$xmlstr = mb_convert_encoding($xmlstr,'UTF-8',$defchars);
				$defchars = '; charset=utf-8';
			}
			else if(!$defchars)
				$defchars = '; charset=utf-8';
			else
				$defchars = '';

			self::SaveXML($mod,$detail,$dt->format('Y-m-d-G-i'),$defchars,$xmlstr);
			return TRUE;
		}
		else
			return 'err_export';
	}

}

?>
