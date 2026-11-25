# Ne_Woo platform requirements

Status: widgets, stock levels/reservations, guest/account orders, authentication/KYC/RBAC, merchant fulfilment, buyer receipt confirmation, delivery holds and supervisor approval implemented. Checkout supports server-side review, private fees and local payment simulation. Real KCB BUNI collection, messaging and payout scheduling/release remain pending. See STOCK_INVENTORY.md, DELIVERY_WORKFLOW.md, ACCOUNTS_AND_SECURITY.md and CHECKOUT_AND_BUNI.md.

## Accounts and access

- Merchants register and submit KYC details before selling; KYC approval workflow is required.
- Buyers may register or check out as guests.
- Internal users have explicit permissions for KYC, fees, delivery review and payout approval.
- No merchant or buyer wallets. Ledgers record transactions and settlements, not spendable balances.

## Products and sharing

- Landing page prominently features the widget creator. Merchants can create many widgets free of charge.
- Each product has a brief description, price and up to five validated photos. Thumbnails expand on hover/focus and click, with touch support.
- Share formats: embeddable widget, sale button and product link, distributed through social media, email and SMS.
- Valid discounted prices are prominent, with the normal price struck through. Flash sales display a countdown.
- Server selects the best currently valid offer at checkout, independent of cached prices or browser time. Buyer confirms the final payable amount before payment if pricing changes.

## Checkout and communication

- Stages: product and quantity, contact and delivery details, review of buyer costs, consent to proceed, payment, confirmation.
- Buyer selects SMS, email or WhatsApp transactional messages.
- Require stock validation, server-side totals, order snapshots and idempotent payment callbacks.
- Payment goes to Ne_Woo and is held until delivery completion and confirmation, followed by supervisor review.
- Payment, merchant payout, messaging and delivery providers remain to be selected. Local development must not move real funds or send real messages.

## Fees and settlements

- Implemented: admin-published merchant fee tiers capped at 5%, using both monthly paid units and monthly paid sales value, plus effective unit prices above KES 10,000. The lowest qualifying rate applies to product subtotal. Individual supervisors require explicit authority. Actual business rates still need publication.
- Buyer secure purchase fee is 0.5% of discounted product subtotal, minimum KES 30 and maximum KES 2,500 per order. Admins and individually approved supervisors can change the percentage; prior orders keep their original fee.
- Buyers only see their own fees and costs; merchants only see theirs. Enforce through authorization and response data, not only templates.
- Approved eligible payouts run at hourly :59 slots, 08:59 through 19:59 inclusive, Africa/Nairobi.
- Confirmed rule: approval at 10:15 qualifies for release at 11:59 EAT (end of the next hour). Slots outside 08:59–19:59 roll to the next available 08:59. Eligibility is recorded for real-payment approvals; automatic payout execution remains disabled.
- Preserve payment, delivery, approval, fee-version and payout audit trails. Retries must not duplicate transfers.
- Store money as integer minor units (KES cents), never floating point. Store timestamps in UTC; apply business windows and display times in Africa/Nairobi.

## Reporting

- Registered users receive purchase or sales totals, periodic summaries and links to detailed ledgers.
- Merchants receive product-level trends and summaries across their widgets.
- Guest orders require secured order verification.

## Build sequence

1. Development foundation: Laravel, MySQL configuration, assets, baseline checks and setup documentation.
2. Data model, registration, roles, merchant profiles and KYC.
3. Products, five-photo upload, offers, widgets, sharing and landing page.
4. Guest/account checkout, stock, fee rules and order snapshots.
5. Sandbox payment collection and selected communication channels.
6. Delivery confirmation, supervisor review, scheduled settlement, retries and reconciliation.
7. Dashboards, trends, ledgers and operational controls.

## Later decisions

KYC fields and retention; payment/payout and messaging providers; delivery responsibility and charges; cancellations, refunds and disputes; fee tiers and bases; offer stacking; stock reservations; precise payout timing. These do not block environment preparation.

### Reporting and ledger access

- Buyers can review their own purchases. Merchants can also review their own sales and settlements. Sales and settlement scope follows the merchant stored on the order at checkout.
- The System Administrator can see platform-wide reports and both ledgers. Grant each Supervisor access from **User profiles** using the three independent reporting permissions. Changes are audited and revoke existing sessions. Existing operational permissions remain governed by their original rules.
- Reporting uses payment confirmation time in Africa/Nairobi. Purchase totals include delivery and buyer fees. Sales totals use merchandise subtotals. Monthly totals group currencies individually and exclude unpaid and simulated orders. Product trends compare the selected month with the preceding month.
- Ledgers include every recorded order or settlement in scope, including unpaid and simulated entries. Settlement history displays receipt, issue and review events. The release eligibility date records when a real payout could be scheduled. The current payment integration does not execute live payouts.
