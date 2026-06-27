# Business In Map (BIM)

## 05 - Project Audit and Roadmap

Version: 1.0 (Draft)

---

# 1. Purpose

This document summarizes the current project state, known technical debt, legacy areas, immediate priorities, and future roadmap.

It should be reviewed before starting any new development phase.

---

# 2. Current High-Level Status

| Area | Status |
|---|---|
| Core Laravel structure | Stable |
| Admin V2 UI | Advanced, needs final menu cleanup |
| Categories | Stable |
| Category Children | Stable |
| Options / Option Groups | Advanced |
| Category Child Options | Stable/advanced |
| Platform Services | Advanced, needs final cleanup |
| Category Service Bulk | High priority, needs testing and stabilization |
| Service Fees | Advanced, needs final testing |
| Business Service Prices | Needs review after service-fee stabilization |
| Bookable Items | Advanced |
| Booking Engine | Advanced, needs guarantee/deposit final integration |
| Wallet | Advanced |
| Wallet Fee Service | Advanced |
| Deposit / Guarantee | Designed, needs final implementation pass |
| Disputes | Advanced, needs lifecycle polish |
| API | Later phase |
| Mobile clients | Later phase |

---

# 3. Immediate Priorities

## Priority 1 - Route and Legacy Cleanup

Files:

- `routes/admin_v2.php`
- Admin menu views

Tasks:

- Remove or isolate test routes.
- Confirm route order.
- Remove duplicated entries.
- Keep dynamic routes after static routes.

## Priority 2 - Category Services Bulk

Files:

- `CategoryServiceBulkController`
- `CategoryPlatformService`
- `CategoryServiceConfig`
- `CategoryChildServiceFee`
- `categories/services-bulk.blade.php`

Tasks:

- Verify append/replace/remove.
- Verify config saving.
- Verify fee saving.
- Verify no undefined variables.
- Verify root + child + service uniqueness.

## Priority 3 - Service Fees

Files:

- `CategoryChildServiceFeeController`
- `CategoryChildServiceFeeBulkController`
- `CategoryChildServiceFee`
- fee views

Tasks:

- Test fixed/percent business fees.
- Test fixed/percent client fees.
- Test inactive fee behavior.
- Test fee matrix UI.

## Priority 4 - Booking + Wallet Fee Integration

Files:

- `BookingEngine`
- `BookingController`
- `WalletFeeService`
- `Booking` model

Tasks:

- Verify booking pricing snapshot.
- Verify fee snapshot.
- Verify wallet idempotency.
- Verify no double charge.

## Priority 5 - Guarantee System

Files:

- `GuaranteeAdminController`
- Guarantee model(s)
- Booking deposit/guarantee services
- Booking show/admin actions

Tasks:

- Finalize policy engine.
- Add client hold logic.
- Add business counter hold logic.
- Add external deposit confirmation.
- Add dispute window and reminders.

---

# 4. Known Technical Debt

- Some routes are still experimental.
- Some legacy naming remains around booking profiles/configs.
- Some modules are advanced but need end-to-end testing.
- Deposit and guarantee concepts need final code alignment.
- Admin sidebar grouping should be rebuilt cleanly.
- Documentation was missing before this phase.

---

# 5. Legacy Areas to Review

Search for:

```text
CategoryBookingProfile
activeBookingProfile
bookingProfiles
CategoryOptionController
ServiceFeeController
booking-test
```

Each match should be classified as:

- Delete
- Keep Legacy
- Refactor Later
- Already replaced

No file should be deleted before confirming routes, views, model references, and database dependencies.

---

# 6. Pending Decisions

## 6.1 API Design

The public/mobile API is not the current priority. It should come after Admin V2, booking, wallet, and guarantee flows are stable.

## 6.2 Business Staff Accounts

Future feature: allow businesses to create staff/editor accounts to manage bookings and operations.

## 6.3 Dynamic Fee Rules Engine

Future feature: calculate fees by rules such as city, booking value, peak time, subscription, or business performance.

## 6.4 Multi-child Business Support

Current assumption: business has one main category child. Future versions may allow a business to operate in multiple children.

---

# 7. Roadmap

## Phase A - Stabilization

- Clean routes.
- Stabilize Admin V2 sidebar.
- Stabilize category services bulk.
- Stabilize service fees.
- Test booking + wallet fee integration.

## Phase B - Guarantee Finalization

- Implement guarantee policy service.
- Implement client wallet hold.
- Implement business counter hold.
- Implement external deposit verification.
- Add admin actions and dispute integration.

## Phase C - Business Services

- Review business service prices.
- Ensure service activation depends on user platform service.
- Validate bookable items against service types.
- Improve business setup UX.

## Phase D - Booking Production Readiness

- Complete booking lifecycle tests.
- Complete calendar/availability checks.
- Complete dispute actions.
- Add audit logs where needed.

## Phase E - API and Mobile Readiness

- Build API endpoints.
- Add authentication flow.
- Add search API.
- Add booking API.
- Add wallet/guarantee state API.

---

# 8. Decision Log

Important decisions already made:

- Category children are no longer rows inside `categories`.
- Root categories are stored in `categories` with `parent_id = 0`.
- Category child data lives in `category_children_master`.
- Options belong to category children through `category_child_option`.
- Direct Category -> Options management is removed from final architecture.
- Platform services are configured per category child.
- Service fees are stored per child/service.
- Wallet fee deductions are handled by `WalletFeeService`.
- Booking pricing should preserve snapshots.
- Guarantee is a core system, not a simple deposit payment.
- External deposit should be verified, not treated as BIM wallet income.

---

# 9. Recommended Next Work Item

Start with:

```text
BIM-0.1 + BIM-2.3
```

Meaning:

1. Clean `routes/admin_v2.php`.
2. Stabilize `CategoryServiceBulkController` and its view.
3. Confirm service config and fee persistence.
4. Then move to service fees and guarantee finalization.
