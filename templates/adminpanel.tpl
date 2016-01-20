{if !empty($message)}<p class="pagemessage">{$message}</p>{/if}

{$tab_headers}

{$start_items_tab}
{$start_itemsform}
<div class="pageinput pageoverflow" style="display:inline-block;">
{if $icount}
 <table id="items" class="pagetable">
  <thead><tr>
  <th>{$title_name}</th>
{if $candev}
  <th>{$title_tag}</th>
{/if}
  <th>{$title_group}</th>
  <th>{$title_status}</th>
  <th class="pageicon"></th>
  <th class="pageicon"></th>
{if $canmod}
  <th class="pageicon"></th>
  <th class="pageicon"></th>
{/if}
  <th class="pageicon"></th>
  <th class="checkbox" style="width:20px;">{$selectall_items}</th>
 </tr></thead>
 <tbody>
{foreach from=$comps item=entry} {cycle values='row1,row2' name='c1' assign='rowclass'}
 <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
  <td>{$entry->name}</td>
{if $candev}
  <td>{ldelim}{$modname} alias='{$entry->alias}'{rdelim}</td>
{/if}
  <td>{$entry->group}</td>
  <td>{$entry->status}</td>
  <td>{$entry->viewlink}</td>
  <td>{$entry->editlink}</td>
{if $canmod}
  <td>{$entry->copylink}</td>
  <td>{$entry->deletelink}</td>
{/if}
  <td>{$entry->exportlink}</td>
  <td class="checkbox">{$entry->selected}</td>
 </tr>
{/foreach}
 </tbody>
 </table>
{else}
<p>{$notourn}</p>
{/if}
<div class="pageoptions">
 {if $canmod}{$addlink}&nbsp;{$addlink2}{/if}
 {if $icount}
  <div style="float:right;">
{$printbtn}{if $canmod} {$notifybtn} {$groupbtn} {$clonebtn} {$deletebtn}{/if} {$exportbtn}
  </div>
  <div class="clearb"></div>
 {/if}
</div>
</div>
{if $canmod}
<div class="pageinput pageoverflow">
 <p class="pagetext leftward">{$title_import}:</p>
 <div>{$input_import}&nbsp;&nbsp;{$submitxml}</div>
</div>
{/if}
{$end_form}
{$end_tab}

{$start_grps_tab}
{$start_groupsform}
{if $gcount}
<div class="pageinput pageoverflow" style="display:inline-block;">
 <table id="groups" class="pagetable" style="border-collapse:collapse">
  <thead><tr>
   <th style="display:none;"></th>
   <th>{$title_gname}</th>
   <th>{$title_active}</th>
{if $canmod}
   <th class="updown">{$title_move}</th>
   <th class="pageicon"></th>
{/if}
   <th class="checkbox" style="width:20px;">{$selectall_groups}</th>
  </tr></thead>
  <tbody>
 {foreach from=$groups item=entry} {cycle values='row1,row2' name='c2' assign='rowclass'}
  <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
   <td class="ord" style="display:none;">{$entry->order}</td>
   <td>{$entry->name}</td>
   <td>{$entry->active}</td>
{if $canmod}
   <td class="updown">{$entry->downlink}{$entry->uplink}</td>
   <td>{$entry->deletelink}</td>
{/if}
   <td class="checkbox">{$entry->selected}</td>
  </tr>
 {/foreach}
  </tbody>
 </table>
{if $canmod && $gcount > 1}<p class="dndhelp">{$dndhelp}</p>{/if}
{else}
 <p style="margin:20px;">{$nogroups}</p>
{/if}
<div class="pageoptions">
{if $canmod}{$addgrplink}
{if $gcount}
<div style="float:right;">
{$cancelbtn2} {$activebtn2} {$sortbtn2} {$deletebtn2} {$submitbtn2}
</div>
<div class="clearb"></div>
</div>
{/if}
{/if}
</div>
{$end_form}
{$end_tab}

{if $config}
{$start_config_tab}
{$start_configform}
 <div class="pageinput pageoverflow" style="display:inline-block;">
 <fieldset class="settings"><legend>{$title_names_fieldset}</legend>
{foreach from=$names item=entry}
  <p class="pagetext leftward">{$entry[0]}:</p>
  <p>{$entry[1]}{if isset($entry[2])}<br />{$entry[2]}{/if}</p>
{/foreach}
 </fieldset>
 <fieldset class="settings"><legend>{$title_misc_fieldset}</legend>
{foreach from=$misc item=entry}
  <p class="pagetext leftward">{$entry[0]}:</p>
  <p>{$entry[1]}{if isset($entry[2])}<br />{$entry[2]}{/if}</p>
{/foreach}
 </fieldset>
{if isset($hidden)}{$hidden}{/if}
<br /><br />
<div class="pageoptions">
{$cancel} {$save}
</div>
</div>
{$end_form}
{$end_tab}
{/if}

{$tab_footers}

{if $canmod}
<div id="confirm" class="modal-overlay">
<div class="confirm-container">
<p style="text-align:center;font-weight:bold;"></p>
<br />
<p style="text-align:center;"><input id="mc_conf" class="cms_submit pop_btn" type="submit" value="{$yes}" />
&nbsp;&nbsp;<input id="mc_deny" class="cms_submit pop_btn" type="submit" value="{$no}" /></p>
</div>
</div>
{/if}

{if isset($jsincs)}{foreach from=$jsincs item=file}{$file}{/foreach}
{/if}
{if isset($jsfuncs)}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
{/if}
