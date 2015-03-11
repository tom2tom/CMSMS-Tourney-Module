<?php
/*
This file is part of CMS Made Simple module: Tourney.
Copyright(C) 2014-2015 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Tourney.module.php
More info at http://dev.cmsmadesimple.org/projects/tourney
This class is not suited for static method-calling
*/
class tmtChartBase
{
	protected $mod;
	public $layout; /* box, level and chart data from ::Layout(), array with
					keys = 'level' or 'column' indices, values = arrays like:
					$mid => box-parameters 'bl' => int 'bt' => int */
	protected $ldata; /* high-level data from ::Layout(), array of (non-numeric-keyed)
						chart parameters, like: 'width'=>int 'height'=>int 'boxwidth'=>int 'boxheight'=>int */
	protected $dashes; //array of segment lengths (px) for dashed lines
	protected $dots; //ditto for dotted lines

/*	function __destruct()
	{
		unset($this->mod);	//ensure reference is cleared
	}
*/
	/**
	MakeChart:
	@mod: reference to module object
	@bdata: reference to array of bracket-table data
	@chartfile: path/name of file to be created or replaced
	@stylefile optional name of .css file to use instead of logged data
	@titles optional, mode enum for type of titles to include in boxes:
	 0 for(printer-ready) no labels in unplayed matches
 	 1 for normal labels in all boxes (default)
	 2 for including match numbers in 'plan' mode
	Returns: TRUE on success, or lang-key for error message if: bad no. of teams, css parsing failed
	*/
	public function MakeChart(&$mod,&$bdata,$chartfile,$stylefile=FALSE,$titles=1)
	{
		$db = cmsms()->GetDb();
		$sql = 'SELECT COUNT(1) AS count FROM '.cms_db_prefix().'module_tmt_teams WHERE bracket_id=? AND flags!=2';
		$teamscount = (int)$db->GetOne($sql,array($bdata['bracket_id']));
		if($teamscount == 0)
			return 'info_nomatch'; //if no team, then no match
		list($minteams,$maxteams) = $mod->GetLimits($bdata['type']);
		if($teamscount > $maxteams || $teamscount < $minteams)
			return 'err_value';
		$css = new tmtStyler();
		if(!$stylefile && $bdata['chartcss'])
			$stylefile = $bdata['chartcss'];
		if($stylefile)
		{
			$config = cmsms()->GetConfig();
			$csspath = cms_join_path($config['uploads_path'],
				$mod->GetPreference('uploads_dir'),$stylefile);
			if(file_exists($csspath))
			{
				if(!$css->Parse($csspath))
					return 'err_styles';
			}
		}
		$this->mod = $mod;
		$this->layout = array();
		$this->ldata = array();
		$this->dashes = array(3,3);
		$this->dots = array(0.2,4);
		//high-use variables sent downstream in array,for extraction there
		$params = array();
		$params['teamscount'] = $teamscount;
		//inter-box gaps
		$lw = $css->pxsize($css->GetWithDefault('.line','width','2px')); //line width
		$bhm = $css->pxsize($css->GetSide('.box','margin','right')); //horz margin
		$gw = $css->pxsize($css->GetWithDefault('.chart','gapwidth','20px')); //gap width
		$gw = max($lw+4,$bhm*2 + $gw);
		$params['gw'] = $gw; //includes box margins, if any
		$bvm = $css->pxsize($css->GetSide('.box','margin','top')); //vert margin
		$params['gh'] = max(2,$bvm*2); //includes box margins, if any
		//chart margins
		$tp = $css->pxsize($css->GetSide('.chart','padding','top'));
		if($tp < 2) $tp = 2;
		$th = $css->pxsize($css->GetWithDefault('.chart','font-size','12pt')); //space for title string
		$params['tm'] = $tp + $th; //top
		$p = $css->pxsize($css->GetSide('.chart','padding','right'));
		$rm = ($bhm+$p >= 5) ? $p:5;
		$params['rm'] = $rm; //right
		$p = $css->pxsize($css->GetSide('.chart','padding','bottom'));
		$params['bm'] = ($bvm+$p >= 5) ? $p:5; //bottom
		$p = $css->pxsize($css->GetSide('.chart','padding','left'));
		$lm = ($bhm+$p >= 5) ? $p:5;
		$params['lm'] = $lm; //left
		//box parameters
		$bh = $css->pxsize($css->GetWithDefault('.box','height','40px'));
		$params['bh'] = $bh; //content-height
		$params['bw'] = $css->pxsize($css->GetWithDefault('.box','width','100px')); //content-width
		$bp = $css->pxsize($css->GetWithDefault('.box','padding','0'));
		$params['bp'] = $bp; //all-sides' padding
		$blw = $css->pxsize($css->GetWithDefault('.box','border-width','1px'));
		$params['blw'] = $blw; //all sides' border-width
		$params['bhm'] = $bhm; //l/r margin
		$params['bvm'] = $bvm; //t/b margin
		$this->Layout($params,$db,$bdata['bracket_id']); //setup boxes' size, position and chart size
		$this->Boxes($bdata,$db,$titles);//setup boxes' text and style

		if($titles == 0)
		{
			//print-chart min size
			$min = $css->pxsize($css->GetWithDefault('.chart','minwidth','770pt'));
			if($this->ldata['width'] < $min)
				$this->ldata['width'] = $min;
			$min = $css->pxsize($css->GetWithDefault('.chart','minheight','526pt'));
			if($this->ldata['height'] < $min)
				$this->ldata['height'] = $min;
		}
		$cw = $this->ldata['width'];
		$ch = $this->ldata['height'];
		
		//check for custom .ttf files
		$config = cmsms()->GetConfig();
		$rel = $mod->GetPreference('uploads_dir');
		$custom = cms_join_path($config['uploads_path'],$rel);
		if(is_dir($custom))
		{
			$pat = cms_join_path($custom,'*.ttf'); //tPDF recognises only lower-case filenames
			if(glob($pat,GLOB_NOSORT))
				define('_SYSTEM_TTFONTS',$ttfpath);
		}
		$enc = $mod->GetPreference('export_encoding','UTF-8');
		$utf = (strcasecmp($enc,'UTF-8') == 0);
		$pdf = new tFPDF(($cw > $ch) ? 'L':'P','px',array($cw,$ch),$utf);

		$pdf->SetAutoPageBreak(FALSE);
		$pdf->AddPage();

		if($titles > 0)
		{
			$back = $css->hex2rgb($css->GetWithDefault('.chart','background-color',FALSE));
			//TODO support image-background
			if($back)
			{
				$pdf->SetFillColor($back[0],$back[1],$back[2]);
				$pdf->Rect(0,0,$cw,$ch,'F'); //no chart border
			}
		}
		//display title
		$class = '.chart';
		$title = ($bdata['name']) ? $bdata['name'] : $mod->MissingName();
		$ft = $css->GetWithDefault($class,'font-family','sans');
		$style = $css->GetWithDefault($class,'font-weight','normal');
		$attr = ($style != 'bold') ? '' : 'b';
		$style = $css->GetWithDefault($class,'font-style','normal');
		if(strpos($style,'italic') !== FALSE || strpos($style,'oblique') !== FALSE)
			$attr .= 'i';
		$style = $css->GetWithDefault($class,'text-decoration','none');
		if(strpos($style,'underline') !== FALSE)
			$attr .= 'u';
		$pdf->AddFont($ft,$attr);
		$pdf->SetFont($ft,$attr,(int)($th*72/96-0.01)+1); //tFPDF needs font size as pts
		if($titles > 0)
		{
			$c = $css->hex2rgb($css->GetWithDefault($class,'color','#000'));
			$pdf->SetTextColor($c[0],$c[1],$c[2]);
		}
		$pdf->SetXY($lm,$tp);
		$pdf->Cell($this->ldata['width']-$lm-$rm,$th,$title,0,0,'C');

		$params = array();
		$params['pdf'] = &$pdf;
		$params['gw'] = $gw;
		$params['lw'] = $lw;
		if($titles > 0)
			$lc = $css->hex2rgb($css->GetWithDefault('.line','color','#000'));
		else
			$lc = FALSE;
		$params['lc'] = $lc;
		$params['ls'] = $css->GetWithDefault('.line','style','solid');
		$params['blw'] = $blw;
		$params['bp'] = $bp;

		$params['boxstyles'] = array();
		foreach(array(
		 'deflt'=>'',
		 'nonf'=>':nonfirm',
		 'firm'=>':firm',
		 'done'=>':played',
		 'final'=>':winner') as $type=>$suffix)
		{
			$class = '.box'.$suffix;
			$ft = $css->GetWithDefault($class,'font-family','sans');
			$style = $css->GetWithDefault($class,'font-weight','normal');
			$attr = ($style != 'bold') ? '' : 'b';
			$style = $css->GetWithDefault($class,'font-style','normal');
			if(strpos($style,'italic') !== FALSE || strpos($style,'oblique') !== FALSE)
				$attr .= 'i';
			$style = $css->GetWithDefault($class,'text-decoration','none');
			if(strpos($style,'underline') !== FALSE)
				$attr .= 'u';
			$pdf->AddFont($ft,$attr);
			$size = $css->pxsize($css->GetWithDefault($class,'font-size',$bh/4));
			if($titles > 0)
			{
				$bc = $css->hex2rgb($css->GetWithDefault($class,'border-color',$lc));
				$fc = $css->hex2rgb($css->GetWithDefault($class,'background-color',$back));//TODO support image/url
				$tc = $css->hex2rgb($css->GetWithDefault($class,'color',$lc));
			}
			else
			{
				$bc = FALSE;
				$fc = FALSE;
				$tc = FALSE;
			}
			$params['boxstyles'][$type] = array(
			 'bw'=>$css->pxsize($css->GetWithDefault($class,'border-width',$lw)),
			 'bc'=>$bc,
			 'bs'=>$css->GetWithDefault($class,'border-style','solid'),
			 'fill'=>$fc,
			 'font'=>$ft,
			 'color'=>$tc,
			 'size'=>(int)($size*72/96-0.01)+1,
			 'attr'=>$attr
			 );
		}
		$this->Draw($params);

		if($bdata['name'])
			$pdf->SetTitle($bdata['name'],TRUE);
		$pdf->Output($chartfile,'F');
		return TRUE;
	}

	/**
	ChartSize:
	Call this after successful MakeChart()
	Returns: array with chart dimensions(px), or FALSE
	*/
	public function ChartSize()
	{
		if(is_array($this->ldata))
			return array(
			'height'=>$this->ldata['height'],
			'width'=>$this->ldata['width']);
		else
			return FALSE;
	}

	//methods to be sub-classed for specific chart-type
	public function Layout(&$params){}
	public function Boxes(&$bdata,&$db,$titles=1){return FALSE;}
	public function Draw(&$params){}
}

?>
