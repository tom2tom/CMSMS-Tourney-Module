<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
if(isset($params['export']))
{
	$newparms = $this->GetEditParms($params,'playerstab');
	if (!empty($params['tsel']))
	{
/*		if($this->GetPreference('export_file',0))
		{
			$path = TODOPATH;
			$handle = fopen($path,'w');
			if($handle)
			{
				$funcs = new tmtCSV();
				$funcs->TeamsToCSV($this,$params['bracket_id'],$params['tsel'],FALSE,$handle);
				fclose($handle);
			}
			else
			{
				$newparms['tmt_message'] = $this->PrettyMessage('TODO CANT WRITE FILE',FALSE));
			}
		}
		else
		{
*/
		$funcs = new tmtCSV();
		$funcs->TeamsToCSV($this,$params['bracket_id'],$params['tsel']);
		return;
//		}
	}
}
elseif(isset($params['notify']))
{
	$newparms = $this->GetEditParms($params,'matchestab');
	if(!empty($params['msel']))
	{
		$ok = TRUE;
		$sql = 'UPDATE '.cms_db_prefix().'module_tmt_matches SET status='.TOLD.' WHERE match_id=?';
		$errs = array();
		$ids = array_keys($params['mat_status']);
		$funcs = new tmtComm($this);
		foreach ($params['msel'] as $mid)
		{
			list($res,$errmsg) = $funcs->TellTeams($params['bracket_id'],$mid);
			if($res)
				$db->Execute($sql,array($mid));
			else
			{
				$ok = FALSE;
				$idx = array_search($mid,$ids);
				$errs[] = sprintf($this->Lang('or_fmt',
					$this->TeamName($params['mat_teamA'][$idx]),
					$this->TeamName($params['mat_teamB'][$idx]))).' '.$errmsg;
			}
		}
		if(!$ok)
		{
			$errmsg = $this->PrettyMessage('err_notice',FALSE);
			if($errs)
				$errmsg .= '<br />'.implode('<br />',$errs);
			$newparms['tmt_message'] = $errmsg;
		}
	}
}

$this->Redirect($id,'addedit_comp',$returnid,$newparms);

?>
