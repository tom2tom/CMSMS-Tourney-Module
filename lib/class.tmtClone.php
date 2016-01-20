<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
Functions involved with XML output
*/

class tmtClone
{
	/**
	CloneBracket:
	No matches are cloned, of course.
	@mod: reference to Tourney module object
	@bracket_id: single bracket identifier, or array of them
	@withteams: optional, whether to clone team data as well, default FALSE
	Returns TRUE or Lang-key for error message
	*/
	function CloneBracket(&$mod,$bracket_id,$withteams=FALSE)
	{
		$gCms = cmsms();
		$config = $gCms->GetConfig();
		$db = $gCms->GetDb();
		$pref = cms_db_prefix();
		$updir = $mod->GetPreference('uploads_dir');
		$cl = $mod->Lang('clone');

		if(!is_array($bracket_id))
			$bracket_id = array($bracket_id);
		foreach($bracket_id as $sid)
		{
			$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$bdata = $db->GetRow($sql,array($sid));
			if ($bdata == FALSE)
				return 'err_missing';
			$did = $db->GenID($pref.'module_tmt_brackets_seq');
			$bdata['bracket_id'] = $did;
			$bdata['name'] .= ' '.$cl;
			if($bdata['alias'])
				$bdata['alias'] .= '_'.strtolower($cl);
			//check for date(s) > local current date
			$tz = new DateTimeZone($bdata['timezone']);
			$dt = new DateTime ('now',$tz);
			$stamp = $dt->getTimestamp();
			$sdt = new DateTime ($bdata['startdate'],$tz);
			$sstamp = $sdt->getTimestamp();
			if ($stamp >= $sstamp)
				$bdata['startdate'] = null;
			$sdt = new DateTime ($bdata['enddate'],$tz);
			$sstamp = $sdt->getTimestamp();
			if ($stamp >= $sstamp)
				$bdata['enddate'] = null;
			if($bdata['chartcss'])
			{
				if ($updir)
					$csspath = cms_join_path($config['uploads_path'],$updir,$bdata['chartcss']);
				else
					$csspath = cms_join_path($config['uploads_path'],$bdata['chartcss']);
				if(!file_exists($csspath))
					$bdata['chartcss'] = '';
			}
			$bdata['chartbuild'] = 1;

			$values = array_values($bdata);
			$fc = count($values);
			$fillers = str_repeat('?,',$fc-1);
			$sql = 'INSERT INTO '.$pref."module_tmt_brackets VALUES ($fillers?)";
			$db->Execute($sql,$values);

			$tpl = tmtTemplate::Get($mod,'mailout_'.$sid.'_template');
			if ($tpl)
				tmtTemplate::Set($mod,'mailout_'.$did.'_template',$tpl);
			$tpl = tmtTemplate::Get($mod,'mailcancel_'.$sid.'_template');
			if ($tpl)
				tmtTemplate::Set($mod,'mailcancel_'.$did.'_template',$tpl);
			$tpl = tmtTemplate::Get($mod,'mailrequest_'.$sid.'_template');
			if ($tpl)
				tmtTemplate::Set($mod,'mailrequest_'.$did.'_template',$tpl);
			$tpl = tmtTemplate::Get($mod,'mailin_'.$sid.'_template');
			if ($tpl)
				tmtTemplate::Set($mod,'mailin_'.$did.'_template',$tpl);
			$tpl = tmtTemplate::Get($mod,'tweetout_'.$sid.'_template');
			if ($tpl)
				tmtTemplate::Set($mod,'tweetout_'.$did.'_template',$tpl);
			$tpl = tmtTemplate::Get($mod,'tweetcancel_'.$sid.'_template');
			if ($tpl)
				tmtTemplate::Set($mod,'tweetcancel_'.$did.'_template',$tpl);
			$tpl = tmtTemplate::Get($mod,'tweetrequest_'.$sid.'_template');
			if ($tpl)
				tmtTemplate::Set($mod,'tweetrequest__'.$did.'_template',$tpl);
			$tpl = tmtTemplate::Get($mod,'tweetin_'.$sid.'_template');
			if ($tpl)
				tmtTemplate::Set($mod,'tweetin_'.$did.'_template',$tpl);
			$tpl = tmtTemplate::Get($mod,'chart_'.$sid.'_template');
			if ($tpl)
				tmtTemplate::Set($mod,'chart_'.$did.'_template',$tpl);

			if($withteams)
			{
				$sql = 'SELECT * FROM '.$pref.'module_tmt_teams WHERE bracket_id=? AND flags!=2';
				$teams = $db->GetAll($sql,array($sid));
				if ($teams)
				{
					$swaps = array();
					$fc = count($teams[0]);
					$fillers = str_repeat('?,',$fc-1);
					$sql = 'INSERT INTO '.$pref."module_tmt_teams VALUES ($fillers?)";
					foreach($teams as &$modteam)
					{
						$tid = $db->GenID($pref.'module_tmt_teams_seq');
						$swaps[] = array($modteam['team_id'],$tid);
						$modteam['team_id'] = $tid;
						$modteam['bracket_id'] = $did;
						$modteam['flags'] = 0;
						$values = array_values($modteam);
						$db->Execute($sql,$values);
					}
					unset($modteam);
					$sql = 'SELECT * FROM '.$pref.'module_tmt_people WHERE id=? AND flags!=2';
					$sql2 = 'INSERT INTO '.$pref.'module_tmt_people VALUES (?,?,?,?,?)';
					foreach($swaps as &$modteam)
					{
						$people = $db->GetAll($sql,array($modteam[0]));
						if ($people)
						{
							foreach ($people as &$person)
							{
								$person['id'] = $modteam[1];
								$person['flags'] = 0;
								$db->Execute($sql2,array_values($person));
							}
							unset($person);
						}
					}
					unset($modteam);
				}
			}
		}
		return TRUE;
	}
}

?>
