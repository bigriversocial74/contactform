# Microgifter HomeServer Cloud Contract v1 — Provider Implementation

This document describes the Microgifter provider-side implementation coordinated with HomeServer Phase 6A draft PR #37.

## Routes

The installed HomeServer uses extensionless HTTPS routes:

- `POST /api/homeserver/v1/pairing/exchange`
- `POST /api/homeserver/v1/entitlements/refresh`
- `POST /api/homeserver/v1/devices/heartbeat`
- `POST /api/homeserver/v1/devices/credentials/rotate`
- `POST /api/homeserver/v1/updates/authorize`
- `POST /api/homeserver/v1/updates/receipts`
- `POST /api/homeserver/v1/devices/replacements/start`
- `POST /api/homeserver/v1/devices/replacements/complete`

Apache rewrites these paths internally to PHP handlers while preserving the original request URI used by Ed25519 request signatures.

## Trust boundaries

- Pairing exchanges a one-time Microgifter Sync Code for a device identity and token.
- Every later request requires the device token, Ed25519 signature, timestamp, nonce, request ID, device ID, provider connection ID, and contract version.
- Pairing and credential-rotation responses are encrypted at rest during their bounded idempotent recovery windows.
- Entitlement leases are signed with a dedicated Ed25519 key and contain no signing secret.
- Pairing is not the update cryptographic trust root. HomeServer still verifies signed manifests, checksums, Authenticode, backups, health checks, and rollback independently.
- Knowledge Vault content, prompts, conversations, model responses, local filenames, local secrets, and backup contents are not accepted by heartbeat or receipt handlers.

## Required server configuration

The provider requires a 32-byte Ed25519 seed:

`MG_HOMESERVER_ENTITLEMENT_SIGNING_SEED`

Accepted formats are 64 hexadecimal characters, standard Base64, or URL-safe Base64. The seed must be stored outside the repository.

Optional settings:

- `MG_HOMESERVER_ENTITLEMENT_SIGNING_KEY_ID`
- `MG_HOMESERVER_PAIRING_RECOVERY_KEY`
- `MG_HOMESERVER_ENTITLEMENT_LEASE_SECONDS`

When no separate recovery key is supplied, a domain-separated recovery key is derived from the entitlement signing seed. No secret value is returned by an API or written to an audit receipt.

## Physical installation and connection identity

A physical HomeServer installation may carry multiple isolated Microgifter provider/site connections. Each connection receives its own cloud device identity, token, Ed25519 public key, capability lease, grants, receipts, and revocation boundary. Package device limits count distinct physical `installation_id` values rather than provider connection rows. A Sync Code can therefore be issued at the physical-device limit for an additional connection on an existing authorized installation; the exchange rejects a previously unseen installation when no physical slot remains.

Requested merchant and site values are treated as hints only. The provider derives merchant ownership from the Sync Code account and returns only sites that belong to that owner or have explicit device grants.

## Compatibility

The existing legacy pairing and synchronization endpoints remain available. This v1 implementation is non-destructive and does not reset existing device records, dataset grants, campaign authorizations, release channels, or local HomeServer state. The migration removes the legacy one-row-per-installation uniqueness constraint and replaces it with a normal lookup index so multiple isolated connections can coexist on one installation.

Update result receipts accept the Phase 6A payload fields `authorization_id`, `update_id`, `version`, `result_state`, and `failure_code`, with idempotency keyed by the authorization or receipt identity.

The final contract must be reconciled against the completed HomeServer Phase 6A contract document and fixtures before either coordinated PR is merged.
