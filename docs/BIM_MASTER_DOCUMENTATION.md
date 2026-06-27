# Business In Map (BIM)

## Master Documentation Index

Version: 1.0 (Draft)

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

# 3. Recommended Reading Order

For a new developer:

```text
01_PROJECT_FOUNDATION.md
02_DATABASE_AND_BUSINESS_CORE.md
03_BOOKING_WALLET_DEPOSIT_GUARANTEE.md
04_ADMIN_ARCHITECTURE_AND_DEVELOPMENT_GUIDE.md
05_PROJECT_AUDIT_AND_ROADMAP.md
```

For current development work:

```text
05_PROJECT_AUDIT_AND_ROADMAP.md
02_DATABASE_AND_BUSINESS_CORE.md
03_BOOKING_WALLET_DEPOSIT_GUARANTEE.md
```

---

# 4. Current Working Recommendation

The next recommended implementation sequence is:

```text
BIM-0.1
BIM-2.3
BIM-3.2
BIM-3.3
BIM Guarantee Finalization
```

Meaning:

1. Clean Admin V2 routes.
2. Stabilize category services bulk.
3. Stabilize category child service fees.
4. Stabilize bulk service fee editing.
5. Finalize guarantee/deposit implementation.

---

# 5. Documentation Maintenance Rule

Every major project decision should update this documentation set.

When code changes affect architecture, update the most relevant document and then update this index if a new document is added.
