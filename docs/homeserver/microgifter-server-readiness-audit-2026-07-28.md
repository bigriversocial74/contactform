# Microgifter HomeServer Server-Side Readiness Audit

**Repository:** `bigriversocial74/contactform`  
**Target branch:** `integration-from-repair-20260628`  
**Audit date:** 2026-07-28  
**Scope:** Microgifter account entitlement, installer distribution, Sync Code pairing, device management, HomeServer status, cloud synchronization authority, signed entitlement leases, and the future `updates.microgifter.com` update service.

## 1. Executive conclusion

Microgifter already contains a substantial HomeServer cloud foundation. The current codebase includes:

- account-owned one-time pairing codes;
- HomeServer device registration and revocation;
- hashed bearer tokens;
- Ed25519 signed device requests;
- timestamp and nonce replay protection;
- idempotent synchronization receipts;
- merchant dataset grants;
- merchant campaign-action authorizations;
- a red/green HomeServer status indicator and quick modal;
- a standalone HomeServer management page;
- an administrator HomeServer Release Center;
- protected installer storage outside the public web root;
- tracked initial installer downloads;
- release channel, version, architecture, minimum-version, and mandatory-update metadata.

The correct next step is **not** to rebuild those systems. The next Microgifter phase should extend them into a paid-account HomeServer lifecycle and a signed update-distribution service.

The current implementation is not yet ready for that lifecycle because:

1. Any authenticated Microgifter user can currently create a pairing code.
2. Any authenticated Microgifter user can currently retrieve release metadata and download a published installer.
3. The standalone HomeServer page is available without a HomeServer subscription capability check.
4. The global status modal exposes the installer section to all authenticated users.
5. The current device status model only stores `active` or `revoked`.
6. There is no signed entitlement lease issuer.
7. There is no capability-negotiation registry.
8. There is no in-band credential rotation endpoint.
9. Pairing exchange is not fully retry-safe after the pairing transaction commits.
10. The Release Center upload path publishes immediately by default.
11. Uploaded installers are not Authenticode-verified by the server.
12. The embedded installer version and signer thumbprint are not verified.
13. The current latest-release response is authenticated browser JSON, not the signed update manifest expected by HomeServer.
14. The current download endpoint is browser-session based and does not support resumable range requests.
15. No publishing bridge exists from the Release Center to `updates.microgifter.com`.
16. No update authorization, staged rollout, adoption, install, failure, or rollback receipt system exists on the Microgifter side.

## 2. Controlling architecture decisions

The server-side build must preserve these decisions:

- The **Microgifter account owns the Microgifter HomeServer connection**.
- The HomeServer retains control of its local runtime and private local data.
- The Microgifter connection is one provider/wrapper connection and must not control unrelated providers.
- Pairing, subscription entitlement, cloud synchronization, installer access, and signed updates are related but separate systems.
- Updating the main Microgifter website must not reset an established HomeServer pairing, device identity, synchronization cursor, provider grant, or update channel.
- The updater executable remains bundled with the normal HomeServer installer.
- The customer does not install a second updater application.
- The existing Release Center remains the administrator control surface.
- `updates.microgifter.com` becomes the machine-facing signed distribution boundary.
- Bootstrap, recovery, and critical security updates must not depend entirely on an active paid pairing.
- Local HomeServer operation must survive Microgifter outages, payment problems, suspension, cancellation, and pairing loss.

## 3. Audited implementation inventory

### 3.1 Pairing and device foundation

Existing migration:

- `database/20260724_homeserver_cloud_pairing_sync_v1.sql`

Existing tables:

- `homeserver_devices`
- `homeserver_pairing_codes`
- `homeserver_request_nonces`
- `homeserver_sync_receipts`

Existing endpoints:

- `api/homeserver/pairing-code.php`
- `api/homeserver/pair.php`
- `api/homeserver/devices.php`
- `api/homeserver/status.php`
- `api/homeserver/sync.php`
- `api/homeserver/revoke.php`
- shared security helper `api/homeserver/_homeserver.php`

Current strengths:

- Pairing codes are random, SHA-256 hashed, one-time, and expire after ten minutes.
- Creating a new code expires the account's previous unused code.
- Device tokens are random and stored only as SHA-256 hashes.
- Device requests require bearer authentication and Ed25519 signatures.
- The canonical signed request includes method, path, timestamp, nonce, and body hash.
- Timestamp and nonce replay protection is enforced.
- Device status requests update `last_seen_at` and installed version.
- Synchronization operations require idempotency keys.
- Commerce, payment, claim, redemption, and ownership mutations are rejected from the generic sync endpoint.
- Revocation invalidates the token hash immediately.

Current gaps:

- Pairing-code creation requires only an authenticated account and CSRF token; there is no paid HomeServer entitlement check.
- Device listing and revocation are owned by `owner_user_id`, but delegated account administration is not modeled.
- The device table stores only `active` and `revoked` status.
- There is no explicit `offline`, `grace`, `suspended`, `replacing`, or `error` state.
- There is no dedicated heartbeat endpoint carrying structured operational status.
- Any successful signed endpoint call updates last seen; heartbeat and synchronization are not independently measured.
- The pairing exchange accepts no pairing request ID or idempotency key.
- If the server commits the pairing and the client loses the response, retrying the consumed code fails.
- Re-pairing an existing installation rotates its token and key, but there is no authenticated in-band credential rotation operation.
- The pair response has no entitlement lease, capability negotiation result, update eligibility, merchant assignment, or site assignment.
- The code uses unversioned paths such as `/api/homeserver/pair.php`; the next contract must use explicit versioned routes while preserving current compatibility.

### 3.2 Ownership hierarchy

The current cloud ownership key is:

- `homeserver_devices.owner_user_id`
- `homeserver_pairing_codes.owner_user_id`

This is compatible with the approved v1 decision that the Microgifter account owns the Microgifter connection, because the current account authority is user-based.

However, the server must distinguish:

- owning Microgifter account;
- administrator who created the Sync Code;
- administrator currently managing the connection;
- merchant workspace assignments;
- location/site assignments;
- physical HomeServer installation;
- unrelated provider connections.

Current risks:

- Any signed-in user can create a pairing code under their personal user ID.
- A team member who inherits merchant access from a workspace owner's subscription could be incorrectly treated as the connection owner if the future gate checks only `merchant_access`.
- Current pairing codes do not record `created_by_user_id`, requested device name, requested merchant scope, requested site scope, or pairing request ID.

Recommended v1 rule:

- The connection remains owned by the canonical account user ID.
- A team member may manage it only through an explicit HomeServer management permission.
- Delegated actions must record the acting user separately.
- Do not create a duplicate account-ownership abstraction until Microgifter introduces a canonical organization account table.

### 3.3 Package and subscription authority

Existing authority:

- `includes/package-entitlements.php`
- `includes/account/subscription-authority.php`
- `platform_account_subscriptions`
- `platform_subscription_packages`
- complimentary subscription grants
- Stripe subscription lifecycle integration

Current strengths:

- `platform_account_subscriptions` is the canonical package source.
- Active direct subscriptions and workspace-owner subscriptions are resolved centrally.
- The entitlement context distinguishes free, paid, complimentary, and administrative access.
- Current active statuses include `active`, `trialing`, `cancel_pending`, and `past_due`.
- The context exposes package features and limits.
- The subscriptions page loads the canonical authority helper before rendering.

Current gaps:

- HomeServer is not represented as a machine-readable package capability.
- Current package features are primarily human-readable strings.
- Current limits do not include HomeServer devices, connections, update channels, or cloud capabilities.
- `merchant_access` cannot be used as the HomeServer gate because it also covers workspace-derived and complimentary access.
- `is_paid` alone is also insufficient because enterprise grants, pilots, internal testing, and future account-specific overrides require explicit policy.

Recommended capability keys:

- `homeserver.download`
- `homeserver.pair`
- `homeserver.cloud_sync`
- `homeserver.operational_data`
- `homeserver.agent_actions`
- `homeserver.feature_updates`
- `homeserver.beta_updates`
- `homeserver.device_limit`

Recommended initial commercial policy:

- Every active paid Starter, Growth, Pro, or Enterprise account receives the base HomeServer capabilities.
- The initial launch can use one active HomeServer device per owning account until product pricing defines additional device limits.
- Complimentary and internal accounts receive HomeServer only through an explicit capability grant.
- Workspace team members do not own a HomeServer connection merely because they inherit merchant access.
- Super administrators may bypass commercial entitlement only for support, release validation, or internal testing, with an audit receipt.

Recommended persistence model:

- Add a package capability table rather than relying on display strings.
- Support account-specific capability overrides for pilots, support, enterprise contracts, and temporary exceptions.
- Resolve one canonical HomeServer entitlement context used by UI, pairing, download, lease issuance, update authorization, and device limits.

### 3.4 Account and subscription UI

Existing pages:

- `account-subscriptions.php`
- `includes/account/subscriptions-view.php`
- `account-homeserver.php`
- `includes/account/homeserver-view.php`
- `assets/js/homeserver-account.js`
- `assets/css/homeserver-account.css`

Current strengths:

- A complete standalone HomeServer management page already exists.
- It supports Sync Code creation, connected-device listing, revocation, and operational grants/campaign policies.
- The management page clearly describes local/cloud authority boundaries.
- The main subscription page already has a strong current-package layout and can host a HomeServer management card.

Current gaps:

- The HomeServer management page is not gated by a HomeServer capability.
- The HomeServer panel is not currently presented as part of the paid subscription lifecycle.
- The page uses “pairing code” rather than the customer-facing “Microgifter Sync Code” terminology.
- The device list cannot rename, replace, reconnect, rotate, or retire a device.
- It does not show entitlement state, lease expiration, update channel, update status, last synchronization, or device allowance.
- Free accounts can reach the page directly.

Recommended UI structure:

1. Add a **HomeServer** card to `My Subscription`.
2. Show the card only when the account has `homeserver.download`, or show a locked sales/upgrade state to Free accounts.
3. Reuse `/account-homeserver.php` as the full management destination.
4. Keep the quick modal for status only.
5. Do not duplicate the full management interface inside the modal.

Subscription card content:

- Included with current package
- Latest available version
- Installed version when paired
- Device allowance and active-device count
- Connection state
- Last synchronization
- Update status
- Download HomeServer
- Generate Sync Code
- Manage HomeServer

### 3.5 Red/green status indicator and modal

Existing implementation:

- `assets/js/homeserver-status-indicator.js`
- `assets/css/homeserver-status-indicator.css`
- globally loaded by `includes/header-components/app-header.php`

Current strengths:

- Green means an active device checked in within ten minutes.
- Red covers not paired, stale, revoked, degraded, and unavailable states.
- The modal already separates cloud connection from local service health.
- It lists paired devices, versions, last-seen time, and installer status.
- It links to HomeServer management and Release Center administration.

Current gaps:

- It loads for every authenticated account.
- It retrieves release metadata without a paid capability check.
- It shows a download link whenever a release is published.
- It collapses multiple conditions into a single online/offline calculation.
- It does not represent grace, suspended, replacing, update failed, lease expired, or unsupported version.
- It has no explicit subscription-attention state.
- It compares versions in the browser rather than consuming a server-calculated update state.

Recommended modal states:

**Green**

- paired;
- active entitlement;
- recent heartbeat;
- successful synchronization;
- supported/current version.

**Amber**

- payment grace;
- update available;
- stale synchronization;
- maintenance deferred;
- replacement pending;
- lease nearing expiration.

**Red**

- not paired;
- revoked;
- suspended;
- unsupported version;
- update failure or rollback;
- credential recovery required;
- device identity conflict.

The server should return one normalized status object. The browser should not infer the entire lifecycle from `status` and `last_seen_at` alone.

### 3.6 Operational data and campaign authority

Existing migration:

- `database/20260728_homeserver_operational_intelligence_campaign_authority_v1.sql`

Existing tables:

- `homeserver_dataset_grants`
- `homeserver_operational_export_receipts`
- `homeserver_campaign_authorizations`
- `homeserver_campaign_action_receipts`

Current strengths:

- Dataset grants are separate from device endpoint scopes.
- Merchant authority remains canonical in Microgifter.
- Sensitive fields and use restrictions are modeled.
- Campaign execution requires separate merchant-owned policies.
- Action receipts are durable and idempotent.
- Merchant/site fields already exist in the data-grant model.

Current gap requiring correction in future migrations:

The migration updates every active device's `scopes_json` to the same full endpoint scope list. Future deployments must not silently rewrite existing device capabilities or permissions. Capability expansion should be negotiated and granted through versioned policy, not a blanket migration update.

Recommended reuse:

- Keep these tables as the authority for operational datasets and campaign action policies.
- Add explicit device-to-merchant/site assignments rather than inferring assignments only from grants.
- Link assignment scope into entitlement leases and capability responses.
- Preserve the rule that device scope alone does not grant a dataset or executable campaign authority.

### 3.7 HomeServer Release Center

Existing implementation:

- `admin/homeserver-releases.php`
- `api/admin/homeserver-releases.php`
- `includes/homeserver-releases.php`
- `database/20260727_homeserver_release_distribution_v1.sql`
- `homeserver_releases`
- `homeserver_release_downloads`

Current strengths:

- Protected by `admin.settings.manage`.
- Stores installers outside the application and public web roots.
- Uses a persistent storage root that survives ordinary application deployment.
- Validates upload errors, maximum size, `.exe` extension, `MZ` header, MIME type, and SHA-256.
- Rechecks the stored checksum.
- Supports stable, beta, and preview channels.
- Supports x64 and arm64 metadata.
- Stores version, minimum supported version, mandatory flag, release notes, size, and checksum.
- Supports draft, published, latest, and retired workflows.
- Tracks initial user downloads.

Critical gaps:

1. Upload defaults to immediate publication because `publish_now` defaults true.
2. Publishing does not require a second approval.
3. No Authenticode signature verification occurs.
4. No approved signer thumbprint is verified.
5. The embedded executable product/version is not verified.
6. No Ed25519 manifest signature is generated.
7. No manifest key ID is stored.
8. No signed manifest payload/hash is stored.
9. No immutable publication receipt exists.
10. No release classification exists for bootstrap, security, maintenance, feature, or preview.
11. No rollout percentage or rollout cohort exists.
12. No withdrawn/unsafe/paused release state exists.
13. No server-side adoption, installation, failure, or rollback telemetry exists.
14. ARM64 is offered as metadata even though a certified ARM64 production installer has not been confirmed.
15. The storage provider currently supports only `persistent_local`.

Required correction:

- New uploads must default to `draft`.
- Publishing must fail closed unless the release passes all signing and metadata checks.
- Promotion to Preview, Beta, or Stable must be an explicit administrator action.
- A published release must become immutable.
- Retiring or withdrawing a release must not rewrite its historical manifest or audit evidence.

### 3.8 Initial installer download

Existing endpoints:

- `api/homeserver/latest-release.php`
- `api/homeserver/download.php`

Current behavior:

- Both require only an authenticated Microgifter user session.
- Release metadata is ordinary unsigned JSON.
- Download is streamed from the main Microgifter application.
- Download tracking records the user, hashed daily IP, user agent, and referer.
- The endpoint explicitly sends `Accept-Ranges: none`.

Current gaps:

- No HomeServer paid capability is required.
- No device allowance is checked.
- No initial-download authorization receipt is issued before streaming.
- No short-lived signed download URL exists.
- No resumable range support exists.
- The main PHP application carries the entire installer download load.

Recommended use:

- Keep this flow for the first paid-account installer download, but add HomeServer capability enforcement.
- Prefer issuing a short-lived signed distribution URL after entitlement verification.
- Keep account/user download tracking in Microgifter.
- Serve the large immutable installer from the update distribution host or object storage.

### 3.9 Automatic update distribution

Current status:

- The HomeServer client already expects a signed HTTPS update manifest under `updates.microgifter.com`.
- The Microgifter repository does not yet publish that signed manifest.
- The current latest-release endpoint is not compatible with a Windows LocalSystem updater because it requires a browser account session and returns unsigned release JSON.

Required separation:

- `microgifter.com/admin/homeserver-releases.php`: human administrator control surface.
- Microgifter account APIs: entitlement, pairing, device identity, heartbeat, and update authorization.
- `updates.microgifter.com`: machine-facing signed manifests, immutable installers, checksums, and release notes.

Recommended distribution layout:

- `/homeserver/releases/{version}/{architecture}/Microgifter-HomeServer-v{version}-Setup.exe`
- `/homeserver/releases/{version}/{architecture}/SHA256SUMS.txt`
- `/homeserver/releases/{version}/{architecture}/release-notes.txt`
- `/homeserver/stable/manifest.json`
- `/homeserver/beta/manifest.json`
- `/homeserver/preview/manifest.json`

The exact manifest and authorization flow must be locked to the contract produced by the HomeServer Phase 6A agent.

### 3.10 Deployment isolation

The current installer storage already survives normal application releases because it is outside the application directory.

The next phase must additionally ensure:

- website deployment cannot alter device identities;
- website deployment cannot reset credentials;
- website deployment cannot clear pairing codes or grants;
- website deployment cannot change a device's update channel;
- website deployment cannot overwrite signed manifests;
- release assets are immutable once published;
- the update host remains available during a main-site deployment or outage;
- schema migrations are additive and idempotent;
- no blanket permission rewrite is performed on existing devices.

## 4. Reuse, extend, and add matrix

### Reuse directly

- `mg_user_package_context()` as the starting subscription authority.
- Existing device authentication and Ed25519 request verification.
- Existing nonce replay protection.
- Existing device ownership checks.
- Existing dataset grants.
- Existing campaign authorizations and action receipts.
- Existing HomeServer management page shell.
- Existing red/green modal shell.
- Existing Release Center page and administrator permission.
- Existing protected persistent storage.
- Existing release and download records.

### Extend carefully

- `homeserver_devices`
- `homeserver_pairing_codes`
- `homeserver_releases`
- HomeServer management API responses
- subscription capability resolution
- status modal payload
- release upload and publish actions
- download authorization and delivery
- device heartbeat and receipts

### Add

- machine-readable HomeServer package capabilities;
- account capability overrides;
- device/merchant/site assignment records;
- pairing request and idempotency records;
- credential rotation records;
- signed entitlement leases;
- lease signing-key registry and rotation support;
- normalized heartbeat/status records;
- update authorization records;
- update offer/install/failure/rollback receipts;
- signed release manifest fields;
- release classification and rollout controls;
- `updates.microgifter.com` publisher/distribution service;
- contract fixtures shared with the HomeServer repository.

### Do not duplicate

- account subscription authority;
- pairing code system;
- device identity table;
- request-signature verification;
- operational dataset grant system;
- campaign authorization system;
- administrator Release Center;
- protected installer storage.

## 5. Proposed additive data model

Final column and table names must follow the HomeServer Phase 6A contract. The following is the recommended server-side shape.

### 5.1 Package capabilities

`platform_subscription_package_capabilities`

- `package_id`
- `capability_key`
- `enabled`
- `value_json`
- timestamps

`platform_account_capability_overrides`

- `user_id`
- `capability_key`
- `override_state`
- `value_json`
- `starts_at`
- `expires_at`
- `reason`
- `created_by_user_id`
- timestamps

### 5.2 Device lifecycle extension

Additive fields or companion table for:

- connection contract version
- entitlement state
- derived connection state
- selected update channel
- last heartbeat
- last successful synchronization
- last update check
- last offered version
- last installed version
- last update result
- current lease ID
- lease expiration
- credential rotation time
- replacement state
- last sanitized error code

Keep the existing `active/revoked` credential-validity status for backward compatibility. Do not overload it with every operational condition.

### 5.3 Device assignments

`homeserver_device_assignments`

- device ID
- owning account user ID
- merchant user/workspace ID
- tenant ID
- site/location ID
- assignment state
- approved by user
- approved time
- revoked time
- metadata

Assignments define scope; dataset grants and campaign authorizations define allowed uses.

### 5.4 Pairing requests

Extend pairing codes or add `homeserver_pairing_requests` with:

- public request ID
- owner account user ID
- created-by user ID
- code hash
- requested device name
- requested merchant/site scope
- idempotency key
- expiration
- consumed time
- consumed device ID
- completion receipt ID
- recoverable exchange state

### 5.5 Entitlement leases

`homeserver_entitlement_leases`

- public lease ID
- account user ID
- device ID
- provider/connection ID
- schema version
- issued time
- not-before time
- expiration time
- subscription state
- capabilities JSON
- merchant/site scope JSON
- update eligibility JSON
- allowed channels JSON
- minimum supported version
- signing key ID
- payload hash
- signature
- status
- revoked/replaced time
- timestamps

The private signing key must not be stored in this table.

### 5.6 Update authorization and receipts

`homeserver_update_authorizations`

- device ID when applicable
- release ID
- account user ID when applicable
- update class
- authorization result
- reason code
- signed URL/token reference
- expiration
- request ID
- created time

`homeserver_update_receipts`

- device ID
- release ID
- offered/downloaded/verified/installed/failed/rolled_back disposition
- installed version before/after
- sanitized error code
- request ID
- event time
- metadata without private local content

### 5.7 Release signing extension

Add to `homeserver_releases` or companion signing table:

- update class
- signer certificate subject
- Authenticode signer thumbprint
- Authenticode verification state/time
- embedded product/version verification state
- manifest schema version
- manifest key ID
- manifest payload hash
- manifest signature
- manifest publication state/time
- immutable asset URL
- checksum file URL
- release notes URL
- rollout state
- rollout percentage
- minimum/maximum eligible version
- withdrawn/unsafe state and reason

## 6. Proposed Microgifter API surface

Do not implement final names until the HomeServer Phase 6A contract arrives. The current server should be prepared to supply versioned operations for:

- Sync Code creation
- Sync Code exchange
- device registration/recovery
- device listing
- device rename
- device heartbeat
- capability negotiation
- entitlement lease issue/refresh
- credential rotation
- merchant/site assignments
- dataset grants
- campaign authorizations
- update authorization
- update result receipts
- revocation
- replacement
- support diagnostics

The existing unversioned endpoints must remain available during a compatibility period.

## 7. Proposed implementation phases

### Microgifter Phase A — Entitlement and ownership enforcement

- Add machine-readable HomeServer capabilities.
- Create one canonical HomeServer entitlement resolver.
- Enforce capability checks on pairing-code creation, installer metadata, installer download, and management actions.
- Resolve the owning account separately from the acting administrator.
- Enforce initial device limit.
- Add HomeServer subscription card.
- Gate direct `/account-homeserver.php` access.
- Update modal behavior for Free and paid accounts.

### Microgifter Phase B — Contracted pairing and device lifecycle

After the HomeServer contract is delivered:

- Add versioned routes and schemas.
- Add pairing idempotency/recovery.
- Add capability negotiation.
- Add structured heartbeat.
- Add connection-state normalization.
- Add credential rotation.
- Add device replacement.
- Add merchant/site assignment records.
- Preserve old routes during transition.

### Microgifter Phase C — Signed entitlement leases

- Provision platform lease-signing key configuration.
- Add public key/key ID registry.
- Issue and refresh signed leases.
- Map subscription lifecycle to active, grace, suspended, canceled, and recovery behavior.
- Add lease audit and revocation.
- Add offline validity windows.

### Microgifter Phase D — Release Center hardening

- Default upload to draft.
- Verify signed release evidence.
- Verify Authenticode signer and thumbprint.
- Verify embedded version/product.
- Store manifest signing metadata.
- Add update class and rollout controls.
- Add pause/withdraw/unsafe actions.
- Make published assets immutable.
- Require explicit promotion.

### Microgifter Phase E — `updates.microgifter.com`

- Build a separate machine-facing distribution boundary.
- Publish immutable installers and release metadata from the existing Release Center.
- Generate exact signed manifests required by HomeServer.
- Add resumable downloads or object-storage/CDN delivery.
- Add device-authorized feature update delivery.
- Preserve public bootstrap/security recovery access where required.

### Microgifter Phase F — Integration certification

Test the full lifecycle:

- Free account cannot download or pair.
- Paid account receives HomeServer panel.
- Initial installer download is authorized and tracked.
- Sync Code pairs one device.
- Lost pairing response recovers idempotently.
- Restart preserves pairing.
- HomeServer software update preserves pairing.
- Main Microgifter deploy preserves pairing.
- Valid offline lease remains usable.
- Past-due account enters grace.
- Suspension pauses paid cloud capabilities without deleting local data.
- Reactivation restores service without re-pairing.
- Security update remains available during suspension/recovery.
- Feature update requires entitlement.
- Invalid manifest, checksum, or signer is rejected.
- Failed install reports rollback.
- Device replacement preserves allowance and audit history.
- Revoking Microgifter does not disturb another provider.

## 8. Immediate corrective priorities

The following should be addressed first when the Microgifter implementation begins:

1. Add the canonical HomeServer capability resolver.
2. Gate pairing-code creation.
3. Gate latest-release metadata and initial download.
4. Gate direct HomeServer management access.
5. Stop showing an active download link to ineligible accounts.
6. Change Release Center upload default from publish to draft.
7. Stop future migrations from blanket-rewriting active device scopes.
8. Lock the HomeServer agent's exact contract before creating versioned routes.
9. Add signing and update-distribution fields before publishing any production update feed.
10. Keep production SQL import and release publication separate from code deployment.

## 9. Production-state limitations of this audit

This audit verifies repository code on `integration-from-repair-20260628`.

It does not confirm:

- whether `database/20260727_homeserver_release_distribution_v1.sql` is imported in production;
- whether `database/20260728_homeserver_operational_intelligence_campaign_authority_v1.sql` is imported in production;
- whether a real signed HomeServer installer has been uploaded;
- whether any release is currently published/latest;
- whether `updates.microgifter.com` DNS, TLS, storage, or hosting exists;
- whether production persistent storage is initialized and writable;
- whether a permanent Ed25519 release key and Windows Authenticode certificate are provisioned.

Those items require deployment/admin verification and must not be inferred from merged code.

## 10. Required handoff from the HomeServer Phase 6A agent

Before Microgifter server implementation begins, obtain:

- PR number and final head SHA;
- exact versioned routes;
- request and response schemas;
- authentication and signing format;
- pairing idempotency behavior;
- capability registry;
- connection-state registry;
- entitlement lease schema/signature format;
- heartbeat payload;
- update authorization request/response;
- update receipt format;
- merchant/site assignment format;
- credential rotation format;
- device replacement format;
- error-code registry;
- mock provider fixtures;
- exact tests and CI results.

The Microgifter build should implement that contract without guessing and without duplicating the existing cloud foundation.

## 11. Final audit decision

The repository is ready to begin the coordinated Microgifter server phase after the HomeServer Phase 6A contract stabilizes.

The recommended project title is:

> **Microgifter HomeServer Subscription, Pairing, Entitlement and Signed Update Service**

The build should extend the existing pairing, release, subscription, status, operational-grant, and campaign-authority systems. It should not create a second Release Center, a second device registry, or a second pairing system.
