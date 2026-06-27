# BIM-2.6 Task Specification

---

## Task Information

**Task ID:** BIM-2.6

**Title:** Stabilize Category Services, Service Fees, Booking Readiness, and Guarantee Integration Boundary

**Status:**

- [x] Planning
- [ ] In Progress
- [ ] Review
- [ ] Completed

**Recommended Branch:** `feature/BIM-2.6-services-fees-guarantee-boundary`

---

# 1. Business Goal

Stabilize the service architecture around Category Child so BIM can safely continue toward booking, wallet, deposit, and guarantee finalization.

This task prepares the project for cleanup by ensuring the current architecture is frozen enough to avoid cleaning code that will be redesigned immediately afterward.

---

# 2. Scope

## Included

- Review and stabilize Category Services Bulk behavior.
- Confirm service config persistence by root + child + platform service.
- Confirm category child service fee persistence.
- Confirm business/client fee separation.
- Confirm booking and wallet fee boundaries.
- Confirm guarantee/deposit integration points without fully implementing future policy engine unless explicitly requested.
- Identify legacy code that should be cleaned after BIM-2.6.

## Not Included

- Full API implementation.
- Mobile app implementation.
- Dynamic Fee Rules Engine.
- Full staff/business employee accounts.
- Broad UI redesign outside affected Admin V2 pages.
- Deleting legacy files without audit.

---

# 3. Source of Truth

Agents must read these files first:

- `docs/BIM_MASTER_DOCUMENTATION.md`
- `docs/AGENTS_ORCHESTRATOR.md`
- `docs/01_PROJECT_FOUNDATION.md`
- `docs/02_DATABASE_AND_BUSINESS_CORE.md`
- `docs/03_BOOKING_WALLET_DEPOSIT_GUARANTEE.md`
- `docs/04_ADMIN_ARCHITECTURE_AND_DEVELOPMENT_GUIDE.md`
- `docs/05_PROJECT_AUDIT_AND_ROADMAP.md`

---

# 4. Related Modules

- Categories
- Category Children
- Platform Services
- Category Services Bulk
- Category Service Configs
- Category Child Service Fees
- Business Service Prices
- Bookable Items
- Booking Engine
- Wallet Fee Service
- Guarantee / Deposit boundary

---

# 5. Allowed Files

Agents may review and modify only these files unless explicitly approved:

```text
routes/admin_v2.php
app/Http/Controllers/AdminV2/CategoryServiceBulkController.php
app/Http/Controllers/AdminV2/CategoryChildServiceFeeController.php
app/Http/Controllers/AdminV2/CategoryChildServiceFeeBulkController.php
app/Http/Controllers/AdminV2/BookableItemController.php
app/Services/BookingEngine.php
app/Services/WalletFeeService.php
app/Models/CategoryPlatformService.php
app/Models/CategoryServiceConfig.php
app/Models/CategoryChildServiceFee.php
app/Models/PlatformService.php
app/Models/BusinessServicePrice.php
resources/views/admin-v2/categories/services-bulk.blade.php
resources/views/admin-v2/category-child-service-fees/
resources/views/admin-v2/bookable-items/
docs/
```

---

# 6. Forbidden

Agents must not:

- Change wallet balance mutation logic unless explicitly required and reviewed.
- Rewrite BookingController broadly.
- Delete legacy controllers or views without producing an audit list first.
- Change table names.
- Introduce direct Category -> Options final architecture.
- Treat external deposit as BIM wallet income.
- Mix booking price, service fee, wallet guarantee hold, and external deposit.
- Commit directly to `main`.

---

# 7. Database Impact

## Is database change required?

- [x] No by default
- [ ] SQL only
- [ ] Migration
- [ ] Data backfill

If an agent believes a schema change is required, it must stop and produce a proposal first.

---

# 8. Backend Impact

Controllers to review:

- `CategoryServiceBulkController`
- `CategoryChildServiceFeeController`
- `CategoryChildServiceFeeBulkController`
- `BookableItemController`

Services to review:

- `BookingEngine`
- `WalletFeeService`

Models to review:

- `CategoryPlatformService`
- `CategoryServiceConfig`
- `CategoryChildServiceFee`
- `BusinessServicePrice`

Routes to review:

- `routes/admin_v2.php`

---

# 9. UI Impact

Views to review:

- `resources/views/admin-v2/categories/services-bulk.blade.php`
- `resources/views/admin-v2/category-child-service-fees/`
- `resources/views/admin-v2/bookable-items/`

UI must use Admin V2 classes and remain RTL-friendly.

---

# 10. Agent Responsibilities

## Architecture Reviewer

- Confirm child-service-fee architecture is respected.
- Confirm guarantee/deposit separation is respected.
- Confirm no legacy architecture is reintroduced.

## Backend Agent

- Fix bugs within allowed files.
- Improve validation and safe defaults.
- Keep logic scoped and readable.

## Database Agent

- Confirm current tables support the task.
- Suggest indexes only if clearly needed.
- Do not apply schema change without approval.

## UI/Admin Agent

- Fix undefined variables in views.
- Improve form consistency only inside allowed views.

## Audit Agent

- Review PR or branch.
- Report risky changes.
- Confirm no forbidden files changed.

---

# 11. Testing Checklist

- [ ] `routes/admin_v2.php` has no obvious ordering conflict.
- [ ] Category Services Bulk page loads.
- [ ] Append services works.
- [ ] Replace services works.
- [ ] Remove services works.
- [ ] Service config saves correctly.
- [ ] Business/client service fee values save correctly.
- [ ] Inactive or zero fees do not become chargeable.
- [ ] Bookable item type validation still works.
- [ ] BookingEngine still resolves price and fee snapshot.
- [ ] WalletFeeService remains idempotent.
- [ ] No undefined variables.
- [ ] No class not found errors.
- [ ] Documentation updated if architecture changes.

---

# 12. Completion Criteria

BIM-2.6 is complete when:

- Service bulk behavior is stable.
- Service fees are stable enough for booking integration.
- No forbidden architecture change is introduced.
- Known legacy cleanup list is produced or updated.
- PR summary explains all changed files.

---

# 13. Recommended Agent Prompt

```text
Read docs/BIM_MASTER_DOCUMENTATION.md,
docs/AGENTS_ORCHESTRATOR.md,
and docs/tasks/BIM-2.6.md.

Act as: Audit Agent first.
Do not modify code yet.
Review the allowed files for bugs, architecture violations, undefined variables, route conflicts, and risky logic.
Return a prioritized report.
```

After review:

```text
Read docs/BIM_MASTER_DOCUMENTATION.md,
docs/AGENTS_ORCHESTRATOR.md,
and docs/tasks/BIM-2.6.md.

Act as: Backend Agent.
Implement only the approved fixes from the audit report.
Do not modify files outside Allowed Files.
Create a PR and explain every changed file.
```
