# Stage 14 — Posts, Feed, and Social Network

Stage 14 turns the existing Stage 4C feed presentation model into a user-facing social network while preserving product, Microgift, subscription, entitlement, communications, and moderation authorities.

## Post types

Users, creators, and merchants can publish:

- general text or media posts;
- product-linked posts;
- Microgift-linked posts;
- public and unlisted posts;
- follower-only posts;
- subscriber-only and premium posts.

## Visibility authority

Backend visibility checks enforce:

- publication and moderation state;
- reciprocal block rules;
- follower relationships for follower-only posts;
- active Stage 13 subscription periods for subscriber and premium posts;
- author access to their own content;
- mute filtering in generated feeds.

Client-side button or card visibility is never treated as authorization.

## Engagement and social graph

Stage 14 adds:

- likes and alternate reactions;
- threaded comments;
- saves;
- shares;
- follows and unfollows;
- mutes and unmutes;
- blocks and unblocks;
- user, post, and comment reports.

Block operations remove follows in both directions. Muted authors are excluded from the viewer's feed without altering the author's canonical content.

## Moderation

Reports are append-only operational records. Post and comment moderation uses flagged, hidden, removed, resolved, and dismissed states. Administrative decisions are permission-gated and audited.

## Existing authorities preserved

- products remain canonical in the catalog tables;
- Microgift ownership and lifecycle remain canonical in the Microgift engine;
- subscriber eligibility remains canonical in Stage 13 subscriptions;
- notifications use the Stage 5H communications foundation;
- Stage 14 does not mutate tips, subscriptions, entitlements, PPPM ownership, claims, redemption, payments, wallets, or ledger records.

## APIs

- `GET|POST /api/social/posts.php`
- `POST /api/social/engage.php`
- `POST /api/social/relationship.php`
- `POST /api/social/report.php`
- `POST /api/admin/social-moderate.php`

## Deferred to Stage 15

PSR records, future visits, committed demand, demand velocity, merchant/location demand snapshots, and predictive demand analytics remain Stage 15 work.
