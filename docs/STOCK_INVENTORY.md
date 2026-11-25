# Product stock

- New products require a whole-number **Stock available** quantity, from 0 to 1,000,000. This is available stock for new orders, not the total including reservations.
- Merchants can amend their own products under My account → Manage stock, or from the product controls. System Administrators can manage stock from Internal → Products. Other merchants, buyers and guests cannot amend stock.
- The stock page shows available units, units reserved for unpaid orders, and a movement history. An amendment sets the new available quantity; it does not add to it or alter existing order reservations.
- Optimistic version checks reject stale forms if an order or another edit changed stock while the form was open. Reload before deciding on the new quantity.
- Merchants can choose Show stock count to buyers during creation or stock amendment. When hidden, the numeric count is omitted from public product data, embeds, checkout limits and shortage messages; buyers still see In stock / Out of stock. Private merchant stock controls always retain the count.
- Public product cards and embeds show stock availability. Zero-stock items show Out of stock. Checkout checks quantity again on the server and reserves units under a product row lock when the order is placed. A price quote alone does not reserve stock. Repeated placement of the same order does not deduct again.
- Confirmed simulated payment commits the allocation without a second stock deduction. The future real payment callback must also commit the reservation in its payment transaction; real collection remains disabled.

## Unpaid reservations

Standard orders reserve stock for 30 minutes. Orders placed at an active offer or flash-sale price reserve stock for five minutes. Expired unpaid reservations are released only when the collection is not initiated or is a known simulated failure. A pending/uncertain payment is never automatically treated as unpaid: its stock stays reserved until its result is known. Such cases need payment reconciliation when the real integration is built.

Run Laravel's scheduler every minute in deployment. The scheduled task is also available manually:

```powershell
php artisan newoo:expire-stock
```

Product/checkout reads and the merchant stock page also release eligible expired reservations for that product. Starting payment and opening an order check expiry too. Released orders cannot be paid or have their old reservation reused; the buyer must place a new order. Releases are atomic and idempotent and are recorded in stock movements.

## Existing data

Existing products start at **0 available** because no previous stock count was supplied. The merchant or administrator must enter the actual available quantity. No stock is invented.

Orders created before this change have stock status `legacy`: no inventory allocation or expiry is retroactively inferred from them. When entering the initial count, merchants must account for units already promised to such orders. Existing order and payment records are preserved.

Order operations can restore verified units after an approved refund is recorded. See DELIVERY_WORKFLOW.md for approval and recording rules.
