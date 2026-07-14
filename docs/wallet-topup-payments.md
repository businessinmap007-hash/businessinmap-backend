# Wallet Top-Up & Payment Gateways

> Money-in for the **points wallet**. Egypt-only targeting. This doc records the
> audit of the pre-existing Fawry integration and the v2 build, so the
> investigation does not have to be repeated.

## 1. What the wallet is (scope)

The wallet is a **points/credit store, NOT a bank wallet and NOT a way to pay for
menu orders or booking service prices.** It is used to:

- charge (top-up) points, then **deduct** them for deposits / insurance (التأمين)
  and **fees** (الرسوم) on real-world services;
- **transfer** points peer-to-peer (a friend who needs points).

Menu-order checkout stays **cash** by design. "Wire the wallet in as an order
payment method" is explicitly **not** a goal.

Because the wallet only funds **real-world services + P2P transfer**, topping it
up with an external processor (Fawry / Apple Pay via Fawry) is **exempt from
Apple's In-App-Purchase requirement** (App Store guidelines 3.1.3 / 3.1.5). The
exemption breaks the moment points can buy in-app **digital** content — so that
must never happen.

## 2. Audit of the legacy (v1) Fawry integration

Found pre-existing, working-in-principle but unsafe:

| Location | What it did |
|---|---|
| `app/Libraries/Main.php` — `fawryPayment()` / `signRequest()` | Built a signed Fawry charge request to `atfawry.com/fawrypay-api/api/payments/init`. |
| `app/Libraries/Main.php` — `checkFawryOrders()` | Polled Fawry payment status as a fallback. |
| `Api\V1\PaymentController::fawrySuccessPayment()` | Fawry callback → credited balance (`recharge`) or activated a subscription. |

**Problems (do not carry forward):**

1. **Credentials hard-coded in source** — `merchantCode` `400000016550` and
   `secKey` were literals in `Main.php`. They must be **rotated** and read from
   env.
2. **Callback signature NEVER verified** — `fawrySuccessPayment()` trusted any
   POST with a known `merchantRefNumber`. This is a spoofable free-credit hole.
3. **Credited the dead legacy wallet** — it wrote the old `transactions` table
   (`calculateUserBalance`), not the modern `WalletService` ledger.

## 3. What was built (v2, phases 1–2 — DONE)

Commit `5273665` → merged to `main` `ef3b784` (2026-07-14). Verified E2E.

### Gateway abstraction (`app/Services/Payments/`)

- `PaymentGatewayInterface` — `name()`, `createCharge()`, `verifyCallbackSignature()`, `parseCallback()`. The only contract the controller knows; no coupling to Fawry.
- `FawryGateway` — cleaned port of `Main.php`; credentials come from `config('services.fawry')` / env, never source.
- `PaymentGatewayFactory` — resolves a gateway by name (add Paymob here later).
- `Dtos/ChargeResult`, `Dtos/CallbackResult` — gateway-agnostic value objects.

Config: `config/services.php` gained `payments` (default gateway, top-up
min/max) and `fawry` (base_url, merchant_code, security_key, currency,
return_url) blocks. `.env.example` gained `PAYMENTS_*` and `FAWRY_*` keys.

### Top-up flow

- Table `wallet_topups` + `App\Models\WalletTopup` — intent ledger, status
  `pending → paid | failed | expired`. `merchant_ref` (= row id, unique forever),
  `gateway_ref` (Fawry's ref), `method`, `amount`, `currency`, `meta`, `paid_at`.
- `Api\V2\WalletTopupController`:
  - `POST /api/v2/wallet/topup` *(auth)* — creates a `pending` intent, returns the
    hosted-checkout payload (`init_url` + signed `charge_request`).
  - `GET /api/v2/wallet/topup/{topup}` *(auth, own only)* — poll status.
  - `POST /api/v2/wallet/topup/callback` *(PUBLIC)* — the gateway's
    server-to-server notification.

### Security model (fixes the v1 holes)

- **Signature is verified** on every callback (`hash_equals`); bad signature → 400.
- Wallet is credited **only** in the server-to-server callback, **never** on the
  customer's browser return.
- Crediting is **idempotent** on `wallet_topup:{intent_id}` *and* guarded by a
  row-locked status flip → a replayed callback can never double-credit.
- **Amount-tamper guard**: the callback amount must match the stored intent
  amount (422 otherwise); we always credit our own stored amount.
- Credits go to the **modern `WalletService::deposit`** (points wallet), not the
  dead legacy `transactions` table.

### Verification result (E2E through the HTTP kernel)

1. `POST /wallet/topup` → **201**, `pending`, returns `init_url` +
   `charge_request` whose signature matches the documented scheme. ✅
2. `POST /wallet/topup/callback` with a valid **PAID** signature → **200**,
   balance +100, intent `paid`, one `deposit` ledger row. ✅
3. Replaying the same callback → **200**, balance unchanged, still **one** ledger
   row (idempotent). ✅
4. Callback with a bad signature → **400**, no credit. ✅

Test data was cleaned up afterward (no residual balance on the reused user).

## 4. Apple Pay / Google Pay — how they fit

They are **not gateways** — they are card-token layers that must ride on a PSP.
There is **no direct "Apple Pay → company account"** rail; Apple is not a
processor. So they are enabled **through Fawry's hosted checkout**:

- **Start (recommended):** Fawry hosted (redirect) checkout — easiest, fastest,
  safest (card data never touches our app/server; Fawry owns PCI). If Fawry's page
  shows the Apple Pay / Google Pay buttons, we get them with no native SDK.
- **Later (UX upgrade):** native in-app Apple/Google Pay — needs an Apple
  Merchant ID + processing cert tied to Fawry + domain verification, and the
  gateway's token-decrypt endpoint. Deferred.

## 5. Phases 3–5 (DONE)

3. **Methods** — `POST /wallet/topup` accepts an optional `payment_method`
   (`card` | `apple_pay` | `google_pay` | `fawry_cash` | `mobile_wallet` |
   `valu`). `FawryGateway::mapMethod` forces Fawry's `paymentMethod` for the
   distinct rails (`fawry_cash`→`PayAtFawry`, `mobile_wallet`→`MWALLET`,
   `valu`→`VALU`); card / Apple Pay / Google Pay are left to the hosted page,
   which presents them from the card rails when enabled on the merchant. The
   requested method is stored in the intent's `meta.requested_method`.
4. **Reconciliation** —
   - `App\Services\Payments\WalletTopupService` now owns settlement
     (`markPaid` / `markFailed`), shared by the callback AND the poller so both
     credit identically and idempotently (`wallet_topup:{id}`).
   - `FawryGateway::fetchStatus` polls Fawry's `/ECommerceWeb/Fawry/payments/
     status/v2` (signature = sha256(merchantCode+merchantRefNum+secKey)); returns
     null when unconfigured/unreachable.
   - Command `php artisan wallet:reconcile-topups {--minutes=15} {--limit=200}`
     settles pending intents the callback missed. **Schedule it every ~5 min in
     production once Fawry creds are set.**
   - AdminV2 oversight page `admin/wallet-topups` (status/amount/refs/method +
     per-status totals) under "Delivery & Tables" in the sidebar.
5. **Tests** — `WalletTopupCallbackTest` (signature / idempotency / amount-tamper
   / PAID / FAILED / owner-scoped) + `WalletTopupMethodsReconcileTest` (method
   forcing, settlement idempotency, poll no-op without creds, admin view).

## 6. Go-live checklist / landmines

- [ ] **Confirm the Fawry callback signature field order** in
  `FawryGateway::callbackSignature()`. It follows Fawry's ServerToServer
  notification (v2) docs but was only self-consistency tested, **not** against
  real Fawry. Fawry has shipped more than one scheme.
- [ ] Set the **server-to-server notification URL** in the Fawry merchant
  dashboard to `/api/v2/wallet/topup/callback`.
- [ ] **Rotate** the old hard-coded `merchantCode` / `secKey`; put fresh values in
  `.env` (`FAWRY_MERCHANT_CODE`, `FAWRY_SECURITY_KEY`, `FAWRY_RETURN_URL`).
- [ ] Ask Fawry whether **Apple Pay and Google Pay** are enabled for the merchant.
  If Google Pay is not supported, add **Paymob** as a secondary gateway for it
  (Fawry stays for card + cash/kiosk).
- [ ] Keep points strictly for **real-world services + P2P** to preserve the Apple
  IAP exemption; note this basis in the App Store review submission.
