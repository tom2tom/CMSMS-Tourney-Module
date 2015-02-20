<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

if(!function_exists('ExportXML'))
{
 function ExportXML(&$mod,$bracket_id,$date,$defchars = FALSE)
 {
 	$pref = cms_db_prefix();
 	$db = cmsms()->GetDb();
	$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
	$properties = $db->GetRow($sql,array($bracket_id));
	if($properties == FALSE)
		return FALSE;
	$sql = 'SELECT * FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2 ORDER BY team_id';
	$teams = $db->GetAll($sql,array($bracket_id));
	if($teams)
	{
		$sql = 'SELECT P.* FROM '.$pref.'module_tmt_people P JOIN '.$pref.
		'module_tmt_teams T ON P.id = T.team_id WHERE T.bracket_id=? AND T.flags!=2 AND P.flags!=2 ORDER BY P.id,P.displayorder';
		$people = $db->GetAll($sql,array($bracket_id));
		$sql = 'SELECT * FROM '.$pref.'module_tmt_matches WHERE bracket_id=? ORDER BY match_id';
		$matches = $db->GetAll($sql,array($bracket_id));
	}
	else
	{
		$people = FALSE;
		$matches = FALSE;
	}

	$xml = array();
	if($defchars)
		$xml[] = '<?xml version="1.0" encoding="'.strtoupper($defchars).'"?>';
	else
		$xml[] = '<?xml version="1.0"?>';
	$xml[] = '<!DOCTYPE tourney [';
	$xml[] = '<!ELEMENT tourney (version,date,properties,teams,people,matches)>';
	$xml[] = '<!ELEMENT version (#PCDATA)>';
	$xml[] = '<!ELEMENT date (#PCDATA)>';
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
	//main-table fieldnames are not namespaced
	$xml[] = "\t<properties>";
	foreach($fields1 as $thisfield)
		$xml[] = "\t\t<$thisfield>".$properties[$thisfield]."</$thisfield>";
	$xml[] = "\t</properties>";
	//to avoid conflicts, other-table fieldnames are namespaced
	$xml[] = "\t<t:teams xmlns:t=\"file:///teams\">";
	if($teams)
	{
		foreach($teams as $thisteam)
		{
			$xml[] = "\t\t<team>";
				foreach($fields2 as $thisfield)
					$xml[] = "\t\t\t<t:$thisfield>".$thisteam[$thisfield]."</t:$thisfield>";
			$xml[] = "\t\t</team>";
		}
	}
	$xml[] = "\t</t:teams>";
	$xml[] = "\t<h:people xmlns:h=\"file:///humans\">";
	if($people)
	{
		foreach($people as $thisone)
		{
			$xml[] = "\t\t<person>";
				foreach($fields3 as $thisfield)
					$xml[] = "\t\t\t<h:$thisfield>".$thisone[$thisfield]."</h:$thisfield>";
			$xml[] = "\t\t</person>";
		}
	}
	$xml[] = "\t</h:people>";
	$xml[] = "\t<m:matches xmlns:m=\"file:///matches\">";
	if($matches)
	{
		foreach($matches as $thismatch)
		{
			$xml[] = "\t\t<match>";
				foreach($fields4 as $thisfield)
					$xml[] = "\t\t\t<m:$thisfield>".$thismatch[$thisfield]."</m:$thisfield>";
			$xml[] = "\t\t</match>";
		}
	}
	$xml[] = "\t</m:matches>";
	$xml[] = '</tourney>';

	return implode("\n",$xml);
 }
}

if(!$this->CheckAccess('adview'))
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

$pref = cms_db_prefix();
$sql = 'SELECT alias,timezone FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
$bdata = $db->GetRow($sql,array($params['bracket_id']));
if($bdata == FALSE)
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('err_missing',FALSE)));

//if necessary and possible, we will convert exported data from site-encoding to UTF-8,
//cuz php's xml-parser doesn't support many codings
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
	if($defchars) $defchars = FALSE;
	$convert = FALSE;
}

$dt = new DateTime('now',new DateTimeZone($bdata['timezone']));
$xmlstr = ExportXML($this,$params['bracket_id'],$dt->format('Y-m-d'),$defchars);
if($xmlstr)
{
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
	$detail = ($bdata['alias']) ? preg_replace('/\W/','_',$bdata['alias']) : $params['bracket_id'];
	$fn = $this->Lang('tournament').'-'.$detail.'-'.$dt->format('Y-m-d G:i');
	@ob_clean();
	header('Pragma: public');
	header('Expires: 0');
	header('Cache-Control: must-revalidate,post-check=0,pre-check=0');
	header('Cache-Control: private',FALSE);
	header('Content-Description: File Transfer');
	//note: some older HTTP/1.0 clients did not deal properly with an explicit charset parameter
	header('Content-Type: text/xml'.$defchars);
	header('Content-Length: '.strlen($xmlstr));
	header('Content-Disposition: attachment; filename="'.$fn.'.xml"');
	echo $xmlstr;
	exit;
}
else
	$this->Redirect($id,'defaultadmin','',
		array('tmt_message'=>$this->PrettyMessage('err_export',FALSE)));
?>
