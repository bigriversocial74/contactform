# Engagement Mutations Foundation

## Scope

This phase turns the existing Stage 14 social tables and Stage 12 tip/payment authorities into production-facing mutations. It does not create parallel follow, reaction, comment, payment, wallet, ledger, moderation, or block systems.

## Relationship mutations

`POST /api/social/relationship.php`

Supported actions:

- `follow`
- `unfollow`
- `mute`
- `unmute`
- `block`
- `unblock`

The endpoint accepts a public profile identifier, slug, or public user identifier at the boundary. Internal numeric user IDs are never returned. Follow and mute operations enforce bidirectional block relationships. Blocking removes follows in both directions and clears the actor's mute relationship.

Every request requires authentication, `social.engage`, CSRF protection, a bounded rate limit, and an idempotency key.

## Post reactions and comments

`POST /api/social/engage.php`

Supported mutations include:

- react and unreact
- create a comment
- delete an authored comment
- hide or restore a comment as the post owner
- moderator comment actions through the existing `social.moderate` permission
- existing save, unsave, and share compatibility actions

All mutations reuse `mg_social_can_view()` and the existing block, publication, subscription, and moderation rules. Counter changes occur in the same transaction as the underlying mutation. Reaction changes do not double-count. Comment deletion and hiding decrement the visible comment count; restoration increments it once.

Comment bodies are normalized, bounded to 2,000 characters, and projected with public author presentation fields only. Replies are limited to one level.

## Public engagement reads

`GET /api/public/post-engagement.php`

The read endpoint returns:

- aggregate reaction and comment counts
- reaction-type counts
- the authenticated viewer's current reaction
- bounded cursor-paginated visible comments
- public author name/profile links
- viewer-safe comment permissions

It enforces the same post visibility and block authority as the mutation service. Anonymous reads use short-lived public caching; authenticated reads use private no-store caching.

## Idempotency

`social_mutation_requests` binds each actor and idempotency key to:

- one canonical action
- one request fingerprint
- one stored response

Exact request replays return the stored result without repeating counters, notifications, comments, or relationship writes. Reusing a key with different request data fails closed.

## Card-funded tips

The public profile tip capability continues to expose the public profile UUID. `mg_tip_engagement_input()` resolves that UUID to the existing canonical numeric recipient authority internally before `mg_tip_create()` runs.

`POST /api/tips/confirm.php` verifies:

- the authenticated sender owns the tip
- the tip uses card funding
- the payment intent belongs to the tip
- amount and currency match the stored tip and intent
- the provider or stored provider event indicates the authoritative state

The client cannot supply a trusted payment state, amount, currency, provider result, ledger identifier, or recipient. Successful confirmation delegates to `mg_tip_finalize_stripe()`, which performs the existing single-ledger posting and tip event workflow. Webhooks remain the primary asynchronous provider authority; confirmation is an idempotent client-facing reconciliation path.

## Profile UI

`/profile.php` now includes:

- follow and unfollow controls with follower-count updates
- like, love, celebrate, and support reactions
- visible comment loading, creation, deletion, and post-owner hiding
- wallet or card tip selection
- card authorization and server confirmation states
- responsive desktop and mobile controls

User content is rendered with text nodes. Profile, post, comment, tip, and payment identifiers crossing the UI boundary are public identifiers.

## Operations and safety

- database-backed abuse throttling
- CSRF on every mutation
- permission and active-session enforcement
- bidirectional block checks
- moderation enforced at read and write time
- audit events and operational events without raw payment secrets or private metadata
- non-enumerating unavailable profile, post, comment, and tip behavior

## Validation

Focused validation covers:

- complete ordered MySQL migration import
- exact replay and conflicting-idempotency behavior
- follow counters and block cleanup
- reaction changes and counter stability
- comment ownership, post-owner moderation, and counters
- hidden-post and block exclusions
- public-safe projections
- public-profile tip target translation
- card-funded pending, confirmation, replay, and single-ledger behavior
- PHPUnit authority contracts
- desktop and mobile Playwright interactions
- full repository PHPUnit and browser regression workflows
