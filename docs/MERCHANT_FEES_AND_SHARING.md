# Merchant platform fees and photo sharing

## Fee management

Internal → Merchant fees is available to System Administrators. An administrator can grant **Can manage merchant fee tiers** on an individual Supervisor profile under Internal → Users. Other supervisors and Support cannot publish policies. Public registration cannot grant this permission. Permission changes revoke older sessions.

Publishing creates an immutable policy version with:

- Base merchant percentage, 0–5%, at most two decimal places.
- Monthly units-sold tiers: confirmed paid units across all of the merchant's products in the current Africa/Nairobi calendar month, plus the current order quantity.
- Monthly sales-value tiers: confirmed paid product revenue across the merchant's products for that month, plus this order's product subtotal. Excludes delivery and buyer platform fees.
- High-value item tiers: the effective unit selling price, after any valid discount. The minimum threshold must be strictly above KES 10,000 (KES 10,000.01 or more).

Both monthly measures can apply independently. The lowest applicable rate wins; rates do not stack, and tiers cannot exceed the policy's base rate. The fee applies to product subtotal only and rounds half up to KES cents using integer arithmetic. Unpaid orders do not count toward previous sales. Local simulated sales count only in local simulator mode. Monthly totals are not retroactively recalculated into previously placed orders; the current order's agreed fee is frozen when placed.

No rates or thresholds were invented or automatically published. Until the first policy is published, existing internal configuration remains the fallback (currently zero by default). The administrator must enter the actual agreed base rate and tier thresholds/percentages. Test percentages exist only in isolated tests.

Each order stores its percentage, fee amount, selected rule and policy version. New policies affect new orders, not existing order snapshots. Changes between quote and placement require a fresh review. The policy pointer is locked during transactional order placement so fee publication cannot race the order's accepted snapshot.

Buyer totals do not include merchant fees. Merchant proceeds = product subtotal + merchant delivery charge − merchant platform fee. Buyer views never receive merchant rule/rate/version data. Only the merchant's own charges appear in their private order view; authorized internal financial roles retain their explicit access. Publishing policies is audited. This feature does not enable bank collection or payout transfers.

## Photo sharing

Public product HTML contains the first uploaded product photo as Open Graph and large-image card metadata, rendered on the server so preview crawlers do not need JavaScript. The image route is public and the metadata uses an absolute product photo URL. The product name and description are included, with no merchant fee or private KYC information.

The merchant sharing page shows the cover photo, name, current price and description, and allows photo download. Device sharing attaches the first photo when the browser supports file sharing; otherwise the product link remains available. WhatsApp/Facebook/X link composers use the public product URL. Plain SMS and many email clients do not render photo cards from a link; merchants can attach the downloaded image where supported.

Social services cannot fetch localhost/127.0.0.1 on this computer. A publicly reachable hosted product and image URL is required for external link previews. Platforms decide whether/when to show previews and may cache them. Metadata follows https://ogp.me/; no external posting, email or SMS is sent automatically.
