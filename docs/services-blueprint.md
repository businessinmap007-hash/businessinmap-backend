# Services Blueprint — Booking / Menu / Delivery

The agreed design for how platform services are configured and consumed, and
how a business owner manages their own offering. This is the reference for all
service/booking work; read it before touching the flow.

Related: [categories.md](categories.md) (category → child → service data model).

---

## 1. Two audiences, cleanly separated

Complexity used to come from one person touching ~6 configuration layers. The
model separates by role:

- **Platform / superadmin (AdminV2):** defines what is *possible* — platform
  services, item types, **branches** (`platform_service_item_groups`), and
  which item types each subcategory may offer (`CategoryServiceConfig`).
- **Business owner (scoped panel at `/business`):** never sees categories /
  matrix / configs. Only: *allowed types for my activity → pick what I have →
  price it → add my units*. A 3-step flow.

---

## 2. Data model (source of truth per concern)

| Concern | Where it lives |
|---------|----------------|
| Services (booking / menu / delivery) | `platform_services` |
| Item types (single_room, restaurant_table, …) | `platform_service_item_types` |
| **Branches** grouping types under a service (hotel / clinic / sports) | `platform_service_item_groups` (+ `group_id` on item types) |
| Which types a subcategory may offer + per-service config | `category_service_configs.config.allowed_item_types` |
| Which services a subcategory offers | `category_platform_services` |
| **Price / deposit / discount** (per business + child + service + type) | `business_service_prices` |
| Physical inventory (room 101, table 5) | `bookable_items` |
| A reservation | `bookings` |

### Pricing authority — single source
**Price and deposit live only in `business_service_prices` (per item type).**
`bookable_items` is inventory only (code / capacity / quantity / active); it
carries no price. Consequence, accepted by design: **two units of the same
type share one price**. A premium unit becomes a **distinct item type** (e.g.
`single_deluxe`), which the branches/types model already supports.

> Note: `bookable_items` still has legacy `price`/`deposit_*` columns; they are
> no longer authored in the business panel and are slated for removal. Do not
> build new logic on them.

---

## 3. Owner setup flow (the 3 steps)

```
category_child.allowed_item_types   (defined by admin)
        ↓
pick the types I offer  →  set a price/deposit/discount per type  →  add my units
   (implicit)              business_service_prices                    bookable_items
```

- Service dropdown for the owner is limited to services their `category_child`
  offers; type dropdown to the types allowed for that (child, service).
- Both are re-validated server-side on store/update (no trust in posted values).

---

## 4. Booking as the invoice container

A booking is the single invoice. For restaurants, ordered food attaches to the
booking as line items (same invoice):

```
Booking (table / unit + time)
   + food line items (linked by booking_id)      ← cross-service, NEW
   = one unified invoice
   + deposit on the unified total (table + food)  ← per business policy
```

- **Fulfillment type** on a menu order: `dine_in` (→ linked to a table
  booking) · `delivery` (address + delivery fee) · `pickup`. — NEW field.
- **Table charge mode** (per business, configurable): `free` /
  `reservation_fee` / `minimum_charge` (+ amount). Stored on the unit with a
  business default (VIP table can have a minimum while a normal table is free).

### Deposit / security
- Deposit is a **hold / guarantee**, not an extra charge (deducted from the
  final total at execution) — consistent with the existing deposit engine
  (`BookingDepositService`, `deposits`).
- Computed on the **unified total (table + food)**, per the business's policy
  (`business_service_prices.deposit_*`, gated by `platform_services.supports_deposit`).
- Deposit precedence mirrors price: configured per type in
  `business_service_prices`.

---

## 5. Business-owner panel (`/business`)

Session-based mini panel, separate from AdminV2.

- **Gate:** `BusinessPanelMiddleware` (alias `business.panel`) — authenticated,
  non-suspended, `type=business`. Everything is scoped to `business_id = auth id`.
- **Auth:** `Business\Auth\LoginController` (rejects non-business accounts).
- **Shared scoping:** `Business\Concerns\ResolvesOwnerCatalog` trait —
  `childId` / `servicesForChild` / `allowedTypesByService` / `assertAllowed`.
- **Screens today:** Dashboard · My Units (`bookable-items`) · My Prices
  (`prices`). All scoped, add/edit/delete restricted to the owner's own rows.

---

## 6. Implementation status

| Phase | Scope | Status |
|-------|-------|--------|
| Branches layer | `platform_service_item_groups` + backfill + admin CRUD | ✅ done |
| Owner panel foundation | middleware / auth / routes / layout / dashboard | ✅ done |
| Owner: My Units | scoped bookable-items CRUD | ✅ done |
| Owner: My Prices | scoped business_service_prices CRUD | ✅ done |
| Pricing authority | `ServiceExecutionEngine` always prices (and bases deposit) from `business_service_prices`, even with a unit selected; discounts now apply to unit bookings | ✅ done |
| — drop `bookable_items` price/deposit columns | schema removal (columns now unused by the engine) | ⏳ pending |
| — `BookablePricingService` (per-day price rules / calendar, BIM-5.6) | still bases off `bookable_items.price`; not in the active booking-price path (injected but uncalled by the engine). Needs its own decision on how per-day rules relate to `business_service_prices` before it goes live | ⏳ pending |
| Table charge mode | `charge_mode` + amount config | ⏳ pending |
| Unified invoice | `booking_id` on order lines + `fulfillment_type` + deposit on combined total | ⏳ pending (largest) |

---

## 7. Decisions locked (for the record)

1. Owner experience = a **scoped mini admin panel** (not API-only).
2. **Price is single-source** in `business_service_prices`; premium units =
   distinct types.
3. Restaurant food is billed on the **same invoice** as the table booking.
4. `dine_in` fulfillment **links to a table booking**.
5. Deposit/security applies to the **combined (table + food)** total, per the
   business's policy.
6. Table charge mode is **per-business configurable** (free / reservation fee /
   minimum charge).
