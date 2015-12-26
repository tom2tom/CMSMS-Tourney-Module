<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney

Class: tmtStyler. Data and functions involved with tournament chart construction and styling
*/
class tmtStyler extends cssparser {
	private $colors;

	function __construct() {
	//color-names supported by modern browsers(from http://www.w3schools.com/html/html_colornames.asp)
	$this->colors = array(
	'aliceblue'=>'#f0f8ff',
	'antiquewhite'=>'#faebd7',
	'aqua'=>'#00ffff',
	'aquamarine'=>'#7fffd4',
	'azure'=>'#f0ffff',
	'beige'=>'#f5f5dc',
	'bisque'=>'#ffe4c4',
	'black'=>'#000000',
	'blanchedalmond'=>'#ffebcd',
	'blue'=>'#0000ff',
	'blueviolet'=>'#8a2be2',
	'brown'=>'#a52a2a',
	'burlywood'=>'#deb887',
	'cadetblue'=>'#5f9ea0',
	'chartreuse'=>'#7fff00',
	'chocolate'=>'#d2691e',
	'coral'=>'#ff7f50',
	'cornflowerblue'=>'#6495ed',
	'cornsilk'=>'#fff8dc',
	'crimson'=>'#dc143c',
	'cyan'=>'#00ffff',
	'darkblue'=>'#00008b',
	'darkcyan'=>'#008b8b',
	'darkgoldenrod'=>'#b8860b',
	'darkgray'=>'#a9a9a9',
	'darkgrey'=>'#a9a9a9',
	'darkgreen'=>'#006400',
	'darkkhaki'=>'#bdb76b',
	'darkmagenta'=>'#8b008b',
	'darkolivegreen'=>'#556b2f',
	'darkorange'=>'#ff8c00',
	'darkorchid'=>'#9932cc',
	'darkred'=>'#8b0000',
	'darksalmon'=>'#e9967a',
	'darkseagreen'=>'#8fbc8f',
	'darkslateblue'=>'#483d8b',
	'darkslategray'=>'#2f4f4f',
	'darkslategrey'=>'#2f4f4f',
	'darkturquoise'=>'#00ced1',
	'darkviolet'=>'#9400d3',
	'deeppink'=>'#ff1493',
	'deepskyblue'=>'#00bfff',
	'dimgray'=>'#696969',
	'dimgrey'=>'#696969',
	'dodgerblue'=>'#1e90ff',
	'firebrick'=>'#b22222',
	'floralwhite'=>'#fffaf0',
	'forestgreen'=>'#228b22',
	'fuchsia'=>'#ff00ff',
	'gainsboro'=>'#dcdcdc',
	'ghostwhite'=>'#f8f8ff',
	'gold'=>'#ffd700',
	'goldenrod'=>'#daa520',
	'gray'=>'#808080',
	'grey'=>'#808080',
	'green'=>'#008000',
	'greenyellow'=>'#adff2f',
	'honeydew'=>'#f0fff0',
	'hotpink'=>'#ff69b4',
	'indianred'=>'#cd5c5c',
	'indigo'=>'#4b0082',
	'ivory'=>'#fffff0',
	'khaki'=>'#f0e68c',
	'lavender'=>'#e6e6fa',
	'lavenderblush'=>'#fff0f5',
	'lawngreen'=>'#7cfc00',
	'lemonchiffon'=>'#fffacd',
	'lightblue'=>'#add8e6',
	'lightcoral'=>'#f08080',
	'lightcyan'=>'#e0ffff',
	'lightgoldenrodyellow'=>'#fafad2',
	'lightgray'=>'#d3d3d3',
	'lightgrey'=>'#d3d3d3',
	'lightgreen'=>'#90ee90',
	'lightpink'=>'#ffb6c1',
	'lightsalmon'=>'#ffa07a',
	'lightseagreen'=>'#20b2aa',
	'lightskyblue'=>'#87cefa',
	'lightslategray'=>'#778899',
	'lightslategrey'=>'#778899',
	'lightsteelblue'=>'#b0c4de',
	'lightyellow'=>'#ffffe0',
	'lime'=>'#00ff00',
	'limegreen'=>'#32cd32',
	'linen'=>'#faf0e6',
	'magenta'=>'#ff00ff',
	'maroon'=>'#800000',
	'mediumaquamarine'=>'#66cdaa',
	'mediumblue'=>'#0000cd',
	'mediumorchid'=>'#ba55d3',
	'mediumpurple'=>'#9370d8',
	'mediumseagreen'=>'#3cb371',
	'mediumslateblue'=>'#7b68ee',
	'mediumspringgreen'=>'#00fa9a',
	'mediumturquoise'=>'#48d1cc',
	'mediumvioletred'=>'#c71585',
	'midnightblue'=>'#191970',
	'mintcream'=>'#f5fffa',
	'mistyrose'=>'#ffe4e1',
	'moccasin'=>'#ffe4b5',
	'navajowhite'=>'#ffdead',
	'navy'=>'#000080',
	'oldlace'=>'#fdf5e6',
	'olive'=>'#808000',
	'olivedrab'=>'#6b8e23',
	'orange'=>'#ffa500',
	'orangered'=>'#ff4500',
	'orchid'=>'#da70d6',
	'palegoldenrod'=>'#eee8aa',
	'palegreen'=>'#98fb98',
	'paleturquoise'=>'#afeeee',
	'palevioletred'=>'#d87093',
	'papayawhip'=>'#ffefd5',
	'peachpuff'=>'#ffdab9',
	'peru'=>'#cd853f',
	'pink'=>'#ffc0cb',
	'plum'=>'#dda0dd',
	'powderblue'=>'#b0e0e6',
	'purple'=>'#800080',
	'red'=>'#ff0000',
	'rosybrown'=>'#bc8f8f',
	'royalblue'=>'#4169e1',
	'saddlebrown'=>'#8b4513',
	'salmon'=>'#fa8072',
	'sandybrown'=>'#f4a460',
	'seagreen'=>'#2e8b57',
	'seashell'=>'#fff5ee',
	'sienna'=>'#a0522d',
	'silver'=>'#c0c0c0',
	'skyblue'=>'#87ceeb',
	'slateblue'=>'#6a5acd',
	'slategray'=>'#708090',
	'slategrey'=>'#708090',
	'snow'=>'#fffafa',
	'springgreen'=>'#00ff7f',
	'steelblue'=>'#4682b4',
	'tan'=>'#d2b48c',
	'teal'=>'#008080',
	'thistle'=>'#d8bfd8',
	'tomato'=>'#ff6347',
	'turquoise'=>'#40e0d0',
	'violet'=>'#ee82ee',
	'wheat'=>'#f5deb3',
	'white'=>'#ffffff',
	'whitesmoke'=>'#f5f5f5',
	'yellow'=>'#ffff00',
	'yellowgreen'=>'#9acd32'
	);
	//some hard-coded default styles
	$this->css = array(
	'.box'=>array(
		'height'=>'40px',
		'width'=>'120px',
		'margin'=>'3px 5px',
		'padding'=>'2px',
		'background-color'=>'#fff',
		'border-width'=>'2px',
		'border-color'=>'#f00',
		'color'=>'#000',
		'font-size'=>'9px'),
	'.box:nonfirm'=>array(
		'border-color'=>'#00f',
		'background-color'=>'#ffcb94'),//dull orange
	'.box:firm'=>array(
		'border-color'=>'#f00',
		'background-color'=>'#b7e8b1'),//green
	'.box:played'=>array(
		'border-color'=>'#002c8f',
		'background-color'=>'#bfd6ec'),//blue
	'.box:winner'=>array(
		'border-color'=>'#80007f',
		'background-color'=>'#e5b400',//gold
		'color'=>'#000'),
	'.chart'=>array(
		'padding'=>'10px',
		'gapwidth'=>'10px',
		'background-color'=>'#fff7ed',//off-white
		'font-family'=>'sans',
		'minheight'=>'526pt', //A4 landscape
		'minwidth'=>'770pt'),
	'.line'=>array(
		'width'=>'2px',
		'color'=>'#008000')
	);
	//replace defaults from file, if possible
	$csspath = cms_join_path(dirname(dirname(__FILE__)),'css','chart.css');
	if(file_exists($csspath))
		self::Parse($csspath);
	}

	function Add($key,$codestr) {
		parent::Add($key,$codestr);
		$lc = strtolower($codestr);
		foreach(array('color','background-color','border-color','outline-color') as $name) {
			if(strpos($codestr,$name)!==FALSE) {
				$val = $this->Get($key,$name);
				if($val && ord($val) != 35 && isset($this->colors[$val])) {
					$hex = $this->colors[$val];
					if($hex) parent::Add($key,$name.':'.$hex);
				}
			}
		}
	}

	function ParseStr($str,$clear=FALSE) {
		if(!$clear) $save = $this->css;
		$ret = parent::ParseStr($str);
		if(!($ret || $clear))
			$this->css = $save;
		return $ret;
	}

	function Parse($filename,$clear=FALSE) {
		if(!$clear) $save = $this->css;
		$ret = parent::Parse($filename);
		if(!($ret || $clear))
			$this->css = $save;
		return $ret;
	}

	function GetWithDefault($key,$property,$default) {
		$key = str_replace(' ','',$key);
		$result = $this->Get($key,$property);
		if($result)
			return $result;
		//special cases
		if(substr($property,0,5) == 'font-') {
			$result = $this->parsefont($key,$property);
			if($result)
				return $result;
		}
		elseif(substr($property,0,7) == 'border-') {
			$result = $this->parseborder($key,$property);
			if($result)
				return $result;
		}
		switch($key) {
		 case '.box:nonfirm':
		 case '.box:firm':
		 case '.box:played':
		 case '.box:winner':
			$result = $this->Get('.box',$property);
			if($result)
				return $result;
			if(substr($property,0,5) == 'font-') {
				$result = $this->parsefont('.box',$property);
				if($result)
					return $result;
			}
			elseif(substr($property,0,7) == 'border-') {
				$result = $this->parseborder('.box',$property);
				if($result)
					return $result;
			}
		}
		$result = $this->Get('.chart',$property);
		if($result)
			return $result;
		if(substr($property,0,5) == 'font-') {
			$result = $this->parsefont('.chart',$property);
			if($result)
				return $result;
		}
		//no border properties for .chart
		return $default;
	}

	function GetSide($key,$type,$side) {
		$sz = $this->Get($key,$type.'-'.$side);
		if($sz != '')
			$sz = $this->pxsize($sz);
		else {
			$sz = $this->Get($key,$type);
			if($sz != '') {
				$sides = preg_split('/\s+/',$sz);
				switch(count($sides)) {
				 case 1:
					$sz = $this->pxsize($sz);
				 	break;
				 case 2:
					switch($side) {
					 case 'bottom':
					 case 'top':
						$sz = $this->pxsize($sides[0]);
						break;
					 case 'left':
					 case 'right':
						$sz = $this->pxsize($sides[1]);
						break;
					}
					break;
				 case 4:
					switch($side) {
					 case 'top':
						$sz = $this->pxsize($sides[0]);
						break;
					 case 'right':
						$sz = $this->pxsize($sides[1]);
						break;
					 case 'bottom':
						$sz = $this->pxsize($sides[2]);
						break;
					 case 'left':
						$sz = $this->pxsize($sides[3]);
						break;
					}
					break;
				 default:
					$sz = 0;
				}
			}
			else
				$sz = 0;
		}
		return $sz;
	}

	function pxsize($sizestr) {
		if($sizestr) {
			//various hacks
			if(stristr($sizestr,'small')!==FALSE)
				return 8;
			if(stristr($sizestr,'large')!==FALSE)
				return 20;
			$num = preg_replace('/[^\d.]/','',$sizestr);
			if(stristr($sizestr,'pt')!==FALSE)
				return $num * 96/72;
			elseif(stristr($sizestr,'em')!==FALSE)
				return $num * 16;
			return $num;
		}
		return 0;
	}

	function hex2rgb($hex) {
		if($hex === FALSE) return FALSE;
		if(is_array($hex)) return $hex;
		$color = trim(str_replace('#','',$hex));
		switch(strlen($color)) {
		 case 1:
		   $r = hexdec($color.$color);
		   $rgb = array($r,$r,$r);
		   break;
		 case 3:
		   $r = substr($color,0,1);
		   $g = substr($color,1,1);
		   $b = substr($color,2,1);
		   $rgb = array(hexdec($r.$r),hexdec($g.$g),hexdec($b.$b));
		   break;
		 case 6:
		  $rgb = array(
		   hexdec(substr($color,0,2)),
		   hexdec(substr($color,2,2)),
		   hexdec(substr($color,4,2)));
		   break;
		 case 12:
		  $rgb = array(
		   hexdec(substr($color,0,4))/256,
		   hexdec(substr($color,4,4))/256,
		   hexdec(substr($color,8,4))/256);
		   break;
		 default:
		  $rgb = array(0,0,0);
		}
		return $rgb;
	}
	
	function parsefont($key,$property)
	{
		$result = $this->Get($key,'font');
		if($result) {
			$parts = preg_split('/\s+/',$result,5);
			switch(strtolower(substr($property,5))) {
			 case 'style':
			 	$i = 0;
			 	break;
			 case 'variant':
			 	$i = 1;
			 	break;
			 case 'weight':
			 	$i = 2;
			 	break;
			 case 'size':
			 	$i = 3;
			 	break;
			 case 'family':
			 	$i = 4;
			 	break;
			 default:
			 	return '';
			}
			if(!empty($parts[$i]))
				return $parts[$i];
		}
		return '';
	}
	
	function parseborder($key,$property)
	{
		$result = $this->Get($key,'border');
		if($result) {
			$parts = preg_split('/\s+/',$result,3);
			switch(strtolower(substr($property,7))) {
			//TODO case '[side]-[color|style|width]':
			 case 'width':
				foreach($parts as $val) {
					if (preg_match(
	'/(([+-]?\d*\.?\d+\s*(px|em|ex|pt|pc|mm|cm|in)?)|thin|medium|thick)/i',
					$val))
						return $val;
				}
			 	break;
			 case 'style':
			 	$valid = array('solid','dotted','dashed','double','groove','ridge','inset','outset','none','hidden');
				foreach($parts as $val) {
					if(in_array($val,$valid))
						return $val;
				}
			 	break;
			 case 'color':
				foreach($parts as $val) {
				 	if(ord($val) == 35 || isset($this->colors[$val]))
						return $val;
				}
			 default:
			 	return '';
			}
		}
		return '';
	}
}
?>
