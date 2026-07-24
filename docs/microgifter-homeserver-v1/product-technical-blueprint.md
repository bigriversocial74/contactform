# Microgifter HomeServer v1
## Reconciled Product and Technical Blueprint

**Document status:** Authoritative approved product direction  
**Planning date:** 2026-07-24  
**Product:** Microgifter HomeServer  
**Primary customer release:** Windows one-click installer  
**Primary installer name:** `Microgifter-HomeServer-Setup.exe`  
**Primary desktop platform:** Windows 11 x64  
**Primary repository direction:** Dedicated HomeServer repository  
**Current document location:** Canonical holding copy in `bigriversocial74/contactform` until the dedicated repository is created and connected  
**Implementation status:** Not started  
**SQL status:** No SQL required for this documentation-only change  
**Deployment status:** Not deployed

---

# 1. Executive Product Definition

Microgifter HomeServer is a private, locally installed extension of the Microgifter cloud platform. It gives merchants, creators, offices, studios, venues, hospitality businesses, community organizations, and other professional users local control over AI models, private business knowledge, automations, integrations, synchronized operational data, and approved agent access.

The standard customer edition is installed through one branded Windows executable:

`Microgifter-HomeServer-Setup.exe`

The installer provides a guided Microgifter setup experience and installs every required runtime and service automatically. Standard customers must not need to install or configure Docker Desktop, PHP, Node.js, a database engine, a reverse proxy, or development tooling.

HomeServer does not replace Microgifter.com. The cloud platform remains authoritative for identity, shared commerce, payments, campaigns, reward inventory, wallet ownership, PPPM ownership, claims, redemption, permissions, and central audit history. HomeServer extends the platform with local computing, private knowledge, optional local AI, offline-capable operations, business integrations, and a secure local MCP gateway.

The customer installs Microgifter, not an infrastructure stack.

---

# 2. Reconciliation With the Initial HomeServer Direction

The initial HomeServer concept established the following direction:

- A private local Microgifter server.
- Cloud registration and synchronization.
- Local AI and agent execution.
- Local business knowledge storage.
- Model management.
- Background services.
- Offline operation.
- Backup and recovery.
- Docker-based deployment.
- A future certified Microgifter appliance.

This blueprint preserves those goals while changing the primary customer delivery method.

## 2.1 Previous primary direction

Docker Compose first.

## 2.2 Approved primary direction

Windows EXE first for standard customers.

## 2.3 Docker remains part of the architecture

Docker and container-compatible services remain appropriate for:

- Internal development.
- Automated testing.
- Linux installations.
- NAS installations.
- Cloud environments.
- Advanced technical deployments.
- Certified Microgifter hardware.
- Future plug-and-play HomeServer appliances.

Docker Desktop is not required for the standard Windows customer edition.

---

# 3. Product Goals

Microgifter HomeServer v1 must:

1. Install through a branded one-click Windows setup application.
2. Run continuously through native Windows services.
3. Provide a customer-friendly desktop Control Center.
4. Connect securely to the owner’s Microgifter account.
5. Store local business knowledge and private files.
6. Support optional local AI models.
7. Provide a local MCP gateway for approved agents and harnesses.
8. Synchronize explicitly approved Microgifter data.
9. Continue selected local operations during temporary internet outages.
10. Queue eligible outbound work for later synchronization.
11. Provide automatic backups and guided recovery.
12. Install signed and verified updates safely.
13. Hide unnecessary infrastructure complexity from customers.
14. Preserve Microgifter cloud authority over shared commerce.
15. Provide clear diagnostics, health monitoring, repair, and support tools.
16. Support a future Docker/Linux/NAS edition without rewriting core business services.
17. Support a future certified appliance using the same product contracts.

---

# 4. Product Non-Goals

HomeServer v1 is not:

- A replacement for the Microgifter cloud platform.
- A second payment processor.
- A separate user-account architecture.
- A second reward, claim, redemption, wallet, or ownership lifecycle.
- A public internet server by default.
- A blockchain or cryptocurrency node.
- A general consumer smart-home server.
- A mandatory requirement for using Microgifter.
- A system that permits local agents to bypass Microgifter rules.
- A customer-managed Docker environment.
- A full enterprise data warehouse in the first release.
- A system that permits unsupported offline financial transactions.
- A public file-sharing server.
- A replacement for the existing Cloud MCP authority over Microgifter commerce.

---

# 5. Target Users and Environments

HomeServer is intended for:

- Restaurants and hospitality businesses.
- Retail merchants.
- Artists and creators.
- Music studios and production companies.
- Venues and event operators.
- Offices and workplace reward programs.
- Community organizations.
- Multi-location businesses.
- Agencies and business-service providers.
- Advanced Microgifter merchants with private AI or data requirements.

HomeServer may run on:

- A merchant’s primary Windows workstation.
- A dedicated office computer.
- A small-business Windows server.
- A certified Microgifter hardware device in a later release.
- A Linux server through the Docker edition.
- A supported NAS through the Docker edition.

---

# 6. Supported Platforms

## 6.1 Initial customer release

- Windows 11 x64.
- Modern Intel or AMD 64-bit processor.
- Administrator access during installation.
- Internet access for pairing, synchronization, updates, and cloud-dependent functions.
- Approved local functions remain available during temporary internet outages.

## 6.2 Windows 10

Windows 10 support is not guaranteed for the first production release. It may be added only after installer, service, security, networking, update, and recovery testing proves reliable.

## 6.3 Future platforms

- Windows Server.
- Linux.
- Supported NAS systems.
- ARM64.
- Certified Microgifter appliances.
- Managed enterprise deployments.

---

# 7. Hardware Requirements

## 7.1 Minimum profile

- 4-core 64-bit processor.
- 8 GB RAM.
- 20 GB free storage.
- Stable local network connection.
- Internet access for cloud synchronization.
- No dedicated GPU required.

## 7.2 Recommended profile

- 8-core processor.
- 16–32 GB RAM.
- 100 GB or more available storage.
- Wired Ethernet.
- Solid-state storage.
- NVIDIA GPU for larger local AI models.

## 7.3 AI hardware behavior

The installer must not assume every computer can run a large local model.

Model Center must:

- Inspect available RAM.
- Inspect CPU capabilities.
- Detect supported GPUs.
- Estimate storage requirements.
- Recommend compatible models.
- Warn before downloading models that exceed the recommended hardware profile.
- Permit CPU-only operation.
- Support cloud-model fallback only when explicitly allowed.

Large AI models are optional downloads and are not bundled inside the main installer.

---

# 8. Distribution and Packaging

## 8.1 Windows customer package

`Microgifter-HomeServer-Setup.exe`

The installer includes:

- Microgifter HomeServer Control Center.
- Windows service supervisor.
- Local API runtime.
- Local web interface where required.
- Local database engine.
- Background worker.
- Scheduler and automation engine.
- Synchronization service.
- Secure secrets manager.
- Knowledge Vault service.
- MCP gateway.
- Backup and recovery service.
- Update manager.
- Health monitor.
- Logging and diagnostics tools.
- Required bundled runtimes.
- Uninstaller and repair support.

## 8.2 Future packages

Possible later packages include:

- `microgifter-homeserver-docker.zip`
- Docker Compose configuration.
- Linux installation package.
- NAS-specific deployment package.
- Certified appliance image.
- Enterprise-managed installer.

---

# 9. Installer Experience

The installer must use Microgifter branding rather than exposing a generic infrastructure setup process.

## 9.1 Installer screens

1. Welcome to Microgifter HomeServer.
2. Product overview.
3. License and privacy terms.
4. System compatibility check.
5. Quick Install or Custom Install.
6. Installation location.
7. Data-storage location.
8. HomeServer name.
9. Local access options.
10. Cloud pairing preference.
11. Optional local AI setup.
12. Installation progress.
13. Service initialization.
14. Readiness verification.
15. Installation complete.
16. Launch Microgifter HomeServer.

## 9.2 Quick Install

Quick Install uses safe defaults:

- Standard application directory.
- Standard data directory.
- Automatic service startup.
- Local-computer access.
- Private LAN access disabled until explicitly enabled.
- Automatic backups enabled.
- Stable update channel.
- Local AI model download deferred.
- Cloud pairing started after installation.
- Control Center launched automatically.

## 9.3 Custom Install

Custom Install permits:

- Custom application directory.
- Custom data directory.
- Backup destination.
- Private local-network access.
- Service startup preference.
- Update channel.
- Optional model installation.
- Advanced port settings.
- Proxy configuration.
- Cloud pairing during or after setup.

## 9.4 Installation failure

When installation fails, the user must receive:

- A plain-language explanation.
- The failed installation stage.
- A support code.
- A redacted diagnostic log.
- Retry, repair, or rollback options.

---

# 10. Desktop Control Center

The HomeServer Control Center is the primary customer interface.

## 10.1 Recommended implementation

- Tauri desktop application.
- Native Windows desktop shell.
- Local secure API communication.
- No external browser required for standard administration.
- Optional browser access for approved local-network devices.

## 10.2 Primary navigation

1. Overview
2. Services
3. Microgifter Connection
4. Agents
5. MCP Access
6. Model Center
7. Knowledge Vault
8. Automations
9. Storage
10. Backups
11. Network
12. Users and Permissions
13. Updates
14. Logs and Support
15. Settings

## 10.3 Overview requirements

The Overview page must answer:

- Is HomeServer running?
- Is it connected to Microgifter?
- When did it last synchronize?
- Are changes waiting to synchronize?
- Is the latest backup healthy?
- Are all services operational?
- Is an update available?
- How much storage remains?
- Which AI model is active?
- Are there security or configuration warnings?

## 10.4 Service states

Use customer-readable states:

- Running
- Starting
- Stopped
- Updating
- Offline
- Needs attention
- Repairing
- Backup in progress
- Synchronizing

---

# 11. Native Windows Service Architecture

The standard Windows release uses native Windows services.

## 11.1 Service supervisor responsibilities

- Start HomeServer automatically with Windows.
- Start child services in the correct dependency order.
- Monitor service health.
- Restart recoverable failed services.
- Prevent repeated crash loops.
- Coordinate updates.
- Coordinate backups.
- Maintain service logs.
- Report health to the Control Center.
- Stop services safely during uninstall or repair.

## 11.2 Core service

Responsible for:

- Installation identity.
- Configuration.
- Local authentication.
- Service coordination.
- Health reporting.
- Secure local API access.
- Update and repair coordination.

## 11.3 Local API service

Responsible for:

- Control Center requests.
- Local integrations.
- MCP requests.
- Knowledge access.
- Model routing.
- Synchronization queues.
- Local permissions.

## 11.4 Database service

Responsible for:

- HomeServer configuration.
- Synchronization metadata.
- Local knowledge indexes.
- Automation state.
- Agent registrations.
- Audit receipts.
- Backup metadata.
- Update history.

## 11.5 Worker service

Responsible for:

- Background jobs.
- Scheduled automations.
- File indexing.
- Embedding generation.
- Sync queue processing.
- Backup processing.
- Model downloads.
- Maintenance tasks.

## 11.6 Synchronization service

Responsible for:

- Secure Microgifter cloud communication.
- Approved inbound synchronization.
- Approved outbound event delivery.
- Retry queues.
- Conflict detection.
- Connection health.
- Synchronization receipts.

## 11.7 AI and model service

Responsible for:

- Local model discovery.
- Model lifecycle.
- Hardware compatibility.
- Prompt routing.
- Resource controls.
- Cloud fallback when allowed.
- Model health.

## 11.8 MCP gateway

Responsible for:

- Agent authentication.
- Capability discovery.
- Permission enforcement.
- Local resource access.
- Cloud API mediation.
- Approval requirements.
- Rate limits.
- Audit receipts.

---

# 12. Repository Strategy

HomeServer should use a dedicated repository rather than becoming a permanent application directory inside `bigriversocial74/contactform`.

Recommended repository:

`bigriversocial74/microgifter-homeserver`

## 12.1 HomeServer repository responsibilities

- Windows installer.
- Desktop Control Center.
- Native Windows service.
- Local API.
- Local database schema.
- Sync client.
- MCP gateway.
- Model Center.
- Knowledge Vault.
- Backup and recovery.
- Update client.
- HomeServer documentation.
- Packaging and release workflows.

## 12.2 Contactform repository responsibilities

The `contactform` repository remains responsible for:

- Microgifter cloud APIs.
- Cloud authentication.
- Merchant accounts.
- Campaigns.
- Rewards.
- Wallet.
- PPPM.
- Microgift ownership.
- Claims.
- Redemption.
- Cloud MCP rules.
- Cloud synchronization endpoints.
- Device registration.
- HomeServer administration from the cloud side.

HomeServer communicates with `contactform` through documented authenticated APIs.

## 12.3 Current holding-document rule

Until the dedicated repository is created and connected, this file is the authoritative HomeServer v1 product and architecture document. When the dedicated repository becomes available, this document should be copied into that repository without changing the approved product decisions, then replaced here with a pointer to the canonical location.

---

# 13. HomeServer Identity and Pairing

Each installation receives:

- Unique HomeServer ID.
- Owner-selected server name.
- Installation key pair.
- Device registration state.
- Owner account association.
- Installation timestamp.
- Application version.
- Operating-system metadata.
- Update channel.
- Environment designation.
- Cloud connection state.

## 13.1 Pairing flow

1. Install HomeServer locally.
2. Complete local setup.
3. Sign in to Microgifter or enter a pairing code.
4. Microgifter displays the requesting HomeServer.
5. The owner approves the device.
6. HomeServer and cloud exchange scoped credentials.
7. HomeServer receives only approved access.
8. Pairing receipts are stored locally and in the cloud.

Local installation must be possible before cloud pairing.

Cloud-dependent functions remain unavailable until pairing is complete.

## 13.2 Device revocation

The owner or authorized administrator must be able to:

- Revoke HomeServer access from Microgifter.com.
- Revoke cloud access from the Control Center.
- Rotate device credentials.
- Review recent connection activity.
- Require re-pairing after a security event.

---

# 14. Cloud and HomeServer Data Authority

Microgifter cloud and HomeServer must never silently compete over authoritative records.

## 14.1 Cloud-authoritative records

The Microgifter cloud remains authoritative for:

- User identity.
- Authentication state.
- Merchant accounts.
- Roles and shared permissions.
- Payments.
- Purchases.
- Public campaigns.
- Reward inventory.
- Wallet ownership.
- PPPM ownership.
- Microgift lifecycle.
- Claims.
- Redemption.
- Public profiles.
- Shared notifications.
- Commerce audit history.
- Account suspension and access state.

## 14.2 HomeServer-authoritative records

HomeServer is authoritative for:

- Local server configuration.
- Local encryption keys.
- Local model installations.
- Local model preferences.
- Private business documents.
- Local knowledge indexes.
- Local device approvals.
- Local integration credentials.
- Local automation schedules.
- Local-only agent configuration.
- Local backup history.
- Local diagnostics.
- Offline job queues before cloud acceptance.

## 14.3 Synchronized records

The following may have synchronized local copies:

- Merchant profile information.
- CRM contacts allowed by policy.
- Product and campaign summaries.
- Reward summaries.
- Approved analytics.
- Customer engagement history.
- Agent configuration.
- Business settings.
- Notification summaries.
- Reporting datasets.

A synchronized copy is not automatically authoritative.

---

# 15. Synchronization Rules

Synchronization must be:

- Authenticated.
- Encrypted.
- Scoped.
- Idempotent.
- Auditable.
- Retry-safe.
- Version-aware.
- Conflict-aware.

## 15.1 Inbound synchronization

Cloud-to-HomeServer synchronization may provide:

- Account state.
- Merchant configuration.
- Approved CRM records.
- Campaign updates.
- Reward updates.
- Agent policies.
- Permission changes.
- Device revocation.
- Cloud-generated events.

## 15.2 Outbound synchronization

HomeServer-to-cloud synchronization may provide:

- Approved local automation requests.
- Agent action requests.
- Local analytics summaries.
- Knowledge-derived recommendations when explicitly allowed.
- Configuration updates.
- Health metadata.
- Backup status.
- Audit receipts.
- Queued approved actions.

## 15.3 Conflict handling

Default rules:

- Cloud-authoritative records use cloud state.
- HomeServer-authoritative records use local state.
- Shared editable settings use version numbers and timestamps.
- High-risk conflicts require user review.
- Commerce conflicts cannot be resolved by overwriting cloud records.
- Every resolved conflict produces an audit receipt.

---

# 16. Offline Operation

HomeServer must remain useful during temporary internet outages.

## 16.1 Available offline

- Control Center.
- Local authentication.
- Local knowledge files.
- Knowledge search.
- Local AI models.
- Local model inference.
- Local automations that do not require cloud authorization.
- Local reporting from synchronized data.
- Local integrations.
- Backup and recovery.
- Service management.
- Queuing of eligible outbound requests.

## 16.2 Unavailable or deferred offline

- Payments.
- Final commerce transactions.
- Public publishing.
- New cloud account verification.
- Cross-server ownership changes.
- Claims requiring cloud validation.
- Redemption requiring cloud validation.
- Actions that depend on current inventory or permission state.
- Actions requiring cloud approval.
- Final synchronization receipts.

## 16.3 Queue states

Queued actions must be marked clearly as:

- Local only
- Waiting to synchronize
- Synchronizing
- Accepted by cloud
- Rejected by cloud
- Requires review

HomeServer must never represent a queued commerce action as complete before the cloud accepts it.

---

# 17. MCP Architecture

Microgifter supports both Cloud MCP and HomeServer MCP.

## 17.1 Cloud MCP

Cloud MCP provides authenticated access to centralized Microgifter services and remains authoritative for:

- Commerce actions.
- Campaign rules.
- Reward rules.
- Wallet and PPPM state.
- Claims.
- Redemption.
- Shared identity.
- Central permissions.
- Cloud receipts.

## 17.2 HomeServer MCP

HomeServer MCP provides controlled access to:

- Local documents.
- Local knowledge.
- Local AI models.
- Local integrations.
- Local automations.
- Approved synchronized business data.
- HomeServer diagnostics.
- Approved Microgifter cloud tools.

HomeServer MCP must not duplicate or bypass cloud commerce logic.

## 17.3 Shared enforcement requirements

All agents remain subject to:

- Authentication.
- User permissions.
- Merchant ownership.
- Campaign rules.
- Reward rules.
- Budget limits.
- Approval requirements.
- Rate limits.
- Idempotency.
- Current cloud state.
- Audit receipts.
- Security logging.

## 17.4 Agent registration

Each agent connection should have:

- Agent ID.
- Agent name.
- Harness or application name.
- Owner.
- Permission scopes.
- Allowed tools.
- Approval requirements.
- Expiration.
- Rate limits.
- Connection history.
- Revocation state.

---

# 18. Agent Capability Levels

## 18.1 Level 1: Read-only

Initial production capability:

- Search local knowledge.
- Read approved business records.
- Review synchronized campaign summaries.
- Generate recommendations.
- Draft messages.
- Analyze local files.
- Produce reports.

## 18.2 Level 2: Local actions

Allowed after focused validation:

- Organize approved files.
- Update local knowledge metadata.
- Run approved local automations.
- Generate local creative assets.
- Trigger internal workflows.
- Prepare cloud action requests.

## 18.3 Level 3: Approval-gated cloud actions

Future capability:

- Prepare campaign changes.
- Prepare customer messages.
- Prepare reward actions.
- Submit actions for owner approval.
- Send approved requests to Microgifter cloud.

## 18.4 Level 4: Policy-authorized automation

Later capability:

- Execute narrowly scoped actions under explicit rules.
- Respect budgets and limits.
- Produce receipts.
- Stop automatically when policy conditions fail.

HomeServer v1 begins with read-only and low-risk local capabilities.

---

# 19. Model Center

Model Center manages local and approved cloud AI models.

## 19.1 Initial runtime

Ollama is the recommended first local model runtime. The architecture must remain extensible to other runtimes.

## 19.2 Model Center functions

- Detect hardware.
- Browse approved models.
- Show model size.
- Show RAM and storage requirements.
- Install models.
- Pause or resume downloads.
- Remove models.
- Update models.
- Test models.
- Set the default model.
- Assign models to agents.
- Limit CPU, RAM, and GPU use.
- Configure cloud fallback.
- Display health and performance.

## 19.3 Model safety requirements

- Models cannot access HomeServer resources without permission.
- Each agent receives scoped tools and knowledge.
- Sensitive folders are denied by default.
- Prompt and output retention follows configurable policy.
- Cloud fallback must be visibly disclosed.
- Secrets must not be inserted into prompts unless explicitly required and permitted.

---

# 20. Knowledge Vault

Knowledge Vault provides private local business knowledge.

## 20.1 Supported content

- Documents.
- PDFs.
- Text files.
- Product information.
- Policies.
- Menus.
- Scripts.
- Marketing materials.
- Training materials.
- Internal procedures.
- Creative assets.
- Approved CRM exports.
- Local integration data.

## 20.2 Required features

- Folder selection.
- File indexing.
- Search.
- Metadata.
- Tags.
- Access rules.
- Re-indexing.
- File-change detection.
- Duplicate detection.
- Storage reporting.
- Knowledge-source visibility.
- Delete and retention controls.
- Backup inclusion settings.

Knowledge Vault must not expose files to agents without explicit permission.

---

# 21. Security Model

Security is a first-release requirement.

## 21.1 Core requirements

- Code-signed installer.
- Code-signed application executables.
- Signed update packages.
- Secure device pairing.
- Encrypted network traffic.
- Encrypted secrets.
- Strong password hashing.
- Local session expiration.
- Role-based access.
- Device approval.
- Rate limiting.
- CSRF protection where applicable.
- Local API authentication.
- Audit logging.
- Security logging.
- Backup encryption.
- Credential rotation.
- Safe diagnostic exports.
- Least-privilege service accounts where feasible.

## 21.2 Local API protection

The local API must:

- Bind to loopback by default.
- Require authentication.
- Use short-lived sessions or tokens.
- Reject unauthorized LAN requests.
- Use origin validation where applicable.
- Prevent browser-based cross-origin abuse.
- Limit request size.
- Rate-limit sensitive actions.
- Log denied access.

## 21.3 Private LAN access

LAN access must be disabled by default or explicitly approved during setup.

Enabling LAN access requires:

- Owner confirmation.
- Firewall configuration.
- Device approval.
- TLS or a secure local trust model.
- A visible list of authorized devices.
- Immediate revocation controls.

---

# 22. Remote Access

HomeServer must not recommend public router port forwarding.

## 22.1 Initial release

- Local computer access.
- Optional private LAN access.

## 22.2 Later release

- Microgifter-managed secure tunnel.
- Explicit owner authorization.
- Device-based access.
- Revocable sessions.
- No direct exposure of the database, model runtime, or internal MCP listener.

Remote access remains optional.

---

# 23. Backup and Recovery

## 23.1 Automatic backups

Recommended default:

- Daily encrypted backup.
- Local retention policy.
- Backup integrity checks.
- Pre-update backup.
- Backup health displayed in Control Center.

## 23.2 Backup destinations

- Local backup directory.
- External drive.
- Network storage.
- Optional encrypted Microgifter cloud backup later.

## 23.3 Recovery package

The owner should be able to create an encrypted HomeServer recovery package containing:

- Configuration.
- Database.
- Knowledge metadata.
- Approved local files.
- Agent configuration.
- Automation state.
- Installed model manifest.
- Pairing metadata where safe.
- Backup manifest.

Large model binaries may be excluded and re-downloaded after restoration.

## 23.4 Restore flow

1. Install HomeServer.
2. Choose Restore Existing HomeServer.
3. Select the recovery package.
4. Enter the recovery password or key.
5. Verify package integrity.
6. Restore data.
7. Re-pair cloud access when required.
8. Run health checks.
9. Resume services.

---

# 24. Updates

## 24.1 Update channels

- Stable.
- Beta.
- Development.

Stable is the default.

## 24.2 Update process

1. Check the update signature.
2. Verify package integrity.
3. Display release information.
4. Confirm storage requirements.
5. Create a pre-update backup.
6. Pause relevant services.
7. Install the application update.
8. Run local database migrations.
9. Restart services.
10. Run health checks.
11. Confirm success.
12. Roll back when safe if validation fails.

## 24.3 Security updates

Critical security updates may be marked urgent, but the owner should still receive clear notice unless immediate enforcement is necessary to protect the platform.

---

# 25. Repair and Uninstall

## 25.1 Repair installation

Repair mode should:

- Validate installed files.
- Restore missing components.
- Repair Windows service registration.
- Rebuild shortcuts.
- Re-run firewall configuration.
- Preserve customer data.
- Preserve pairing when safe.
- Produce a diagnostic report.

## 25.2 Uninstall choices

1. Remove application and preserve data.
2. Export backup and uninstall.
3. Remove application and local data.
4. Cancel.

The default must preserve HomeServer data.

Permanent deletion requires explicit confirmation.

---

# 26. Licensing and Editions

The architecture should support future editions without fragmenting the core application.

Possible editions:

- HomeServer Personal.
- HomeServer Merchant.
- HomeServer Business.
- HomeServer Enterprise.
- Certified Hardware Edition.

Edition differences may include:

- Number of users.
- Number of merchants.
- Local AI capabilities.
- Cloud backup.
- Remote access.
- Storage management.
- Advanced integrations.
- Support level.
- Managed updates.
- Multi-location synchronization.

Licensing must not prevent emergency local access to owned data.

---

# 27. Diagnostics and Support

The Control Center must include:

- HomeServer version.
- Installation ID.
- Windows version.
- Service health.
- Cloud connection test.
- Database health.
- Storage health.
- Backup history.
- Update history.
- Port and firewall test.
- Model runtime health.
- MCP health.
- Sync queue status.
- Recent failures.
- Service restart controls.
- One-click diagnostic package.

Diagnostic exports must redact:

- Passwords.
- API keys.
- Pairing secrets.
- Customer private data.
- Full document contents.
- Claim codes.
- Payment data.
- Sensitive personal information.

---

# 28. Release Phases

## Phase 1: Installable foundation

Deliver:

- Dedicated repository.
- Tauri Control Center shell.
- Branded NSIS installer.
- Native Windows service supervisor.
- Local API.
- Local database.
- Configuration management.
- Service health.
- Logging.
- Repair and uninstall.
- Basic update framework.

Acceptance:

- One EXE installs HomeServer.
- No Docker Desktop is required.
- HomeServer starts after reboot.
- Control Center shows service health.
- Repair preserves data.
- Uninstall preserves data by default.

## Phase 2: Cloud connection and synchronization

Deliver:

- Device identity.
- Pairing flow.
- Scoped credentials.
- Cloud connection.
- Synchronization queue.
- Offline state.
- Conflict handling.
- Audit receipts.

Acceptance:

- Owner pairs HomeServer securely.
- Revocation works.
- Cloud-authoritative records remain authoritative.
- Offline requests cannot falsely appear completed.
- Synchronization retries safely.

## Phase 3: Backup, recovery, and updates

Deliver:

- Automatic backups.
- Manual backups.
- Encrypted recovery package.
- Restore wizard.
- Signed updater.
- Pre-update backup.
- Failed-update recovery.

Acceptance:

- Backup integrity can be verified.
- HomeServer restores to another supported machine.
- Updates do not silently destroy local data.
- Failed updates produce recovery options.

## Phase 4: Knowledge Vault and Model Center

Deliver:

- File indexing.
- Knowledge search.
- Access rules.
- Ollama integration.
- Model installation.
- Model assignments.
- Hardware recommendations.
- Resource controls.

Acceptance:

- User can install a compatible model.
- Agents only access approved knowledge.
- Removing a model does not remove business documents.
- Local AI works without internet.

## Phase 5: MCP and agent runtime

Deliver:

- Local MCP gateway.
- Agent registration.
- Scoped permissions.
- Read-only tools.
- Low-risk local actions.
- Audit history.
- Cloud MCP mediation.

Acceptance:

- External harness connects using approved credentials.
- Agent cannot bypass Microgifter rules.
- Agent access can be revoked.
- Read-only and local permissions are enforced.
- Commerce remains cloud-authoritative.

## Phase 6: Advanced deployment

Deliver:

- Secure remote tunnel.
- Multi-user administration.
- Advanced integrations.
- Docker/Linux edition.
- NAS deployment.
- Certified hardware profile.
- Appliance preparation.

---

# 29. HomeServer v1 Completion Standard

HomeServer v1 is production-ready only when:

- The installer is code signed.
- Application executables are signed.
- A clean Windows 11 computer can install through one EXE.
- No separate runtime installation is required.
- Services start automatically.
- Services recover from controlled failures.
- Local API access is protected.
- Pairing and revocation work.
- Cloud authority is enforced.
- Offline behavior is accurately represented.
- Backups restore successfully.
- Updates are signed and recoverable.
- Uninstall preserves data by default.
- Knowledge permissions are enforced.
- MCP access is scoped and revocable.
- Security review is complete.
- Installation, repair, update, restore, and uninstall documentation is complete.
- Deployment status is reported accurately.
- Production readiness is not claimed solely because source code exists.

---

# 30. Locked Decisions

The following decisions are approved for HomeServer v1:

- Windows EXE is the primary customer release.
- Docker Desktop is not required for standard customers.
- Docker remains part of development and advanced deployment.
- Windows services are native in the standard release.
- The Control Center uses a native desktop shell.
- Tauri is the recommended Control Center framework.
- NSIS is the recommended Windows installer format.
- HomeServer uses a dedicated repository.
- The Microgifter cloud remains authoritative for shared commerce.
- HomeServer owns local knowledge, models, configuration, and automation state.
- HomeServer MCP cannot bypass Cloud MCP or Microgifter commerce rules.
- Local installation may occur before cloud pairing.
- Public internet exposure is disabled by default.
- Secure managed remote access is a later capability.
- Large AI models are optional downloads.
- Read-only and low-risk local agents ship before transactional automation.
- Backup, repair, update, restore, and uninstall are core product features.
- Certified hardware and appliance delivery remain future extensions of the same architecture.

---

# 31. Authoritative Product Statement

Microgifter HomeServer is a private local extension of the Microgifter cloud platform.

It gives businesses a one-click Windows installation for local AI, private business knowledge, automations, integrations, synchronized data, and secure agent access while preserving Microgifter’s centralized identity, commerce, campaign, reward, wallet, PPPM, claim, redemption, permission, and audit rules.

The customer installs Microgifter—not Docker, a database server, or a development environment.

---

# 32. Current Delivery Status

```text
Document: Microgifter HomeServer v1 Product and Technical Blueprint
Document status: Approved and committed to a docs-only branch
Implementation code: Not started
Dedicated HomeServer repository: Not yet created/connected
Windows installer: Not built
SQL: No SQL required for this documentation-only change
Code deployment: Not performed
Production deployment: Not performed
Production verification: Not performed
```
