# Business In Map (BIM)

## Master Documentation Index

Version: 2.0 — last reviewed 2026-07-16

---

# 1. Purpose

This file is the main entry point for BIM project documentation.

The documentation is intentionally split into small focused files instead of one very large document. This makes it easier to update, review, and use during development.

---

# 2. Documentation Files

## 01 - Project Foundation

File:

```text
docs/01_PROJECT_FOUNDATION.md
```

Covers:

- Project vision
- Core philosophy
- Main modules
- High-level architecture
- Current project status
- Development priorities

---

## 02 - Database and Business Core

File:

```text
docs/02_DATABASE_AND_BUSINESS_CORE.md
```

Covers:

- Domain model
- Database layers
- Category and child architecture
- Options
- Platform services
- Business service logic
- Booking and wallet data flows

---

## 03 - Booking, Wallet, Deposit, Guarantee and Disputes

File:

```text
docs/03_BOOKING_WALLET_DEPOSIT_GUARANTEE.md
```

Covers:

- Booking lifecycle
- Booking engine responsibilities
- Wallet transactions
- Wallet fee service
- Deposit policy
- Guarantee system
- External deposit verification
- Business counter hold
- Dispute flow

---

## 04 - Admin Architecture and Development Guide

File:

```text
docs/04_ADMIN_ARCHITECTURE_AND_DEVELOPMENT_GUIDE.md
```

Covers:

- Laravel structure
- Admin V2 organization
- Route rules
- UI standards
- Controller/service rules
- Database change policy
- Feature development workflow

---

## 05 - Project Audit and Roadmap

File:

```text
docs/05_PROJECT_AUDIT_AND_ROADMAP.md
```

Covers:

- Current project status
- Immediate priorities
- Technical debt
- Legacy review targets
- Pending decisions
- Roadmap
- Decision log

---

## 06 - Final Engineering Reference (BIM-12.1)

File:

```text
docs/06_ENGINEERING_REFERENCE.md
```

Covers:

- DB map (143 tables by domain)
- Modules map (the six typed platform services)
- Routes / controllers / models / services maps
- Wallet, booking, deposit, order and trip-reservation lifecycles
- Fee resolution layering (base → rules → promotion)
- Pending tasks and the legacy cleanup plan
- Testing conventions

Docs 01–05 explain intent and history; 06 is the map of what actually exists,
generated from the codebase and database rather than from memory.

---

## API reference

```text
docs/api/openapi-v2.yaml
docs/api/README.md
```

The whole `/api/v2` surface (129 paths). `OpenApiSpecCoverageTest` fails if a
route is added without documenting it.

---

# 3. Recommended Reading Order

For a new developer:

```text
01_PROJECT_FOUNDATION.md
06_ENGINEERING_REFERENCE.md
02_DATABASE_AND_BUSINESS_CORE.md
03_BOOKING_WALLET_DEPOSIT_GUARANTEE.md
04_ADMIN_ARCHITECTURE_AND_DEVELOPMENT_GUIDE.md
05_PROJECT_AUDIT_AND_ROADMAP.md
```

Read §0 of `06_ENGINEERING_REFERENCE.md` before running anything: the test suite
runs against the real development database, and the wrong testing trait wipes it.

For current development work:

```text
06_ENGINEERING_REFERENCE.md
05_PROJECT_AUDIT_AND_ROADMAP.md
03_BOOKING_WALLET_DEPOSIT_GUARANTEE.md
```

---

# 4. Current Working Recommendation

The numbered roadmap (BIM-0 … BIM-13) is **complete**, including BIM-3.5 (the
dynamic fee rules engine) and BIM-12.1 (this documentation set). The 5-phase
architecture reorganization and the 7-point v2 gap list are also closed.

There is no named next phase — remaining work is ad-hoc and listed under
"Pending tasks" in `06_ENGINEERING_REFERENCE.md`.

---

# 5. Documentation Maintenance Rule

Every major project decision should update this documentation set.

When code changes affect architecture, update the most relevant document and then update this index if a new document is added.
