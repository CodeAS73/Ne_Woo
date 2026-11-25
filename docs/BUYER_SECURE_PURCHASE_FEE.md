# Buyer secure purchase fee

The current default is **0.5% of the discounted product subtotal**, excluding delivery, with a **KES 30 minimum** and **KES 2,500 maximum per order**. This supersedes the earlier fixed buyer fee and KES 50 cap. This rule is for the buyer, not the merchant platform fee.

Calculation uses integer minor units and half-up rounding to cents:

`fee = max(3000, min(250000, round_half_up(product_subtotal_minor × rate_bps / 10000)))`

50 basis points = 0.5%. Examples: KES 1,000 subtotal → KES 30 fee; KES 10,000 → KES 50; KES 1,000,000 → KES 2,500. Buyer total is product subtotal + delivery + this fee, collected once by Ne_Woo. Merchant proceeds and merchant platform fee rules are unaffected.

## Administration

Internal → Buyer fees shows the active policy and version history. System Administrators may publish an adjusted percentage. A supervisor additionally needs the distinct **Can manage buyer secure purchase fees** grant on their profile under Internal → Users. Merchant fee-tier authority alone does not grant access to buyer fee controls. Permission changes revoke older sessions. Support, buyers, merchants and unapproved supervisors cannot publish rates.

The percentage supports two decimal places from 0.01% to 100%. The KES 30/KES 2,500 bounds remain fixed and cannot be overridden through request input. Each change creates an immutable policy version and an audit event. No external payment transfer is triggered by changing a rate.

## Order snapshots and privacy

New quotes use the active policy. A rate-policy change before order placement requires a fresh review. Placement locks the active settings row and saves the agreed fee amount, rate and policy reference on the order. Existing orders are not repriced. Older orders retain their previous fixed fee and have no retroactively inferred policy reference.

Checkout updates the displayed fee when quantity changes and verifies all totals again on the server. Buyer pages show the secure purchase fee; merchant-facing presenters exclude it and exclude the buyer total and buyer policy metadata. Internal financial roles retain their explicitly authorised access. The old `NEWOO_BUYER_FEE_MINOR` setting is no longer used.
