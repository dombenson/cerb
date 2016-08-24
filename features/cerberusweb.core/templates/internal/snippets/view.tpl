{$view_context = CerberusContexts::CONTEXT_SNIPPET}
{$view_fields = $view->getColumnsAvailable()}
{$results = $view->getData()}
{$total = $results[1]}
{$data = $results[0]}

{include file="devblocks:cerberusweb.core::internal/views/view_marquee.tpl" view=$view}

<table cellpadding="0" cellspacing="0" border="0" class="worklist" width="100%">
	<tr>
		<td nowrap="nowrap"><span class="title">{$view->name}</span></td>
		<td nowrap="nowrap" align="right" class="title-toolbar">
			{if $active_worker->hasPriv('core.snippets.actions.create')}<a href="javascript:;" title="{'common.add'|devblocks_translate|capitalize}" class="minimal" onclick="genericAjaxPopup('peek','c=internal&a=showSnippetsPeek&id=0&owner_context={$owner_context}&owner_context_id={$owner_context_id}&view_id={$view->id}',null,false,'50%');"><span class="glyphicons glyphicons-circle-plus"></span></a>{/if}
			<a href="javascript:;" title="{'common.search'|devblocks_translate|capitalize}" class="minimal" onclick="genericAjaxPopup('search','c=internal&a=viewShowQuickSearchPopup&view_id={$view->id}',null,false,'400');"><span class="glyphicons glyphicons-search"></span></a>
			<a href="javascript:;" title="{'common.customize'|devblocks_translate|capitalize}" class="minimal" onclick="genericAjaxGet('customize{$view->id}','c=internal&a=viewCustomize&id={$view->id}');toggleDiv('customize{$view->id}','block');"><span class="glyphicons glyphicons-cogwheel"></span></a>
			<a href="javascript:;" title="{'common.subtotals'|devblocks_translate|capitalize}" class="subtotals minimal"><span class="glyphicons glyphicons-signal"></span></a>
			<a href="javascript:;" title="{'common.export'|devblocks_translate|capitalize}" class="minimal" onclick="genericAjaxGet('{$view->id}_tips','c=internal&a=viewShowExport&id={$view->id}');toggleDiv('{$view->id}_tips','block');"><span class="glyphicons glyphicons-file-export"></span></a>
			<a href="javascript:;" title="{'common.copy'|devblocks_translate|capitalize}" onclick="genericAjaxGet('{$view->id}_tips','c=internal&a=viewShowCopy&view_id={$view->id}');toggleDiv('{$view->id}_tips','block');"><span class="glyphicons glyphicons-duplicate"></span></a>
			<a href="javascript:;" title="{'common.refresh'|devblocks_translate|capitalize}" class="minimal" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewRefresh&id={$view->id}');"><span class="glyphicons glyphicons-refresh"></span></a>
			<input type="checkbox" class="select-all">
		</td>
	</tr>
</table>

<div id="{$view->id}_tips" class="block" style="display:none;margin:10px;padding:5px;">Loading...</div>
<form id="customize{$view->id}" name="customize{$view->id}" action="#" onsubmit="return false;" style="display:none;"></form>
<form id="viewForm{$view->id}" name="viewForm{$view->id}" action="{devblocks_url}{/devblocks_url}" method="post">
<input type="hidden" name="view_id" value="{$view->id}">
<input type="hidden" name="context_id" value="cerberusweb.contexts.snippet">
<input type="hidden" name="c" value="tickets">
<input type="hidden" name="a" value="">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<table cellpadding="5" cellspacing="0" border="0" width="100%" class="worklistBody">

	{* Column Headers *}
	<thead>
	<tr>
		{foreach from=$view->view_columns item=header name=headers}
			{* start table header, insert column title and link *}
			<th class="{if $view->options.disable_sorting}no-sort{/if}">
			{if !$view->options.disable_sorting && !empty($view_fields.$header->db_column)}
				<a href="javascript:;" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewSortBy&id={$view->id}&sortBy={$header}');">{$view_fields.$header->db_label|capitalize}</a>
			{else}
				<a href="javascript:;" style="text-decoration:none;">{$view_fields.$header->db_label|capitalize}</a>
			{/if}
			
			{* add arrow if sorting by this column, finish table header tag *}
			{if $header==$view->renderSortBy}
				<span class="glyphicons {if $view->renderSortAsc}glyphicons-sort-by-attributes{else}glyphicons-sort-by-attributes-alt{/if}" style="font-size:14px;{if $view->options.disable_sorting}color:rgb(80,80,80);{else}color:rgb(39,123,213);{/if}"></span>
			{/if}
			</th>
		{/foreach}
	</tr>
	</thead>

	{* Column Data *}
	{foreach from=$data item=result key=idx name=results}
	{$custom_placeholders = $result.s_custom_placeholders_json|json_decode:true}

	{if $smarty.foreach.results.iteration % 2}
		{$tableRowClass = "even"}
	{else}
		{$tableRowClass = "odd"}
	{/if}
	
	{$dict = null}
	
	<tbody style="cursor:pointer;">
		<tr class="{$tableRowClass}">
		{foreach from=$view->view_columns item=column name=columns}
			{if substr($column,0,3)=="cf_"}
				{include file="devblocks:cerberusweb.core::internal/custom_fields/view/cell_renderer.tpl"}
			{elseif $column=="s_title"}
			<td data-column="{$column}" context="{$result.s_context}" id="{$result.s_id}" has_custom_placeholders="{if !empty($custom_placeholders)}true{else}false{/if}">
				<input type="checkbox" name="row_id[]" value="{$result.s_id}" style="display:none;">
				<a href="javascript:;" onclick="genericAjaxPopup('peek','c=internal&a=showSnippetsPeek&view_id={$view->id}&id={$result.s_id}', null, false, '50%');" class="subject">{if empty($result.$column)}(no title){else}{$result.$column}{/if}</a>
			</td>
			{elseif $column=="s_context"}
			<td data-column="{$column}">
				{if '' == $result.$column}
					Plaintext
				{elseif isset($contexts.{$result.$column})}
					{$contexts.{$result.$column}->name}
				{else}
					{$result.$column}
				{/if}
			</td>
			{elseif $column=="*_owner"}
				{$owner_context = $result.s_owner_context}
				{$owner_context_id = $result.s_owner_context_id}
				{$owner_context_ext = Extension_DevblocksContext::get($owner_context)}
				<td data-column="{$column}">
					{if !is_null($owner_context_ext)}
						{$meta = $owner_context_ext->getMeta($owner_context_id)}
						{if !empty($meta)}
						{$meta.name} 
						{/if}
						({$owner_context_ext->manifest->name})
					{/if}
				</td>
			{elseif $column=="s_updated_at"}
			<td data-column="{$column}">
				<abbr title="{$result.$column|devblocks_date}">{$result.$column|devblocks_prettytime}</abbr>
			</td>
			{else}
			<td data-column="{$column}">{$result.$column}</td>
			{/if}
		{/foreach}
		</tr>
		<tr class="{$tableRowClass} preview" style="display:none;">
			<td data-column="preview" colspan="{count($view->view_columns)}">
				{$snippet_content = $result.s_content|regex_replace:'#({{.*?}})#':'[ph]\1[/ph]'}
				
				{if isset($dicts.{$result.s_context}) && isset($tpl_builder)}
					{$dict = $dicts.{$result.s_context}}
					
					{foreach from=$custom_placeholders item=placeholder key=placeholder_key}
					{$dict.$placeholder_key = '{{'|cat:$placeholder.key|cat:'}}'}
					{/foreach}
					
					{$snippet_content = $tpl_builder->build($snippet_content, $dict)}
				{/if}
				
				{$snippet_content = $snippet_content|escape:'htmlall'}
				{$snippet_content = $snippet_content|regex_replace:'#(\[ph\](.*?)\[/ph\])#':'<div class="bubble">\2</div>'}
				{*{$snippet_content = $snippet_content|regex_replace:'#(\(\(_+(.*?)_+\)\))#':'<span class="placeholder placeholder-input">(\2)</span>'}*}
				
				<div class="emailbody" style="border-left:2px solid rgb(220,220,220);font-size:12px;margin-left:15px;color:rgb(75,75,75);padding:5px 10px;">{$snippet_content nofilter}</div>
			</td>
		</tr>
	</tbody>
	{/foreach}
</table>

<div style="padding-top:5px;">
	<div style="float:right;">
		{math assign=fromRow equation="(x*y)+1" x=$view->renderPage y=$view->renderLimit}
		{math assign=toRow equation="(x-1)+y" x=$fromRow y=$view->renderLimit}
		{math assign=nextPage equation="x+1" x=$view->renderPage}
		{math assign=prevPage equation="x-1" x=$view->renderPage}
		{math assign=lastPage equation="ceil(x/y)-1" x=$total y=$view->renderLimit}
		
		{* Sanity checks *}
		{if $toRow > $total}{assign var=toRow value=$total}{/if}
		{if $fromRow > $toRow}{assign var=fromRow value=$toRow}{/if}
		
		{if $view->renderPage > 0}
			<a href="javascript:;" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewPage&id={$view->id}&page=0');">&lt;&lt;</a>
			<a href="javascript:;" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewPage&id={$view->id}&page={$prevPage}');">&lt;{'common.previous_short'|devblocks_translate|capitalize}</a>
		{/if}
		({'views.showing_from_to'|devblocks_translate:$fromRow:$toRow:$total})
		{if $toRow < $total}
			<a href="javascript:;" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewPage&id={$view->id}&page={$nextPage}');">{'common.next'|devblocks_translate|capitalize}&gt;</a>
			<a href="javascript:;" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewPage&id={$view->id}&page={$lastPage}');">&gt;&gt;</a>
		{/if}
	</div>
	
	{if $total}
	<div style="float:left;" id="{$view->id}_actions">
		{if $active_worker && $active_worker->is_superuser}
			<button type="button" class="action-always-show action-bulkupdate" onclick="genericAjaxPopup('peek','c=internal&a=showSnippetBulkPanel&view_id={$view->id}&ids=' + Devblocks.getFormEnabledCheckboxValues('viewForm{$view->id}','row_id[]'),null,false,'50%');"><span class="glyphicons glyphicons-folder-closed"></span> {'common.bulk_update'|devblocks_translate|lower}</button>
		{/if}
	</div>
	{/if}
</div>

<div style="clear:both;"></div>

</form>

{include file="devblocks:cerberusweb.core::internal/views/view_common_jquery_ui.tpl"}

<script type="text/javascript">
$(function() {
	var $frm = $('#viewForm{$view->id}');
	
	{if $pref_keyboard_shortcuts}
	$frm.bind('keyboard_shortcut',function(event) {
		var $view_actions = $('#{$view->id}_actions');
		var hotkey_activated = true;
	
		switch(event.keypress_event.which) {
			case 98: // (b) bulk update
				var $btn = $view_actions.find('button.action-bulkupdate');
			
				if(event.indirect) {
					$btn.select().focus();
					
				} else {
					$btn.click();
				}
				break;
			
			default:
				hotkey_activated = false;
				break;
		}
	
		if(hotkey_activated)
			event.preventDefault();
	});
	{/if}
});
</script>