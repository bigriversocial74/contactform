# Loyalty Quest Abuse, Fraud, and Integrity Controls v1

## Purpose

Loyalty Quest Integrity adds coordinated abuse detection and human-review routing to the existing participant, merchant, administrator, reward, and PPPM workflows.

The system does not create a second reward, claim, Wallet, or ownership authority. A suspicious auto-verification is converted into a pending merchant review before progress or reward issuance. Reward ownership remains Microgift → PPPM → Inbox.

## Required database migration

Apply the registered migration before deploying the PHP files:

`database/loyalty_quest_integrity_controls_v1.sql`

The migration is additive and safely repeatable under MySQL 8. It adds hashed evidence attributes and integrity state to `loyalty_quest_evidence`, plus:

- `loyalty_quest_integrity_attempts`
- `loyalty_quest_integrity_signals`

No raw IP address or raw device identifier is stored in these tables.

## Required integrity pepper

Configure a production secret with at least 24 characters:

```text
MG_LOYALTY_QUEST_INTEGRITY_PEPPER=<random server-only secret>
```

Use a cryptographically random value of at least 32 bytes. The application falls back to the existing claim-code pepper only for deployment compatibility. Production should configure a dedicated integrity pepper and keep it outside GitHub.

The integrity service fails closed when the schema or pepper is unavailable.

## Request controls

Enrollment and completion submissions use the existing database-backed rate limiter. Current ten-minute limits are:

| Action | Participant | IP hash | Device hash |
|---|---:|---:|---:|
| Start | 20 | 80 | 40 |
| Submit | 10 | 40 | 20 |

The device identifier is a random HttpOnly, SameSite=Lax cookie. The stored value is an HMAC hash, not the cookie token. IP addresses are also HMAC hashed with the server-only pepper.

## Integrity signals

The v1 evaluator can create these signals:

- duplicate evidence across participants
- shared IP velocity
- shared device velocity
- rapid completion
- repeated rejected evidence
- high daily quest-completion velocity
- impossible geolocation travel
- static, staff, or event code velocity

Signals have a severity and score. Any critical signal, or a combined score of 50 or more, routes the completion to merchant review.

Thresholds are deliberately conservative. They identify activity requiring review; they do not prove fraud.

## Participant behavior

A low-risk verified submission follows the existing flow and may increment progress or issue the reward.

A high-risk verified submission:

1. stores evidence as `submitted`
2. stores integrity score and signals
3. changes participation to `pending_review`
4. does not increment progress
5. does not issue a reward
6. notifies the merchant through the existing review flow

Participant responses disclose only that review is required. They do not disclose signal rules, hashes, other participants, or internal thresholds.

## Merchant review

The merchant evidence queue displays:

- integrity score and review state
- signal type and severity
- a limited operational context such as counts, timing, distance, or speed

It never displays raw IP addresses, device identifiers, HMAC hashes, QR secrets, claim codes, or another participant’s evidence.

Approving evidence with a score of 50 or more requires an explicit merchant acknowledgment. A confirmed administrator signal sets evidence to `blocked`; merchant approval must remain unavailable until an administrator clears the confirmed signal.

## Administrator integrity center

Open:

`/admin/loyalty-quest-integrity.php`

Read access uses `admin.operations_command.view`. Integrity resolutions require `admin.operations_command.manage`, CSRF validation, and an administrator reason between 12 and 1000 characters.

Administrators may:

- acknowledge a signal without deciding whether it is valid
- clear a false positive or resolved concern
- confirm an abuse signal and block pending merchant approval
- clear a previously confirmed signal after documented re-review

Each decision creates a campaign event and platform audit record.

## Privacy boundary

The administrator interface exposes only:

- masked participant email
- public campaign, participation, evidence, and signal identifiers
- signal type, severity, score, state, and safe aggregate context

It excludes:

- raw IP addresses
- device tokens
- HMAC fingerprints and source hashes
- proof notes and proof URLs
- precise submitted coordinates
- signed QR payloads and nonces
- claim codes and voucher tokens
- passwords, cookies, and authorization values

## Deployment order

1. Configure `MG_LOYALTY_QUEST_INTEGRITY_PEPPER`.
2. Run the registered migration.
3. Deploy the PHP, JavaScript, and CSS files.
4. Open the merchant review queue and administrator integrity center.
5. Test one low-risk verified completion.
6. Test one seeded high-risk completion and confirm it remains pending review without a reward.
7. Confirm and then clear an integrity signal as an administrator.
8. Verify audit, campaign-event, and security-log evidence.

## Operational limitations

The v1 rules are deterministic and explainable. They do not use device fingerprinting libraries, third-party identity graphs, facial recognition, or opaque machine-learning fraud scores.

Shared networks and shared devices can produce false positives. Human review is therefore required before adverse action. Confirmed signals block pending approval but do not revoke previously issued Microgifts or alter PPPM ownership.

Browser cookie behavior, proxy configuration, real administrator and merchant role assignments, production alert delivery, threshold tuning, and production-volume query performance require environment-specific verification after deployment.
