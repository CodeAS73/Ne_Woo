# Checkout and KCB BUNI

## Implemented

- Guest checkout with quantity, name, Kenyan phone, optional email, and preferred SMS/email/WhatsApp updates.
- Merchant-configured pickup and/or delivery. Delivery supports a flat per-order charge (including free delivery), allowed areas, and an estimate. Buyers can only select an offered area; delivery address is required only for delivery.
- The product creator can update delivery and pickup from the product page. Changes apply to new orders; already placed orders keep their accepted delivery terms and prices.
- A separate order review with a 15-minute server quote. Price/offer expiry, delivery terms and fees are checked again under a database transaction before order creation. Changed terms require a new review.
- An immutable order snapshot and one unique payment collection per order. Reposting the same placement reuses the existing order. Payment does not go directly to a merchant.
- Guest quote, order and payment routes require the creating browser session. Registered buyers access account-linked orders through their own account, across sessions. Unguessable order references and matching email addresses alone grant no access. Buyer and address details are encrypted in the database; private pages use no-store caching.
- Buyer and merchant response projections are explicit. Buyer pages receive only buyer charges; merchant pages receive only sale revenue, their delivery charge, their lead fee, expected proceeds and settlement status. Merchant finance data is in a separate table.
- Merchant order views at `/merchant/products/{product-slug}/orders` require ownership by an authenticated merchant, or System Administrator permission. My account lists owned products. Merchant authentication/KYC and internal RBAC are implemented; see ACCOUNTS_AND_SECURITY.md.

## Initial fee policy

Buyer secure purchase fees now default to 0.5% of discounted product subtotal, with a KES 30 minimum and KES 2,500 maximum per order. Admins and separately authorised supervisors can publish percentage changes under Internal → Buyer fees. Existing orders retain their agreed fee. Merchant fee tiers remain separate. See BUYER_SECURE_PURCHASE_FEE.md and MERCHANT_FEES_AND_SHARING.md.

For this first checkout implementation the merchant lead fee applies to product subtotal, excludes delivery and buyer fees, and rounds half up to the nearest cent using integer arithmetic. Expected merchant proceeds = product subtotal + merchant delivery charge − merchant lead fee. Buyer total = product subtotal + delivery charge + buyer platform fee. The fee values/version are snapshotted per order. Tier management is implemented; the administrator must still enter and publish the actual business percentages and thresholds.

## KCB BUNI status

Selected service: M-PESA STK Push through KCB BUNI. The intended payee is Ne_Woo's platform collection account, never a merchant account. A successful request acknowledgment must never be treated as a completed payment.

Official sources verified:

- https://buni.kcbgroup.com/getting-started — developer registration, application/API subscription, credentials, OAuth client-credentials token request.
- https://buni.kcbgroup.com/discover-apis — payment collection services and the developer portal for detailed API documentation.

`KcbBuniClient` implements only the publicly documented sandbox OAuth contract: HTTP Basic authentication and a form-encoded client_credentials grant to `https://uat.buni.kcbgroup.com/token`. It is tested with fake HTTP responses. It is not called by local checkout.

**Real STK collection is not implemented or enabled.** The approved application-specific STK request/response contract, platform account/reference format, callback authentication and independent transaction-verification contract must be obtained from KCB's portal before the collection adapter can be implemented and enabled. No guessed callback is accepted and no browser or unverified webhook can mark a real payment paid.

Environment placeholders in `.env.example`: `BUNI_CONSUMER_KEY`, `BUNI_CONSUMER_SECRET`, `BUNI_PLATFORM_ACCOUNT`, `BUNI_SHORT_CODE`, `BUNI_CALLBACK_URL`. Configure secrets directly in the local environment or a deployment secret manager, not source control or chat. Configuration alone does not enable collection.

## Local test mode

The current local `.env` uses `BUNI_MODE=simulator`. Place an order, choose **Test M-PESA payment flow**, then simulate success or cancellation. This sends no STK request, collects no money and never sets a real paid status. A test success records `test_paid` and `test_only_no_funds`; it cannot fund settlement. Repeated requests use the same order and collection record. Failed simulations may be retried on the same collection.

Set `BUNI_MODE=disabled` to show only the saved unpaid order. Simulation requires both `simulator` mode and a local/testing application environment. Sandbox mode currently permits explicit OAuth testing only and still blocks real checkout collection. Production/live mode is not supported yet.

## Before real collection

1. Obtain the approved STK specification and sandbox credentials for Ne_Woo. Confirm the collection account and invoice/reference mapping with KCB.
2. Implement STK initiation, authenticated or independently verified callback processing, amount/currency/account/reference checks, duplicate and conflicting event handling, reconciliation, and ambiguous-timeout handling. Never retry an uncertain collection blindly. Persist provider references and payment events under a one-order collection state machine.
3. Verify provider handling of KES fractional amounts and limits; do not silently round the order amount to fit a provider.
4. Complete stock reservations, operational order expiry, cancellations/refunds, privacy retention and guest receipt recovery. Merchant registration/KYC, account access and internal RBAC are implemented.
5. Connect notification providers. Selecting a channel currently saves a preference; messages are not sent.
6. Implement delivery confirmation, supervisor approval, disputed-order holds and the agreed 08:59–19:59 Africa/Nairobi payout schedule. No settlement release is enabled. No user wallets exist.
7. Verify the entire sandbox flow, secure the production environment and enable only the approved integration.

## Verification

`php artisan test` covers fee visibility and caps, correct totals, private guest access, encrypted contact data, forbidden delivery, invalid addresses, offer/quote changes, repeated placement/payment requests, simulation isolation and OAuth request shape. `npm.cmd run build` compiles assets; `php artisan migrate:status` verifies MySQL migrations. Browser QA covers the details, delivery, review and simulated-payment path. Tests do not send live payment requests.
