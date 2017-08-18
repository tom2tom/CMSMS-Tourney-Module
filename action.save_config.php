<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/
if(!$this->CheckAccess('modify')) exit;

if(isset($params['cancel']))
{
	unset ($params);
	$this->Redirect($id,'defaultadmin');
}

$errkey = FALSE;
$this->SetPreference('last_match_name',$params['tmt_final']);
$this->SetPreference('2ndlast_match_name',$params['tmt_semi']);
$this->SetPreference('3rdlast_match_name',$params['tmt_quarter']);
$this->SetPreference('4thlast_match_name',$params['tmt_eighth']);
$this->SetPreference('other_match_name',$params['tmt_roundname']);
$this->SetPreference('against_name',$params['tmt_versus']);
$this->SetPreference('noop_name',$params['tmt_bye']);
$this->SetPreference('forfeit_name',$params['tmt_forfeit']);
$this->SetPreference('abandon_name',$params['tmt_nomatch']);
if(isset($params['tmt_phoneid']))
	$this->SetPreference('phone_regex',trim($params['tmt_phoneid'])); //excludes pre- post-
$this->SetPreference('time_zone',$params['tmt_timezone']);
$this->SetPreference('time_format',$params['tmt_time_format']);
$this->SetPreference('date_format',$params['tmt_date_format']);
$subdir = trim($params['tmt_uploads_dir']," /\\\t\n\r");
if(!$subdir)
	$subdir = $this->GetName();
$updir = $config['uploads_path'];
if($updir)
{
	$updir .= DIRECTORY_SEPARATOR.$subdir;
	if(is_dir($updir) || mkdir($updir,0755))
		$this->SetPreference('uploads_dir',$subdir);
	else
		$errkey = 'err_uploaddir'; //TODO can't create
}
else
	$errkey = 'err_uploaddir'; //can't find
//$this->SetPreference('export_file',$params['tmt_export_file']);
//$this->SetPreference('strip_on_export',$params['tmt_strip_on_export']);
$this->SetPreference('export_encoding',$params['tmt_export_encoding']);

/*
$cfuncs = new Tourney\Crypter($this);
$key = Tourney\Crypter::MKEY;
$oldpw = $cfuncs->decrypt_preference($key);
$newpw = trim($params['tmt_'.$key]);
if($oldpw != $newpw) {
	//TODO update all data which uses password
	$cfuncs->encrypt_preference($key,$newpw);
}
*/

unset($params);
$msg = ($errkey) ?
	$this->PrettyMessage($errkey,FALSE) : $this->PrettyMessage('prefs_updated');
$this->Redirect($id,'defaultadmin','',
	array('showtab'=>2,'tmt_message'=>$msg));

?>
