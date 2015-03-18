<?php
#-------------------------------------------------------------------------
# CMS Made Simple module: Tourney.
# Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
# Version: 0.1.2
# This module allows ...
# More info at http://dev.cmsmadesimple.org/projects/tourney
#-------------------------------------------------------------------------
# This module is free software; you can redistribute it and/or modify it
# under the terms of the GNU Affero General Public License as published by the
# Free Software Foundation; either version 3 of the License, or (at your option)
# any later version.
#
# This module is distributed in the hope that it will be useful, but
# WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details. If you don't have a copy
# of that license, read it online at: www.gnu.org/licenses/licenses.html#AGPL
#-------------------------------------------------------------------------

const KOTYPE = 0;
const DETYPE = 1;
const RRTYPE = 2;
const KOMIN = 2;
const KOMAX = 256;
const DEMIN = 3;
const DEMAX = 128;
const RRMIN = 2;
const RRMAX = 20;
//time interval (hours) before which new matches aren't scheduled and within which FIRM matches are considered 'committed'
const LEADHOURS = 48;
//match-status enum values
// scheduled (<MRES,!=0)
const NOTYET = -1; //runtime data sometimes, not stored
const SOFT = 1;
const FIRM = 2;
const TOLD = 3;	//notification sent
// threshold for scheduled matches with NULL teamA and/or teamB, not stored
const ANON = 6;
// scheduled matches with unknown participant(s) >= ANON < MRES
const ASOFT = 6;
const AFIRM = 7;
// threshold between schedules and results, not stored
const MRES = 11;
// match result (>=MRES)
const WONA = 11;
const WONB = 12;
const FORFA = 13;
const FORFB = 14;
const MTIED = 15;
const NOWIN = 16;

class Tourney extends CMSModule
{
	protected $PermAdminName = 'Modify TourneyModule Settings';
	protected $PermModName = 'Modify Brackets';
	protected $PermScoreName = 'Modify BracketData';
	protected $PermSeeName = 'See Brackets';

	function __construct()
	{
		parent::__construct();

		$this->RegisterModulePlugin();
	}

	function AllowAutoInstall()
	{
		return FALSE;
	}

	function AllowAutoUpgrade()
	{
		return FALSE;
	}

	function GetName()
	{
		return 'Tourney';
	}

	function GetFriendlyName()
	{
		return $this->Lang('friendlyname');
	}

	function GetVersion()
	{
		return '0.2.0';
	}

	function GetHelp()
	{
		$fn = cms_join_path(dirname(__FILE__),'css','chart.css');
		$cont = @file_get_contents($fn);
		if ($cont)
		{
			$example = preg_replace(array('~\s?/\*(.*)?\*/~Usm','~\s?//.*$~m'),array('',''),$cont);
			$example = str_replace(array("\n\n","\n","\t"),array('<br />','<br />',' '),trim($example));
		}
		else
			$example = $this->Lang('missing');
		return $this->Lang('help',$example);
	}

	function GetAuthor()
	{
		return 'tomphantoo';
	}

	function GetAuthorEmail()
	{
		return 'tpgww@onepost.net';
	}

	function GetChangeLog()
	{
		$fn = cms_join_path(dirname(__FILE__),'include','changelog.inc');
		return @file_get_contents($fn);
	}

	function IsPluginModule()
	{
		return TRUE;
	}

	function HasAdmin()
	{
		return TRUE;
	}

	function LazyLoadAdmin()
	{
		return FALSE; //need immediate load for menu creation
	}

	function GetAdminSection()
	{
		return 'content';
	}

	function GetAdminDescription()
	{
		return $this->Lang('admindescription');
	}

	function VisibleToAdminUser()
	{
		return $this->CheckAccess();
	}

	function AdminStyle()
	{
	}

	function GetHeaderHTML()
	{
		$url = $this->GetModuleURLPath();
		return '<link rel="stylesheet" type="text/css" href="'.$url.'/css/pikaday.css" />
<link rel="stylesheet" type="text/css" href="'.$url."/css/module.css\" />\n";
	}

	function SuppressAdminOutput(&$request)
	{
		//is this general enough? string 'mact' and 'm1_' are hardcoded into CMSMS for backend actions
		if (isset($request['mact']))
		{
			if (strpos($request['mact'],',check_data'))
				return TRUE;
			if (strpos($request['mact'],',move_team'))
				return TRUE;
			if (strpos($request['mact'],',order_team'))
				return TRUE;
			if (strpos($request['mact'],',order_groups'))
				return TRUE;
			if (strpos($request['mact'],',export_comp'))
				return TRUE;
			if (strpos($request['mact'],',addedit_comp,')
				&& isset($request['m1_real_action'])
				&& $request['m1_real_action'] == 'm1_export')
				return TRUE; //export selected team(s)
			if (strpos($request['mact'],',addedit_team,')
				&& isset($request['m1_export']))
					return TRUE; //export selected team-member(s)
			if (strpos($request['mact'],',process_items,')
				&& isset($request['m1_export']))
					return TRUE; //export selected bracket(s)
		}
		return FALSE;
	}

	function GetDependencies()
	{
		return array();
	}
	//for 1.11+
	function AllowSmartyCaching()
	{
		return FALSE;
	}
	function LazyLoadFrontend()
	{
		return TRUE;
	}

	//setup for pre-1.10
	function SetParameters()
	{
		$this->InitializeAdmin();
		$this->InitializeFrontend();
	}

	//partial setup for pre-1.10, backend setup for 1.10
	function InitializeFrontend()
	{
		$this->RestrictUnknownParams();
		//button actions
		$this->SetParameterType('chart',CLEAN_STRING);
		$this->SetParameterType('list',CLEAN_STRING);
		$this->SetParameterType('nosend',CLEAN_STRING);
		$this->SetParameterType('real_action',CLEAN_STRING);
		$this->SetParameterType('result',CLEAN_STRING);
		$this->SetParameterType('send',CLEAN_STRING);
		$this->SetParameterType('connect',CLEAN_STRING);
		//hidden data
		$this->SetParameterType('bracket_id',CLEAN_INT);
		$this->SetParameterType('captcha',CLEAN_STRING);
		$this->SetParameterType('comment',CLEAN_STRING);
		$this->SetParameterType('match',CLEAN_INT);
		$this->SetParameterType('message',CLEAN_STRING);
		$this->SetParameterType('score',CLEAN_STRING);
		$this->SetParameterType('sender',CLEAN_STRING);
		$this->SetParameterType('when',CLEAN_STRING);
		$this->SetParameterType('shown',CLEAN_STRING);
		//twitter authorisation
		$this->SetParameterType('oauth_token',CLEAN_STRING);
		$this->SetParameterType('oauth_verifier',CLEAN_STRING);
		//page-tag variables
		$this->SetParameterType('alias',CLEAN_STRING); //alias string
		$this->SetParameterType('view',CLEAN_STRING); //list or chart
		$this->SetParameterType('cssfile',CLEAN_STRING); //absolute or uploads-relative path of chart-css file
		$this->SetParameterType('tweetauth',CLEAN_INT);
		//the rest are for internal use only
//		$this->SetParameterType(CLEAN_REGEXP.'/tmt_.*/',CLEAN_STRING);
	}

	//partial setup for pre-1.10, backend setup for 1.10
	function InitializeAdmin()
	{
		//document only the parameters relevant for external (page-tag) usage
		$this->CreateParameter('alias','',$this->Lang('params_tmt_alias'),FALSE);
		$this->CreateParameter('view','chart',$this->Lang('params_view_type'));
		$this->CreateParameter('cssfile','',$this->Lang('params_chart_css'));
		$this->CreateParameter('tweetauth','',$this->Lang('params_tweet_auth'));
	}

	function MinimumCMSVersion()
	{
		return '1.9';
	}

	function MaximumCMSVersion()
	{
		return '1.11.99';
	}

	function InstallPostMessage()
	{
		return $this->Lang('postinstall');
	}

	function UninstallPreMessage()
	{
		return $this->Lang('confirm_uninstall');
	}

	function UninstallPostMessage()
	{
		return $this->Lang('postuninstall');
	}

	function GetActiveTab(&$params)
	{
		if (empty($params['active_tab']))
			return 'maintab';
		else
			return $params['active_tab'];
	}

	//construct ordered array of groups data : key = group_id, value = other row data array
	//if $active, only the active groups are returned - otherwise all
	function GetGroups($active=FALSE)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDB();
		$sql = 'SELECT * FROM '.$pref.'module_tmt_groups ORDER BY displayorder';
		$groups = $db->GetAssoc($sql);
		if($groups && $active)
		{
			foreach ($groups as &$data)
			{
				if (((int)$data['flags'] & 1) == 0)
					$data = NULL;
			}
			unset($data);
			$groups = array_diff($groups,array(NULL));
		}
		return $groups;
	}
	
	//construct series of hidden objects to support page-recreation when editing comp
	function GetHiddenParms($id,&$params,$tabname=FALSE)
	{
		$hidden = $this->CreateInputHidden($id,'bracket_id',$params['bracket_id']);
		if(isset($params['newbracket']))
			$hidden .= $this->CreateInputHidden($id,'newbracket',$params['newbracket']);
		$val = (isset($params['matchview']))?$params['matchview']:'actual';
		$hidden .= $this->CreateInputHidden($id,'matchview',$val);
		$val = (isset($params['resultview']))?$params['resultview']:'future';
		$hidden .= $this->CreateInputHidden($id,'resultview',$val);
		if(!$tabname)
			$tabname = $this->GetActiveTab($params);
		$hidden .= $this->CreateInputHidden($id,'active_tab',$tabname);
		return $hidden;
	}

	//construct array of parameters for redirection to a specific tab, when editing comp
	function GetEditParms(&$params,$tabname=FALSE,$message=FALSE)
	{
		$newp = array('bracket_id'=>$params['bracket_id'],'real_action'=>'edit');
		if(isset($params['newbracket']))
			$newp['newbracket'] = $params['newbracket'];
		if(isset($params['active_tab']))
			$newp['active_tab'] = $params['active_tab'];
		elseif($tabname)
			$newp['active_tab'] = $tabname;
		if(isset($params['matchview']))
			$newp['matchview'] = $params['matchview'];
		else
			$newp['matchview'] = 'actual';
		if(isset($params['resultview']))
			$newp['resultview'] = $params['resultview'];
		else
			$newp['resultview'] = 'future';
		if ($message)
			$newp['tmt_message'] = $message;
		return $newp;
	}

	function TeamName($team_id, $missing=TRUE)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDB();
		$sql = 'SELECT name FROM '.$pref.'module_tmt_teams WHERE team_id=?';
		$name = $db->GetOne($sql,array($team_id));
		if ($name)
		{
			$name = trim($name);
			if ($name)
				return $name;
		}
		$sql = 'SELECT name FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder';
		$rs = $db->SelectLimit($sql,1,-1,array($team_id));
		if ($rs)
		{
			if (!$rs->EOF)
			{
				extract($rs->FetchRow());
				$name = trim($name);
			}
			$rs->Close();
		}
		if ($name)
		{
			$name = trim($name);
			if ($name)
				return $name;
		}
		return ($missing) ? $this->MissingName() : '';
	}

	function MissingName()
	{
		return '<'.$this->Lang('noname').'>';
	}

	function GetLimits($type=KOTYPE)
	{
		switch ($type)
		{
		case KOTYPE:
		 return array (KOMIN,KOMAX);
		case DETYPE:
		 return array (DEMIN,DEMAX);
		case RRTYPE:
		 return array (RRMIN,RRMAX);
		default:
		 return array (0,0);
		}
	}

	function GetTimeZones()
	{
		static $regions = array(
			DateTimeZone::AFRICA,
			DateTimeZone::AMERICA,
			DateTimeZone::ANTARCTICA,
			DateTimeZone::ASIA,
			DateTimeZone::ATLANTIC,
			DateTimeZone::AUSTRALIA,
			DateTimeZone::EUROPE,
			DateTimeZone::INDIAN,
			DateTimeZone::PACIFIC,
		);

		$timezones = array();
		foreach($regions as $region)
			$timezones = array_merge($timezones, DateTimeZone::listIdentifiers($region));

		ksort($timezones);

		$current = new DateTime();
		$timezone_offsets = array();
		foreach($timezones as $onezone)
		{
			$tz = new DateTimeZone($onezone);
			$timezone_offsets[$onezone] = $tz->getOffset($current);
		}

		$timezone_list = array();
		foreach($timezone_offsets as $onezone => $offset)
		{
			$offset_prefix = $offset < 0 ? '-' : '+';
			$offset_formatted = gmdate('G:i', abs($offset));
			$pretty_offset = "UTC${offset_prefix}${offset_formatted}";
			$timezone_list["$onezone (${pretty_offset})"] = $onezone;
		}

		return $timezone_list;
	}

	/**
	GetZoneDateType:
	@zone: bracket timezone string
	Returns: indicator of date-string format for @zone 'us' or 'uk' or 'ymd'
	*/
	function GetZoneDateType($zone)
	{
		if(!$zone)
			return 'uk'; //default UK format
		//US date format ?
		if(strpos(
'Africa/Johannesburg
America/Adak
America/Anchorage
America/Belize
America/Boise
America/Chicago
America/Denver
America/Detroit
America/Juneau
America/Los_Angeles
America/Menominee
America/Metlakatla
America/New_York
America/Nome
America/Phoenix
America/Shiproc
America/Sitka
America/Yakutat
Asia/Kathmandu
Europe/Copenhagen
Europe/Stockholm
Pacific/Honolulu'
			,$zone) !== FALSE)
			return 'us';
		//US date format format for subzones
		if (substr($zone,0,7) == 'America')
		{
			foreach(array('Indiana' => 7,'Kentucky' => 8,'North_Dakota' => 12) as $name=>$len)
			{
				if(substr_compare($zone,$name,9,$len) === 0)
					return 'us';
			}
		}
		//YMD date format ?
		if(strpos(
'America/Atikokan
America/Blanc-Sablon
America/Cambridge_Bay
America/Creston
America/Dawson
America/Dawson_Creek
America/Edmonton
America/Glace_Bay
America/Goose_Bay
America/Halifax
America/Inuvik
America/Iqaluit
America/Moncton
America/Montreal
America/Nipigon
America/Pangnirtung
America/Rainy_River
America/Rankin_Inlet
America/Regina
America/Resolute
America/St_Johns
America/Swift_Current
America/Thunder_Bay
America/Toronto
America/Vancouver
America/Whitehorse
America/Winnipeg
America/Yellowknife
Asia/Choibalsan
Asia/Chongqing
Asia/Harbin
Asia/Hovd
Asia/Kashgar
Asia/Pyongyang
Asia/Seoul
Asia/Shanghai
Asia/Tehran
Asia/Tokyo
Asia/Ulaanbaatar
Asia/Urumqi
Europe/Budapest
Europe/Vilnius'
		,$zone) !== FALSE)
			return 'ymd';

		return 'uk';
	}

	/**
	GetZoneDateFormat:
	@zone: bracket timezone string
	Get date format string with substrings in timezone-specific order.
	0..3 substrings may have various values, and be in any order, in the original.
	Returns: date format string derived from stored preference
	*/
	function GetZoneDateFormat($zone)
	{
		$ret = $this->GetPreference('date_format');
		$offs = array();
		$day = preg_match_all('/^([djl]S?)|[^\\\\]([djl]S?)/',$ret,$mm,PREG_OFFSET_CAPTURE);
		if($day > 0)
		{
			if($day > 1)//some error in the format
				return $ret;
			$a = (isset($mm[1][0][1]) && $mm[1][0][1] != -1) ? 1:2;
			$s1 = $mm[$a][0][0]; //matched string
			$offs[1] = $mm[$a][0][1]; //its offset into $pref
		}
		$mth = preg_match_all('/^([FMmn])|[^\\\\]([FMmn])/',$ret,$mm,PREG_OFFSET_CAPTURE);
		if($mth > 0)
		{
			if($mth > 1)
				return $ret;
			$a = (isset($mm[1][0][1]) && $mm[1][0][1] != -1) ? 1:2;
			$s2 = $mm[$a][0][0];
			$offs[2] = $mm[$a][0][1];
		}
		$yr = preg_match_all('/^([Yoy])|[^\\\\]([Yoy])/',$ret,$mm,PREG_OFFSET_CAPTURE);
		if($yr > 0)
		{
			if($yr > 1)
				return $ret;
			$a = (isset($mm[1][0][1]) && $mm[1][0][1] != -1) ? 1:2;
			$s3 = $mm[$a][0][0];
			$offs[3] = $mm[$a][0][1];
		}
		unset($mm);
		asort($offs); //replacements in order of increasing offset
		reset($offs);

		if($zone)
		{
			switch($this->GetZoneDateType($zone))
			{
			 case 'us':
				$repl = array('2','1','3'); //indices for MDY
				break;
			 case 'ymd':
				$repl = array('3','2','1'); //indices for YMD
				break;
			 default:
				$repl = array('1','2','3'); //indices for DMY
				break;
			}
		}
		else
			$repl = array('1','2','3');
		
		foreach($repl as $indx)
		{
			if(isset(${"s$indx"}))
			{
				$k = key($offs);
				$ret = substr_replace($ret,${"s$indx"},current($offs),strlen(${"s$k"}));
				next($offs);
			}
		}
		return $ret;
	}

	/**
	ResultTemplates:
	@bracket_id: enumerator of bracket being processed
	@chartmode: whether templates are to be used for bracketchart content, default TRUE
	Get internationalised templates for expressing match status (mainly, result) for @bracket_id.
	'%s' in a template to be replaced upstream by appropriate teamname(s).
	Other text is lowercase, bracket-specific if available or else generic.
	Returns: associative array of templates, keys being 'won','def',
	 'forf','nomatch',(if relevant to the bracket)'tied', and 'vs'
	*/
	function ResultTemplates($bracket_id,$chartmode=TRUE)
	{
		if ($bracket_id == FALSE) return FALSE;
		$sql = 'SELECT cantie,versus,defeated,tied,forfeit,nomatch FROM '.cms_db_prefix().'module_tmt_brackets WHERE bracket_id=?';
		$db = cmsms()->GetDb();
		$row = $db->GetRow($sql, array($bracket_id));
		$results = array();
		$rel = $this->Lang('won_fmt');//not in bracket data
		$results['won'] = strtolower($rel);
		$rel = $this->Lang('or_fmt');//not in bracket data
		$results['or'] = strtolower($rel);

		$rel = $row['versus']; //prefer bracket-specific language
		if (!$rel)
			$rel = $this->Lang('vs'); //or else generic
		$rel = str_replace('%r',strtolower($rel),$this->Lang('versus_fmt'));
		$results['vs'] = $rel;

		$rel = $row['defeated'];
		if (!$rel)
			$rel = $this->Lang('defeated');
		$rel = str_replace('%r',strtolower($rel),$this->Lang('defeated_fmt'));
		 $results['def'] = $rel;

		$rel = $row['forfeit'];
		if (!$rel)
			$rel = $this->Lang('forfeited');
		$rel = str_replace('%r',strtolower($rel),$this->Lang('forfeited_fmt'));
		$results['forf'] = $rel;

		$rel = $row['nomatch'];
		if (!$rel)
			$rel = $this->Lang('abandoned');
		$rel = str_replace('%r',strtolower($rel),$this->Lang('abandoned_fmt'));
		$results['nomatch'] = $rel;

		if ($row['cantie'])
		{
			$rel = $row['tied'];
			if (!$rel)
				$rel = $this->Lang('tied');
			$rel = str_replace('%r',strtolower($rel),$this->Lang('tied_fmt'));
			$results['tied'] = $rel;
		}

		if ($chartmode)
		{
			//wrapping for chart in-box presentation
			foreach($results as &$rel)
				$rel = str_replace(array(' ',','),array("\n","\n"),$rel);
			unset($rel);
		}
		return $results;
	}

/*	function ChartCssFile($bracket_id, $exists=TRUE)
	{
		if ($bracket_id == FALSE) return FALSE;
		$sql = 'SELECT chartcss FROM '.cms_db_prefix().'module_tmt_brackets WHERE bracket_id=?';
		$db = cmsms()->GetDb();
		$cssfile = $db->GetOne($sql, array($bracket_id));
		if ($cssfile)
		{
			$config = cmsms()->GetConfig();
			$csspath = cms_join_path($config['uploads_path'],
				$this->GetPreference('uploads_dir'),$cssfile);
			if (!$exists || file_exists($csspath))
				return $csspath;
		}
		return FALSE;
	}
*/
	function ChartImageFile($bracket_id, $exists=TRUE)
	{
		$config = cmsms()->GetConfig();
		$chartfile = cms_join_path($config['root_path'],'tmp','bracket-'.$bracket_id.'-chart.pdf');
		if (!$exists || file_exists($chartfile))
			return $chartfile;
		return FALSE;
	}

	function CreateImageObject($imgurl, $height=400)
	{
		$failtext = $this->Lang('chart_noshow');
		$txlt = strpos($failtext,'>>');
		if ($txlt !== FALSE)
		{
			$txrt = strrpos($failtext,'<<');
			if ($txrt > $txlt)
			{
				$failtext = substr($failtext,0,$txrt).'</a>'.substr($failtext,$txrt+2);
				$failtext = substr($failtext,0,$txlt).'<a href="'.$imgurl.'">'.substr($failtext,$txlt+2);
			}
		}
		//arbitrary limit on visible height
		if ($height > 800)
			$height = 800;
		$ret = '<object type="application/pdf" data="'.$imgurl.'" height="'.$height.'" width="100%">'."\n".$failtext."\n".'</object>'."\n";
		return $ret;
	}

	/**
	CreateInputLinks:
	@id: system-id to be passed to the module
	@name: name of action to be performed when (either of) the object(s) is clicked
	@iconfile: optional name of theme icon, or module-relative or absolute URL
	  of some other icon for image input, default FALSE i.e. no image
	@link: optional whether to (also) create a submit input, default FALSE
	@text: optional title and tip for an image, or mandatory displayed text for a link, default ''
	@extra: optional additional text that should be added into the object, default ''
	Generate xhtml for image and/or submit input(s) which can be styled like an icon
	and/or link, using class "fakeicon" for an image, "fakelink" for a standard link

	Such object(s) is(are) needed where the handler/action requires all form data,
	instead of just the data for the oject itself (which happens for a normal link)
	*/
	function CreateInputLinks($id, $name, $iconfile=FALSE, $link=FALSE, $text='', $extra='')
	{
		if ($iconfile)
		{
			$p = strpos($iconfile,'/'); 
			if ($p === FALSE)
			{
				$theme = cmsms()->get_variable('admintheme');
				$imgstr = $theme->DisplayImage('icons/system/'.$iconfile,$text,'','','fakeicon systemicon');
				//trim string like <img src="..." class="fakeicon systemicon" alt="$text" title="$text" />
				$imgstr = str_replace(array('<img','/>'),array('',''),$imgstr);
			}
			elseif ($p == 0)
				$imgstr = $this->GetModuleURLPath().$iconfile;
			elseif (strpos($iconfile,'://',$p-1) === $p-1)
				$imgstr = $iconfile;
			else
				$imgstr = $this->GetModuleURLPath().'/'.$iconfile;
			$ret = '<input type="image" '.$imgstr.' name="'.$id.$name.'"'; //conservative assumption about spaces
			if ($extra)
				$ret .= ' '.$extra;
			$ret .= ' />';
		}
		else
			$ret = '';
		if ($link && $text)
		{
			$ret .='<input type="submit" value="'.$text.'" name="'.$id.$name.'" class="fakelink"';
			if ($extra)
				$ret .= ' '.$extra;
			$ret .= ' />';
		}
		return $ret;
	}
	/**
	CreateInputSubmitDefault:
	Generate xhtml like CreateInputSubmit, but with extra class "default"
	*/
	function CreateInputSubmitDefault($id, $name, $value = '', $addttext = '', $image = '', $confirmtext = '')
	{
		$s = $this->CreateInputSubmit($id,$name,$value,$addttext,$image,$confirmtext);
		return str_replace('class="','class="default ',$s);
	}

	//for admin-display only
	function PrettyMessage($text, $success=TRUE, $faillink=FALSE, $key = TRUE)
	{
		$base = ($key) ? $this->Lang($text) : $text;
		if ($success)
			return $this->ShowMessage($base);
		else
		{
			$msg = $this->ShowErrors($base);
			if ($faillink == FALSE)
			{
				//strip the link
				$pos = strpos($msg,'<a href=');
				$part1 = ($pos !== FALSE) ? substr($msg,0,$pos) : '';
				$pos = strpos($msg,'</a>',$pos);
				$part2 = ($pos !== FALSE) ? substr($msg,$pos+4) : $msg;
				$msg = $part1.$part2;
			}
			return $msg;
		}
	}

	function ProcessDataTemplate($data, $display=FALSE)
	{
		$smarty = cmsms()->GetSmarty();
		$smarty->_compile_source('data',$data,$compiled);
		@ob_start();
		$result = ($smarty->_eval('?>'.$compiled) !== FALSE) ? @ob_get_contents():FALSE;
		@ob_end_clean();
		if(!$display)
			return $result;
		echo ($result !== FALSE) ? $result:$this->Lang('err_template');
	}

	function CheckAccess($operation=FALSE)
	{
		$allow = FALSE;
		switch($operation)
		{
		case FALSE:  // any module-related permission
			$allow = $this->CheckPermission($this->PermSeeName);
			if (!$allow) $allow = $this->CheckPermission($this->PermScoreName);
			if (!$allow) $allow = $this->CheckPermission($this->PermModName);
			if (!$allow) $allow = $this->CheckPermission($this->PermAdminName);
			break;
		case 'admod':
			$allow = $this->CheckPermission($this->PermModName);
			if (!$allow) $allow = $this->CheckPermission($this->PermAdminName);
			break;
		case 'adview':
			$allow = $this->CheckPermission($this->PermSeeName);
			if (!$allow) $allow = $this->CheckPermission($this->PermAdminName);
			break;
		case 'admin':
			$allow = $this->CheckPermission($this->PermAdminName);
			break;
		case 'modify':
			$allow = $this->CheckPermission($this->PermModName);
			break;
		case 'score':
			$allow = $this->CheckPermission($this->PermScoreName);
			break;
		case 'view':
			$allow = $this->CheckPermission($this->PermSeeName);
			break;
		}
		return $allow;
	}

/*	//for module-specific events
	function GetEventDescription($eventname)
	{
		return $this->Lang('event_info_'.$eventname);
	}

	function GetEventHelp($eventname)
	{
		return $this->Lang('event_help_'.$eventname);
	}
*/
	function HandlesEvents()
	{
		return TRUE;
	}

	function DoEvent($originator,$eventname,&$params)
	{
		if ($originator == 'Core')
	    {
			switch ($eventname)
			{
			case 'LoginPost':
				//clear chart files older than 24 hrs
				$stamp = time()-86400; //24*60*60
				$limit = gmdate("Y-m-d H:i:s",$stamp);
				$db = cmsms()->GetDb();
				if(empty($config))
					$config = cmsms()->GetConfig();
				$charts = cms_join_path($config['root_path'],'tmp','bracket-*-chart.pdf');
				foreach (glob($charts) as $fn)
				{
					if (filemtime($fn) < $stamp)
						unlink($fn);
				}
				break;
			}
		}
	}

	function DoAction($name, $id, $params, $returnid='')
	{
		//diversions
		switch ($name)
		{
		 case 'addedit_comp':
			if (!empty($params['real_action']))
			{
				//'real_action' will be correctly set only if js is enabled
				$task = substr($params['real_action'], strlen($id));
				switch ($task)
				{
				 case 'apply':
				 case 'cancel':
				 case 'submit':
					$name = 'save_comp';
					break;
				 case 'addteam':
					$name = 'addedit_team';
					break;
				 case 'delteams':
					$name = 'delete_team';
					break;
				 case 'notify':
				 case 'export':
					$name = 'multi_task';
					break;
				 case 'chart':
				 case 'list':
				 case 'print':
					$name = 'show_comp';
					break;
				 case 'reset':
					$name = 'schedule';
					break;
				 case 'connect':
					$params['real_action'] = $task;
					break;
				 case 'import_team':
				 case 'schedule':
				 case 'upload_css':
				 case 'changelog':
					$name = $task;
					break;
				}
				if(substr($task,0,4) == 'edit')
					$name = 'addedit_team';
				elseif(substr($task,0,6) == 'update')
					$name = 'save_comp';
				elseif(substr($task,0,11) == 'delete_team')
					$name = 'delete_team';
				elseif(substr($task,0,8) == 'movedown')
					$params['real_action'] = $task;
				elseif(substr($task,0,6) == 'moveup')
					$params['real_action'] = $task;
			}
			break;
		 case 'process_items':
			if (!empty($params['import']))
				$name = 'import_comp';
			break;
		 case 'default':
		 	if (!empty($params['result']))
				$name = 'result';
			break;
		 case 'result':
			if (empty($params['send']))
				$name = 'default';
			break;
		 case 'addgroup':
			$name = 'defaultadmin';
			$params = array('showtab' => 1,'addgroup' => TRUE);
			break;
		}
		parent::DoAction($name, $id, $params, $returnid);
	}
}

?>
