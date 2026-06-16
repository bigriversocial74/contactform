# Stage 11A Action Center Readiness Review

## Overall assessment

The canonical backend direction is sound. Commerce, PPPM ownership, Microgift lifecycle, merchant-location claim authority, atomic redemption, messaging, notifications, outbox delivery, and audit history are separated correctly.

The current customer Action Center is not yet a complete production workflow. PR #44 establishes the page boundaries and read experience while the remaining mutation and reconciliation work stays in Stage 11A.

## Ready now

- Canonical INBOX, SENT, and CLAIMED folder model.
- User-scoped Action Center list endpoint and per-folder counts.
- PPPM remains ownership authority.
- Microgift instances remain lifecycle authority.
- Merchant redemption is transactional and location-authorized.
- Claim attempts, redemptions, outbox events, and operational history are persisted.
- Gift-linked message threads and replies are user-scoped.
- Notification history, unread badges, header dropdowns, and delivery preferences are available.
- Gift, messaging, notification, and preference pages are separated.

## Immediate PR #44 corrections

- Notification preferences uses the shared customer sidebar.
- Each gift folder loads independently from the Action Center API.
- Folder counters use backend counts instead of the currently loaded list.
- Demo claim code `123456` is isolated to the demo coupon.
- Real gifts do not receive a fabricated claim code in the browser.
- Demo send behavior is clearly separated from production transfer behavior.

## Stage 11A work still required

1. Stable cursor pagination for Action Center lists.
2. User-scoped Action Center detail endpoint.
3. Mark read, unread, archive, and restore endpoint integration.
4. Production recipient lookup and gift-send endpoint.
5. Atomic PPPM ownership transfer plus sender SENT and recipient INBOX projection.
6. Projection creation on purchase, free pickup, delivery, resend, replacement, redemption, expiration, revocation, and refund policy events.
7. Reconciliation job to rebuild missing Action Center rows from PPPM and Microgift history.
8. Privacy-safe sender, recipient, merchant, and location display contracts.
9. Receipt and redemption-detail endpoint for CLAIMED items.
10. Tests proving a sender cannot redeem after ownership transfer.
11. Tests proving only merchant redemption moves a gift to CLAIMED.
12. End-to-end notification generation for send, receive, claim failure, redemption, expiration, and revocation.

## UI work that can proceed separately

The Inbox, Sent, Claimed, Messages, Notifications, and Preferences pages still need a unified visual redesign. That work can proceed without changing the canonical ownership or redemption services, provided the UI consumes the approved Action Center, messaging, notification, and merchant-claim APIs.
