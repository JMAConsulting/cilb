# Refunds UI

If supported by the payment processor (eg. Stripe) you can issue a full or partial refund from within CiviCRM.

It is enabled by default via the setting `mjwshared_refundpaymentui` which can be found at
*Administer->CiviContribute->Payment Shared Settings: Enable refund payment via UI?*

It allows you to issue refunds for `Completed` payments.

It also allows you to choose whether to cancel related entities if there are any linked to the contribution (via line-items):
- Event registrations
- Memberships

To access the refunds UI you must have **Refund contributions** permission.

It can be accessed in a few ways:

#### Contribution

![Contribution refund link](images/refundpaymentcontributionlink.png)

This works if the paid amount on the Contribution is > 0 and there is only one payment.
Otherwise you have to select the specific payment to refund.

#### Payment

![Refund icon](images/refundpaymenticon.png)

1. Click the "arrow" to expand the contribution and show payments.
2. To access the refund form click the "undo" icon by the payment.

### Using the Refund Form

You will see a refund form.

If the contribution was used to pay for a membership you can optionally cancel the membership:
![Refund UI - memberships](images/refundui-membership.png)

If the contribution was used to pay for an event you can optionally cancel the event registration:
![Refund UI - events](images/refundui-events.png)
