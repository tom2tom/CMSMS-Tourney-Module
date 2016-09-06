<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Functions involved with tournament data conversion
*/
class tmtData
{
	private function GetIntegerFor($val,$positive=TRUE)
	{
		$ret = (int)$val;
		if ($positive && $ret < 0) $ret = 0;
		return $ret;
	}

	private function GetFloatFor($val,$positive=TRUE)
	{
		$ret = (float)($val+0); //strip trailing 0's
		if ($positive && $ret < 0) $ret = 0.0;
		return $ret;
	}

	/**
	GetValidData:
	@mod reference to module object
	@params reference to parameters array supplied to action which called here

	Returns: stdClass object with members corresponding to data ready to be saved,
		or else an object with member ->error and possibly also ->errmsg
		If it is available, member ->bracket_id is included in the object
	*/
	function &GetValidData(&$mod,&$params)
	{
		$pref = cms_db_prefix();
		$db = cmsms()->GetDb();
		$data = new stdClass();
		//each identifier in the data object must match the relevant database-fieldname
		//identifiers are created here in more-or-less the field-order of the database table
		if (!empty($params['bracket_id']))
		{
			$data->bracket_id = (int)$params['bracket_id'];
			$data->groupid = (int)$params['tmt_groupid'];
		}
		if(!empty($params['newbracket']))
		{
			$data->groupid = 0; //ungrouped
			$data->added = 1;
		}
		if (!empty($params['tmt_type']))
			$data->type = (int)$params['tmt_type'];
		else
			$data->type = Tourney::KOTYPE; //default to knockout
		if (!empty($params['tmt_name']))
			$data->name = trim($params['tmt_name']);
		else
		{
			switch ($data->type)
			{
			 case Tourney::KOTYPE:
				$key = 'title_bracket_single';
			 	break;
			 case Tourney::DETYPE:
				$key = 'title_bracket_double';
			 	break;
			 case Tourney::RRTYPE:
				$key = 'title_bracket_round';
			 	break;
			 default:
				$key = '';
			 	break;
			}
			$data->name = ($key) ? $mod->Lang($key) : '';
		}
		$tmp = (empty($params['tmt_alias'])) ? $data->name : $params['tmt_alias'];
		$tmp = strtolower(preg_replace(array('/\s+/','/__+/'),array('_','_'),$tmp));
		$data->alias = substr($tmp,0,24); //no auto-check for alias duplication

		$data->description = $params['tmt_description'];
		$data->owner = $params['tmt_owner'];
		$data->contact = $params['tmt_contact'];
		$data->locale = trim($params['tmt_locale']);
		$data->twtfrom = $params['tmt_twtfrom'];
		if(isset ($params['tmt_smspattern']))	//relevant SMS module present
		{
			$tmp = trim($params['tmt_smspattern']);
			$data->smspattern = $tmp;
			if ($tmp && preg_match('~'.$tmp.'~',$params['tmt_smsfrom']))
				$data->smsfrom = $params['tmt_smsfrom'];
			else
				$data->smsfrom = '';
			$data->smsprefix = $params['tmt_smsprefix'];
		}
		if (isset ($params['tmt_feu_editgroup']))
			$data->feu_editgroup = $params['tmt_feu_editgroup'];
		$data->seedtype = intval($params['tmt_seedtype']);
		$data->fixtype = intval($params['tmt_fixtype']);
		$data->teamsize = self::GetIntegerFor($params['tmt_teamsize']);
		if ($data->teamsize == 0)
			$data->teamsize = 1;
		$data->sametime = self::GetIntegerFor($params['tmt_sametime']);
		if ($data->sametime == 0)
			$data->sametime = NULL;
		if(isset ($params['tmt_calendarid'])) //relevant bookings module present
			$data->calendarid = $params['tmt_calendarid'];
		$tmp = $params['tmt_playgaptype'];
		switch ($tmp)
		{
		 case 0: //none
			$data->playgap = NULL;
			break;
		 case 2: //hours
		 case 3: //days
			$data->playgap = self::GetFloatFor($params['tmt_playgap']);
			break;
		 default:
			$data->playgap = self::GetIntegerFor($params['tmt_playgap']);
			break;
		}
		$data->playgaptype = $tmp;
		$tmp = trim($params['tmt_available']);
		if($tmp == FALSE)
			$data->available = NULL;
		else
		{
			$cal = new WhenRules($mod);
			$clean = $cal->CheckDescriptor($tmp,$data->locale);
			unset($cal);
			if($clean)
				$data->available = $clean;
			else
			{
				$msg = $mod->Lang('err_value');
				if(strpos($tmp,$msg)===FALSE)
					$data->available = $msg.' >> '.$tmp;
				else
					$data->available = $tmp;
			}
			unset($cal);
		}
		$tmp = $params['tmt_latitude'] + 0; //strip trailing 0
		if($tmp == FALSE)
			$tmp = NULL;
		$data->latitude = $tmp;
		$tmp = $params['tmt_longitude'] + 0;
		if($tmp == FALSE)
			$tmp = NULL;
		$data->longitude = $tmp;

		$tmp = $params['tmt_placegaptype'] + 0;
		switch ($tmp)
		{
		 case 0: //none
			$data->placegap = NULL;
			break;
		 case 2: //hours
		 case 3: //days
			$data->placegap = self::GetFloatFor($params['tmt_placegap']);
			break;
		 default:
			$data->placegap = self::GetIntegerFor($params['tmt_placegap']);
			break;
		}
		$data->placegaptype = $tmp;

		if (empty($params['tmt_startdate']))
			$data->startdate = NULL;
		else
		{
			$dt = strtotime($params['tmt_startdate']);
			$data->startdate = ($dt != FALSE) ? date('Y-m-d',$dt).' 00:00:00' : NULL;
		}
		if (empty($params['tmt_enddate']))
			$data->enddate = NULL;
		else
		{
			$dt = strtotime($params['tmt_enddate']);
			$data->enddate = ($dt != FALSE) ? date('Y-m-d',$dt).' 23:59:59' : NULL;
		}

		$data->timezone = $params['tmt_timezone'];
		//email and/or twitter parameters may not be available
		if(isset($params['tmt_html']))
			$data->html = (int)$params['tmt_html'];
		else
			$data->html = NULL;
		if(isset($params['tmt_motemplate']))
		{
			if($params['tmt_motemplate'])
				$data->motemplate = $params['tmt_motemplate'];
			else
				$data->motemplate = tmtTemplate::Get($mod,'mailout_default_template');
		}
		else
			$data->motemplate = NULL;
		if(isset($params['tmt_mcanctemplate']))
		{
			if($params['tmt_mcanctemplate'])
				$data->mcanctemplate = $params['tmt_mcanctemplate'];
			else
				$data->mcanctemplate = tmtTemplate::Get($mod,'mailcancel_default_template');
		}
		else
			$data->mcanctemplate = NULL;
		if(isset($params['tmt_mreqtemplate']))
		{
			if($params['tmt_mreqtemplate'])
				$data->mreqtemplate = $params['tmt_mreqtemplate'];
			else
				$data->mreqtemplate = tmtTemplate::Get($mod,'mailrequest_default_template');
		}
		else
			$data->mreqtemplate = NULL;
		if(isset($params['tmt_mitemplate']))
		{
			if($params['tmt_mitemplate'])
				$data->mitemplate = $params['tmt_mitemplate'];
			else
				$data->mitemplate = tmtTemplate::Get($mod,'mailin_default_template');
		}
		else
			$data->mitemplate = NULL;

		if(isset($params['tmt_totemplate']))
		{
			if($params['tmt_totemplate'])
				$data->totemplate = $params['tmt_totemplate'];
			else
				$data->totemplate = tmtTemplate::Get($mod,'tweetout_default_template');
		}
		else
			$data->totemplate = NULL;
		if(isset($params['tmt_tcanctemplate']))
		{
			if($params['tmt_tcanctemplate'])
				$data->tcanctemplate = $params['tmt_tcanctemplate'];
			else
				$data->tcanctemplate = tmtTemplate::Get($mod,'tweetcancel_default_template');
		}
		else
			$data->tcanctemplate = NULL;
		if(isset($params['tmt_treqtemplate']))
		{
			if($params['tmt_treqtemplate'])
				$data->treqtemplate = $params['tmt_treqtemplate'];
			else
				$data->treqtemplate = tmtTemplate::Get($mod,'tweetrequest_default_template');
		}
		else
			$data->treqtemplate = NULL;
		if(isset($params['tmt_titemplate']))
		{
			if($params['tmt_titemplate'])
				$data->titemplate = $params['tmt_titemplate'];
			else
				$data->titemplate = tmtTemplate::Get($mod,'tweetin_default_template');
		}
		else
			$data->titemplate = NULL;
		$data->logic = $params['tmt_logic'];

		if($params['tmt_chttemplate'])
			$data->chttemplate = $params['tmt_chttemplate'];
		else
			$data->chttemplate = tmtTemplate::Get($mod,'chart_default_template');
		$data->chartcss = $params['tmt_chartcss'];
		if (isset($params['cssfile']))
			$data->cssfile = $params['cssfile'];
		else
			$data->cssfile = NULL;
		$data->atformat = $params['tmt_atformat'];
		$data->final = $params['tmt_final'];
		$data->semi = $params['tmt_semi'];
		$data->quarter = $params['tmt_quarter'];
		$data->eighth = $params['tmt_eighth'];
		$data->roundname = $params['tmt_roundname'];
		$data->versus = $params['tmt_versus'];
		$data->defeated = $params['tmt_defeated'];
		if (empty($params['tmt_cantie'])) //checkbox FALSE value may not be present
		{
			$params['tmt_cantie'] = 0;
			$data->cantie = 0;
		}
		else
			$data->cantie = 1;
		$data->tied = $params['tmt_tied'];
		$data->bye = $params['tmt_bye'];
		$data->forfeit = $params['tmt_forfeit'];
		$data->nomatch = $params['tmt_nomatch'];

		$items = array();
		if (!empty($params['tem_teamid']))
		{
			foreach ($params['tem_teamid'] as $indx=>$tid)
			{
				$name = $params['tem_name'][$indx];
				$contact = $params['tem_contact'][$indx];
				$toall = $params['tem_contactall'][$indx];
				$seed = $params['tem_seed'][$indx];

				$name = ($name == FALSE) ? NULL : trim($name);
				$contact = ($contact == FALSE) ? NULL : trim($contact);
				$seed = ($seed == FALSE) ? NULL : intval($seed);

				$items[$tid] = array('name'=>$name,'seeding'=>$seed,
				 'contactall'=>$toall,'contact'=>$contact,
				 'displayorder'=>$indx+1);
			}
		}
		$data->teams = $items;

		$items = array();
		if (!empty($params['mat_playwhen']))
		{
			$ids = array_keys($params['mat_status']);
			foreach ($params['mat_playwhen'] as $indx=>$when)
			{
				$on = self::GetFormattedDate($when);
				$at = trim($params['mat_playwhere'][$indx]);

				if ($on == FALSE) $on = NULL;
				if ($at == FALSE) $at = NULL;

				$id = $ids[$indx];
				$items[$id] = array('playwhen'=>$on,'place'=>$at,
				'status'=>$params['mat_status'][$id]);
			}
		}
		$data->matches = $items;
		$data->matchview = $params['matchview'];

		$items = array();
		if (!empty($params['res_playwhen']))
		{
			$ids = array_keys($params['res_status']);
			foreach ($params['res_playwhen'] as $indx=>$when)
			{
				$on = self::GetFormattedDate($when);
				if ($on == FALSE) $on = NULL;

				$id = $ids[$indx];
				$items[$id] = array(
				'playwhen'=>$on,'status'=>$params['res_status'][$id],
				'score'=>$params['res_score'][$indx]);
			}
		}
		$data->results = $items;
		$data->resultview = $params['resultview'];

		return $data;
	}

	/**
	GetBracketData:
	@mod: reference to current module object
	@op: 'real-action' string signalling what sort of data to return
	@id: id parameter supplied to action which called here
	@bracket_id optional, bracket identifier, default FALSE
	@params optional, parameters array supplied to action which called here, default FALSE
	Returns: stdClass object, or FALSE if no tabled bracket-data for several types of $op
	*/
	function &GetBracketData(&$mod,$op,$id,$bracket_id=FALSE,$params=FALSE)
	{
		$data = FALSE;
		switch ($op)
		{
		 case 'add':
			$db = cmsms()->GetDb();
			$data = new stdClass();
			$data->bracket_id = $db->GenID(cms_db_prefix().'module_tmt_brackets_seq');
			$data->groupid = 0; //ungrouped
			$data->type = ''; //select the 'choose one' item in list
			$data->name = '';
			$data->description = '';
			$data->alias = '';
			$data->seedtype = 0; //random allocation of seed-matches
			$data->fixtype = 0; //no special-case allocation
			$data->teamsize = 1;
			$data->owner = '';
			$data->contact = '';
			$data->locale = '';
			$data->twtfrom = '';
			$data->smsfrom = '';
			$data->smsprefix = '';
			$data->smspattern = $mod->GetPreference('phone_regex');
			$data->feu_editgroup = 'none';

			$data->motemplate = tmtTemplate::Get($mod,'mailout_default_template');
			$data->mcanctemplate = tmtTemplate::Get($mod,'mailcancel_default_template');
			$data->mreqtemplate = tmtTemplate::Get($mod,'mailrequest_default_template');
			$data->mitemplate = tmtTemplate::Get($mod,'mailin_default_template');
			$data->totemplate = tmtTemplate::Get($mod,'tweetout_default_template');
			$data->tcanctemplate = tmtTemplate::Get($mod,'tweetcancel_default_template');
			$data->treqtemplate = tmtTemplate::Get($mod,'tweetrequest_default_template');
			$data->titemplate = tmtTemplate::Get($mod,'tweetin_default_template');
			$data->html = 0;
			$data->logic = '';

			$data->chttemplate = tmtTemplate::Get($mod,'chart_default_template');
			$data->chartcss = '';
			$data->atformat = $mod->GetPreference('date_format').' '.$mod->GetPreference('time_format');
			$data->final = $mod->GetPreference('last_match_name');
			$data->semi = $mod->GetPreference('2ndlast_match_name');
			$data->quarter = $mod->GetPreference('3rdlast_match_name');
			$data->eighth = $mod->GetPreference('4thlast_match_name');
			$data->roundname = $mod->GetPreference('other_match_name');
			$data->versus = $mod->GetPreference('against_name');
			$data->defeated = $mod->GetPreference('def_name');
			$data->cantie = 0;
			$data->tied = $mod->GetPreference('tied_name');
			$data->bye = $mod->GetPreference('noop_name');
			$data->forfeit = $mod->GetPreference('forfeit_name');
			$data->nomatch = $mod->GetPreference('abandon_name');

			$data->timezone = $mod->GetPreference('time_zone');
			$data->startdate = '';
			$data->enddate = '';
			$data->sametime = '';
			$data->calendarid = '';
			$data->playgap = 1;
			$data->playgaptype = 2;
			$data->available = '';
			$data->latitude = '';
			$data->longitude = '';
			$data->placegap = 1;
			$data->placegaptype = 2;
			//the rest are not in brackets-table data
			$data->added = 1; //signal this is a new addition
			$data->cssfile = '';
			$data->teams = array();
			$data->matches = array();
			$data->matchview = 'actual';
			$data->results = array();
			$data->resultview = 'future';
			break;

		 case 'view':
		 case 'apply':
		 case 'edit':
		 case 'result_view':
		 case 'match_view':
			$pref = cms_db_prefix();
			$db = cmsms()->GetDb();
			$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$row = $db->GetRow($sql,array($bracket_id));
			if ($row == FALSE)
				break;
			$data = new stdClass();

			$data->bracket_id = $bracket_id;
			$data->groupid = (int)$row['groupid'];
//TODO also allow picker for copy, allow edit ONLY before comp starts
			$data->type = (int)$row['type'];
//		$data->teamcount = $row['teamcount'];
			$data->name = $row['name'];
			$data->description = $row['description'];
			$data->alias = $row['alias'];
			$data->seedtype = intval($row['seedtype']);
			$data->fixtype = intval($row['fixtype']);
			$data->teamsize = intval($row['teamsize']);
			$data->owner = $row['owner'];
			$data->contact = $row['contact'];
			$data->locale = $row['locale'];
			$data->twtfrom = $row['twtfrom'];
			$data->smsfrom = $row['smsfrom'];
			$data->smsprefix = $row['smsprefix'];
			$data->smspattern = $row['smspattern'];
			$data->feu_editgroup = $row['feu_editgroup'];

			$data->motemplate = tmtTemplate::Get($mod,'mailout_'.$row['bracket_id'].'_template');
			$data->mcanctemplate = tmtTemplate::Get($mod,'mailcancel_'.$row['bracket_id'].'_template');
			$data->mreqtemplate = tmtTemplate::Get($mod,'mailrequest_'.$row['bracket_id'].'_template');
			$data->mitemplate = tmtTemplate::Get($mod,'mailin_'.$row['bracket_id'].'_template');
			$data->totemplate = tmtTemplate::Get($mod,'tweetout_'.$row['bracket_id'].'_template');
			$data->tcanctemplate = tmtTemplate::Get($mod,'tweetcancel_'.$row['bracket_id'].'_template');
			$data->treqtemplate = tmtTemplate::Get($mod,'tweetrequest_'.$row['bracket_id'].'_template');
			$data->titemplate = tmtTemplate::Get($mod,'tweetin_'.$row['bracket_id'].'_template');
			$data->html = (int)$row['html'];
			$data->logic = $row['logic'];

			$data->chttemplate = tmtTemplate::Get($mod,'chart_'.$row['bracket_id'].'_template');
			$data->chartcss = $row['chartcss'];
			$data->atformat = $row['atformat'];
			$data->final = $row['final'];
			$data->semi = $row['semi'];
			$data->quarter = $row['quarter'];
			$data->eighth = $row['eighth'];
			$data->roundname = $row['roundname'];
			$data->versus = $row['versus'];
			$data->defeated = $row['defeated'];
			$data->cantie = $row['cantie'];
			$data->tied = $row['tied'];
			$data->bye = $row['bye'];
			$data->forfeit = $row['forfeit'];
			$data->nomatch = $row['nomatch'];

			$data->timezone = $row['timezone'];
			$data->startdate = self::GetFormattedDate($row['startdate'],TRUE);
			$data->enddate = self::GetFormattedDate($row['enddate'],TRUE);
			$data->sametime = $row['sametime'];
			$data->calendarid = $row['calendarid'];
			$data->playgap = $row['playgap'];
			$data->playgaptype = $row['playgaptype'];
			$data->available = $row['available'];
			$data->latitude = $row['latitude'];
			$data->longitude = $row['longitude'];
			$data->placegap = $row['placegap'];
			$data->placegaptype = $row['placegaptype'];

			//the rest are not in brackets-table data
			if(isset($params['newbracket']))
				$data->added = 1;
			$data->cssfile = '';

			$sql = 'SELECT team_id,name,seeding,contactall,displayorder FROM '.
				$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2 ORDER BY displayorder';
			$data->teams = $db->GetAssoc($sql,array($bracket_id));
			if ($data->teams)
			{
				//supplement with related data from people table
				foreach ($data->teams as $tid=>&$row)
				{
					list($name,$contact) = self::GetforFirstPlayer($tid);
					if ($row['name'] == '')
						$row['name'] = $name;
					$row['contact'] = $contact;
				}
				unset($row);
			}
			$data->matchview = ($params && !empty($params['matchview']))?$params['matchview']:'actual';
			if($data->matchview == 'actual')
				//sort cuz matches may be in database in reverse order
				$sql = 'SELECT match_id,teamA,teamB,playwhen,place,status,score FROM '.
					$pref.'module_tmt_matches WHERE bracket_id=? AND status<'.Tourney::ANON.
					' AND teamA IS NOT NULL AND teamB IS NOT NULL ORDER BY match_id';
			else
				$sql = 'SELECT match_id,nextm,nextlm,teamA,teamB,playwhen,place,status,score FROM '.
					$pref.'module_tmt_matches WHERE bracket_id=? ORDER BY match_id';
			$data->matches = $db->GetAssoc($sql,array($bracket_id));
			if ($data->matches)
			{
				foreach ($data->matches as $k=>&$mdata)
				{
					//strip seconds from the time
					$mdata['playwhen'] = substr($mdata['playwhen'],0,-3);
				}
				unset($mdata);
			}
			if($data->type == Tourney::RRTYPE && $data->matchview == 'plan')
			{
				$sql = 'SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=? ORDER BY displayorder';
				$allteams = $db->GetCol($sql,array($bracket_id));
				if ($allteams)
				{
					//populate data->matches with fake 'not-yet-constructed' matches (match id < 0)
					$fakes = array();
					if ($data->matches)
					{
						end($data->matches);
						$k = -key($data->matches)-1;
					}
					else
						$k = -1;
					foreach ($allteams as $tidA)
					{
						$otherteams = array_diff ($allteams, array($tidA));
						if ($otherteams)
						{
							foreach ($otherteams as $tidB)
							{
								$m = FALSE;
								foreach ($data->matches as &$mdata)
								{
									if ($mdata['teamA'] == $tidA)
									{
										if ($mdata['teamB'] == $tidB)
										{
											$m = TRUE;
											break;
										}
									}
									elseif($mdata['teamB'] == $tidA)
									{
										if ($mdata['teamA'] == $tidB)
										{
											$m = TRUE;
											break;
										}
									}
								}
								unset ($mdata);
								if (!$m) //match not recorded already
								{
									$fakes[$k--] = array (
//									 'nextm' => NULL,
//									 'nextlm' => NULL,
									 'teamA' => $tidA,
									 'teamB' => $tidB,
									 'playwhen' => NULL,
									 'place' => NULL,
									 'status' => 0,
									 'score' => NULL
									);
								}
							}
							$allteams = $otherteams;
						}
					}
					$data->matches = $data->matches + $fakes;
				}
			}
			$data->resultview = ($params && !empty($params['resultview']))?$params['resultview']:'future';
			if ($data->resultview == 'future')
				$cond = '<'.Tourney::ANON; //or MRES if ??
			else
				$cond = '>='.Tourney::MRES; //or ANON if ??
			$sql = 'SELECT match_id, teamA,teamB,playwhen,place,status,score FROM '.
				$pref.'module_tmt_matches WHERE bracket_id=? AND status'.$cond.' AND teamA IS NOT NULL AND teamB IS NOT NULL ORDER BY match_id';
			$data->results = $db->GetAssoc($sql,array($bracket_id));
			if ($data->results)
			{
				foreach ($data->results as &$mdata)
				{
					if (intval($mdata['status']) < Tourney::MRES) $mdata['status'] = Tourney::NOTYET;
					//strip seconds from the time
					$mdata['playwhen'] = substr($mdata['playwhen'],0,-3);
				}
				unset($mdata);
			}
			break;
		}
		return $data;
	}

	/**
	GetTypeNames:
	@mod: reference to current module object
	Returns: array with keys = dropdown-object labels, values = bracket-type enum values
	*/
	function GetTypeNames(&$mod)
	{
		return array(
		 $mod->Lang('select_one')=>'',
		 $mod->Lang('title_bracket_single')=>Tourney::KOTYPE,
		 $mod->Lang('title_bracket_double')=>Tourney::DETYPE,
		 $mod->Lang('title_bracket_round')=>Tourney::RRTYPE
		);
	}

	/**
	GetforFirstPlayer:
	@tid: team identifier
	Returns: array with name and contact data for first-ordered member of team @tid.
	  Name and contact are both empty if no such member can be found.
	*/
	function GetforFirstPlayer($tid)
	{
		$name = '';
		$contact = '';
		$db = cmsms()->GetDB();
		$sql = 'SELECT name,contact FROM '.cms_db_prefix().'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder';
		$rs = $db->SelectLimit($sql,1,-1,array($tid));
		if ($rs)
		{
			if (!$rs->EOF)
				extract($rs->FetchRow());
			$rs->Close();
		}
		return array($name,$contact);
	}

	/**
	UpdateFirstPlayer:
	@tid: team identifier
	@name: optional, string to be saved as player's name, default FALSE
	@contact: optional, string to be saved as player's contact, default FALSE
	Insert or update the name and/or contact of the first-ordered member of team @tid.
	At least one of @name, @contact must be supplied. They may be empty strings.
	*/
	function UpdateFirstPlayer($tid,$name=FALSE,$contact=FALSE)
	{
		if ($name === FALSE && $contact === FALSE) return;
		$pref = cms_db_prefix();
		$db = cmsms()->GetDB();
		$flags = 3; //default change-flag
		$args = array();
		$sql = 'SELECT displayorder FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2 ORDER BY displayorder';
		$rs = $db->SelectLimit($sql,1,-1,array($tid));
		if ($rs)
		{
			if (!$rs->EOF)
			{
				extract($rs->FetchRow());
				$rs->Close();
			}
			else
			{
				$rs->Close();
				$sql = 'INSERT INTO '.$pref.'module_tmt_people (
id,
name,
contact,
available,
displayorder,
flags
) VALUES (?,NULL,NULL,NULL,1,0)';
				$db->Execute($sql,array($tid));
				$flags = 1; //added-flag set instead
				$displayorder = 1;
			}
		}
		$sql = 'UPDATE '.$pref.'module_tmt_people SET ';
		if ($name !== FALSE)
		{
			if ($name)
			{
				$sql .= 'name=?';
				$args[] = $name;
			}
			else
				$sql .= 'name=NULL';
		}
		if ($contact !== FALSE)
		{
			if ($name !== FALSE)
				$sql .= ',';
			if ($contact)
			{
				$sql .= 'contact=?';
				$args[] = $contact;
			}
			else
				$sql .= 'contact=NULL';
		}
		$sql .= ',flags=? WHERE id=? AND flags!=2 AND displayorder=?';
		array_push ($args,$flags,$tid,$displayorder);
		$db->Execute($sql,$args);
	}

	/**
	GetFormattedDate:
	@dtstr string to be processed with strtotime()
	@dateonly TRUE if want formatted date without time, default FALSE
	@seconds TRUE if want time to include seconds, default FALSE
    Convert @dtstr to a timestamp-like format.
	Returns: string, maybe ''
	*/
	function GetFormattedDate($dtstr,$dateonly=FALSE,$seconds=FALSE)
	{
		if ($dtstr)
		{
			if ($dateonly)
				$fmt = '%F';
			elseif ($seconds)
				$fmt = '%F %T';
			else
				$fmt = '%F %R';
			
			return strftime ($fmt,strtotime($dtstr));
		}
		else
			return '';
	}
}
?>
