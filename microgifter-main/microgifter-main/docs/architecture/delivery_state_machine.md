# Microgifter Delivery State Machine

## Purpose

This document defines the baseline state model for Microgifter's instant gifting delivery system.

The backend acts like a delivery carrier for phygital gifts. It must know where a gift is in the workflow, what happened, what should happen next, and what proof exists for each step.

## Core gift statuses

```text
draft
created
validated
sent
opened
claim_started
claim_verified
claimed
fulfilled
expired
cancelled
failed
```

## Status meaning

### draft

The gift exists as a user or merchant draft. It has not entered the delivery system.

### created

The gift was created and has a durable record, but delivery has not started.

### validated

The system has checked required fields, ownership, merchant/product eligibility, and delivery readiness.

### sent

The gift has been marked sent and notification work has been queued or attempted.

### opened

The recipient or claimant opened the gift link or claim page.

### claim_started

The recipient began a claim flow.

### claim_verified

The claim code, QR code, identity rule, merchant rule, or voucher rule passed verification.

### claimed

The gift has been claimed and cannot be claimed again unless business rules explicitly allow multi-use.

### fulfilled

The merchant/system confirmed delivery or redemption fulfillment.

### expired

The gift can no longer be claimed because the expiration rule passed.

### cancelled

The sender, admin, merchant, or system cancelled the gift before completion.

### failed

The delivery system could not complete the workflow and human/system review may be required.

## Allowed baseline transitions

```text
draft -> created
created -> validated
validated -> sent
sent -> opened
opened -> claim_started
claim_started -> claim_verified
claim_verified -> claimed
claimed -> fulfilled
created -> cancelled
validated -> cancelled
sent -> cancelled
created -> expired
validated -> expired
sent -> expired
opened -> expired
claim_started -> expired
created -> failed
validated -> failed
sent -> failed
claim_started -> failed
```

## Invalid transitions

These should be blocked unless a future explicit admin repair workflow is created:

```text
fulfilled -> claimed
claimed -> sent
expired -> claimed
cancelled -> sent
failed -> fulfilled
```

## Proof-of-delivery model

Every critical transition should have evidence:

```text
sent: notification outbox event or external provider response
opened: request metadata and timestamp
claim_verified: verification rule and hash-safe proof metadata
claimed: claim record and unique claim public_id
fulfilled: merchant/admin/system confirmation
failed: error category and recovery action
```

## Reliability rule

A notification can fail while the gift remains valid. A websocket can fail while the delivery event remains true. The state machine must never depend on frontend delivery as proof of backend delivery.

## Stage 2 carry-forward

When gift tables are implemented, every gift status update must:

1. Validate the transition.
2. Write the new current status.
3. Write a delivery event.
4. Queue outbox work if needed.
5. Return idempotent results where applicable.
