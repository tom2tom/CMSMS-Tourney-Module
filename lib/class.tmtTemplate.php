<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright (C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

class tmtTemplate
{
	/**
	Set:
	@mod: reference to current Tourney module object
	@tplname: template identifier
	@content: template contents
	*/
	public static function Set(&$mod,$tplname,$content)
	{
		if($mod->before20 || 1)	//TODO if using old templates anyway
			$mod->SetTemplate($tplname,$content);
		else
		{
			try {
				$tpl = new CmsLayoutTemplate();
				$tpl->set_name('tmt_'.$tplname); //must be unique!
			} catch (CmsInvalidDataException $e) {
				$tpl = CmsLayoutTemplate::load('tmt_'.$tplname);
				$tpl->set_content($content);
				$tpl->save();
				return;
			}
			$offs = strpos($tplname,'_');
			if($offs !== FALSE)
				$type = substr($tplname,0,$offs);
			else
				$type = $tplname; //probably BAD!
			$tpl->set_type($type);
			$tpl->set_content($content);
			$tpl->set_type_dflt(false);
//		$tpl->set_listable(true); //CMSMS 2.1+
			$tpl->save();
		}
	}

	/**
	Get:
	@mod: reference to current Tourney module object
	@tplname: template identifier
	*/
	public static function Get(&$mod,$tplname)
	{
		if($mod->before20 || 1) //TODO if using old templates anyway
			return $mod->GetTemplate($tplname);
		else
		{
			try {
				$tpl = CmsLayoutTemplate::load('tmt_'.$tplname);
			} catch (CmsDataNotFoundException $e) {
				return '';
			}
			return $tpl->get_content();
		}
	}

	/**
	Delete:
	@mod: reference to current Tourney module object
	@tplname: template identifier
	*/
	public static function Delete(&$mod,$tplname)
	{
		if($mod->before20 || 1) //TODO if using old templates anyway
			$mod->DeleteTemplate($tplname);
		else
		{
			try {
				$tpl = CmsLayoutTemplate::load('tmt_'.$tplname);
			} catch (CmsDataNotFoundException $e) {
				return;
			}
			$tpl->delete();
		}
	}

	/**
	Process:
	@mod: reference to current Tourney module object
	@tplname: template identifier
	@tplvars: associative array of template variables
	@cache: optional boolean, default TRUE
	Returns: nothing
	*/
	public static function Process(&$mod,$tplname,$tplvars,$cache=TRUE)
	{
		global $smarty;
		if($mod->before20)
		{
			$smarty->assign($tplvars);
			echo $mod->ProcessTemplate($tplname);
		}
		else
		{
			if($cache)
			{
				$cache_id = md5('tmt'.$tplname.serialize(array_keys($tplvars)));
				$lang = CmsNlsOperations::get_current_language();
				$compile_id = md5('tmt'.$tplname.$lang);
				$tpl = $smarty->CreateTemplate($mod->GetFileResource($tplname),$cache_id,$compile_id,$smarty);
				if(!$tpl->isCached())
					$tpl->assign($tplvars);
			}
			else
			{
				$tpl = $smarty->CreateTemplate($mod->GetFileResource($tplname),NULL,NULL,$smarty,$tplvars);
			}
			$tpl->display();
		}
	}

	/**
	ProcessFromData:
	@mod: reference to current Tourney module object
	@data: string
	@tplvars: associative array of template variables
	No cacheing.
	Returns: string, processed template
	*/
	public static function ProcessFromData(&$mod,$data,$tplvars)
	{
		global $smarty;
		if($mod->before20)
		{
			$smarty->assign($tplvars);
			return $mod->ProcessTemplateFromData($data);
		}
		else
		{
			$tpl = $smarty->CreateTemplate('eval:'.$data,NULL,NULL,$smarty,$tplvars);
			return $tpl->fetch();
		}
	}

}

?>
