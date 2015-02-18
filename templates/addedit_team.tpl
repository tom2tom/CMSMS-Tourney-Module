<h4 class="pagetext">{$pagetitle}</h4>
{$form_start}{$hidden}
{foreach from=$opts item=entry}
{if !empty($entry[0])}<p class="pagetext">{$entry[0]}:{if !empty($entry[2])} {$showtip}{/if}</p>{/if}
{if isset($entry[1])}<div class="pageinput">{$entry[1]}</div>{/if}
{if !empty($entry[2])}<p class="pageinput help">{$entry[2]}</p>{/if}
{/foreach}
<br />
{if $pc > 0}
	<div class="pageinput">
	<table id="team" class="table_sort" style="margin:0 auto 0 0; border-collapse:collapse">
	 <thead><tr>
	  <th class="{ldelim}sss:'textinput'{rdelim}">{$nametext}</th>
	  <th class="{ldelim}sss:'textinput'{rdelim}">{$contacttext}</th>
{if $canmod}{if $pc > 1}<th class="updown {ldelim}sss:false{rdelim}">{$movetext}</th>{/if}
	  <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
	  <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{if $pc > 1}{$selectall}{/if}</th>
	 </tr></thead>
	 <tbody>
 {foreach from=$items item=entry}
	  <tr class="{$entry->rowclass}" onmouseover="this.className='{$entry->rowclass}hover';" onmouseout="this.className='{$entry->rowclass}';">
		<td>{$entry->input_name}</td>
		<td>{$entry->input_contact}</td>
{if $canmod}{if $pc > 1}<td class="updown">{$entry->downlink}{$entry->uplink}</td>{/if}
		<td class="plr_delete">{$entry->deletelink}</td>{/if}
		<td class="checkbox">{$entry->selected}{$entry->hidden}</td>
	  </tr>
 {/foreach}
	 </tbody>
	</table>
{if $canmod && $pc > 1}<p class="dndhelp">{$dndhelp}</p>{/if}
	</div>
	<br />
{/if}
	<div class="pageinput">{if $canmod}{if isset($add)}{$add} {/if}{$submit} {/if}{$export}{if $canmod} {$delete}{/if} {$cancel}</div>
<div id="confirm" class="modal-overlay">
<div class="confirm-container">
<p style="text-align:center;font-weight:bold;"></p>
<br />
<p style="text-align:center;"><input id="mc_conf" class="cms_submit pop_btn" type="submit" value="{$yes}" />
&nbsp;&nbsp;<input id="mc_deny" class="cms_submit pop_btn" type="submit" value="{$no}" /></p>
</div>
</div>
{$form_end}

<script type="text/javascript" src="{$incpath}jquery.tmtfuncs.js"></script>
<script type="text/javascript" src="{$incpath}jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$incpath}jquery.SSsort.min.js"></script>
<script type="text/javascript" src="{$incpath}jquery.tablednd.min.js"></script>
<script type="text/javascript" src="{$incpath}jquery.modalconfirm.min.js"></script>
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
