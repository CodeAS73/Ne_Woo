# Delivery confirmation and supervisor review

Implemented workflow:

1. Payment must have a matching confirmed collection record. Local simulator payments can exercise the workflow only in local/testing simulator mode.
2. Merchant: Unstarted → Preparing → Dispatched (delivery) or Ready for pickup (pickup) → Delivered/collected.
3. Buyer confirms actual receipt after dispatch, ready-for-pickup, or delivered. The merchant's delivered update alone never counts as buyer confirmation.
4. Buyer confirmation queues settlement for supervisor review. A manual internal hold remains in place even if the buyer confirms later.
5. Supervisor or System Administrator approves future settlement or keeps it on hold, with a required internal note. Approval requires confirmed payment, buyer-confirmed receipt, an assigned merchant and no open delivery issue.

## Access

- Merchants use My account → All merchant orders & fulfilment. Only the order's snapshotted merchant owner (or System Administrator acting operationally) can update fulfilment.
- Buyers use their private order page to confirm receipt or report an issue. Existing guest session and registered-account ownership checks apply.
- Supervisors use Internal → Delivery reviews. Support, buyers, merchants and unauthenticated visitors have no access to review endpoints.
- A reviewer cannot review their own purchase, sale, or an order on which they performed merchant fulfilment actions. Guest ownership in the reviewing browser also blocks self-review.
- Product reassignment does not move existing order ownership. The merchant's all-orders view retains their historical orders and product views exclude another seller's historical orders.

## Delivery issues and holds

A buyer can report an issue after confirmed payment, including after receipt confirmation or supervisor approval while settlement is unreleased. This puts settlement on hold and clears the current approval. Issue descriptions are shared with the merchant and authorized internal reviewers. An internal reviewer resolves the issue with a private note; resolution never fabricates buyer confirmation or automatically restores approval. A previously confirmed order returns to pending review; an unconfirmed one still needs the buyer to confirm.

The delivery issue screen handles delivery holds. Use Order operations for broader cases, written evidence and refund decisions.

## Privacy and audit

Shared fulfilment events show timestamps in East Africa Time and merchant/buyer updates. Internal decision notes and review history are excluded from public presenters and stored encrypted. Buyer and merchant fee views remain separate. Review queues do not expose either party's fee breakdown. Each meaningful transition creates an order event and security audit record. Row locks serialize fulfilment, buyer confirmation, issue reporting and review; repeated receipt confirmations or approvals are idempotent.

## No payout release

Real approval records `approved_awaiting_schedule`; simulator approval records `test_approved_no_funds`. Approval does not transfer money or create a payout instruction. Test orders can never fund real settlement. Automatic releases remain disabled regardless of review approval.

Still needed: real KCB BUNI collection/verification, payout integration, review-to-payout reconciliation and the precise hourly cutoff rule. The configured business window remains 08:59 through 19:59 Africa/Nairobi. The agreed rule is the end of the next hour: approval at 10:15 qualifies at 11:59 EAT. A calculated slot outside 08:59–19:59 rolls to the next available 08:59 (same morning when before opening, next day after closing). Real-payment approvals store this UTC eligibility timestamp; holds and delivery issues clear it. Test approvals have no payout eligibility timestamp. Calculating eligibility does not enable transfers.

## Order cancellations, refunds and disputes

- Standard orders reserve stock for 30 minutes. Orders placed at an active offer or flash-sale price reserve stock for five minutes. The offer price is determined at checkout. Pending or uncertain collections keep their reservation until the payment outcome is known.
- Buyers, the order's merchant, and authorized internal staff can cancel an unpaid order with no pending collection. Stock is restored once. For confirmed payments, use the paid cancellation, refund, or dispute case on the order.
- Buyers and merchants can submit written evidence. Internal reviewers can close the case or approve a full or partial refund. An active case pauses fulfilment, receipt confirmation and unreleased settlement. Case notes are shared with the parties. Record only relevant information.
- System Administrators record confirmed external refunds with a unique external reference after verification. The platform does not initiate a transfer. The simulator can record a test refund without a reference. Approved restock quantities must represent physically recovered or never-dispatched units and are restored once at recording.
- Unreleased fully refunded orders are closed for settlement. Partial refunds leave unreleased settlement held for finance reconciliation because the refundable fee and merchant allocation rules are not defined. Post-payout cases preserve the original payout history. Existing delivery-issue review remains available for delivery issues.
