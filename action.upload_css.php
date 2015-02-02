<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
Action to upload a selected .css file for bracket chart(s)
*/

if (isset($params['upcancel']))
{
	$newparms = $this->GetEditParms($params,'charttab');
	$this->Redirect($id,'addedit_comp',$returnid,$newparms);
}

if (!$this->CheckAccess('admod'))
{
	$newparms = $this->GetEditParms($params,'charttab',$this->PrettyMessage('lackpermission',FALSE));
	$this->Redirect($id,'addedit_comp',$returnid,$newparms);
}

$fn = $id.'cssfile';
if (isset($_FILES) && isset($_FILES[$fn]))
{
	$file_data = $_FILES[$fn];
		$parts = explode('.',$file_data['name']);
		$ext = end($parts);
	if ($file_data['type'] != 'text/css'
	 || !($ext == 'css' || $ext == 'CSS')
	 || $file_data['size'] <= 0 || $file_data['size'] > 2048 //plenty big enough in this context
	 || $file_data['error'] != 0)
	{
		$newparms = $this->GetEditParms($params,'charttab',$this->PrettyMessage('err_file',FALSE));
		$this->Redirect($id,'addedit_comp',$returnid,$newparms);
	}
	$h = fopen($file_data['tmp_name'],'r');
	if ($h)
	{
		//basic validation of file-content
		$content = fread($h,512);
		fclose($h);
		if ($content == FALSE)
			$message = $this->PrettyMessage('lackpermission',FALSE);
		if (!preg_match('/\.bracket.*\\n?{/',$content))
			$message = $this->PrettyMessage('err_file',FALSE);
		unset($content);
	}
	else
		$message = $this->PrettyMessage('lackpermission',FALSE);

	if (empty($message))
	{
		$csspath = cms_join_path($config['uploads_path'],
			$this->GetPreference('uploads_dir'),$file_data['name']);
		if (!chmod($file_data['tmp_name'],0644) ||
			!cms_move_uploaded_file($file_data['tmp_name'], $csspath))
			$message = $this->PrettyMessage('upload_failed',FALSE);
		else //all good
		{
			$sql = 'UPDATE '.cms_db_prefix().'module_tmt_brackets SET chartcss=? WHERE bracket_id=?';
			$db->Execute($sql,array($file_data['name'],$params['bracket_id']));
		}
	}
	if (empty($message))
		$message = FALSE;
	$newparms = $this->GetEditParms($params,'charttab',$message);
	$this->Redirect($id,'addedit_comp',$returnid,$newparms);
}

$smarty->assign('start_form',$this->CreateFormStart($id,'upload_css',$returnid,'post','multipart/form-data'));
$smarty->assign('end_form',$this->CreateFormEnd());
$smarty->assign('hidden',$this->GetHiddenParms($id,$params,'charttab'));
$smarty->assign('title',$this->Lang('title_cssfile2',$params['tmt_name']));
$smarty->assign('chooser',$this->CreateInputFile($id,'cssfile','text/css',30));
$smarty->assign('upload',$this->CreateInputSubmit($id,'upstart',$this->Lang('upload')));
$smarty->assign('cancel',$this->CreateInputSubmit($id,'upcancel',$this->Lang('cancel')));
$smarty->assign('help',$this->Lang('help_cssupload'));

echo $this->ProcessTemplate('upload.tpl');
?>
