{$start_form}
{$hidden}
<h4>{$title}</h4>
<p>{$description}</p><br />
{if isset($matches)}
<div style="overflow:auto;">
{foreach from=$matches item=entry}
<div>{$entry->button}</div>
<div class="seeblock">*{$titleresult}: {$entry->chooser} {$titlescore}: {$entry->score} *{$titlewhen}: {$entry->when}</div>
{/foreach}
</div>
<br />
*{$titlesender}: {$inputsender}
<br /><br />
{$titlecomment}:<br />{$inputcomment}
{if $captcha}
<br /><br />
{$captcha}<br />
*{$titlecaptcha}: {$inputcaptcha}
{/if}
{else}
<p>{$nomatches}</p>
{/if}
<br />
{if !empty($message)}<p id="syserr" style="color:red;">{$message}</p>{/if}
<p id="localerr" style="color:red;display:none;"></p>
<br />
{if isset($matches)}{$send}&nbsp;&nbsp;{/if}{$cancel}
{$end_form}
