# Stage 896 — Training Lab Pilot Reward Issue Endpoint v1

Stage 896 adds one disabled-by-default, signed server endpoint for a controlled Training Lab reward pilot.

## Endpoint

```text
POST /api/integrations/training-lab-reward-pilot-issue.php
Content-Type: application/json
```

This endpoint is not a general reward API. It accepts only the `training_lab_reward_issue_pilot_v1` contract, requires `pilot_only=true` and `readback_required=true`, and applies a server-configured value ceiling.

## Configuration

Keep the endpoint disabled during deployment:

```text
MG_TRAINING_LAB_PILOT_ISSUE_ENABLED=false
MG_TRAINING_LAB_PILOT_ISSUE_SECRET=<dedicated minimum 32-character random secret>
MG_TRAINING_LAB_PILOT_ISSUE_MAX_SKEW_SECONDS=300
MG_TRAINING_LAB_PILOT_ISSUE_NONCE_TTL_SECONDS=900
MG_TRAINING_LAB_PILOT_ISSUE_MAX_BODY_BYTES=65536
MG_TRAINING_LAB_PILOT_ISSUE_MAX_VALUE_CENTS=2500
```

Use a dedicated issue secret. Do not reuse the Stage 894 read-only lookup secret.

## Signing contract

The client sends:

```text
X-Microgifter-Training-Lab-Issue-Timestamp
X-Microgifter-Training-Lab-Issue-Nonce
X-Microgifter-Training-Lab-Issue-Signature
```

Canonical signing input:

```text
training-lab-reward-issue-v1\n
<timestamp>\n
<nonce>\n
<sha256 raw JSON body>
```

The signature is HMAC-SHA256 using the dedicated Stage 896 secret.

## Production boundaries

The service:

- resolves the merchant issuer from `merchant_workspaces.public_id`
- never accepts a raw merchant user ID as authority
- verifies that the recipient user exists
- requires a published, active, merchant-owned Microgift template version
- requires exact template value and currency equality
- applies the configured pilot value ceiling
- calls the canonical `mg_microgift_issue()` engine
- preserves the Training Lab handoff idempotency key
- verifies duplicate recipient and idempotency binding
- projects sender and recipient records through `mg_action_center_sent()`
- returns no claim credential or private customer information

## Rollout

1. Deploy with the endpoint disabled.
2. Configure the dedicated secret on Microgifter and Training Lab.
3. Keep the scheduled Training Lab worker disabled.
4. Complete Stage 895 acceptance.
5. Enable this endpoint and the Training Lab Stage 896 issue client.
6. Run one low-value pilot through `/admin/reward-pilot.php`.
7. Confirm Stage 894 read-back reports the same Microgift instance.
8. Disable the Stage 896 issue endpoint after pilot validation until the next approved rollout phase.

## Rollback

```text
MG_TRAINING_LAB_PILOT_ISSUE_ENABLED=false
```

Disabling the endpoint prevents new issue calls. Existing idempotent Microgift and Action Center records remain intact.

## SQL

**No SQL required.**

Stage 896 reuses the existing Microgift, Action Center, idempotency, merchant workspace, user, and audit infrastructure.
