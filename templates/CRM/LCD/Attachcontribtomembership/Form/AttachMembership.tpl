{* HEADER *}

<div class="help">{ts}Select the membership record you would like to attach the contribution to.{/ts}</div>

{if $existingMembership}
  <div class="help">{ts}This contribution is currently attached to a membership for:{/ts} {$existingMembership}</div>
{/if}

{* FIELD EXAMPLE: OPTION 1 (AUTOMATIC LAYOUT) *}

{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">
      {if $descriptions.$elementName}<div class="description">{$descriptions.$elementName}</div>{/if}
      {$form.$elementName.html}
    </div>
    <div class="clear"></div>
  </div>
{/foreach}

{* FIELD EXAMPLE: OPTION 2 (MANUAL LAYOUT)

  <div>
    <span>{$form.favorite_color.label}</span>
    <span>{$form.favorite_color.html}</span>
  </div>

{* FOOTER *}
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
