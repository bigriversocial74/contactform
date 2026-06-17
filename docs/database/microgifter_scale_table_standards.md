# Microgifter Scale Table Standards

## Required baseline columns

High-growth tables should use this baseline shape unless there is a documented reason not to:

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
public_id CHAR(26) NOT NULL UNIQUE
account_id BIGINT UNSIGNED NULL
store_id BIGINT UNSIGNED NULL
owner_user_id BIGINT UNSIGNED NULL
status VARCHAR(40) NOT NULL DEFAULT 'active'
metadata_json JSON NULL
created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
```

## Scope columns

Use the smallest correct scope:

- `owner_user_id` for user-owned personal records.
- `account_id` for organization/merchant/workplace records.
- `store_id` for catalog/storefront/checkout records.

Do not use global-only lookup patterns for high-growth tables.

## Public IDs

Every externally referenced record should have a non-sequential `public_id`.

Examples:

- product URLs
- gift claim URLs
- order receipts
- inbox threads
- agent workspace records

Internal numeric IDs are acceptable for joins. Public IDs are safer for URLs and APIs.

## Hot-read indexes

Indexes should start with the scope key.

Examples:

```text
INDEX(account_id, status, created_at)
INDEX(store_id, slug)
INDEX(owner_user_id, created_at)
INDEX(account_id, public_id)
```

## Current truth vs history

Do not overload current-state tables with history.

Example:

- `gift_vouchers` stores current status.
- `gift_events` stores what happened over time.

## Lifecycle categories

Every table should have a lifecycle category:

- core truth: retained long-term
- operational: pruned after success or expiry
- event/audit: retained and archived
- analytics: moved to warehouse later
- cache/read model: rebuildable

## No casual joins on hot paths

Hot public reads should avoid deep joins. Prefer snapshot/read-model tables where appropriate.

## Stage gate

Before accepting a new module, confirm its tables follow these standards or document the exception in the stage delta register.
