{if !empty($message)}<p style="color:red;">{$message}</p>{/if}
{$start_form}
{$hidden}
<h4 style="line-height:32px;"><img style="margin:0;vertical-align:middle;" src="{$icon}"/> {$title}</h4>
{if !empty($description)}<p>{$description}</p>{/if}
<br />{$submit}
{$end_form}
