{if !empty($message)}<p class="pagemessage">{$message}</p>{/if}
{$form_start}
{$hidden}
{$tabs_start}

{$maintab_start}
 <div class="pageoverflow">
{foreach from=$main item=entry}
{if isset($entry[0])}<p class="pagetext">{$entry[0]}:</p>{/if}
{if isset($entry[1])}<p class="pageinput">{$entry[1]}</p>{/if}
{if isset($entry[2])}<p class="pageinput">{$entry[2]}</p>{/if}
{/foreach}
</div>
{$tab_end}

{$scheduletab_start}
 <div class="pageoverflow">
{foreach from=$schedulers item=entry}
{if isset($entry[0])}<p class="pagetext">{$entry[0]}:</p>{/if}
{if isset($entry[1])}<p class="pageinput">{$entry[1]}</p>{/if}
{if isset($entry[2])}<p class="pageinput">{$entry[2]}</p>{/if}
{/foreach}
</div>
{$tab_end}

{$advancedtab_start}
 <div class="pageoverflow">
{foreach from=$advanced item=entry}
{if isset($entry[0])}<p class="pagetext">{$entry[0]}:</p>{/if}
{if isset($entry[1])}<p class="pageinput">{$entry[1]}</p>{/if}
{if isset($entry[2])}<p class="pageinput">{$entry[2]}</p>{/if}
{/foreach}
</div>
{$tab_end}

{$charttab_start}
 <div class="pageoverflow">
{foreach from=$names item=entry}
{if isset($entry[0])}<p class="pagetext">{$entry[0]}:</p>{/if}
{if isset($entry[1])}<p class="pageinput">{$entry[1]}</p>{/if}
{if isset($entry[2])}<p class="pageinput">{$entry[2]}</p>{/if}
{/foreach}
{if isset($matches)}<br /><p class="pageinput">{$chart}&nbsp;{$list}&nbsp;{$print}</p>{/if}
</div>
{$tab_end}

{$playertab_start}
{if $teamcount > 0}
	<div style="overflow:auto;">
	<table id="tmt_players" style="margin:0 auto 0 auto; border-collapse:collapse">
	 <thead><tr>
	  <th{if $canmod} class="{ldelim}sss:false{rdelim}"{/if}>{$ordertitle}</th>
	  <th class="{ldelim}sss:'textinput'{rdelim}">{$teamtitle}</th>
	  <th class="{ldelim}sss:'numberinput'{rdelim}">{$seedtitle}</th>
	  <th class="{ldelim}sss:false{rdelim}">{$contacttitle}</th>
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
<div style="overflow:auto;">
<table id="tmt_matches" cellpadding="2" style="margin:0 auto 0 auto; border-collapse:collapse">
 <thead><tr>
{if $plan}<th>{$idtitle}</th>{/if}
  <th class="{ldelim}sorter:'isoinput'{rdelim}">{$scheduledtitle}</th>
  <th>{$placetitle}</th>
  <th>{$teamtitle}</th>
  <th>{$teamtitle}</th>
  <th class="{ldelim}sorter:false{rdelim}">{$statustitle}</th>
  <th class="checkbox {ldelim}sorter:false{rdelim}" style="width:20px;">{$selmatches}</th>
 </tr></thead>
 <tbody>
 {foreach from=$matches item=entry}
 <tr class="{$entry->rowclass}" onmouseover="this.className='{$entry->rowclass}hover';" onmouseout="this.className='{$entry->rowclass}';">
{if $plan}<td>{$entry->mid}</td>{/if}
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
<p class="pageinput">{if isset($reset)}{$reset}&nbsp;{/if}{$altmview}{if !$plan}&nbsp;{$chart}&nbsp;{$list}&nbsp;{$notify}{/if}{if $canmod}&nbsp;{$update2}{/if}</p>
</div>
{if $plan}<div style="overflow:auto;"><br />{$image}</div>{/if}
{else}
<p class="pageinput">{$nomatches}
{if $malldone}<br /><br />{$chart}&nbsp;{$list}{elseif isset($schedule)}<br /><br />{$schedule}{/if}</p>
{/if}
{$tab_end}

{$resultstab_start}
{if isset($results)}
<div style="overflow:auto;">
<table id="tmt_results" cellpadding="2" style="margin:0 auto 0 auto; border-collapse:collapse">
 <thead><tr>
  <th class="{ldelim}sorter:false{rdelim}">{$scheduledtitle}</th>
  <th class="{ldelim}sorter:'isoinput'{rdelim}">{$playedtitle}</th>
  <th>{$teamtitle}</th>
  <th>{$teamtitle}</th>
  <th class="{ldelim}sorter:false{rdelim}">{$resulttitle}</th>
  <th class="{ldelim}sorter:false{rdelim}">{$scoretitle}</th>
  <th class="checkbox {ldelim}sorter:false{rdelim}" style="width:20px;">{$selresults}</th>
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
<p class="pageinput">{$chart}&nbsp;{$list}&nbsp;{$altrview}&nbsp;{$changes}{if $canmod}&nbsp;{$update3}{/if}</p>
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
<p style="text-align:center;">{$yes}&nbsp;&nbsp;{$no}</p>
</div>
</div>
{$form_end}

<script type="text/javascript" src="{$incpath}jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$incpath}jquery.SSsort.min.js"></script>
<script type="text/javascript" src="{$incpath}jquery.tablednd.min.js"></script>
<script type="text/javascript" src="{$incpath}jquery.modalconfirm.min.js"></script>
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
<script type="text/javascript" src="{$incpath}jquery.tmtfuncs.js"></script>
