{if !empty($message)}<p style="color:red;">{$message}</p>{/if}
{$start_form}
{$hidden}
<h4>{$title}</h4>
{if isset($descsription)}<br /><p>{$description}</p><br />{/if}
<div style="overflow:auto;">
{foreach from=$items item=entry}<p>{$entry}</p>{/foreach}
</div>
<br /><p class="pageinput">{$chart}{if $submit}&nbsp;{$submit}{/if}</p>
{$end_form}
