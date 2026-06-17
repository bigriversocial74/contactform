# Stage 4C Feed Posts, Gift Stream, Product Pages, and Storefronts

## Envelope model

- PPPM item: permanent envelope and stamp ID.
- Feed post version: immutable contents placed inside the envelope.
- Feed post elements: text, image, audio, video, offer, CTA, claim panel, and other presentation elements.
- Product version: commercial definition used by the feed post and PPPM issuance template.

## Immutability rules

Published feed post versions are immutable. Promoted posts cannot be edited in place. A new presentation requires a new post/version. Once a feed post version is bound to a PPPM item, the binding cannot be replaced. Existing issued items therefore retain the media, offer, terms, and presentation that influenced promotion or purchase.

Assets referenced by published product or post versions are retained. They may be retired from future use, but historical references are not deleted.

## Engagement rules

Behavioral events are stored separately from authoritative PPPM lifecycle events. High-value milestones such as first content open and claim-panel open may also write summarized PPPM item events.

Tracked events include impressions, opens, plays, pauses, quartile progress, completion, replay, mute state, carousel movement, CTA clicks, claim-panel opens, and sharing.

## Gift stream behavior

- Vertical swipe or arrow keys move between envelopes.
- Horizontal left reveals the PPPM data/claim sheet.
- Horizontal right restores the feed post contents.
- Only the active video plays.
- Nearby posts preload metadata, images, and video metadata without downloading the entire inbox.
- Cursor pagination loads more inbox items as the user approaches the end of the current window.

## Stage 4D carry-forward

Stage 4D retains production media delivery work: object storage, signed URLs, byte-range responses, transcoding, posters, thumbnails, CDN policy, bandwidth-aware preload rules, download entitlements, moderation/takedown states, and analytics aggregation dashboards.
