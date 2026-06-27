# Business In Map (BIM)

## 05 - Project Audit and Roadmap

Version: 1.0 (Draft)

---

# 1. Purpose

This document summarizes the current audit state of BIM and defines the recommended roadmap for the next development phases.

It is intended to answer three questions:

1. What is already stable?
2. What needs review or refactor?
3. What should be built next?

---

# 2. Stable or Advanced Areas

## 2.1 Categories Architecture

Status: Advanced / mostly stable.

Completed decisions:

- Root categories remain in `categories` with `parent_id = 0`.
- Children moved to `category_children_master`.
- Parent-child relation handled by `category_parent_child`.
- Category children can be reused across root categories.
- Options are attached to category children, not root categories.

Needs:

- Final UI polishing.
- Legacy cleanup.
- Sidebar grouping.

---

## 2.2 Options and Option Groups

Status: Advanced.

Completed:

- Options CRUD.
- Option Groups CRUD.
- Child option assignment.
- Group-based and individual option selection.

Needs:

- Final duplication cleanup.
- Usage statistics later.

---

## 2.3 Users Module

Status: Advanced.

Completed:

- Type filter.
- Category and child assignment.
- Options assignment.
- Service assignment for businesses.
- Service filter based on active business services.

Needs:

- Better business profile service setup screen.
- Staff/editor accounts in a later phase.

---

## 2.4 Admin V2 CSS

Status: Strong base.

Completed:

- Unified `a2-*` system.
- Cards.
- Tables.
- Forms.
- Filter bars.
- Buttons.
- Pills.
- Responsive sidebar styles.

Needs:

- Remove duplicated CSS blocks over time.
- Continue standardizing old pages.

---

# 3. Areas Requiring Immediate Attention

## 3.1 routes/admin_v2.php

Priority: Very high.

Tasks:

- Remove or isolate test routes.
- Ensure route order is safe.
- Group modules clearly.
- Clean unused imports.
- Rebuild sidebar to match route groups.

---

## 3.2 Category Services Bulk

Priority: Very high.

Tasks:

- Verify append/replace/remove.
- Verify category_id + child_id + platform_service_id keys.
- Verify service config is written correctly.
- Verify service fees are saved and disabled correctly.
- Verify no old root-only service logic remains.

---

## 3.3 Service Fees

Priority: Very high.

Tasks:

- Stabilize child/service fee edit page.
- Stabilize bulk fee edit page.
- Validate fixed/percent behavior.
- Validate business/client fee enable flags.
- Ensure inactive fees do not charge.

---

## 3.4 Booking Engine

Priority: High.

Tasks:

- Finalize integration with category child services.
- Finalize pricing snapshots.
- Finalize deposit/guarantee policy.
- Confirm service fee snapshots.
- Confirm idempotent fee application.

---

## 3.5 Guarantee System

Priority: High.

Tasks:

- Define final DB fields/tables if not complete.
- Implement policy resolver.
- Implement client wallet hold.
- Implement business counter hold.
- Implement external deposit verification.
- Implement release/refund/split.
- Add admin UI.
- Add dispute reminders.

---

# 4. Deferred Areas

These should not block the current stabilization work:

- Dynamic fee rules engine.
- API V1 expansion.
- Mobile application integration.
- Advanced reports and statistics.
- AI/search recommendations.
- Staff/editor accounts for business users.
- Subscription packages refinement.
- Public-facing UI enhancements.

---

# 5. Technical Debt

Known technical debt:

- Legacy service/profile names may still exist in code or comments.
- Some Admin V2 CSS sections are duplicated.
- Some routes are still test-oriented.
- Some service/deposit decisions are implemented partially.
- Business rules are split between controllers and services in some areas.
- Some screens load full catalogs and may need optimization later.

---

# 6. Cleanup Candidates

Review before deleting:

- Old `CategoryOptionController` references.
- Old category option views.
- Old `CategoryBookingProfile` references.
- Old service fee controllers or models if replaced.
- Booking test routes/controllers.
- Any backup tables or temporary SQL structures.

Rule:

Do not delete before search + route check + backup confirmation.

---

# 7. Recommended Roadmap

## Phase A - Stabilization

1. Clean routes.
2. Rebuild sidebar groups.
3. Stabilize category services bulk.
4. Stabilize category child service fees.
5. Stabilize wallet fee charging.

## Phase B - Booking and Guarantee

1. Final guarantee policy.
2. Client hold.
3. Business counter hold.
4. External deposit verification.
5. Booking lifecycle integration.
6. Dispute automation.

## Phase C - Business Services

1. Business active services screen.
2. Business service prices validation.
3. Bookable items improvements.
4. Availability and price rules finalization.

## Phase D - Admin UX

1. Full sidebar rebuild.
2. Forms standardization.
3. Tables standardization.
4. Finance pages polish.
5. Booking show page improvements.

## Phase E - API and Future Growth

1. Public API endpoints.
2. Client app support.
3. Business app support.
4. Notifications.
5. Reports and analytics.

---

# 8. Current Recommended Next Work

Start with:

```text
BIM-0.1 - Clean routes/admin_v2.php
BIM-2.3 - Stabilize Category Services Bulk
BIM-3.2 - Stabilize Category Child Service Fees
BIM-3.3 - Bulk Service Fees
BIM-6.4 - Wallet Fee Service validation
BIM-6.5 - Deposits and Guarantees
```

This order protects the project from building new features on unstable service/fee foundations.

---

# 9. Documentation Roadmap

Current documentation files:

- `docs/01_PROJECT_FOUNDATION.md`
- `docs/02_DATABASE_AND_BUSINESS_CORE.md`
- `docs/03_BOOKING_WALLET_DEPOSIT_GUARANTEE.md`
- `docs/04_ADMIN_V2_AND_DEVELOPMENT_RULES.md`
- `docs/05_PROJECT_AUDIT_AND_ROADMAP.md`

Future optional docs:

- `06_CONTROLLERS_AND_ROUTES_MAP.md`
- `07_MODELS_AND_RELATIONSHIPS_MAP.md`
- `08_SQL_SCHEMA_NOTES.md`
- `09_API_PLAN.md`
- `10_DECISIONS_LOG.md`

---

# 10. Final Note

The project is not in a starting state. It already has a strong architecture base. The current work should focus on stabilization, cleanup, and finishing the financial/booking core before adding large new features.
