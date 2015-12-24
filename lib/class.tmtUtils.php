<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
*/

class tmtUtils
{
	const ENC_ROUNDS = 10000;
	/**
	encrypt_value:
	@mod: reference to current module object
	@value: string to encrypted, may be empty
	@passwd: optional password string, default FALSE (meaning use the module-default)
	@based: optional boolean, whether to base64_encode the encrypted value, default TRUE
	Returns: encrypted @value, or just @value if it's empty
	*/
	function encrypt_value(&$mod,$value,$passwd=FALSE,$based=TRUE)
	{
		if($value)
		{
			if(!$passwd)
			{
				$passwd = self::unfusc($mod->GetPreference('masterpass'));
			}
			if($passwd && $mod->havemcrypt)
			{
				$e = new Encryption(MCRYPT_BLOWFISH,MCRYPT_MODE_CBC,self::ENC_ROUNDS);
				$value = $e->encrypt($value,$passwd);
				if($based)
					$value = base64_encode($value);
			}
			else
				$value = self::fusc($passwd.$value);
		}
		return $value;
	}

	/**
	decrypt_value:
	@mod: reference to current module object
	@value: string to decrypted, may be empty
	@passwd: optional password string, default FALSE (meaning use the module-default)
	@based: optional boolean, whether to base64_decode the value, default TRUE
	Returns: decrypted @value, or just @value if it's empty
	*/
	function decrypt_value(&$mod,$value,$passwd=FALSE,$based=TRUE)
	{
		if($value)
		{
			if(!$passwd)
			{
				$passwd = self::unfusc($mod->GetPreference('masterpass'));
			}
			if($passwd && $mod->havemcrypt)
			{
				if($based)
					$value = base64_decode($value);
				$e = new Encryption(MCRYPT_BLOWFISH,MCRYPT_MODE_CBC,self::ENC_ROUNDS);
				$value = $e->decrypt($value,$passwd);
			}
			else
				$value = substr(strlen($passwd),self::unfusc($value));
		}
		return $value;
	}

	/**
	fusc:
	@str: string or FALSE
	obfuscate @str
	*/
	function fusc($str)
	{
		if($str)
		{
			$s = substr(base64_encode(md5(microtime())),0,5);
			return $s.base64_encode($s.$str);
		}
		return '';
	}

	/**
	unfusc:
	@str: string or FALSE
	de-obfuscate @str
	*/
	function unfusc($str)
	{
		if($str)
		{
			$s = base64_decode(substr($str,5));
			return substr($s,5);
		}
		return '';
	}

}

?>
