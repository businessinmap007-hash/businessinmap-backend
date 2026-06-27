# Business In Map (BIM)

## 04 - Admin V2 and Development Rules

Version: 1.0 (Draft)

---

# 1. Purpose

This document explains the Admin V2 structure and the development rules that should be followed when extending BIM.

Admin V2 is the operational control center of the project. It manages users, categories, services, bookings, wallet operations, fees, disputes, content, and future system settings.

---

# 2. Admin V2 Goals

Admin V2 should be:

- Clean
- Modular
- RTL friendly
- Consistent across pages
- Easy to extend
- Safe for financial operations
- Usable on desktop and mobile screens

The UI should not become a collection of unrelated pages. It should follow one visual and structural system.

---

# 3. Main Admin Groups

Recommended sidebar groups:

1. Dashboard
2. Users
3. Categories
4. Platform Services
5. Business Services
6. Bookings
7. Wallet and Finance
8. Disputes
9. Content
10. Settings

---

# 4. Route Organization

Main route file:

```text
routes/admin_v2.php
```

Rules:

- Keep all Admin V2 routes under `admin.` route name prefix.
- Static routes must come before dynamic routes.
- Use `whereNumber()` for numeric route parameters.
- Remove test routes from production route groups.
- Group routes by module.
- Avoid duplicate route names.

Current cleanup priority:

- Remove or isolate `booking-test` routes.
- Remove old experimental category option routes.
- Ensure dynamic routes do not override fixed endpoints.

---

# 5. Controller Rules

Controllers should:

- Validate requests.
- Call services for complex business logic.
- Prepare data for views.
- Avoid direct financial calculations.
- Avoid wallet mutations unless delegated to wallet services.

Controllers should not contain:

- Complex fee calculation.
- Deposit/guarantee logic.
- Wallet balance math.
- Large repeated query blocks when they can be moved to services or support classes.

---

# 6. Service Layer Rules

Complex logic belongs in `app/Services` or dedicated domain/support classes.

Examples:

- `BookingEngine`
- `WalletFeeService`
- Future `GuaranteePolicyService`
- Future `ServiceFeeRuleEngine`
- Future `BookableAvailabilityService`
- Future `BookablePricingService`

Rules:

- Services should be reusable outside Admin V2.
- Services should receive clear inputs and return clear outputs.
- Financial services must be idempotent where needed.
- Services should snapshot important metadata for audit.

---

# 7. Models and Relationships

Models should define relationships clearly.

Important relationships:

- User -> category
- User -> categoryChild
- User -> options
- User -> platformServices
- User -> wallet
- Category -> children
- CategoryChild -> parents
- CategoryChild -> options
- CategoryChild -> services
- PlatformService -> itemTypes
- Booking -> user/client
- Booking -> business
- Booking -> service
- Booking -> bookableItem

---

# 8. Admin CSS System

Main CSS file:

```text
public/admin-v2/css/admin.css
```

The UI uses `a2-*` class naming.

Core components:

- `a2-page`
- `a2-page-head`
- `a2-card`
- `a2-table`
- `a2-filterbar`
- `a2-btn`
- `a2-alert`
- `a2-pill`
- `a2-stat-grid`
- `a2-form-grid`

Rules:

- Reuse existing components before adding new CSS.
- Avoid page-specific CSS unless necessary.
- Keep RTL support.
- Make tables responsive using wrappers.
- Use the album pages as a UI quality baseline.

---

# 9. Forms Standard

Admin forms should use:

- Clear labels
- Validation errors
- Helpful hints
- Consistent grid layout
- Consistent buttons
- Clear cancel/back actions
- Searchable selects where data is large

For dynamic dependent selects:

```text
Root category -> Category child -> Options -> Services
```

The UI must avoid duplicate select rendering when using Tom Select or similar libraries.

---

# 10. Tables Standard

Tables should include:

- Search/filter bar
- Pagination
- Actions column
- Status pills
- Empty state
- Truncated long names/emails
- Consistent image components where needed

---

# 11. SQL and Migration Policy

Project preference:

- Use SQL-only changes when explicitly requested.
- Avoid creating migrations during exploratory restructuring if the user requests SQL first.
- Document every schema change.
- Do not delete legacy data without clear backup or confirmation.

---

# 12. Git Workflow

Recommended workflow:

```text
git status
git add .
git commit -m "clear message"
git push origin main
```

For local machines pulling GitHub changes:

```text
git pull origin main
```

Rules:

- Commit small logical changes.
- Avoid mixing documentation, UI, and financial logic in one commit.
- Keep commit messages clear.

---

# 13. Security Rules

Financial actions must be protected.

Rules:

- Validate all user inputs.
- Do not allow wallet deduction without explicit service logic.
- Use transactions for financial operations.
- Use idempotency keys for repeatable operations.
- Prevent self-delete for current admin.
- Prevent unsafe deletion of admin users.
- Keep payment callbacks protected in later phases.

---

# 14. Performance Rules

- Add indexes to foreign keys.
- Add indexes to status/search columns where needed.
- Avoid loading large catalogs repeatedly if the UI does not need them.
- Consider caching lookup data later.
- Avoid N+1 queries in index pages.

---

# 15. Documentation Rules

Every major module should have:

- Purpose
- Main files
- Main tables
- Business rules
- Current status
- Pending work

Documentation files should be updated after major architecture changes.

---

# 16. Current Admin V2 Priority

1. Clean `routes/admin_v2.php`.
2. Rebuild/organize sidebar groups.
3. Stabilize category services bulk screen.
4. Stabilize service fee screens.
5. Improve booking and wallet pages.
6. Add clear guarantee admin screens.
7. Standardize filters and forms across all modules.
