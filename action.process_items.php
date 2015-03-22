<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
 
Processes form-submission from admin brackets tab
*/

if(isset($params['cancel']) || empty($params['selitems']))
	$this->Redirect($id,'defaultadmin');
elseif(isset($params['clone']))
{
	if (!$this->CheckAccess('admod'))
		$this->Redirect($id,'defaultadmin','',
			array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

	$vals = array_flip($params['selitems']); //convert strings
	$vals = array_flip($vals);
	if($vals)
	{
		$funcs = new tmtClone();
		$res = $funcs->CloneBracket($this,$vals);
		if($res !== TRUE)
			$this->Redirect($id,'defaultadmin','',
				array('tmt_message'=>$this->PrettyMessage($res,FALSE)));
	}
}
elseif(isset($params['group'])) //begin grouping process
{
	$smarty->assign('start_form',$this->CreateFormStart($id,'process_items',$returnid));
	$smarty->assign('end_form',$this->CreateFormEnd());
	$smarty->assign('hidden',$this->CreateInputHidden($id,'selitems',implode(';',$params['selitems'])));
	$smarty->assign('title',$this->Lang('title_bracketsgroup'));
	$options = $this->GetGroups();
	if($options)
	{
		foreach($options as &$row)
			$row = $row['name'];
		unset($row);
		$options = array_flip($options);
	}
	else
		$options = array($this->Lang('groupdefault')=>0); //ensure something exists
	$options = array($this->Lang('select_one')=>-1) + $options;
	$smarty->assign('chooser',$this->CreateInputDropdown($id,'togroup',$options,-1,-1));
	$smarty->assign('apply',$this->CreateInputSubmitDefault($id,'apply',$this->Lang('apply')));
	$smarty->assign('cancel',$this->CreateInputSubmit($id,'cancel', $this->Lang('cancel')));
	$smarty->assign('help',''); //$this->Lang('help_bracketsgroup'));
	echo $this->ProcessTemplate('onepage.tpl');
	return;
}
elseif(isset($params['apply'])) //selected group
{
	$gid = (int)$params['togroup']; //new group choice
	if($gid != -1)
	{
		$vals = array_flip(explode(';',$params['selitems'])); //convert strings
		$vals = array_flip($vals);
		$vc = count($vals);
		$fillers = str_repeat('?,',$vc-1).'?';
		$pref = cms_db_prefix();
		$sql = 'UPDATE '.$pref.'module_tmt_brackets SET groupid=? WHERE bracket_id IN ('.$fillers.')';
		array_unshift($vals,$gid);
		$db->Execute($sql,$vals);
	}
}
elseif(isset($params['delete_item'])) //delete selected bracket(s)
{
	if (!$this->CheckAccess('admod'))
		$this->Redirect($id,'defaultadmin','',
			array('tmt_message'=>$this->PrettyMessage('lackpermission',FALSE)));

	$vals = array_flip($params['selitems']); //convert strings
	$vals = array_flip($vals);
	if($vals)
	{
		$funcs = new tmtDelete();
		$funcs->DeleteBracket($this,$vals);
	}
}
elseif(isset($params['notify']))
{
	$vals = array_flip($params['selitems']); //convert strings
	$vals = array_flip($vals);
	if($vals)
	{
		$vc = count($vals);
		$fillers = str_repeat('?,',$vc-1).'?';
		$pref = cms_db_prefix();
		$sql = 'SELECT match_id,bracket_id FROM '.$pref.'module_tmt_matches WHERE bracket_id IN ('.$fillers.
		') AND flags=0 AND status<'.MRES.
		' AND playwhen IS NOT NULL AND ((teamA IS NOT NULL AND teamA>0) OR (teamB IS NOT NULL AND teamB>0))';
		$matches = $db->GetAssoc($sql,$vals);
		if($matches)
		{
			$allmsg = '';
			$sql = 'UPDATE '.$pref.'module_tmt_matches SET status='.TOLD.' WHERE match_id=?';
			$funcs = new tmtComm($this);
			foreach($matches as $mid=>$bid)
			{
				list($res,$errmsg) = $funcs->TellTeams((int)$bid,$mid);
				if($res)
					$db->Execute($sql,array($mid));
				elseif($errmsg)
				{
					if($allmsg)
						$allmsg .= '<br />';
					$allmsg .= $errmsg;
				}
			}
			if($allmsg)
				$this->Redirect($id,'defaultadmin','',
					array('tmt_message'=>$this->PrettyMessage($allmsg,FALSE,FALSE,FALSE)));
		}
	}
}
elseif(isset($params['print']))
{
	$vals = array_flip($params['selitems']); //convert strings
	$vals = array_flip($vals);
	if($vals)
	{
		$pref = cms_db_prefix();
		$sql = 'SELECT * FROM '.$pref.'module_tmt_brackets WHERE bracket_id=?';
		$sql2 = 'UPDATE '.$pref.'module_tmt_brackets SET chartbuild = 1 WHERE bracket_id=?';
		$sch = new tmtSchedule();
		$lyt = new tmtLayout();
		$message = '';
		$onefile = (count($vals) == 1);
		if($onefile)
			$zip = FALSE;
		else
		{
			$fn = 'brackets-charts-'.implode('-',$vals).'.zip';
			$fp = cms_join_path($config['root_path'],'tmp',$fn);
			$zip = new ZipArchive();
			if($zip->open($fp,ZipArchive::CREATE) !== TRUE)
			{
				$message = $this->PrettyMessage('err_zip',FALSE);
				$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$message));
			}
		}
		
		foreach($vals as $bid)
		{
			$bdata = $db->GetRow($sql,array($bid));
			//refresh the matches table, if necessary
			switch ($bdata['type'])
			{
			 case DETYPE:
				$sch->UpdateDEMatches ($this,$bid);
				break;
			 case RRTYPE:
				$sch->NextRRMatches($this,$bid);
				break;
			 default:
			// case KOTYPE:
				$sch->UpdateKOMatches($this,$bid);
				break;
			}
			$bdata['chartbuild'] = 1; //tell downstream that rebuild is needed
			list($chartfile,$errkey) = $lyt->GetChart($this,$bdata,FALSE,0);
			if($chartfile)
			{
				//force refresh next time
				$db->Execute($sql2,array($bid));
				if(!$onefile && is_file($chartfile))
				{
					$zip->addFile($chartfile,basename($chartfile));
					//cannot delete $chartfile yet
				}
			}
			else
			{
				if(!$message)
					$message = $this->PrettyMessage('err_chart',FALSE);
				$message .= '<br />'.$bdata['name'];
				if($errkey)
					$message .= ': '.$this->Lang($errkey);
			}
		}
		unset($sch);
		unset($lyt);
		if($onefile)
		{
			if($chartfile && is_file($chartfile))
			{
				//export $chartfile
				$content = file_get_contents($chartfile);
				$fn = basename($chartfile);
				$ft = 'application/pdf';
			}
			else
				$content = FALSE;
		}
		elseif($zip)
		{
			$zip->close();
			if(is_file($fp))
			{
				$content = file_get_contents($fp);
				unlink($fp);
				$ft = 'application/zip';
			}
			else
				$content = FALSE;
		}

		if($content)
		{
			@ob_clean();
			header('Pragma: public');
			header('Expires: 0');
			header('Cache-Control: must-revalidate,post-check=0,pre-check=0');
			header('Cache-Control: private',FALSE);
			header('Content-Description: File Transfer');
			header('Content-Type: '.$ft);
			header('Content-Length: '.strlen($content));
			header('Content-Disposition: attachment; filename="'.$fn.'"');
			echo $content;
			if(!$message)
				exit;
			$this->Redirect($id,'defaultadmin','',array('tmt_message'=>$message));
		}
	}
}
elseif(isset($params['export']))
{
	$vals = array_flip($params['selitems']); //convert strings
	$vals = array_flip($vals);
	if($vals)
	{
		$funcs = new tmtXML();
		$res = $funcs->ExportXML($this,$params['selitems']);
		if ($res === TRUE)
			exit;
		$this->Redirect($id,'defaultadmin','',
				array('tmt_message'=>$this->PrettyMessage($res,FALSE)));
	}
}

$this->Redirect($id,'defaultadmin');

?>
