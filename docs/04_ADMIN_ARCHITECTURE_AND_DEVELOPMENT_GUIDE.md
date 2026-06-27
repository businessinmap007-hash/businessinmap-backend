# Business In Map (BIM)

## 04 - Admin Architecture and Development Guide

Version: 1.0 (Draft)

---

# 1. Purpose

This document is the development guide for BIM Admin V2 and backend work. It explains how the project should be extended without breaking the existing architecture.

It is intended for future developers and for maintaining consistency across modules.

---

# 2. Laravel Project Structure

Main backend areas:

```text
app/
  Http/Controllers/AdminV2/
  Models/
  Services/
  Support/
  Providers/

resources/views/admin-v2/

routes/
  admin_v2.php

public/admin-v2/css/
```

Controllers should handle request validation, simple orchestration, and view responses.

Business logic should live in services, support classes, or dedicated action classes when complexity grows.

---

# 3. Admin V2 Role

Admin V2 is the operational control panel for BIM.

It manages:

- Users
- Categories
- Category children
- Options
- Option groups
- Platform services
- Category service bulk setup
- Service fees
- Business service prices
- Bookable items
- Bookable calendars
- Bookings
- Wallet transactions
- Wallet operations
- Guarantees
- Payments
- Disputes
- Content modules

Admin V2 should remain clean, consistent, and modular.

---

# 4. Route Organization

Main route file:

```text
routes/admin_v2.php
```

Rules:

- Keep routes grouped by module.
- Static routes must come before dynamic `{id}` routes.
- Avoid test routes in production route files.
- Use consistent route names under `admin.*`.
- Avoid duplicate paths or duplicated feature entry points.

Current cleanup priority:

- Remove or isolate `booking-test` routes when no longer needed.
- Keep experimental routes outside production groups.

---

# 5. Admin UI Standards

Main CSS:

```text
public/admin-v2/css/admin.css
```

Core UI classes:

- `a2-page`
- `a2-page-head`
- `a2-card`
- `a2-table`
- `a2-filterbar`
- `a2-btn`
- `a2-pill`
- `a2-alert`
- `a2-form-grid`

Rules:

- Reuse Admin V2 components/classes.
- Keep filters consistent with the albums/users style.
- Avoid page-specific CSS unless necessary.
- Support RTL layout.
- Keep tables responsive.

---

# 6. Controller Development Rules

Controllers should:

- Validate requests.
- Load required models.
- Call services for business logic.
- Return views or redirects.
- Avoid complex financial calculations.
- Avoid duplicated query logic when it can be extracted.

Controllers should not directly own:

- Wallet fee calculations.
- Guarantee resolution logic.
- Complex booking lifecycle rules.
- Reusable pricing logic.

---

# 7. Service Layer Rules

Services are required for complex logic such as:

- Booking preparation
- Pricing
- Wallet fees
- Deposit/guarantee holds
- Dispute resolution
- Availability checks
- Calendar pricing

Important services:

- `BookingEngine`
- `WalletFeeService`
- `BookableAvailabilityService`
- `BookablePricingService`

Planned services:

- `GuaranteePolicyService`
- `BookingDepositService`
- `ServiceFeeRuleEngine`
- `ResolveServiceFeesAction`

---

# 8. Database Change Policy

The project has used both SQL-first and Laravel migration approaches depending on the phase.

Current rule:

- Use SQL directly when the user explicitly requests SQL-only.
- Use migrations when creating stable long-term structure in a controlled development phase.
- Always document schema decisions.
- Never remove legacy tables without confirming that no routes, models, or views depend on them.

---

# 9. Feature Development Workflow

For any new feature:

1. Define the business goal.
2. Define the data model.
3. Check affected modules.
4. Design the flow before writing code.
5. Add or update models.
6. Add services if logic is complex.
7. Add controller actions.
8. Add views.
9. Update routes.
10. Test manually through Admin V2.
11. Update documentation.

---

# 10. Safety Rules

Do not:

- Mix booking price with guarantee hold.
- Calculate service fees outside the approved fee service.
- Reintroduce direct Category -> Options logic.
- Add child categories as rows inside `categories`.
- Break existing booking lifecycle when adding deposit or guarantee features.
- Delete legacy files without search/audit.

---

# 11. Naming Conventions

Preferred concepts:

- Root category: `Category`
- Child category: `CategoryChild`
- Service: `PlatformService`
- Child service link: `CategoryPlatformService`
- Service config: `CategoryServiceConfig`
- Service fee: `CategoryChildServiceFee`
- Business service price: `BusinessServicePrice`
- Bookable resource: `BookableItem`

Avoid ambiguous names such as generic `profile`, `fee`, or `config` without context.

---

# 12. Admin Module Map

| Module | Main responsibility |
|---|---|
| Users | Manage clients, businesses, admins, categories, options, services |
| Categories | Manage root categories and child relations |
| Options | Manage option groups and options dictionary |
| Platform Services | Manage global service types |
| Service Fees | Manage fees by child/service |
| Business Service Prices | Manage business-level pricing |
| Bookable Items | Manage resources that can be booked |
| Bookings | Manage booking lifecycle |
| Wallet | View and operate financial wallet records |
| Guarantees | Manage guarantee records and review state |
| Disputes | Resolve conflicts |
| Content | Posts, jobs, sponsors, albums |

---

# 13. Git Workflow Notes

Common local workflow:

```bash
git status
git add .
git commit -m "message"
git push origin main
```

When pulling GitHub changes to local laptop:

```bash
git pull origin main
```

Developers should avoid committing generated or temporary files unless intentionally part of the project.

---

# 14. Documentation Rule

Every major architectural change should update at least one file in `docs/`.

The documentation set should remain concise, accurate, and aligned with code.
