<div id="tourConfigMailRouting"></div>
<form action="{devblocks_url}{/devblocks_url}" style="margin-bottom:5px;">
	<button type="button" onclick="genericAjaxPanel('c=config&a=showMailRoutingRulePanel&id=0',null,false,'550px');"><img src="{devblocks_url}c=resource&p=cerberusweb.core&f=images/funnel.gif{/devblocks_url}" align="top"> Add Mail Routing Rule</button>
</form>

<div class="block" id="configGroupRouting">
	<form action="{devblocks_url}{/devblocks_url}" method="post">
	<input type="hidden" name="c" value="config">
	<input type="hidden" name="a" value="saveRouting">

	<h2>Mail-to-Group Routing</h2>
	<br>

	<b>Which group inbox should receive any unrouted new mail?</b><br> 
	<select name="default_group_id">
		<option value="0">-- None (Bounce) --
	{if !empty($groups)}
	{foreach from=$groups item=group key=group_id}
		<option value="{$group_id}" {if $group->is_default}selected{/if}>{$group->name}
	{/foreach}
	{/if}
	</select><br>
	<br>
	
	{if !empty($rules)}
	<table cellspacing="2" cellpadding="2">
		<tr>
			<td align="center" style="padding-right:10px;"><b>{$translate->_('common.order')|capitalize}</b></td>
			<td><b>Routing Rule</b></td>
			<td align="center"><b>{$translate->_('common.remove')|capitalize}</b></td>
		</tr>
		{counter start=0 print=false name=order}
		{foreach from=$rules item=rule key=rule_id name=rules}
			<tr>
				<td valign="top" align="center">
					{if $rule->is_sticky}
						<input type="hidden" name="sticky_ids[]" value="{$rule_id}">
						<input type="text" name="sticky_order[]" value="{counter name=order}" size="2" maxlength="2">
					{else}
						<i><span style="color:rgb(180,180,180);font-size:80%;">(auto)</span></i>
					{/if}
				</td>
				<td style="{if $rule->is_sticky}background-color:rgb(255,255,221);border:2px solid rgb(255,215,0);{else}{/if}padding:5px;">
					<a href="javascript:;" onclick="genericAjaxPanel('c=config&a=showMailRoutingRulePanel&id={$rule_id}',null,false,'550px');" style="color:rgb(0,120,0);font-weight:bold;">{$rule->name|escape}</a>
					{if $rule->is_stackable}<span style="font-size:90%;padding-left:5px;color:rgb(0,120,0);">(Stackable)</span>{/if}
					<br>
					
					{foreach from=$rule->criteria item=crit key=crit_key}
						{if $crit_key=='tocc'}
							To/Cc = <b>{$crit.value}</b><br>
						{elseif 'header'==substr($crit_key,0,6)}
							Header <i>{$crit.header}</i> = <b>{$crit.value}</b><br>
						{elseif $crit_key=='body'}
							Body = <b>{$crit.value}</b><br>
						{elseif $crit_key=='dayofweek'}
							Day of Week is 
								{foreach from=$crit item=day name=timeofday}
								<b>{$day}</b>{if !$smarty.foreach.timeofday.last} or {/if}
								{/foreach}
								<br>
						{elseif $crit_key=='timeofday'}
							{assign var=from_time value=$crit.from|explode:':'}
							{assign var=to_time value=$crit.to|explode:':'}
							Time of Day 
								<i>between</i> 
								<b>{$from_time.0|string_format:"%d"}:{$from_time.1|string_format:"%02d"}</b> 
								<i>and</i> 
								<b>{$to_time.0|string_format:"%d"}:{$to_time.1|string_format:"%02d"}</b> 
								<br>
						{elseif 0==strcasecmp('cf_',substr($crit_key,0,3))}
							{* [TODO] Custom Field Types *}
							{assign var=col value=$crit_key|explode:'_'}
							{assign var=cf_id value=$col.1}
							
							{if isset($custom_fields.$cf_id)}
								{assign var=cfield value=$custom_fields.$cf_id}
								{assign var=crit_oper value=$crit.oper}
								{assign var=cfield_source value=$cfield->source_extension}
								{$source_manifests.$cfield_source->name}:{$custom_fields.$cf_id->name} 
								{if isset($crit.value) && is_array($crit.value)}
									 = 
									{foreach from=$crit.value item=i name=vals}
									<b>{$i}</b>{if !$smarty.foreach.vals.last} or {/if}
									{/foreach}
								{elseif 'E'==$cfield->type}
									<i>between</i> <b>{$crit.from}</b> <i>and</i> <b>{$crit.to}</b>
								{else}
									{if !empty($crit_oper)}{$crit_oper}{else}={/if}
									<b>{$crit.value}</b>
								{/if}
								<br>
							{/if}
						{/if}
					{/foreach}
					
					<blockquote style="margin:2px;margin-left:20px;font-size:95%;color:rgb(100,100,100);">
						{foreach from=$rule->actions item=action key=action_key}
							{if $action_key=="status"}
								{if $action.is_deleted==1}Delete Ticket{elseif $action.is_closed==1}Close Ticket{elseif $action.is_waiting==1}Waiting for Reply{else}Open Ticket{/if}<br>
							{elseif $action_key=="move"}
								{assign var=g_id value=$action.group_id}
								{assign var=b_id value=$action.bucket_id}
								{if isset($groups.$g_id) && (0==$b_id || isset($buckets.$b_id))}
									Move to 
									<b>{$groups.$g_id->name}</b>:
									<b>{if 0==$b_id}Inbox{else}{$buckets.$b_id->name}{/if}</b>
								{/if}
								<br>
							{elseif $action_key=="assign"}
								{assign var=worker_id value=$action.worker_id}
								{if isset($workers.$worker_id)}
									Assign to <b>{$workers.$worker_id->getName()}</b><br>
								{/if}
							{elseif $action_key=="spam"}
								{if $action.is_spam}Report Spam{else}Mark Not Spam{/if}<br>
							{elseif 0==strcasecmp('cf_',substr($action_key,0,3))}
								{* [TODO] Custom Field Types *}
								{assign var=col value=$action_key|explode:'_'}
								{assign var=cf_id value=$col.1}
								
								{if isset($custom_fields.$cf_id)}
									Set 
									{assign var=cfield value=$custom_fields.$cf_id}
									{assign var=cfield_source value=$cfield->source_extension}
									{$source_manifests.$cfield_source->name}:{$custom_fields.$cf_id->name} 
									 = 
									{if is_array($action.value)}
										{foreach from=$action.value item=i name=vals}
										<b>{$i}</b>{if !$smarty.foreach.vals.last} and {/if}
										{/foreach}
									{else}
										<b>{$action.value}</b>
									{/if}
									<br>
								{/if}
							{/if}
						{/foreach}
					<span>(Matched {$rule->pos} new messages)</span><br>
					</blockquote>
				</td>
				<td valign="top" align="center">
					<label><input type="checkbox" name="deletes[]" value="{$rule_id}">
					<input type="hidden" name="ids[]" value="{$rule_id}">
				</td>
			</tr>
		{/foreach}
	</table>
	<br>	
	{/if}

	<button type="submit"><img src="{devblocks_url}c=resource&p=cerberusweb.core&f=images/check.gif{/devblocks_url}" align="top"> {$translate->_('common.save_changes')|capitalize}</button>
	</form>
</div>
<br>
