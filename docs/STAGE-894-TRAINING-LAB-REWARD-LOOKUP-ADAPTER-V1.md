# Stage 894 — Signed Training Lab Reward Lookup Adapter v1

Stage 894 gives Training Lab Stage 893 a production-safe way to confirm whether a canonical Microgift already exists before any retry is allowed.

## Purpose

A Training Lab handoff may lose its worker lease after Microgifter has already issued a reward. Stage 893 quarantines that uncertain result. This endpoint provides the missing read-only verification path.

The endpoint never issues, claims, redeems, cancels, refunds, replaces, or transfers a reward.

## Endpoint

```text
POST /api/integrations/training-lab-reward-lookup.php
Content-Type: application/json
```

The endpoint is disabled until explicitly configured.

## Microgifter configuration

Set these server environment variables:

```text
MG_TRAINING_LAB_REWARD_LOOKUP_ENABLED=false
MG_TRAINING_LAB_REWARD_LOOKUP_SECRET=<minimum 32-character random shared secret>
MG_TRAINING_LAB_REWARD_LOOKUP_MAX_SKEW_SECONDS=300
MG_TRAINING_LAB_REWARD_LOOKUP_NONCE_TTL_SECONDS=900
MG_TRAINING_LAB_REWARD_LOOKUP_MAX_BODY_BYTES=65536
```

Use the same secret in the Training Lab Stage 894 client configuration. Do not commit the secret.

## Signed request headers

```text
X-Microgifter-Training-Lab-Timestamp: <unix timestamp>
X-Microgifter-Training-Lab-Nonce: <16-128 character random nonce>
X-Microgifter-Training-Lab-Signature: <lowercase hexadecimal HMAC-SHA256>
```

The canonical signing input is:

```text
training-lab-reward-lookup-v1\n
<timestamp>\n
<nonce>\n
<sha256 raw JSON body>
```

The signature is:

```text
HMAC-SHA256(canonical input, shared secret)
```

Requests outside the configured clock-skew window are rejected. Nonces are reserved in the existing `idempotency_keys` table and cannot be reused.

## Request contract

```json
{
  "contract": "training_lab_reward_reconciliation_v1",
  "source": "training_lab",
  "idempotency_key": "training-reward-handoff-v1...",
  "external_reference": "optional Microgift public ID or legacy gift ID",
  "training_handoff_id": 123,
  "training_handoff_public_id": "...",
  "training_reward_event_id": 456,
  "training_reward_public_id": "...",
  "training_user_id": 789,
  "microgifter_user_id": "42",
  "read_only": true
}
```

Requirements:

- `microgifter_user_id` must be a positive numeric Microgifter user ID.
- At least one of `idempotency_key` or `external_reference` is required.
- A result is returned only when that user is the canonical Microgift owner or recipient.
- When both references resolve, they must identify the same Microgift instance.

## Response contract

Found canonical instance:

```json
{
  "ok": true,
  "data": {
    "found": true,
    "status": "delivered",
    "delivery_status": "delivered",
    "lifecycle_status": "claimed",
    "external_reference": "microgift-public-id",
    "microgift_instance_id": "microgift-public-id",
    "gift_id": null,
    "pppm_item_id": "optional-pppm-public-id",
    "issued_at": "2026-07-10 12:00:00",
    "claimed_at": "2026-07-10 12:05:00",
    "read_only": true
  }
}
```

Missing canonical instance:

```json
{
  "ok": true,
  "data": {
    "found": false,
    "status": "not_found",
    "delivery_status": "not_found",
    "read_only": true
  }
}
```

Canonical instance existence confirms that issuance occurred. Later lifecycle values such as `claimed`, `redeemed`, `expired`, `cancelled`, `revoked`, or `replaced` are returned separately as `lifecycle_status`; they do not authorize a duplicate issue.

## Data boundaries

The lookup reads:

```text
microgift_instances
pppm_items
```

The response excludes recipient email, claim credentials, redemption credentials, title, description, value, terms, payment details, and internal database IDs.

The only writes are:

- signed-request nonce reservation/completion in `idempotency_keys`
- a security audit record in `audit_logs`

## Deployment order

1. Merge and deploy Stage 894 Microgifter code.
2. Keep `MG_TRAINING_LAB_REWARD_LOOKUP_ENABLED=false`.
3. Generate one random shared secret of at least 32 characters.
4. Configure the same secret in Microgifter and Training Lab private server settings.
5. Deploy the Training Lab Stage 894 client.
6. Run signed lookup acceptance with production reward issuing still disabled.
7. Confirm valid, tampered, expired, replayed, wrong-user, missing, and found tests.
8. Enable the Microgifter lookup endpoint.
9. Enable Training Lab reconciliation only after acceptance passes.
10. Keep production reward processing disabled until the complete reward adapter acceptance is approved.

## Rollback

Disable the endpoint immediately:

```text
MG_TRAINING_LAB_REWARD_LOOKUP_ENABLED=false
```

Disabling the endpoint does not alter Microgift, PPPM, claim, redemption, wallet, payment, or Training Lab handoff records.

## SQL

**No SQL required.**

Stage 894 reuses the existing `idempotency_keys`, `microgift_instances`, `pppm_items`, and `audit_logs` tables.
