{if !empty($message)}<p class="pagemessage">{$message}</p>{/if}

{$tab_headers}
{$start_main_tab}

{if $count}
<div style="overflow:auto;">
 <table class="pagetable" style="border-collapse:collapse;">
  <thead><tr>
  <th>{$title_name}</th>
{if $candev}
  <th>{$title_tag}</th>
{/if}
  <th>{$title_status}</th>
  <th class="pageicon"></th>
  <th class="pageicon"></th>
{if $canconfig == 1}
  <th class="pageicon"></th>
  <th class="pageicon"></th>
{/if}
  <th class="pageicon"></th>
 </tr></thead>
 <tbody>
{foreach from=$comps item=entry}
{cycle values='row1,row2' assign='rowclass'}
	<tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
  <td>{$entry->name}</td>
{if $candev}
  <td>{ldelim}{$modname} alias='{$entry->alias}'{rdelim}</td>
{/if}
  <td>{$entry->status}</td>
  <td>{$entry->viewlink}</td>
  <td>{$entry->editlink}</td>
{if $canconfig}
  <td>{$entry->copylink}</td>
  <td>{$entry->deletelink}</td>
{/if}
  <td>{$entry->exportlink}</td>
 </tr>
{/foreach}
 </tbody>
 </table>
</div>
{else}
<p>{$notourn}</p>
{/if}
{if $canconfig}
<p class="pageoptions">{$addlink}&nbsp;{$addlink2}</p>
<div class="pageoverflow">
{$start_importform}
 <p class="pagetext">{$title_import}:</p>
 <p class="pageinput">{$input_import}&nbsp;&nbsp;{$submitxml}</p>
{$end_importform}
</div>
{/if}
{$end_tab}
{if $canconfig}
{$start_configuration_tab}
{$start_configform}
 <div class="module_fbr_overflow">
 <fieldset><legend>{$title_names_fieldset}</legend>
{foreach from=$names item=entry}
  <p class="pagetext">{$entry[0]}:</p>
  <p class="pageinput">{$entry[1]}{if isset($entry[2])}<br />{$entry[2]}{/if}</p>
{/foreach}
 </fieldset>
 <fieldset><legend>{$title_misc_fieldset}</legend>
{foreach from=$misc item=entry}
  <p class="pagetext">{$entry[0]}:</p>
  <p class="pageinput">{$entry[1]}{if isset($entry[2])}<br />{$entry[2]}{/if}</p>
{/foreach}
 </fieldset>
{if isset($hidden)}{$hidden}{/if}
<p class="pageinput" style="margin-top:10px;">{$save}&nbsp;{$cancel}</p>
</div>
{$end_configform}
{$end_tab}
{/if}

{$tab_footers}

{if $canconfig}
<div id="confirm" class="modal-overlay">
<div class="confirm-container">
<p style="text-align:center;font-weight:bold;"></p>
<br />
<p style="text-align:center;"><input id="mc_conf" class="cms_submit pop_btn" type="submit" value="{$yes}" />
&nbsp;&nbsp;<input id="mc_deny" class="cms_submit pop_btn" type="submit" value="{$no}" /></p>
</div>
</div>
{/if}

{if isset($jsfuncs)}
{foreach from=$jsincs item=file}{$file}
{/foreach}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}
{/foreach}
//]]>
</script>
{/if}
