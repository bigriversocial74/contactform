# Microgifter User Models and Identity Profiles Design

## Purpose

Microgifter uses one login identity with multiple enableable user models. A person should not need separate accounts to act as a customer, creator, merchant, moderator, vendor manager, marketing affiliate, trader, admin, or super admin.

This design keeps authentication simple while allowing users to operate in multiple platform contexts as permissions and business needs expand.

## Core rule

```text
users = login identity
roles/permissions = authorization
user_models = enabled operating modes
identity profile tables = model-specific profile data
accounts/stores/objects = scoped business records
```

A user model is not a separate account type. It is a status-controlled capability layer attached to one user account.

## Supported user models

```text
customer
creator
merchant
moderator
vendor_manager
marketing_affiliate
trader
admin
super_admin
```

## Status lifecycle

```text
pending
active
disabled
suspended
revoked
rejected
```

Recommended transitions:

```text
not assigned -> pending
not assigned -> active        auto-enabled models such as customer
pending -> active             approval
pending -> rejected           rejection
active -> disabled            user/admin disable
active -> suspended           platform review action
disabled -> active            re-enable
suspended -> active           reinstatement
any non-revoked -> revoked    terminal removal where required
```

Privileged model changes should create audit records and append-only model events.

## Tables

### user_models

Global catalog of available models.

Fields:

```text
code
name
description
is_system
is_assignable
requires_approval
default_status
sort_order
```

### user_model_assignments

Connects users to models.

Fields:

```text
user_id
user_model_id
status
requested_at
enabled_at
approved_at
approved_by_user_id
disabled_at
disabled_by_user_id
reason
metadata_json
```

Unique rule:

```text
one assignment row per user per model
```

### user_model_events

Append-only history for model changes.

Example events:

```text
user_model.requested
user_model.approved
user_model.enabled
user_model.disabled
user_model.suspended
user_model.revoked
user_model.rejected
```

### model_default_roles

Maps a model to default roles that may be assigned when a model is enabled. Model activation and role assignment are related, but not identical.

### profile tables

Model-specific profile tables hold extra profile data only when needed.

Initial profile tables:

```text
creator_profiles
merchant_profiles
moderator_profiles
vendor_manager_profiles
marketing_affiliate_profiles
trader_profiles
```

Customer profile behavior can continue through the existing Stage 1 user profile table unless a separate customer profile table is needed later.

## Authorization policy

A model being active does not automatically grant every action in that domain.

Actual access must still be enforced through roles, permissions, object ownership, and account/store scope.

## Stage 2 endpoint rule

Before adding feature endpoints, check:

```text
1. Is the user authenticated?
2. Is the required user model active?
3. Does the user have the required permission?
4. Does the user own or belong to the target scope/account/store/object?
5. Should the action emit audit/model/delivery events?
```

## Runtime direction

HostGator mode:

```text
MySQL tables + synchronous PHP endpoints + audit/model events
```

AWS enhanced mode later:

```text
Aurora MySQL-compatible + event/outbox processing + queue-backed notifications + stronger admin observability
```

The schema must remain MySQL/Aurora-compatible.
