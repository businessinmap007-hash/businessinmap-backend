# Business In Map (BIM)

## 03 - Booking, Wallet, Deposit, Guarantee and Disputes

Version: 1.0 (Draft)

---

# 1. Purpose

This document explains the financial and booking core of BIM. It covers the booking lifecycle, wallet transactions, service fees, deposit policy, guarantee architecture, and dispute handling.

This is one of the most important documents in the project because it explains how BIM protects both clients and businesses while keeping booking and financial logic scalable.

---

# 2. Main Concepts

BIM separates the following concepts clearly:

- Booking price
- Service execution fee
- Client wallet guarantee hold
- External deposit verification
- Business counter hold
- Wallet transaction
- Deposit dispute
- Guarantee release/refund/split

This separation is critical. A guarantee is not the same as a payment. A deposit is not always a BIM wallet transaction. A service fee is not the same as the booking price.

---

# 3. Booking Lifecycle

The booking lifecycle should follow this high-level path:

```text
Draft / Pending
  -> Client confirmation
  -> Business confirmation
  -> Guarantee / deposit requirements checked
  -> In progress
  -> Completed
```

Alternative paths:

```text
Pending
  -> Cancelled
```

```text
In progress / Deposit issue
  -> Dispute opened
  -> Under review
  -> Resolved
```

---

# 4. Booking Engine Responsibilities

The booking engine should prepare and snapshot:

- Business context
- Category child context
- Platform service
- Business service price
- Base price
- Discount if available
- Final price
- Fee snapshot
- Deposit/guarantee snapshot
- Bookable item if used

The booking engine should not directly mutate wallets unless the operation is explicitly a financial action.

---

# 5. Pricing Snapshot

Every booking should preserve pricing metadata so historical records remain correct even if prices change later.

Expected metadata includes:

- Original price
- Final price
- Quantity
- Unit price
- Discount state
- Service fee snapshot
- Business context
- Category child context

---

# 6. Wallet System

The wallet system is responsible for:

- User balance
- Locked balance
- Money in
- Money out
- Holds
- Releases
- Refunds
- Platform/service fee deductions
- Idempotency protection

A wallet transaction should record:

- Wallet ID
- User ID
- Type
- Direction
- Amount
- Balance before/after
- Locked balance before/after
- Reference type/id
- Idempotency key
- Meta snapshot

---

# 7. Wallet Fee Service

`WalletFeeService` is the central service for applying service fees.

Responsibilities:

- Resolve booking fees from `category_child_service_fees`.
- Check whether the business/client has fee auto-charge consent.
- Apply active promotions.
- Create idempotent wallet transactions.
- Prevent double charging.
- Store a complete metadata snapshot.

Important rule:

Service fees should not be calculated from `platform_services` directly. The active source is `category_child_service_fees`.

---

# 8. Deposit Policy

The new deposit model is not a simple down payment. BIM distinguishes between:

## 8.1 BIM Wallet Hold

A wallet hold is a frozen amount used as a guarantee. It proves seriousness and protects the opposite party.

It is not automatically deducted from the booking price.

## 8.2 External Deposit Verification

A business may request an external deposit outside BIM, such as cash or bank transfer.

This must be confirmed by both parties.

If confirmed, it can be deducted from the remaining payable amount by agreement, but it is not a BIM wallet balance movement.

## 8.3 Business Counter Hold

The business may also be required to freeze a counter-guarantee amount, for example 50% of the client guarantee.

This balances responsibility between client and business.

---

# 9. Guarantee System

The guarantee system is a core BIM feature.

Its purpose is to reduce fraud, no-shows, unserious bookings, and unfair cancellations.

## 9.1 Client Guarantee

The client guarantee is a hold from the client wallet.

It may be based on:

- First day value
- Total booking value
- Service maximum percentage
- Business policy
- Bookable item override

## 9.2 Business Guarantee

The business guarantee is a counter hold from the business wallet.

Default policy can be:

```text
Business hold = 50% of client guarantee
```

This value can later become configurable.

## 9.3 Guarantee Is Not Revenue

A guarantee should never be treated as business revenue when it is created.

It remains frozen until:

- Released
- Refunded
- Split
- Resolved by dispute

---

# 10. Guarantee Lifecycle

```text
Booking created
  -> Policy resolved
  -> Client hold required? yes/no
  -> Business counter hold required? yes/no
  -> Holds created
  -> Booking proceeds
  -> Completed or cancelled
  -> Holds released/refunded/split
```

If dispute opens:

```text
Dispute opened
  -> Friendly resolution window
  -> Reminder every 3 days
  -> Deadline after 15 days
  -> Resolution action
```

---

# 11. Dispute Flow

Disputes may be opened for booking or deposit/guarantee conflicts.

Possible states:

- Open
- Under review
- Cancelled
- Closed
- Resolved

Possible resolution actions:

- Release to business
- Refund to client
- Split
- No action

---

# 12. Friendly Resolution Window

The planned dispute policy includes:

- 15-day friendly resolution period.
- Reminder every 3 days.
- Encourage agreement between both parties.
- Apply non-cooperation rules if one side does not respond.

This makes BIM an organizer and escrow-style platform, not only a booking form.

---

# 13. Service Fees vs Guarantee vs Booking Price

These must remain separated:

| Concept | Meaning | Source | Wallet effect |
|---|---|---|---|
| Booking price | Service value | Business price / item price | Not automatically wallet movement |
| Service fee | Platform/service charge | category_child_service_fees | Wallet deduction |
| Client guarantee | Seriousness hold | Deposit/guarantee policy | Locked balance |
| Business counter hold | Business commitment | Guarantee policy | Locked balance |
| External deposit | Paid outside BIM | Business/client confirmation | No direct wallet movement |

---

# 14. Current Implementation Status

Implemented / advanced:

- Booking routes and admin actions.
- Booking engine initial pricing preparation.
- Wallet fee service.
- Service fee resolution from category child fees.
- Dispute routes and resolution actions.
- Guarantee admin routes exist.

Needs finalization:

- Final guarantee policy engine.
- External deposit confirmation flow.
- Automated reminders every 3 days.
- Non-cooperation fee/rule.
- Full integration with booking status transitions.
- Clear admin UI for guarantee state.

---

# 15. Development Rules

- Never mix booking price with guarantee hold.
- Never treat external deposit as BIM wallet income.
- Never charge service fees without consent if consent is required.
- Always use idempotency for wallet charges.
- Always snapshot pricing and fee details on booking.
- Keep dispute resolution auditable.

---

# 16. Future Enhancements

- Dedicated `GuaranteePolicyService`.
- Dedicated `BookingDepositService` or merge into guarantee engine if appropriate.
- Scheduled dispute reminders.
- Admin dispute timeline.
- Automated non-cooperation fee.
- More configurable guarantee percentages per service, business, or bookable item.
