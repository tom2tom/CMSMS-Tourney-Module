<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

class tmtTemplate
{
	public static function Set(&$mod,$name)
	{
		if($mod->before20)
			$mod->SetTemplate($name);
		else
		{
			if(1) //using old templates anyway
				$mod->SetTemplate($name);
			else
			{
				//TODO new form
			}
		}
	}

	public static function Get(&$mod,$name)
	{
		if($mod->before20)
			return $mod->GetTemplate($name);
		else
		{
			if(1) //using old templates anyway
				return $mod->GetTemplate($name);
			else
			{
				//TODO new form
			}
		}
	}

	public static function Delete(&$mod,$name)
	{
		if($mod->before20)
			$mod->DeleteTemplate($name);
		else
		{
			if(1) //using old templates anyway
				$mod->DeleteTemplate($name);
			else
			{
				//TODO new form
			}
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
