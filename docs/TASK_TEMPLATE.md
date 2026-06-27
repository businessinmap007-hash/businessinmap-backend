# BIM Task Specification Template

---

## Task Information

**Task ID:** BIM-X.X

**Title:** ...

**Status:**

- [ ] Planning
- [ ] In Progress
- [ ] Review
- [ ] Completed

**Branch:** `feature/BIM-X.X-short-name`

---

# 1. Business Goal

Explain the business goal of this feature.

Why are we adding it?

What problem does it solve?

---

# 2. Scope

## Included

- ...
- ...

## Not Included

- ...
- ...

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

Add any task-specific docs here:

- ...

---

# 4. Related Modules

- ...
- ...

---

# 5. Allowed Files

Agents may modify only these files/paths:

```text
app/...
resources/...
routes/...
docs/...
```

---

# 6. Forbidden

Agents must not:

- Modify files outside Allowed Files.
- Change wallet logic unless explicitly included.
- Change booking lifecycle unless explicitly included.
- Delete controllers, models, or tables without approval.
- Change database schema without SQL/migration section.
- Reintroduce deprecated architecture.
- Commit directly to `main`.

---

# 7. Database Impact

## Is database change required?

- [ ] No
- [ ] SQL only
- [ ] Migration
- [ ] Data backfill

## Details

```sql
-- Add SQL here if needed
```

---

# 8. Backend Impact

Controllers:

- ...

Models:

- ...

Services:

- ...

Routes:

- ...

Validation:

- ...

---

# 9. UI Impact

Views:

- ...

CSS:

- ...

Admin menu:

- ...

---

# 10. Agent Responsibilities

## Architecture Reviewer

- Confirm architecture compatibility.
- Confirm no forbidden pattern is introduced.

## Backend Agent

- Implement backend scope only.
- Keep business logic in services when complex.

## Database Agent

- Review schema impact.
- Validate relationships and indexes.

## UI/Admin Agent

- Implement views using Admin V2 patterns.
- Keep UI consistent.

## Audit Agent

- Review PR without broad unrelated changes.
- Report bugs, risks, missing tests, and architecture violations.

---

# 11. Testing Checklist

- [ ] Routes load correctly.
- [ ] No undefined variables.
- [ ] No class not found errors.
- [ ] Create works.
- [ ] Edit/update works.
- [ ] Delete/disable works if included.
- [ ] Filters/search work if included.
- [ ] No obvious N+1 issue.
- [ ] Wallet/booking/guarantee logic is not broken.
- [ ] Documentation updated.

---

# 12. Completion Criteria

The task is complete when:

- All included scope is implemented.
- Manual checks pass.
- Documentation is updated.
- PR includes clear summary.
- Review comments are resolved.

---

# 13. PR Summary Template

```md
## Task
BIM-X.X - Title

## Summary
- ...

## Changed Files
- ...

## Not Changed
- ...

## Manual Testing
- [ ] ...

## Risks
- ...

## Rollback
- Revert this PR.
```
