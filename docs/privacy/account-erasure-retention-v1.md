# Microgifter Privacy Retention and Account Erasure v1

## Purpose

This phase replaces unsafe direct user deletion with a governed privacy-request lifecycle. Account closure, data erasure, required retention, merchant-controller review, legal holds, backup expiration, and completion evidence are separate operations.

There is no universal rule that every account must remain stored for 30 days. Microgifter calculates request deadlines and product grace periods by jurisdiction and keeps retention policies configurable by data category. Counsel should approve the final schedules used in production.

## Deployment

1. Deploy the integration code.
2. Import `database/20260723_privacy_retention_account_erasure_v1_single_install.sql` once.
3. Configure a stable secret named `MG_PRIVACY_HASH_KEY`. Do not rotate it casually; it protects deterministic suppression tombstones and completion receipts. See `config/privacy-retention-example.php` for a deploy-safe example.
4. Run the validation workflow.
5. Schedule the queue worker at least daily:

```bash
php scripts/process_privacy_erasure_queue.php --limit=25
```

Use `--dry-run` to review due requests. `--request-id=123` limits processing to one request. `--force` bypasses the configured grace date, but it never bypasses an active legal hold, an unresolved merchant-controller handoff, or an account-ownership continuity handoff.

## Request lifecycle

1. **Verified or administrative request** — A signed-in user confirms the current password, chooses a jurisdiction, and types `DELETE`; or an authorized administrator creates a documented review request for a matched account.
2. **Immediate restriction after approval** — A verified self-service request restricts the account immediately. An administrator-created request remains under review until an authorized administrator approves it. Restriction disables the account, rotates the authentication version, revokes sessions, destroys recovery credentials, and hides public identity.
3. **Controller and ownership routing** — Merchant CRM relationships create merchant-controller handoffs. Merchant account owners also receive an operational handoff so balances, subscriptions, campaigns, customer obligations, and workspace ownership can be transferred or closed safely.
4. **Review and holds** — Administrators can acknowledge, approve, deny, extend, place or release a legal hold, and mark merchant or operational handoffs complete.
5. **Finalization gates** — The jurisdiction/product date must be due, no legal hold may be active, and every controller or operational handoff must be completed or marked not applicable.
6. **Erase and anonymize** — Private and ephemeral data is deleted; roles, model assignments, private public-profile children, and obsolete identity relationships are removed; public identity and CRM history are anonymized; and minimum commerce, gift, audit, fraud, tax, and dispute evidence is retained under policy.
7. **Receipt** — The request is unlinked from the active identity and receives a cryptographic completion receipt.

## Default operational deadlines

These values are product defaults and not legal advice:

| Jurisdiction | Acknowledgement | Response target | Product grace period |
| --- | ---: | ---: | ---: |
| EU / EEA | Immediate | 28 days | 14 days, capped by response target |
| United Kingdom | Immediate | 28 days | 14 days, capped by response target |
| California | 10 business days | 45 days | 30 days, capped by response target |
| Other United States | 10 days | 45 days | 30 days |
| Other / unknown | 7 days | 30 days | 30 days |

Administrators may extend a response deadline by up to two additional months when a documented reason exists. The extension also moves the finalization date and unresolved handoff deadlines. Irreversible processing remains blocked by an active legal hold or pending handoff.

## Default retention map

| Category | Default action | Default duration |
| --- | --- | ---: |
| Passwords, sessions, MFA, reset and verification credentials | Delete | Immediate |
| Profiles, preferences, device and personalization records | Delete/anonymize | At finalization |
| Private agent prompts, memory and planning data | Delete | At finalization |
| Merchant CRM identifiers | Merchant review, then anonymize | At finalization |
| Orders, payments, refunds, commissions and payout evidence | Anonymize/minimize | 2,555 days |
| Gift ownership, delivery, claim and redemption evidence | Anonymize/minimize | 2,555 days |
| Privacy, consent, security and audit evidence | Anonymize/minimize | 2,555 days |
| Encrypted backups | Expire through rotation | 35 days |

Retention policies live in `privacy_retention_policies` and should be reviewed with privacy, accounting, tax, payment, fraud, and litigation counsel.

## Backup and restore rule

Deletion from an active database does not immediately rewrite historical backup archives. Backups must:

- remain encrypted and access restricted;
- expire on the documented rotation;
- not be used for ordinary business access;
- apply `privacy_suppression_tombstones` after any restore before restored systems become available;
- re-run due erasure requests after restoration;
- preserve legal holds, pending handoffs, and completion receipts.

## Merchant data

Merchant data remains owned by the merchant. Microgifter acts as platform operator for account, security, and transaction data and may act as a processor or service provider for merchant CRM data. The handoff queue records which merchants must review, erase, anonymize, or lawfully retain their copy.

Microgifter should not silently destroy merchant-controlled records when doing so would violate the merchant’s independent legal duties. It should not use controller review as a reason to retain Microgifter-controlled personal data unnecessarily.

Merchant account deletion additionally requires a continuity review for active subscriptions, balances, campaigns, rewards, claims, customer obligations, staff access, and workspace ownership. Final erasure remains blocked until that handoff is completed or marked not applicable.

## Safety controls

- Self-service requests require current-password confirmation, typed confirmation, an explicit acknowledgement checkbox, CSRF protection, authentication, and rate limiting.
- Account access is stopped immediately after a verified self-service request or administrative approval.
- The last active super-administrator cannot be disabled; another active super-administrator must be assigned first.
- Administrators require explicit privacy permissions.
- Every administrative mutation requires CSRF protection, rate limiting, a reason where appropriate, audit logging, and security logging.
- Legal holds block finalization.
- Pending merchant-controller and account-ownership handoffs block finalization.
- Finalization is idempotent and records per-category action receipts.
- Direct `DELETE FROM users` is not part of the workflow.
- Suppression tombstones are HMAC hashes, not reusable email addresses.

## Review before production

Qualified counsel should review:

- Microgifter’s legal entity and contact information;
- applicable state-law thresholds and appeals;
- GDPR and UK GDPR controller and processor roles;
- merchant data-processing terms;
- payment, tax, accounting, and gift-ownership retention;
- minor-recipient data;
- international transfers;
- backup rotation and vendor deletion contracts;
- any advertising, profiling, or data-sharing changes.
