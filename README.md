# Ne_Woo

Merchant product widgets, guest/account checkout, first-party signup/login, merchant KYC review and role-based internal administration. Checkout includes delivery/pickup, final review, private party-specific fees and a local M-PESA simulator. Real KCB BUNI collection, messaging and settlements remain pending.

## Stack

- Laravel 13 with Blade, middleware and backend services. Upgrade to Laravel 14 after its stable release and compatibility review.
- PHP 8.3+; local CLI PHP 8.3.28; MySQL 8.4 (WAMP) using pdo_mysql.
- Vite, Tailwind CSS, Node.js and npm.
- Database queues, sessions and cache. Local email writes to the Laravel log.
- UTC timestamps, Africa/Nairobi business times, KES currency.

## Local setup (PowerShell)

Start MySQL from WAMP. Create a fresh development database and dedicated user using a MySQL administrator account:

```sql
CREATE DATABASE IF NOT EXISTS ne_woo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ne_woo'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_LOCAL_PASSWORD';
GRANT ALL PRIVILEGES ON ne_woo.* TO 'ne_woo'@'127.0.0.1';
```

Set the matching DB_PASSWORD in .env. Never commit .env or use the example password. Do not point migrations at an unrelated existing database.

For a new checkout:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm.cmd ci
```

This installation already has the database, dedicated account, application key and initial tables. Its generated database password is saved only in the ignored .env file. Preserve this file and key. For future schema or asset changes:

```powershell
php artisan migrate
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://127.0.0.1:8000 for the widget creator. /up checks application boot, not database connectivity.

## Product widgets

Upload one to five JPG, PNG or WebP photos (5 MB each), add a name, description and KES price, and optionally set a lower offer price and future expiry in East Africa Time. Saving creates public product, iframe widget and quantity-review URLs. The server resolves offer validity when showing products and reviewing quantities. Amounts are stored as integer cents.

Copy the widget embed, sale button HTML or product link. WhatsApp, Facebook, X, email and SMS controls open the relevant sharing service or application; they do not send automatically. Loopback links work only locally; external sharing requires a hosted domain and matching APP_URL.

Widget creation now requires an active merchant account and approved KYC. Products belong to merchant accounts; browser-session hashes no longer authorize merchant access. Merchants find saved products from My account. Photos are stored privately and served through public product photo routes. Older development products require explicit administrator assignment to a merchant.

Buy now opens guest checkout, order review and order placement. Buyers pay one combined total to Ne_Woo; merchant charges stay in a separate internal finance record and merchant-only view. Real collection is disabled. See [checkout and KCB BUNI setup](docs/CHECKOUT_AND_BUNI.md) for the local test flow and integration requirements.

For frontend development run `npm.cmd run dev` in another terminal. Run `php artisan queue:work --tries=3` when queued features are introduced. On Windows use separate terminals: the upstream composer dev script includes Pail, which requires Unix process extensions.

For WAMP Apache configure the virtual host document root to C:/wamp64/www/Ne_Woo/public, enable URL rewriting and set the matching APP_URL. Never serve the project root, which contains environment secrets.

## Verification

```powershell
composer validate --strict
php artisan test
npm.cmd run build
php artisan migrate:status
```

Default tests use in-memory SQLite for isolation. Tests do not verify MySQL; migrate and migrate:status must also succeed against MySQL.

See [platform requirements and build sequence](docs/PLATFORM_REQUIREMENTS.md). Business defaults are in config/newoo.php. No actual fee charging or settlement is enabled.

See [accounts, KYC and RBAC](docs/ACCOUNTS_AND_SECURITY.md) for registration, private internal login, first-admin setup, roles, invitation management and security checks. Public signup is at /register; internal accounts use /internal/login with no public navigation entry.

See [delivery confirmation and supervisor review](docs/DELIVERY_WORKFLOW.md). Merchants update fulfilment from their orders, buyers confirm receipt or report issues, and supervisors review at /internal/delivery-reviews. Approval does not release funds; automatic payout remains disabled.

See [stock levels and reservations](docs/STOCK_INVENTORY.md). Products require an available quantity; merchants amend it through Manage stock. Order placement reserves units atomically, and eligible unpaid reservations expire after 30 minutes. Existing products start at zero until their actual count is supplied. Run Laravel's scheduler for regular reservation cleanup.

MySQL connections explicitly use InnoDB so transactions and foreign keys work even when the local server defaults to MyISAM. The local PHP Xdebug configuration currently reports an unwritable WAMP log path; it does not prevent the application or tests from running.

See [merchant fee tiers and photo sharing](docs/MERCHANT_FEES_AND_SHARING.md). Configure rates under Internal → Merchant fees. Admins can authorise individual supervisors through Users.

Buyer secure purchase fees default to 0.5%, minimum KES 30 and maximum KES 2,500 per order. Manage the rate at /internal/buyer-fees. See [buyer fee policy](docs/BUYER_SECURE_PURCHASE_FEE.md). Merchant fees remain separate.
