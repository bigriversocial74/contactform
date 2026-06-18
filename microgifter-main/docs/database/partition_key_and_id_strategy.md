# Partition Key and ID Strategy

## Decision

Microgifter will remain relational-first while designing every high-growth table to be partitionable later.

## Primary scale boundaries

Use these keys consistently:

```text
account_id      B2B, merchant, workplace, program, admin-owned workflows
store_id        storefront, catalog, checkout, store agent workflows
owner_user_id   consumer, personal gifting, inbox, profile-owned workflows
```

## Shardability rule

A future shard can be selected from a stable scope key:

```text
account_id % shard_count
store_id % shard_count
owner_user_id % shard_count
```

Do not build future tables that require cross-shard joins for common reads.

## ID layers

Use three ID concepts:

1. `id` for internal relational joins.
2. `public_id` for URLs and APIs.
3. `slug` for human-readable pages where needed.

## Public ID format

Stage 1 uses app-generated 26-character Crockford-base32 IDs. These are URL-safe and time-sortable enough for early production. We can later replace the generator with a formal ULID library without changing column shape.

## URL examples

```text
/store/{store_slug}
/product/{product_public_id}/{slug}
/gift/{gift_public_id}
/claim/{claim_public_id}
/thread/{thread_public_id}
/agent/{agent_public_id}
```

## Database portability

The current codebase is MySQL-compatible. For AWS, the preferred production path is Aurora MySQL-compatible first, with SQL conventions kept as portable as reasonably possible.

## Carry-forward rule

No user-facing URL or API should rely only on an auto-increment integer ID.
