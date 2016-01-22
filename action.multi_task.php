<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
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
elseif(isset($params['notify']) || isset($params['abandon']))
{
	$newparms = $this->GetEditParms($params,'matchestab');
	if(!empty($params['msel']))
	{
		if(isset($params['notify']))
			$tpltype = 1;
		else //isset($params['abandon'])
			$tpltype = 2;
		$ok = TRUE;
		$sql = 'UPDATE '.cms_db_prefix().'module_tmt_matches SET status='.Tourney::TOLD.' WHERE match_id=?';
		$errs = array();
		$ids = array_keys($params['mat_status']); //match id's
		$funcs = new tmtComm($this);
		foreach ($params['msel'] as $mid)
		{
			list($res,$errmsg) = $funcs->TellTeams($params['bracket_id'],$mid,$tpltype);
			if($res)
				$db->Execute($sql,array($mid));
			else
			{
				$ok = FALSE;
				if($errmsg)
					$errs[] = $errmsg;
				else
				{
					$idx = array_search($mid,$ids);
					$tA = (int)$params['mat_teamA'][$idx];
					$tB = (int)$params['mat_teamB'][$idx];
					if($tA > 0 && $tB > 0)
						$errs[] = sprintf($this->Lang('or_fmt',
							$this->TeamName($tA),
							$this->TeamName($tB)));
					elseif($tA > 0)
						$errs[] = $this->TeamName($tA);
					elseif($tB > 0)
						$errs[] = $this->TeamName($tB);
				}
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
elseif(isset($params['getscore']))
{
	$newparms = $this->GetEditParms($params,'resultstab');
	if(!empty($params['rsel']))
	{
		$ok = TRUE;
		$sql = 'UPDATE '.cms_db_prefix().'module_tmt_matches SET status='.Tourney::ASKED.' WHERE match_id=?';
		$errs = array();
		$ids = array_keys($params['res_status']); //match id's
		$funcs = new tmtComm($this);
		foreach ($params['rsel'] as $mid)
		{
			list($res,$errmsg) = $funcs->TellTeams($params['bracket_id'],$mid,3,TRUE);
			if($res)
				$db->Execute($sql,array($mid));
			else
			{
				$ok = FALSE;
				if($errmsg)
					$errs[] = $errmsg;
				else
				{
					$idx = array_search($mid,$ids);
					$tA = (int)$params['res_teamA'][$idx];
					$tB = (int)$params['res_teamB'][$idx];
					if($tA > 0 && $tB > 0)
						$errs[] = sprintf($this->Lang('or_fmt',
							$this->TeamName($tA),
							$this->TeamName($tB)));
					elseif($tA > 0)
						$errs[] = $this->TeamName($tA);
					elseif($tB > 0)
						$errs[] = $this->TeamName($tB);
				}
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
