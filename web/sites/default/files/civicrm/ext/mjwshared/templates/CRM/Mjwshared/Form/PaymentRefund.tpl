{crmScope extensionKey='mjwshared'}
  <h3>Payment details</h3>
  <div class="crm-section crm-mjwshared-paymentrefund-paymentinfo">
    <div class="label">{ts}Amount{/ts}</div><div class="content">{$paymentInfo.total_amount|crmMoney:$paymentInfo.currency}</div>
    <div class="label">{ts}Payment date{/ts}</div><div class="content">{$paymentInfo.trxn_date|crmDate}</div>
      {if $paymentInfo.trxn_id}<div class="label">{ts}Transaction ID{/ts}</div><div class="content">{$paymentInfo.trxn_id}</div>{/if}
      {if $paymentInfo.order_reference}<div class="label">{ts}Order Reference{/ts}</div><div class="content">{$paymentInfo.order_reference}</div>{/if}
      {if $paymentInfo.payment_processor_title}<div class="label">{ts}Payment Processor{/ts}</div><div class="content">{$paymentInfo.payment_processor_title}</div>{/if}
  </div>
{if $participants}
  <h3>{ts}This payment was used to register the following participants:{/ts}</h3>
  <div class="crm-section crm-mjwshared-paymentrefund-participants">
    <br />
    <ul>
        {foreach from=$participants item=participant}
          <li>{$participant.display_name}: {$participant.event_title} (<em>{$participant.status}</em>)</li>
        {/foreach}
    </ul>
  </div>
  <br />
  <div class="crm-section crm-mjwshared-paymentrefund-participant-canceloption">
    <div class="label">{$form.cancel_participants.label}</div>
    <div class="content">{$form.cancel_participants.html}</div>
    <div class="clear"></div>
  </div>
{/if}

{if $memberships}
  <h3>{ts}This payment was used for the following memberships:{/ts}</h3>
  <div class="crm-section crm-mjwshared-paymentrefund-memberships">
    <br />
    <ul>
        {foreach from=$memberships item=membership}
          <li>{$membership.display_name}: {$membership.type} (<em>{$membership.status}</em>)</li>
        {/foreach}
    </ul>
  </div>
  <br />
  <div class="crm-section crm-mjwshared-paymentrefund-membership-canceloption">
    <div class="label">{$form.cancel_memberships.label}</div>
    <div class="content">{$form.cancel_memberships.html}</div>
    <div class="clear"></div>
  </div>
{/if}

  <div class="crm-section crm-mjwshared-paymentrefund-refundamount">
    <div class="label">{$form.refund_amount.label}</div>
    <div class="content">
      <span id='totalAmount'>{$form.currency.html|crmAddClass:eight}&nbsp;{$form.refund_amount.html|crmAddClass:eight}</span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="help">{ts}Click "refund" to refund this payment{/ts}</div>
  <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
{/crmScope}
