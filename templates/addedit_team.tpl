<h4 class="pagetext">{$pagetitle}</h4>
{$form_start}{$hidden}
<div class="pageinput pageoverflow" style="display:inline-block;max-width:95%;overflow:auto;" >
{foreach from=$opts item=entry}
{if !empty($entry[0])}<p class="pagetext leftward">{$entry[0]}:{if !empty($entry[2])} {$showtip}{/if}</p>{/if}
{if isset($entry[1])}<div>{$entry[1]}</div>{/if}
{if !empty($entry[2])}<p class="help">{$entry[2]}</p>{/if}
{/foreach}
<br />
{if $pc > 0}
	<table id="team" class="leftside table_sort">
	 <thead><tr>
	  <th class="{ldelim}sss:'textinput'{rdelim}">{$nametext}</th>
	  <th class="{ldelim}sss:'textinput'{rdelim}">{$contacttext}</th>
	  <th class="{ldelim}sss:'textinput'{rdelim}">{$availtext} {$showtip}</th>
{if $canmod}{if $pc > 1}<th class="updown {ldelim}sss:false{rdelim}">{$movetext}</th>{/if}
	  <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
	  <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{$selectall}</th>
	 </tr></thead>
	 <tbody>
 {foreach from=$items item=entry}
	  <tr class="{$entry->rowclass}" onmouseover="this.className='{$entry->rowclass}hover';" onmouseout="this.className='{$entry->rowclass}';">
		<td>{$entry->input_name}</td>
		<td>{$entry->input_contact}</td>
		<td>{$entry->input_available}</td>
{if $canmod}{if $pc > 1}<td class="updown">{$entry->downlink}{$entry->uplink}</td>{/if}
		<td class="plr_delete">{$entry->deletelink}</td>{/if}
		<td class="checkbox">{$entry->selected}{$entry->hidden}</td>
	  </tr>
 {/foreach}
	 </tbody>
	</table>
<p class="help">{$availhelp}</p>
{if $canmod && $pc > 1}<p class="dndhelp">{$dndhelp}</p>{/if}
	<br />
{/if}
	<div class="pageoptions">{if $canmod}{if isset($add)}{$add} {/if}{$submit} {/if}{$export}{if $canmod && $pc > 1} {$delete}{/if} {$cancel}</div>
</div>
<div id="confirm" class="modal-overlay">
<div class="confirm-container">
<p style="text-align:center;font-weight:bold;"></p>
<br />
<p style="text-align:center;"><input id="mc_conf" class="cms_submit pop_btn" type="submit" value="{$yes}" />
&nbsp;&nbsp;<input id="mc_deny" class="cms_submit pop_btn" type="submit" value="{$no}" /></p>
</div>
</div>
{$form_end}

{foreach from=$jsincs item=file}{$file}
{/foreach}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
