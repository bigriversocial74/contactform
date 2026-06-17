# Stage 4D Digital Fulfillment, Secure Downloads, and Production Media Delivery

## Scope

Stage 4D turns the Stage 4C envelope-content layer into a production-ready fulfillment boundary. It adds signed access tokens, byte-range streaming, digital entitlements, download limits, media-processing jobs, moderation controls, storage-provider abstraction, bandwidth-aware client policy, and merchant analytics aggregation.

## Security model

- Raw storage keys are never returned to clients.
- Access URLs contain random, short-lived tokens; only HMAC hashes are stored.
- Tokens may be tied to a user, entitlement, asset or processed variant, purpose, disposition, expiry, and usage limit.
- Digital downloads require an active entitlement tied to a PPPM item.
- Expired, exhausted, revoked, quarantined, blocked, or takedown assets are denied.
- IP addresses and user agents are stored only as keyed hashes in access logs.
- Local storage paths are resolved through a provider abstraction and must remain inside the private storage root.

## Digital entitlement lifecycle

1. A published product version defines a fulfillment rule.
2. A PPPM item receives an entitlement for that rule.
3. The entitled recipient requests a short-lived access token.
4. The delivery endpoint validates entitlement, token, moderation, expiry, and usage limits.
5. Completed downloads increment entitlement usage and may exhaust the entitlement.
6. Access events remain auditable without exposing raw storage locations.

## Media processing

Media jobs are provider-neutral records for scanning, metadata extraction, transcoding, poster generation, thumbnails, and audio normalization. Variants retain their own storage key, checksum, dimensions, duration, bitrate, and status. The current package provides the queue and data contracts; worker execution and external transcoding providers can be attached without changing product or PPPM records.

## Streaming and preload

The delivery endpoint supports HTTP byte ranges and 206 responses. The browser client detects data-saver and constrained connections, disables autoplay where appropriate, and reduces nearby-item preloading. Standard connections retain the Stage 4C window of current minus one through current plus two.

## Retention and immutability

Published and issued media is not physically deleted through normal merchant actions. Assets can be retired, quarantined, blocked, or placed under takedown while their metadata and historical references remain available for audit, disputes, and redemption integrity.

## Analytics

Raw content engagement remains separate from PPPM lifecycle events. A daily aggregation job creates merchant, post-version, event-type, viewer, and playback summaries. Digital access events track token issuance, stream starts, download starts/completions, bytes served, expiry, denial, and revocation.

## Stage 4E carry-forward

Stage 4E should focus on distribution programs and external source adapters. Operational deployment work that may continue alongside it includes object-storage adapters, queue workers, FFmpeg/transcoding workers, antivirus scanning, CDN integration, retention sweeps, and scheduled analytics aggregation.