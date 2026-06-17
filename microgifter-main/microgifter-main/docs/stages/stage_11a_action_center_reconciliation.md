# Stage 11A — Action Center Reconciliation and Cleanup

## Purpose

Stage 11A reconciles the official Stage 11 Inbox/Post-Purchase plan with the approved customer UI and the canonical systems completed through Stage 10.

This phase does not create a new gift, payment, ownership, or redemption engine. It locks the Action Center as a user-facing projection over canonical records.

## Canonical authority

- Commerce owns carts, orders, payments, refunds, and financial purchase truth.
- PPPM owns permanent issued-unit identity and current ownership.
- Entitlements own protected digital access.
- Microgift templates and instances own gift lifecycle behavior.
- Stage 10 merchant claim services own verification and redemption authority.
- The Action Center owns display state only: folder placement, read state, archive state, counters, and privacy-safe presentation.

## Approved customer folders

The customer-facing Action Center exposes exactly three primary folders:

1. INBOX — gifts currently owned by the user, including purchased, received, redeemable, expired, revoked, or replacement states where appropriate.
2. SENT — sender history after transfer. Sent history never grants current redemption authority.
3. CLAIMED — gifts redeemed by the current owner through the canonical merchant-location claim flow.

Internal lifecycle states remain richer than the visible folder model. UI folders must never become ownership or redemption authority.

## Envelope, content, and voucher model

A Microgift is presented as a tracked envelope:

1. Envelope — paid, issued, owned, sent, delivered, expired, claimed, and audited through canonical systems.
2. Content stack — messages, images, carousels, video, audio, and future interactive modules that travel with the envelope.
3. Voucher — the protected value-bearing component displayed beneath the content stack.

Content enriches the experience but does not replace or control the voucher. The voucher retains merchant, value, location, expiration, identity, and redemption status.

Extended media analytics are future scope. Stage 11A only preserves clean extension points.

## Demo-content policy

- Demo content is visible only to users with the `super_admin` role.
- Normal customers, merchants, staff, and other roles see only real records.
- Demo records are presentation-only and clearly labeled.
- Demo actions must never create payments, ownership transfers, claims, tips, messages, notifications, ledger entries, payouts, inventory changes, or external webhooks.
- The future Admin/Operations build will add enable, disable, reset, and management controls for the Super Admin demo dataset.
- Until that admin control exists, role-based gating is the authoritative switch.

## Stage 11A cleanup boundaries

### Included

- Lock the three-folder presentation contract.
- Inventory duplicate Inbox, notification, ownership, delivery, claim, shell, modal, drawer, CSS, and JavaScript implementations.
- Confirm one canonical Stage 10 redemption path.
- Confirm PPPM remains ownership authority.
- Expand Super Admin demo records across Inbox, Sent, and Claimed.
- Preserve the approved authenticated application shell.
- Improve the LOAD drawer hierarchy so content appears above the protected voucher.
- Document later-stage placeholders without implementing those systems early.

### Excluded

- Universal Tips execution, which belongs to Stage 12.
- Subscription billing and access, which belongs to Stage 13.
- Full social feed implementation, which belongs to Stage 14.
- PSR expansion, which belongs to Stage 15.
- Autonomous Agent Commerce, which belongs to Stage 16.
- Full Admin/Support/Fraud operations, which belongs to Stage 17.
- Final reliability certification, which belongs to Stage 18.

## Immediate Stage 11 follow-up

After this reconciliation pass:

- Stage 11B: stable list, count, detail, filtering, and cursor pagination APIs.
- Stage 11C: read/unread and archive/restore display operations.
- Stage 11D: complete idempotent lifecycle projection coverage.
- Stage 11E: connect Send, Claim, Message, and other approved actions to canonical backend services.
- Stage 11F: reconciliation job and end-to-end ownership/redemption tests.

## Acceptance rules

- Regular users receive no demo fallback when a folder is empty.
- Super Admin demo items are clearly marked.
- Demo form submissions terminate locally without mutation.
- Content modules render before the voucher in the LOAD drawer.
- The voucher remains visible and authoritative.
- Stage 1–10 ownership, commerce, entitlement, and redemption tests remain valid.
