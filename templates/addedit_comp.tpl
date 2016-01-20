{if !empty($message)}<p class="pagemessage">{$message}</p>{/if}
{$form_start}
{$hidden}
{$tabs_start}

{$maintab_start}
 <div class="pageoverflow">
{foreach from=$main item=entry}
{if !empty($entry[0])}<p class="pagetext">{$entry[0]}:{if !empty($entry[2])} {$showtip}{/if}</p>{/if}
{if isset($entry[1])}<div class="pageinput">{$entry[1]}</div>{/if}
{if !empty($entry[2])}<p class="pageinput help">{$entry[2]}</p>{/if}
{/foreach}
</div>
{$tab_end}

{$scheduletab_start}
 <div class="pageoverflow">
{foreach from=$schedulers item=entry}
{if !empty($entry[0])}<p class="pagetext">{$entry[0]}:{if !empty($entry[2])} {$showtip}{/if}</p>{/if}
{if isset($entry[1])}<div class="pageinput">{$entry[1]}</div>{/if}
{if !empty($entry[2])}<p class="pageinput help">{$entry[2]}</p>{/if}
{/foreach}
</div>
{$tab_end}

{$advancedtab_start}
 <div class="pageoverflow">
{foreach from=$advanced item=entry}
{if !empty($entry[0])}<p class="pagetext">{$entry[0]}:{if !empty($entry[2])} {$showtip}{/if}</p>{/if}
{if isset($entry[1])}<div class="pageinput">{$entry[1]}</div>{/if}
{if !empty($entry[2])}<p class="pageinput help">{$entry[2]}</p>{/if}
{/foreach}
</div>
{$tab_end}

{$charttab_start}
 <div class="pageoverflow">
{foreach from=$names item=entry}
{if !empty($entry[0])}<p class="pagetext">{$entry[0]}:{if !empty($entry[2])} {$showtip}{/if}</p>{/if}
{if isset($entry[1])}<div class="pageinput">{$entry[1]}</div>{/if}
{if !empty($entry[2])}<p class="pageinput help">{$entry[2]}</p>{/if}
{/foreach}
{if isset($matches)}<br /><p class="pageinput">{$chart}&nbsp;{$list}&nbsp;{$print}</p>{/if}
</div>
{$tab_end}

{$playertab_start}
{if $teamcount > 0}
	<div style="padding-right:10px; padding-bottom:10px; overflow:auto;">
	<table id="tmt_players" style="margin:0 auto; border-collapse:collapse">
	 <thead><tr>
	  <th class="{if $canmod}{ldelim}sss:false{rdelim}{else}ord{/if}">{$ordertitle}</th>
	  <th class="{ldelim}sss:'textinput'{rdelim}">{$teamtitle}</th>
	  <th class="{ldelim}sss:'neglastinput'{rdelim}">{$seedtitle}</th>
	  <th class="{ldelim}sss:'textinput'{rdelim}">{$contacttitle}</th>
{if $canmod}<th class="updown {ldelim}sss:false{rdelim}">{$movetitle}</th>
	  <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
	  <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
	  <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{$selteams}</th>
	 </tr></thead>
	 <tbody>
	 {foreach from=$teams item=entry}
	 <tr class="{$entry->rowclass}" onmouseover="this.className='{$entry->rowclass}hover';" onmouseout="this.className='{$entry->rowclass}';">
	 <td class="ord">{$entry->order}</td>
	 <td>{$entry->name}</td>
	 <td>{$entry->seed}</td>
	 <td>{$entry->contact}</td>
{if $canmod}<td class="updown">{$entry->downlink}{$entry->uplink}</td>
	 <td>{$entry->editlink}</td>
	 <td class="tem_delete">{$entry->deletelink}</td>{/if}
	 <td class="checkbox">{$entry->selected}{$entry->hidden}</td>
	 </tr>
	 {/foreach}
	 </tbody>
	</table>
	{if $canmod > 0}<p class="dndhelp pageinput">{$dndhelp}</p>{/if}
	</div>
{else}
	<p class="pageinput">{$noteams}</p>
{/if}
<div class="pageinput" style="margin:1em 20% 0 20%;">
{if $canmod}{$addteam}&nbsp;&nbsp;{$import}&nbsp;{/if}
{if $teamcount > 0}
 {$export}{if $canmod}&nbsp;{$delete}&nbsp;{$update1}{/if}
{/if}
</div>
{$tab_end}

{$matchtab_start}
{if isset($matches)}
<div style="padding-right:10px; padding-bottom:10px; overflow:auto;">
<div id="matchcalendar" style="margin:0 auto 10px 20%;"></div>
<table id="tmt_matches" style="margin:0 auto; border-collapse:collapse">
 <thead><tr>
{if $plan}<th>{$idtitle}</th>{/if}
  <th class="{ldelim}sss:'isoinput'{rdelim}">{$scheduledtitle}</th>
  <th>{$placetitle}</th>
  <th>{$teamtitle}</th>
  <th>{$teamtitle}</th>
  <th class="{ldelim}sss:false{rdelim}">{$statustitle}</th>
  <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{$selmatches}</th>
 </tr></thead>
 <tbody>
 {foreach from=$matches item=entry}
 <tr class="{$entry->rowclass}" onmouseover="this.className='{$entry->rowclass}hover';" onmouseout="this.className='{$entry->rowclass}';">
{if $plan}<td>{if ($entry->mid < 0)}<span style="display:none;">{$entry->mid}</span>{else}{$entry->mid}{/if}</td>{/if}
 <td>{$entry->schedule}</td>
 <td>{$entry->place}</td>
 <td>{$entry->teamA}</td>
 <td>{$entry->teamB}</td>
 <td>{$entry->btn1}&nbsp;{$entry->btn2}&nbsp;{$entry->btn3}</td>
 <td class="checkbox">{$entry->selected}{$entry->hidden}</td>
 </tr>
 {/foreach}
 </tbody>
</table><br />
<p class="pageinput">{if isset($reset)}{$reset}&nbsp;{/if}{$altmview}{if !$plan}&nbsp;{$chart}&nbsp;{$list}&nbsp;{$print}{/if}&nbsp;{$notify}{if $canmod}&nbsp;{$abandon}&nbsp;{$update2}{/if}</p>
</div>
{if $plan}<div style="overflow:auto;"><br />{$image}</div>{/if}
{else}
<p class="pageinput">{$nomatches}
{if $malldone}<br /><br />{$chart}&nbsp;{$list}{elseif isset($schedule)}<br /><br />{$schedule}{/if}</p>
{/if}
{$tab_end}

{$resultstab_start}
{if isset($results)}
<div style="padding-right:10px; padding-bottom:10px; overflow:auto;">
<table id="tmt_results" style="margin:0 auto; border-collapse:collapse">
 <thead><tr>
  <th class="{ldelim}sss:false{rdelim}">{$scheduledtitle}</th>
  <th class="{ldelim}sss:'isoinput'{rdelim}">{$playedtitle}</th>
  <th>{$teamtitle}</th>
  <th>{$teamtitle}</th>
  <th class="{ldelim}sss:false{rdelim}">{$resulttitle}</th>
  <th class="{ldelim}sss:false{rdelim}">{$scoretitle}</th>
  <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{$selresults}</th>
 </tr></thead>
 <tbody>
 {foreach from=$results item=entry}
 <tr class="{$entry->rowclass}" onmouseover="this.className='{$entry->rowclass}hover';" onmouseout="this.className='{$entry->rowclass}';">
 <td>{$entry->hidden}{$entry->schedule}</td>
 <td>{$entry->actual}</td>
 <td>{$entry->teamA}</td>
 <td>{$entry->teamB}</td>
 <td>{$entry->result}</td>
 <td>{$entry->score}</td>
 <td class="checkbox">{$entry->selected}</td>
 </tr>
 {/foreach}
 </tbody>
</table><br />
<p class="pageinput">{$chart}&nbsp;{$list}&nbsp;{$altrview}&nbsp;{$changes}&nbsp;{$getscore}{if $canmod}&nbsp;{$update3}{/if}</p>
</div>
{else}
<p class="pageinput">{$noresults}
{if $ralldone}<br /><br />{$chart}&nbsp;{$list}&nbsp;{$altrview}&nbsp;{$changes}{/if}</p>
{/if}
{$tab_end}

{$tabs_end}
{if $canmod > 0}
<br />
<p class="pageinput">{$save}&nbsp;{$cancel}&nbsp;{$apply}</p>
{/if}
<div id="confirm" class="modal-overlay">
<div class="confirm-container">
<p style="text-align:center;font-weight:bold;"></p>
<br />
<p style="text-align:center;"><input id="mc_conf" class="cms_submit pop_btn" type="submit" value="{$yes}" />
&nbsp;&nbsp;<input id="mc_deny" class="cms_submit pop_btn" type="submit" value="{$no}" /></p>
</div>
</div>
{$form_end}

{foreach from=$jsincs item=file}{$file}{/foreach}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
