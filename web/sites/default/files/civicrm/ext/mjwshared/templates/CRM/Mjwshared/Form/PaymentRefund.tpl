{crmScope extensionKey='mjwshared'}
  <div class="crm-content">
    <div class="alert alert-info">
      <h4>Payment details</h4>
      <div class="crm-section crm-mjwshared-paymentrefund-paymentinfo">
        <div class="label">{ts}Total Amount{/ts}</div><div class="content">{$paymentInfo.total_amount|crmMoney:$paymentInfo.currency}</div>
          {if $paymentInfo.paid_amount}<div class="label">{ts}Amount Paid{/ts}</div><div class="content">{$paymentInfo.paid_amount|crmMoney:$paymentInfo.currency}</div>{/if}
        <div class="label">{ts}Payment date{/ts}</div><div class="content">{$paymentInfo.trxn_date|crmDate}</div>
          {if $paymentInfo.trxn_id}<div class="label">{ts}Transaction ID{/ts}</div><div class="content">{$paymentInfo.trxn_id}</div>{/if}
          {if $paymentInfo.order_reference}<div class="label">{ts}Order Reference{/ts}</div><div class="content">{$paymentInfo.order_reference}</div>{/if}
          {if $paymentInfo.payment_processor_title}<div class="label">{ts}Payment Processor{/ts}</div><div class="content">{$paymentInfo.payment_processor_title}</div>{/if}
        <div class="clear"></div>
      </div>
    </div>
      {if $participants}
        <div class="crm-section crm-content crm-mjwshared-paymentrefund-participantinfo">
          <h4>{ts}This payment was used to register the following participants:{/ts}</h4>
          <div class="crm-section crm-mjwshared-paymentrefund-participants">
            <ul>
                {foreach from=$participants item=participant}
                  <li>{$participant.display_name}: {$participant.event_title} (<em>{$participant.status}</em>)</li>
                {/foreach}
            </ul>
          </div>
          <div class="crm-section crm-mjwshared-paymentrefund-participant-canceloption">
              {$form.cancel_participants.label} {$form.cancel_participants.html}
            <div class="clear"></div>
          </div>
        </div>
      {/if}

      {if $memberships}
        <div class="crm-section crm-mjwshared-paymentrefund-membershipinfo">
          <h4>{ts}This payment was used for the following memberships:{/ts}</h4>
          <div class="crm-section crm-mjwshared-paymentrefund-memberships">
            <ul>
                {foreach from=$memberships item=membership}
                  <li class="">{$membership.display_name}: {$membership.type} (<em>{$membership.status}</em>)</li>
                {/foreach}
            </ul>
          </div>
          <div class="crm-section crm-mjwshared-paymentrefund-membership-canceloption">
              {$form.cancel_memberships.label} {$form.cancel_memberships.html}
            <div class="clear"></div>
          </div>
        </div>
      {/if}

    <div class="crm-section crm-mjwshared-paymentrefund-refundinfo">
      <div class="crm-section crm-mjwshared-paymentrefund-refundamount">
          {$form.refund_amount.label} {$form.currency.html|crmAddClass:eight}&nbsp;{$form.refund_amount.html|crmAddClass:eight}
        <div class="clear"></div>
      </div>
    </div>

      {if $paymentInfo.payment_processor_title}
        <div class="alert alert-success">{icon icon="fa-info-circle"}{/icon} {ts 1=$paymentInfo.payment_processor_title}When you click "Process Refund" a refund will be requested using the %1 payment processor and, if successful, recorded on the Contribution record.{/ts}</div>
      {else}
        <div class="alert alert-warning">{icon icon="fa-info-circle"}{/icon} {ts}This payment was recorded manually and did not use a payment processor. A refund will be recorded but you will need to process the actual refund externally.{/ts}</div>
      {/if}

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
  </div>
{/crmScope}
