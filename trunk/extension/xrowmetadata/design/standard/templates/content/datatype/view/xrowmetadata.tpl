{* DO NOT EDIT THIS FILE! Use an override template instead. *}
{if $attribute.has_content}
<table>
<tr><th class="table_no_border">{'Title'|i18n( 'design/standard/content/datatype' )}:</th><td class="table_no_border">{$attribute.content.title|wash()}</td></tr>
<tr><th class="table_no_border">{'Description'|i18n( 'design/standard/content/datatype' )}:</th><td class="table_no_border">{$attribute.content.description|wash()}</td></tr>
<tr><th class="table_no_border">{'Keywords'|i18n( 'design/standard/content/datatype' )}:</th><td class="table_no_border">{$attribute.content.keywords|wash()}</td></tr>

<tr><th class="table_no_border">{'Googlemap'|i18n( 'design/standard/content/datatype' )}:</th><td class="table_no_border">{if eq($attribute.content.googlemap,'1')}enabled{else}disabled{/if}</td></tr>
<tr><th class="table_no_border">{'Google Change'|i18n( 'design/standard/content/datatype' )}:</th><td class="table_no_border">{$attribute.content.change|wash()}</td></tr>
<tr><th class="table_no_border">{'Google Priority'|i18n( 'design/standard/content/datatype' )}:</th><td class="table_no_border">{$attribute.content.priority|wash()}</td></tr>

</table>
{/if}
