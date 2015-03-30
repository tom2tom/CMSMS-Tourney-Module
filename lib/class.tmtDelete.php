<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
Functions involved with XML output
*/

class tmtDelete
{
	/**
	DeleteBracket:
	@mod: reference to Tourney module object
	@bracket_id: single bracket identifier, or array of them
	*/
	function DeleteBracket(&$mod,$bracket_id)
	{
		$db = cmsms()->GetDb();
		$pref = cms_db_prefix();
		if(!is_array($bracket_id))
			$bracket_id = array($bracket_id);
		foreach($bracket_id as $bid)
		{
			$sql = 'SELECT chartcss FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$file = $db->GetOne($sql,array($bid));
			if ($file)
			{
				$sql = 'SELECT COUNT(*) AS sharers FROM '.$pref.'module_tmt_brackets WHERE chartcss=?';
				$num = $db->GetOne($sql,array($file));
				if ($num < 2)
				{
					if($mod->GetPreference('uploads_dir'))
						$path = cms_join_path($config['uploads_path'],
							$mod->GetPreference('uploads_dir'),$file);
					else
						$path = cms_join_path($config['uploads_path'],$file);
					if(is_file($path))
						unlink($path);
				}
			}
			$file = $mod->ChartImageFile($bid);
			if ($file)
				unlink($file);

			$sql = 'DELETE FROM '.$pref.'module_tmt_tweet WHERE bracket_id=?';
			$db->Execute($sql,array($bid));
			$sql = 'DELETE FROM '.$pref.'module_tmt_people WHERE id IN
			 (SELECT team_id FROM '.$pref.'module_tmt_teams WHERE bracket_id=?)';
			$db->Execute($sql,array($bid));
			$sql = 'DELETE FROM '.$pref.'module_tmt_teams WHERE bracket_id=?';
			$db->Execute($sql,array($bid));
			$sql = 'DELETE FROM '.$pref.'module_tmt_matches WHERE bracket_id=?';
			$db->Execute($sql,array($bid));
			$sql = 'DELETE FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
			$db->Execute($sql,array($bid));

			$bid = $params['bracket_id'];
			$mod->DeleteTemplate('mailout_'.$bid.'_template');
			$mod->DeleteTemplate('mailcancel_'.$bid.'_template');
			$mod->DeleteTemplate('mailrequest_'.$bid.'_template');
			$mod->DeleteTemplate('mailin_'.$bid.'_template');
			$mod->DeleteTemplate('tweetout_'.$bid.'_template');
			$mod->DeleteTemplate('tweetcancel_'.$bid.'_template');
			$mod->DeleteTemplate('tweetrequest_'.$bid.'_template');
			$mod->DeleteTemplate('tweetin_'.$bid.'_template');
			$mod->DeleteTemplate('chart_'.$bid.'_template');
		}
	}
}

?>
