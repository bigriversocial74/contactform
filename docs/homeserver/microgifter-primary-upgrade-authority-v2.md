# Microgifter Primary HomeServer Upgrade Authority v2

## Purpose

Microgifter is the primary customer-facing connection for HomeServer account ownership, pairing, entitlement, installer access, update authorization, signed release distribution, update receipts, and rollback visibility.

This control plane extends the already deployed Microgifter HomeServer pairing and release-distribution systems. It does not replace the HomeServer updater and does not roll HomeServer back to an earlier repository state.

The coordinated runtime restoration is HomeServer PR #69. The coordinated cloud/update control is Microgifter PR #1390. Both PRs remain independent drafts until their exact final heads pass retained and cross-repository certification.

## Existing contracts retained

- one-time Microgifter Sync Code pairing
- permanent device and installation identity
- signed entitlement leases
- dataset and capability grants
- update authorization and update-result receipt endpoints
- protected installer storage and release history
- HomeServer Ed25519 manifest verification
- installer SHA-256 and Authenticode verification
- pre-update backup, installed-version health verification, and automatic rollback
- multiple isolated wrapper/provider connections
- local-first operation and private-data boundaries

## Release flow

1. Build and Authenticode-sign `Microgifter-HomeServer-Setup.exe` through the certified HomeServer release workflow.
2. Upload the installer through `/admin/homeserver-releases.php`.
3. Open `/admin/homeserver-upgrades.php` and choose the uploaded release.
4. Enter the exact Authenticode signer thumbprint and release key ID.
5. Generate the canonical manifest payload.
6. Sign the exact UTF-8 payload bytes with the offline Ed25519 release private key.
7. Paste the base64url signature into the update control center.
8. Microgifter verifies the signature against `MG_HOMESERVER_RELEASE_PUBLIC_KEY_BASE64`.
9. Activate the release and select rollout percentage and an optional prior signed rollback release.
10. HomeServer retrieves the signed manifest from `/api/homeserver/update-manifest-stable.php`.
11. HomeServer obtains entitlement authorization before downloading feature-class updates.
12. The public updater download endpoint revalidates the signed payload, stored payload hash, release state, file size, SHA-256, and revocation state before returning bytes.
13. HomeServer verifies the same signature, checksum, size, and Authenticode identity locally before staging installation.
14. Installation, failure, and rollback receipts return to Microgifter through the existing authenticated device contract.

## Key custody

The Ed25519 release private key is never stored in:

- the GitHub repository
- Microgifter application configuration
- MySQL
- release metadata
- browser storage
- HomeServer

Only the public verification key is configured on Microgifter and compiled/pinned into certified HomeServer builds. Offline signing or a separately controlled release-signing workflow produces the signature.

Required server settings:

- `MG_HOMESERVER_RELEASE_PUBLIC_KEY_BASE64`
- `MG_HOMESERVER_RELEASE_KEY_ID` (default `homeserver-release-2026-01`)
- `MG_HOMESERVER_UPDATE_PUBLIC_BASE_URL` (default `https://microgifter.com`)

The Microgifter public key and the HomeServer pinned public key must represent the same Ed25519 keypair and key ID.

## Rollout behavior

Active signed releases are evaluated newest first. The rollout bucket is deterministic for the release and `X-Microgifter-HomeServer-Installation` header. Until all installed clients send that header, the public request address is used as a compatibility fallback.

If a client is outside the newest release percentage, Microgifter can return the next eligible active signed release. This preserves a valid updater response rather than returning an unsigned or empty manifest.

## Pause, revocation, and rollback

- **Pause:** stops the release from being selected while retaining its signed evidence.
- **Resume:** restores a paused release without changing signed payload bytes.
- **Rollout change:** changes distribution percentage without altering the signed installer manifest.
- **Revoke:** permanently removes the release from manifest and updater download eligibility and records a reason.
- **Rollback:** makes a selected prior active signed release latest and pauses the current release. HomeServer still performs its own installed-version verification and rollback safeguards.

## Deployment order

1. Deploy this Microgifter application branch.
2. Import `database/20260803_homeserver_upgrade_control_v2.sql` through the canonical migration runner.
3. Configure the public release key and public update base URL.
4. Deploy the coordinated HomeServer primary-authority branch.
5. Publish a certified signed release through the update control center.
6. Validate fresh pairing, existing Microgifter pairing, former VP3-primary migration, update authorization, installation receipt, failure receipt, and rollback receipt.

No existing HomeServer device, pairing, entitlement, provider connection, dataset grant, installer release, update receipt, or local private data is deleted by this change.
