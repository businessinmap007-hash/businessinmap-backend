# Business In Map (BIM)

## 02 - Database and Business Core

Version: 1.0 (Draft)

---

# 1. Purpose

This document explains the database and business-domain core of BIM. It is not a raw schema dump. It explains how the main entities relate to each other, why they exist, and which business rules depend on them.

The goal is to make the system understandable before reading the code.

---

# 2. Domain Model Overview

The current BIM domain is built around this chain:

```text
Root Category
  -> Category Child
    -> Options
    -> Platform Services
    -> Service Configs
    -> Service Fees
      -> Business
        -> Business Service Prices
        -> Bookable Items
          -> Booking
            -> Wallet Transactions
            -> Deposit / Guarantee
            -> Dispute
```

The most important architectural decision is that `Category Child` is the operational center of the project.

A root category is used for high-level organization. A category child represents the real operational specialization that controls services, options, fees, and booking behavior.

---

# 3. Core Tables by Layer

## 3.1 Users Layer

Main tables:

- `users`
- `wallets`
- `wallet_transactions`
- `user_platform_service`
- `option_user`
- `user_service_fee_consents`

Purpose:

Users can be clients, businesses, or admins. Business users are linked to root category and category child. They can also be linked to platform services that they actually provide.

Important business rule:

A business should not appear in service-based search only because its category child supports a service. It must also have that service enabled through `user_platform_service`.

---

## 3.2 Categories and Classification Layer

Main tables:

- `categories`
- `category_children_master`
- `category_parent_child`
- `option_groups`
- `options`
- `category_child_option`

Purpose:

- `categories` stores root categories only.
- `category_children_master` stores reusable child categories.
- `category_parent_child` links children to one or more root categories.
- `option_groups` groups related options.
- `options` stores the actual filterable options.
- `category_child_option` determines which options are available for each child category.

Final rule:

There is no direct final architecture path from `Category` to `Options`. Options belong operationally to `Category Child`.

---

## 3.3 Platform Services Layer

Main tables:

- `platform_services`
- `category_platform_services`
- `category_service_configs`
- `category_child_service_fees`
- `platform_service_item_types`
- `platform_service_fee_promotions`

Purpose:

Platform services define what the platform can offer, such as booking, menu, delivery, and future services.

`category_platform_services` decides which services are available for a specific root + child pair.

`category_service_configs` stores service-specific configuration for each child/service pair.

`category_child_service_fees` stores the real fee rules for business/client charges.

`platform_service_item_types` defines allowed bookable item types per service.

`platform_service_fee_promotions` allows temporary fee discounts or waivers.

Important rule:

Fees must not be calculated directly from `platform_services`. The active source of service fees is `category_child_service_fees`, with promotions applied by the fee service.

---

## 3.4 Business Service Layer

Main tables:

- `business_service_prices`
- `user_platform_service`
- `bookable_items`

Purpose:

This layer defines what a business actually offers and how much it costs.

A business can only price or expose a service if:

1. The business has a root category.
2. The business has a category child.
3. The child supports the service.
4. The business has enabled the service.
5. The service is active.

---

## 3.5 Booking Layer

Main tables:

- `bookings`
- `bookable_items`
- `bookable_item_blocked_slots`
- `bookable_item_price_rules`

Purpose:

The booking layer handles reservations and pricing context.

A booking links:

- Client
- Business
- Platform service
- Optional bookable item
- Pricing snapshot
- Fee snapshot
- Deposit / guarantee state
- Dispute state if needed

Important rule:

Booking should preserve pricing and fee snapshots in metadata to avoid historical changes when fee rules or prices change later.

---

## 3.6 Wallet and Finance Layer

Main tables:

- `wallets`
- `wallet_transactions`
- `deposits`
- `guarantees`
- `disputes`
- `payments`

Purpose:

This layer manages balances, holds, fee deductions, deposits, guarantees, refunds, releases, and disputes.

Important rule:

Wallet transactions should be idempotent when linked to booking fees to avoid double charging.

---

# 4. Business Rules

## 4.1 Business Classification

A business account must have:

- `category_id`
- `category_child_id`

Selected options must belong to the selected child.

Selected services must be active for the selected child.

---

## 4.2 Category Child as Operational Center

A child category controls:

- Available options
- Available services
- Service configuration
- Service fees
- Bookable item behavior
- Business filtering

This avoids duplication and makes the platform scalable.

---

## 4.3 Services

A service must be active at several levels:

1. Active in `platform_services`.
2. Active for the category child in `category_platform_services`.
3. Active for the business in `user_platform_service` when filtering businesses.
4. Properly priced in `business_service_prices` when booking requires pricing.

---

## 4.4 Bookable Items

A bookable item must be linked to:

- Business
- Platform service
- Item type
- Price
- Active state

The allowed item type is resolved from `platform_service_item_types`, optionally restricted by `category_service_configs.config.allowed_item_types`.

---

## 4.5 Fees

Service fees are resolved from `category_child_service_fees`.

Fee calculation supports:

- Business fee
- Client fee
- Fixed amount
- Percentage amount
- Active/inactive state
- Promotions
- Wallet auto-charge consent

---

## 4.6 Guarantee and Deposit

The system distinguishes between:

- Booking price
- BIM wallet guarantee hold
- External deposit verification
- Business counter hold
- Service execution fee

Guarantee is not a normal payment. It is a commitment mechanism used to protect both parties.

---

# 5. Main Data Flows

## 5.1 Admin Setup Flow

```text
Create root category
  -> Create/link category children
  -> Attach options to children
  -> Attach platform services to children
  -> Configure service behavior
  -> Configure service fees
  -> Create business
  -> Assign business category/child/options/services
  -> Create business service prices
  -> Create bookable items
```

## 5.2 Client Booking Flow

```text
Client searches
  -> Selects category / child / options / service
  -> System filters eligible businesses
  -> Client selects business/service/item
  -> Pricing preview is generated
  -> Booking is created
  -> Guarantees/deposit rules are applied
  -> Parties confirm
  -> Wallet fees may be charged
  -> Booking completes or dispute opens
```

---

# 6. Current Constraints

- A business currently has one main category and one main category child.
- Options are attached to users through user-option pivot logic.
- Service fees are child/service based.
- Dynamic fee rules engine is not final yet.
- Deposit policy is being redesigned into the guarantee architecture.
- API and mobile clients are later phases.

---

# 7. Design Decision Summary

- Root categories are for grouping only.
- Category children are operational.
- Options belong to category children, not root categories.
- Services are enabled per category child.
- Fees are stored per child/service.
- Wallet fee deductions are handled by `WalletFeeService`.
- Booking pricing is handled by `BookingEngine` and related services.
- Guarantee is a core platform feature, not a simple deposit field.
