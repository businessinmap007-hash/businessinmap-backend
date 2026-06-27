# BIM Agents Orchestrator

Version: 1.0 (Draft)

---

# 1. Purpose

This document defines how AI coding agents, Codex, or GitHub-based agents should work on the Business In Map (BIM) Laravel project.

The goal is to allow agents to help with review, refactor, and implementation while preventing uncontrolled changes that break the architecture.

---

# 2. Source of Truth

Before starting any task, every agent must read:

```text
docs/BIM_MASTER_DOCUMENTATION.md
docs/01_PROJECT_FOUNDATION.md
docs/02_DATABASE_AND_BUSINESS_CORE.md
docs/03_BOOKING_WALLET_DEPOSIT_GUARANTEE.md
docs/04_ADMIN_ARCHITECTURE_AND_DEVELOPMENT_GUIDE.md
docs/05_PROJECT_AUDIT_AND_ROADMAP.md
```

For a specific task, the agent must also read the matching file under:

```text
docs/tasks/
```

---

# 3. Main Rule

Agents must never work on the whole project without a task file.

Every task must define:

- Goal
- Scope
- Allowed files
- Forbidden files/actions
- Database impact
- Testing checklist
- Completion criteria

---

# 4. Agent Roles

## 4.1 Architecture Reviewer

Responsibilities:

- Check compatibility with BIM architecture.
- Prevent reintroducing legacy patterns.
- Ensure Category Child remains the operational center.
- Ensure wallet, booking, deposit, guarantee, and service-fee logic remain separated.

Must not:

- Implement large code changes.
- Change database schema.

---

## 4.2 Backend Agent

Responsibilities:

- Implement controllers, services, models, requests, validation, and route changes inside the allowed scope.
- Keep business logic inside services when complex.
- Avoid duplicating existing logic.

Must not:

- Modify UI unless explicitly allowed.
- Modify wallet or booking core outside the task.

---

## 4.3 Database Agent

Responsibilities:

- Review SQL/migrations.
- Check indexes and relationships.
- Confirm data integrity.
- Detect dangerous schema changes.

Must not:

- Delete tables or columns without explicit task permission.
- Rename tables without migration/SQL plan and approval.

---

## 4.4 UI/Admin Agent

Responsibilities:

- Implement Blade views.
- Use Admin V2 CSS classes.
- Keep RTL and responsive layout.
- Avoid duplicated UI patterns.

Must not:

- Change backend business rules unless explicitly allowed.

---

## 4.5 Audit Agent

Responsibilities:

- Review PRs and patches.
- Detect bugs, undefined variables, route errors, N+1 issues, duplicated logic, and architecture violations.
- Prefer review comments over direct code modifications.

Must not:

- Make broad refactors without a dedicated task.

---

# 5. Required Working Flow

For every feature:

```text
1. Create or update docs/tasks/BIM-X.X.md
2. Create a dedicated branch
3. Agent reads documentation and task file
4. Agent changes only allowed files
5. Agent creates PR
6. Audit Agent reviews PR
7. Human/ChatGPT reviews architecture and business logic
8. Merge only after approval
```

---

# 6. Branch Rules

Agents should never commit directly to `main` unless explicitly requested.

Recommended branch format:

```text
feature/BIM-X.X-short-name
fix/BIM-X.X-short-name
audit/BIM-X.X-short-name
```

---

# 7. Forbidden Global Actions

Agents must not:

- Rewrite the whole project.
- Delete legacy files without audit.
- Change wallet balance logic casually.
- Change booking lifecycle without explicit task scope.
- Mix deposit, guarantee, booking price, and service fee concepts.
- Reintroduce direct Category -> Options architecture.
- Add new dependencies without justification.
- Change `.env` or secrets.

---

# 8. Pull Request Requirements

Every PR must include:

- Task ID
- Summary
- Changed files
- What was intentionally not changed
- Manual test checklist
- Risks
- Rollback notes

---

# 9. Prompt Pattern

Use this prompt style with agents:

```text
Read docs/BIM_MASTER_DOCUMENTATION.md,
docs/AGENTS_ORCHESTRATOR.md,
and docs/tasks/BIM-X.X.md.

Act as: Backend Agent.

Implement only the Backend Agent scope.
Do not modify files outside Allowed Files.
Create a PR and explain every changed file.
```

For review-only work:

```text
Read docs/BIM_MASTER_DOCUMENTATION.md,
docs/AGENTS_ORCHESTRATOR.md,
and docs/tasks/BIM-X.X.md.

Act as: Audit Agent.
Do not modify code.
Review the current branch/PR for bugs, architecture violations, missing tests, and risky changes.
```

---

# 10. BIM-Specific Architecture Guards

Agents must respect these rules:

- Root categories are stored in `categories` with `parent_id = 0`.
- Child categories are stored in `category_children_master`.
- Children are linked to roots through `category_parent_child`.
- Options belong to children through `category_child_option`.
- Platform services are enabled per child/root pair.
- Service fees come from `category_child_service_fees`.
- Wallet fee charges go through `WalletFeeService`.
- Booking preparation goes through booking services/engine.
- Guarantee is a core concept and is not the same as booking price.
