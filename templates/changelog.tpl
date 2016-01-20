<h4 class="pageinput">{$title}</h4>
{$start_form}
{$hidden}
{if isset($changes)}
<div class="pageinput pageoverflow">
 <table class="pagetable">
  <thead><tr>
  <th>{$changer}</th>
  <th>{$changewhen}</th>
  <th>{$newdata}</th>
  <th>{$olddata}</th>
 </tr></thead>
 <tbody>
{foreach from=$changes item=entry}
 <tr class="{$entry->rowclass}" onmouseover="this.className='{$entry->rowclass}hover';" onmouseout="this.className='{$entry->rowclass}';">
  <td>{$entry->who}</td>
  <td>{$entry->when}</td>
  <td>{$entry->to}</td>
  <td>{$entry->from}</td>
 </tr>
{/foreach}
 </tbody>
 </table>
<div>
{else}
<br />
<p class="pageinput">{$nochanges}</p>
{/if}<br /><br />
<p class="pageinput">{$close}</p>
{$end_form}
