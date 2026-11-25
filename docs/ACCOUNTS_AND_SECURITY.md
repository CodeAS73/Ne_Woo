# Accounts, merchant KYC and RBAC

Ne_Woo now uses its own Laravel session authentication, password hashing, CSRF protection and role checks. No third-party authentication provider or authentication package is used.

## Public accounts

- `/register`: choose Merchant or Buyer. Public registration cannot select an internal role, set approval status or change authorization flags.
- `/login`: accepts active buyer and merchant accounts only.
- `/account`: account purchases and, for merchants, owned products, merchant order ledgers and delivery controls.
- Guest buyers can still check out without registering. Signup or login in the same browser attaches that browser's guest orders to the account; no order is claimed by email alone. Account-linked orders remain accessible across login sessions. Logging out removes the browser session.
- Merchant signup leads to `/merchant/kyc`. Widget creation requires an active merchant role and approved KYC. Pending, rejected, buyer and guest accounts cannot bypass this by posting directly to the product endpoint.

## Merchant KYC

Collect legal/trading name, business type and address, contact phone, identity type/number, a document copy and review consent. Registered businesses additionally supply a registration number. Documents accept PDF, JPG and PNG up to 5 MB. These are initial product requirements for manual internal review, not a claim of automated identity validation or a complete regulatory compliance program.

KYC details are encrypted in MySQL. Documents are in private local storage, outside the web root. Only authorized internal reviewers can download documents; downloads are attachments with no-store and nosniff headers. Reviewers approve or reject pending submissions. A rejection requires a correction note and permits resubmission. Pending and approved records cannot be silently overwritten by merchants. Submission, review and document access are audited.

## Internal access

Staff use `/internal/login`, not the public login. This URL is deliberately absent from public navigation. Separate authentication guards and server-side permissions provide security; an unlisted URL is not treated as an access control. Internal routes require the internal guard even if a public account is logged in. Signing into a portal signs out the other guard to avoid ambiguous identity.

| Role | Access |
| --- | --- |
| System Administrator | All current permissions: profiles, activation invitations, role/access changes, KYC, product ownership, product controls, order records, private financial detail and audit events. |
| Supervisor | Internal overview, KYC review/documents and operational order overview. Cannot create staff or view the restricted financial detail. |
| Support | Internal overview and basic user list. Cannot change access, review KYC or download identity documents. |
| Merchant | Owned product management/order costs; new widgets only after KYC approval. |
| Buyer | Own purchases; no merchant publishing or internal access. |

Role definitions and named permissions are in `config/rbac.php`. Administrators create profiles and assign existing roles. Arbitrary custom-role editing is not exposed. Account type must match the role; unknown or mismatched roles fail closed.

## First administrator

The first profile is reserved for **andrew.siyoi@gmail.com**, as requested, with the System Administrator role. It remains inactive until its owner sets a password with the one-time activation link in `storage/app/private/admin-setup-link.txt`. No default/shared password exists. The setup link expires after 24 hours and is valid once. The user sets their password themselves; no outbound email has been sent.

If the first invitation expires before an administrator is activated, a trusted server operator may run:

```powershell
php artisan newoo:bootstrap-admin andrew.siyoi@gmail.com
```

This command refuses to bootstrap another administrator once an active one exists. It refuses to promote an existing unrelated customer account. It saves a new private setup link and invalidates the previous invitation.

## Profile management

An administrator can create Buyer, Merchant, Support, Supervisor or System Administrator profiles. New profiles receive an expiring, single-use setup link displayed only in the internal workspace; the administrator shares it with the intended recipient. Passwords are chosen by recipients, never prefilled by the administrator. Inviting a merchant does not approve KYC.

Roles and active status can be changed by System Administrators. Changes increment the account session version, ending older sessions. Disabling an account also revokes pending invitations. A new setup link can be issued explicitly for an inactive profile. The last active System Administrator cannot be disabled or demoted. Profiles are not hard-deleted through this interface.

## Existing development products

Older products remain stored and readable. Anonymous session hashes and `created_widgets` are no longer authority for managing them. An administrator must explicitly assign them from Internal → Products to a registered merchant; ownership is audited. No products are automatically assigned merely because their browser session registers an account.

## Fee privacy

Buyer-facing order and account pages expose buyer costs only. Merchant-facing order views expose the merchant's sale, delivery charge, lead fee and expected proceeds. Access now uses account ownership rather than the old anonymous widget session. Restricted internal financial detail requires `orders.finance`, available to the System Administrator. Guest/account order access cannot be gained by guessing an order reference or matching an email address.

## Security checks and remaining rollout work

Passwords require at least 12 characters with letters and numbers, and a maximum of 72 UTF-8 bytes for bcrypt. Authentication has generic failure messages, attempt limits, session regeneration and disabled-account checks. Authenticated/internal responses are private and no-store. Sensitive setup pages disable referrer transmission and search indexing. Activation tokens are random and only their hashes are stored in MySQL. KYC data and tokens are not written to audit events.

Before external deployment, configure HTTPS/secure cookies, `APP_DEBUG=false`, trusted hosts/proxies, email ownership verification and password recovery, KYC retention/deletion policy, backups and operational access. Local email delivery is still log-only. KCB BUNI real collection and supervisor-approved payout workflows remain disabled. This account change does not enable live payments.

Tests cover role escalation, separate portals, KYC gating/private storage, ownership, permission checks, last-admin protection, session revocation, activation expiry/replay/revocation, guest checkout and account-linked purchase access.
