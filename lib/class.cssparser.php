<?php
/*
Class: cssparser.
Copyright (C) 2003 Thomas Björk
This file is free software; you can redistribute it and/or modify it
under the terms of the GNU Library General Public License as published by the
Free Software Foundation; either version 2.1 of the License, or (at your option)
any later version.

This file is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Lesser General Public License for more details. If you don't have a copy of
that license, read it online at: http://www.gnu.org/licenses/licenses.html#LGPL

Html-related functionality omitted. Redundant unsetting omitted. Bugs fixed.
*/
class cssparser {
  var $css;

  function cssparser() {
    $this->css = array ();
  }

  function Clear() {
    unset($this->css);
    $this->css = array();
  }

  function Add($key, $codestr) {
    $key = strtolower($key);
    if(!isset($this->css[$key])) {
      $this->css[$key] = array();
    }
    $codes = explode(';',$codestr);
    if($codes) {
      foreach($codes as $code) {
        $code = trim($code);
        if ($code) {
          list($codekey, $codevalue) = explode(':',$code,2) + array(NULL,NULL);
          if($codekey) {
            $codekey = strtolower(rtrim($codekey));
            $lcv = strtolower(ltrim($codevalue));
            if ($lcv) {
              $this->css[$key][$codekey] = (substr($lcv,0,3) != 'url') ?
                $lcv : 'url'.rtrim(substr($codevalue,3));
            }
          }
        }
      }
    }
  }

  function Get($key, $property) {
    $key = strtolower($key);
    $property = strtolower($property);

    list($tag, $subtag) = explode(':',$key) + array(NULL,NULL);
    list($tag, $class) = explode('.',$tag) + array(NULL,NULL);
    list($tag, $id) = explode('#',$tag) + array(NULL,NULL);
    $result = '';
    foreach($this->css as $_tag => $value) {
      list($_tag, $_subtag) = explode(':',$_tag) + array(NULL,NULL);
      list($_tag, $_class) = explode('.',$_tag) + array(NULL,NULL);
      list($_tag, $_id) = explode('#',$_tag) + array(NULL,NULL);

      $tagmatch = (strcmp($tag, $_tag) == 0) | (strlen($_tag) == 0);
      $subtagmatch = (strcmp($subtag, $_subtag) == 0) | (strlen($_subtag) == 0);
      $classmatch = (strcmp($class, $_class) == 0) | (strlen($_class) == 0);
      $idmatch = (strcmp($id, $_id) == 0);

      if($tagmatch & $subtagmatch & $classmatch & $idmatch) {
        $temp = $_tag;
        if((strlen($temp) > 0) & (strlen($_class) > 0)) {
          $temp .= '.'.$_class;
        } elseif(strlen($temp) == 0) {
          $temp = '.'.$_class;
        }
        if((strlen($temp) > 0) & (strlen($_subtag) > 0)) {
          $temp .= ':'.$_subtag;
        } elseif(strlen($temp) == 0) {
          $temp = ':'.$_subtag;
        }
        if(isset($this->css[$temp][$property])) {
          $result = $this->css[$temp][$property];
        }
      }
    }
    return $result;
  }

  function GetSection($key) {
    $key = strtolower($key);

    list($tag, $subtag) = explode(':',$key) + array(NULL,NULL);
    list($tag, $class) = explode('.',$tag) + array(NULL,NULL);
    list($tag, $id) = explode('#',$tag) + array(NULL,NULL);
    $result = array();
    foreach($this->css as $_tag => $value) {
      list($_tag, $_subtag) = explode(':',$_tag) + array(NULL,NULL);
      list($_tag, $_class) = explode('.',$_tag) + array(NULL,NULL);
      list($_tag, $_id) = explode('#',$_tag) + array(NULL,NULL);

      $tagmatch = (strcmp($tag, $_tag) == 0) | (strlen($_tag) == 0);
      $subtagmatch = (strcmp($subtag, $_subtag) == 0) | (strlen($_subtag) == 0);
      $classmatch = (strcmp($class, $_class) == 0) | (strlen($_class) == 0);
      $idmatch = (strcmp($id, $_id) == 0);

      if($tagmatch & $subtagmatch & $classmatch & $idmatch) {
        $temp = $_tag;
        if((strlen($temp) > 0) & (strlen($_class) > 0)) {
          $temp .= '.'.$_class;
        } elseif(strlen($temp) == 0) {
          $temp = '.'.$_class;
        }
        if((strlen($temp) > 0) & (strlen($_subtag) > 0)) {
          $temp .= ':'.$_subtag;
        } elseif(strlen($temp) == 0) {
          $temp = ':'.$_subtag;
        }
        foreach($this->css[$temp] as $property => $value) {
          $result[$property] = $value;
        }
      }
    }
    return $result;
  }

  function ParseStr($str) {
    $this->Clear();
    // Remove comments
    $str = preg_replace(array('~/\*(.*)?\*/~Usm','~\s?//.*$~m'),array('',''),$str);
    // Parse this damn csscode
    $parts = explode('}',$str);
    if($parts) {
      foreach($parts as $part) {
        if ($part) {
          list($keystr,$codestr) = explode('{',$part) + array(NULL,NULL);
          $keys = explode(',',trim($keystr));
          if($keys) {
            foreach($keys as $key) {
              if(strlen($key) > 0) {
                $key = str_replace(array(PHP_EOL,'\\'),array('',''),$key);
                $this->Add($key, trim($codestr));
              }
            }
          }
        }
      }
    }
    return (count($this->css) > 0);
  }

  function Parse($filename) {
    $this->Clear();
    if(file_exists($filename)) {
      return $this->ParseStr(file_get_contents($filename));
    } else {
      return FALSE;
    }
  }

  function GetCSS() {
    $result = '';
    foreach($this->css as $key => $values) {
      $result .= $key.' {'.PHP_EOL;
      foreach($values as $key => $value) {
        $result .= "  $key: $value;".PHP_EOL;
      }
      $result .= '}'.PHP_EOL.PHP_EOL;
    }
    return $result;
  }
}
?>
