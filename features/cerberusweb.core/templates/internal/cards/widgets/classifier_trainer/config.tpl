{$config_uniqid = uniqid('widgetConfig_')}
<div id="cardWidgetConfig{$config_uniqid}" style="margin-top:10px;">
    <fieldset class="peek">
        <legend>Display this classifier:</legend>

        <b><a href="javascript:;" class="cerb-chooser" data-context="{CerberusContexts::CONTEXT_CLASSIFIER}" data-single="true">ID</a>:</b>

        <div style="margin-left:10px;">
            <input type="text" name="params[classifier_id]" value="{$widget->extension_params.classifier_id}" class="placeholders" style="width:95%;padding:5px;border-radius:5px;" autocomplete="off" spellcheck="false">
        </div>
    </fieldset>
</div>

<script type="text/javascript">
$(function() {
    var $config = $('#cardWidgetConfig{$config_uniqid}');
    var $input_classifier_id = $config.find('input[name="params[classifier_id]"]');

    $config.find('.cerb-chooser').cerbChooserTrigger()
        .on('cerb-chooser-selected', function(e) {
            {literal}$input_classifier_id.val(e.values[0] + '{# ' + e.labels[0] + ' #}');{/literal}
        })
    ;
});
</script>