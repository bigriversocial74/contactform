# Microgift permission system plan

Local Quest Rewards exposes the next backend requirement: Microgifter needs a clear permission model for outside apps that send rewards.

## Core idea

A reward issue request should be checked in this order:

```text
credential -> app -> program -> template -> linked account -> event -> limits -> decision
```

Microgifter should keep the final decision. The outside app may request a reward, but Microgifter decides if that reward is allowed.

## Permission layers

### Developer app

The developer app controls:

- environment: sandbox or live
- credential scopes
- allowed origins
- webhook URL
- allowed Distribution Programs
- allowed event types
- allowed reward templates
- launch QA state

### Distribution Program

The program controls:

- status
- delivery window
- total capacity
- per-recipient limit
- attached product/template list
- template quantity limit
- allowed app IDs

### Recipient link

The linked account controls:

- app ID
- external user ID
- Microgifter user ID
- link status
- consent timestamp
- revocation timestamp
- recipient reward history

### External event

The event controls:

- external event ID
- event type
- source app
- idempotency key
- metadata
- duplicate status

## Decision result shape

Internally, the permission evaluator should return a stable result:

```json
{
  "allowed": false,
  "code": "template_not_allowed",
  "message": "Reward template is not attached to this Distribution Program.",
  "http_status": 404
}
```

The public API can keep responses short, but logs and launch QA should keep the structured decision.

## Decision order

1. Credential exists and is active.
2. Credential has the required scope.
3. Developer app is active for the requested environment.
4. Endpoint is allowed for that environment.
5. Program exists and belongs to the merchant/app context.
6. Program is active.
7. Template exists and is attached to the program.
8. Template and program capacity remain available.
9. Linked account exists and belongs to this app.
10. Recipient has not exceeded limits.
11. External event ID and event type are valid.
12. Idempotency key is checked.
13. Reward issue is allowed or rejected with a stable code.

## Stable codes

| Code | Meaning | Status |
|---|---|---|
| `credential_missing` | No bearer credential supplied. | 401 |
| `credential_invalid` | Credential is expired, disabled, or unknown. | 401 |
| `scope_missing` | Credential lacks the required scope. | 403 |
| `environment_mismatch` | Sandbox/live endpoint does not match app mode. | 403 |
| `app_not_live` | Live request was made before launch approval. | 403 |
| `origin_not_allowed` | Return URL origin is not allowed. | 403 |
| `program_not_found` | Program is unavailable to the app. | 404 |
| `program_not_active` | Program cannot currently issue rewards. | 409 |
| `template_not_allowed` | Template is not attached to the program. | 404 |
| `capacity_exceeded` | Program or template capacity is exhausted. | 409 |
| `linked_account_not_found` | Recipient link is not active for this app. | 404 |
| `recipient_limit_reached` | User already hit the program limit. | 409 |
| `invalid_event` | Event ID or event type is malformed. | 422 |
| `duplicate_event` | Idempotency matched an existing reward. | 200 |
| `quota_exceeded` | API quota or rate limit exceeded. | 429 |

## Merchant controls to add

The merchant Developer API screen should eventually expose:

- programs an app may use
- reward templates an app may send
- allowed event types
- sandbox/live mode state
- launch QA blockers
- recipient and program caps
- webhook status
- recent permission decisions

## Developer improvements to add

The public docs should eventually show:

- a permission preflight endpoint
- stable decision codes
- event-to-reward examples
- idempotency examples
- linked account revocation behavior
- sandbox versus live differences

## Future preflight endpoint

```text
POST /api/public/v1/rewards/preflight.php
```

This endpoint would let a demo app check whether a reward is allowed before issuing it.

## Why this matters

The Local Quest app decides what it wants to send. Microgifter decides whether that request is allowed. The permission system should make that decision explicit, auditable, and easy to debug.
