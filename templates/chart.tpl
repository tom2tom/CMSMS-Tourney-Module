{if !empty($message)}<p style="color:red;">{$message}</p>{/if}
{$start_form}
{$hidden}
<h4>{$title}</h4>
{if isset($descsription)}<br /><p>{$description}</p><br />{/if}
|CUSTOM|
<br /><p>{$list}{if $submit}&nbsp;{$submit}{/if}</p>
{$end_form}
