<h4 class="pagetext">{$pagetitle}</h4>
{$form_start}{$hidden}
{if isset($title_teamname)}<p class="pagetext">{$title_teamname}:</p>
	<p class="pageinput">{$input_name}<br />{$help_name}</p>{/if}
	<p class="pagetext">{$title_seed}:</p>
	<p class="pageinput">{$input_seed}</p>
	<p class="pagetext">{$title_order}:</p>
	<p class="pageinput">{$input_order}<br />{$help_order}</p>
{if isset($title_sendto)}<p class="pagetext">{$title_sendto}:</p>
	<p class="pageinput">{$input_sendto}<br />{$help_sendto}</p>{/if}
	<br />
{if $pc > 0}
	<div class="pageinput">
	<table id="team" class="table_sort" style="margin:0 auto 0 0; border-collapse:collapse">
	 <thead><tr>
	  <th class="{ldelim}sss:'textinput'{rdelim}">{$nametext}</th>
	  <th class="{ldelim}sss:'textinput'{rdelim}">{$contacttext}</th>
{if $canmod}<th class="updown {ldelim}sss:false{rdelim}">{$movetext}</th>
	  <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
	  <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{if $pc > 1}{$selectall}{/if}</th>
	 </tr></thead>
	 <tbody>
 {foreach from=$items item=entry}
	  <tr class="{$entry->rowclass}" onmouseover="this.className='{$entry->rowclass}hover';" onmouseout="this.className='{$entry->rowclass}';">
		<td>{$entry->input_name}</td>
		<td>{$entry->input_contact}</td>
{if $canmod}<td class="updown">{$entry->downlink}{$entry->uplink}</td>
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
	<p class="pageinput">{if $canmod}{if isset($add)}{$add} {/if}{$submit} {/if}{$export}{if $canmod} {$delete}{/if} {$cancel}</p>
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
