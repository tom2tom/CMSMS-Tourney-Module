{$startform}
{$hidden}
<h4 class="pagetext leftward">{$pagetitle}</h4>
{if isset($pagedesc)}<br /><p>{$pagedesc}</p><br />{/if}
<div style="overflow:auto;">
{foreach from=$items item=entry}<p>{$entry}</p>{/foreach}
<br />
<div class="pageinput">{$close}</div>
</div>
{$endform}
