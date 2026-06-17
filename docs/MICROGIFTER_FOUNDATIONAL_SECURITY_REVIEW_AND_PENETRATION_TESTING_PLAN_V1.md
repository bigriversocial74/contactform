# Microgifter Foundational Security Review and Penetration Testing Plan v1

**Status:** Foundational security plan  
**Applies to:** Stage 1 identity foundation, Stage 2 UI prototype, and Stage 3 implementation  
**Testing posture:** Authorized, defensive, non-destructive testing only

## 1. Purpose

This document defines the security baseline, threat model, assessment scope, test program, severity model, remediation workflow, and Stage 3 release gates for Microgifter.

Microgifter is evolving from identity and interface foundations into a platform that will manage users, merchants, agents, gifts, claims, messages, orders, invoices, scanner-assisted redemption, and third-party AI providers. Those capabilities introduce financial, privacy, authorization, and fraud risks that must be addressed before production launch.

The immediate goal is a foundational security assessment of the current authentication, authorization, session, API, configuration, and logging layers. A broader independent penetration test should follow once Stage 3 has database-backed agents, claims, commerce, notifications, scanner validation, and AI tool execution in a production-like staging environment.

## 2. Security objectives

Microgifter must protect:

- user identities, profiles, passwords, sessions, and recovery tokens;
- roles, permissions, administrator access, and merchant access;
- agents, agent configuration, runtime state, archives, and history;
- gifts, recipients, delivery data, claim tokens, and redemption status;
- messages and notifications;
- orders, invoices, refunds, and payment references;
- scanner input and future hardware integrations;
- AI prompts, outputs, usage records, model entitlements, and provider credentials;
- audit and security logs.

The platform must preserve confidentiality, integrity, availability, accountability, privacy, and reliable historical records.

## 3. Authorized scope

### In scope

- public and authenticated PHP pages;
- sign-up, sign-in, logout, password recovery, and verification;
- session and CSRF controls;
- role, permission, model, and administrative checks;
- account, agent, Inbox, Sent, Claimed, archive, merchant, CRM, and admin interfaces;
- PHP API endpoints and database access;
- audit logs, security logs, and rate limits;
- Stage 3 agent, gift, claim, message, notification, order, invoice, scanner, and AI APIs as introduced;
- deployment configuration, security headers, secret handling, and dependency hygiene.

### Out of scope without separate authorization

- destructive production testing;
- denial-of-service testing;
- social engineering;
- physical security;
- attacks against third-party providers outside Microgifter-controlled accounts;
- accessing real customer data;
- creating real charges, gifts, claims, or merchant redemptions.

Testing must use synthetic accounts and data in local, security-test, or staging environments.

## 4. Rules of engagement

Every assessment must define:

- approved environments and domains;
- testing dates;
- authorized testers;
- permitted test categories;
- prohibited actions;
- emergency contacts;
- stop conditions;
- evidence-storage and deletion requirements.

Testing stops immediately if it exposes production data, creates real financial activity, affects availability, exposes provider credentials, or causes uncontrolled access.

## 5. Trust boundaries

### Browser

The browser is untrusted. Hidden fields, localStorage, object IDs, model selections, cart values, scanner output, notification counts, and UI state can be changed by the user.

### Application server

The server must independently verify:

- authentication;
- authorization;
- object ownership;
- CSRF;
- request schema;
- pricing and totals;
- state transitions;
- replay safety;
- merchant and scanner context;
- AI model entitlement and tool permission.

### Database

The database is authoritative for production state and must enforce appropriate uniqueness, foreign keys, transaction boundaries, retention, and least-privilege access.

### External services

Payment, email, AI, storage, analytics, and scanner services are separate trust domains. Credentials remain server-side and responses must be validated.

## 6. Current foundational strengths

The current codebase includes several valuable controls:

- strict PHP typing in sensitive files;
- parameterized SQL queries;
- password verification through PHP password APIs;
- CSRF tokens generated with cryptographic randomness and compared safely;
- session regeneration after successful authentication;
- role and permission helpers;
- environment-driven secrets and configuration;
- database-backed session records;
- rate-limit storage;
- audit and security logging;
- API security headers;
- account-status checks for protected APIs.

These controls should be preserved and covered by regression tests.

## 7. Priority findings from the static review

### 7.1 Rate-limit failure behavior

The current rate-limit helper logs database failures and permits the request to continue.

**Risk:** Authentication, recovery, claim, or AI usage limits could be unavailable during a storage failure.

**Required Stage 3 action:** Define a safe degraded mode, fail closed for high-risk operations, add monitoring, and test rate-limit storage failure.

### 7.2 Session validation failure behavior

The current database-backed session validator returns active when validation throws an exception.

**Risk:** A revoked, expired, or disabled session could remain usable during a validation failure.

**Required Stage 3 action:** Sensitive requests must fail closed when session validity cannot be confirmed.

### 7.3 Client-side prototype state

Saved agents, runtime status, archives, tabs, model selection, scanner preferences, and cart data may currently use localStorage.

**Required Stage 3 action:** Move authoritative state to server APIs and database tables. Browser state may only be a cache or unsaved draft.

### 7.4 Session and permission freshness

API authorization is refreshed from the database, while server-rendered pages may rely on session-cached roles and permissions.

**Required Stage 3 action:** Add session or authorization versioning and invalidate access after role, permission, password, or account-status changes.

### 7.5 Security-log redaction

Structured logs and exception context could accidentally contain personal data, tokens, claim codes, or provider details.

**Required Stage 3 action:** Add centralized redaction rules and prohibited fields.

### 7.6 Page security headers

API headers are strong, but production browser pages require a separate CSP and permissions policy that supports the scanner without weakening the rest of the application.

### 7.7 Automated security coverage

A comprehensive security regression suite and CI release gate are not yet established.

## 8. Threat model

Primary threat actors include:

- unauthenticated internet attackers;
- credential-stuffing bots;
- malicious or compromised customers;
- malicious or compromised merchant staff;
- compromised administrator accounts;
- insiders with excessive access;
- attackers controlling browser state or scanner input;
- malicious gift senders or recipients;
- compromised third-party integrations;
- users attempting to manipulate AI agents into unauthorized actions.

Primary threats include:

- account takeover;
- session theft or fixation;
- horizontal object access;
- vertical privilege escalation;
- stored or reflected script injection;
- request forgery;
- unsafe file or URL handling;
- gift and claim replay;
- order and price tampering;
- merchant impersonation;
- notification or message data leakage;
- provider-key disclosure;
- prompt injection and unauthorized AI tool use;
- audit or log manipulation.

## 9. Assessment methodology

The assessment should combine:

- architecture and data-flow review;
- source and configuration review;
- role and permission matrix validation;
- manual authenticated testing;
- safe automated scanning;
- business-logic and state-transition testing;
- race-condition and idempotency testing;
- dependency and secret review;
- remediation retesting.

Use OWASP ASVS, OWASP Web Security Testing Guide, OWASP API Security Top 10, CWE, and CVSS as reference frameworks.

## 10. Required test accounts

Create synthetic accounts for:

- unauthenticated visitor;
- two customers;
- two merchant organizations;
- merchant owner and merchant staff;
- sales user;
- administrator;
- super administrator;
- disabled user;
- unverified user;
- user with revoked session;
- users with different AI model entitlements.

Create synthetic agents, gifts, claims, messages, notifications, orders, invoices, merchant locations, scanner codes, and expired or reused tokens.

## 11. Authentication and session test plan

Verify:

- account-enumeration resistance;
- password policy and hashing;
- login throttling by account and network source;
- session ID regeneration after login;
- logout and database-session revocation;
- disabled-account enforcement;
- concurrent-session controls;
- password-reset token expiration and single use;
- email-verification token expiration and single use;
- session invalidation after password or permission changes;
- idle and absolute session expiration;
- secure cookie attributes;
- safe failure when session validation or rate-limit storage is unavailable;
- internal-only post-login and return redirects.

## 12. Authorization test plan

For every protected page and API:

- test unauthenticated access;
- test each lower-privileged role;
- test super-admin-only controls;
- replace object identifiers with another user's IDs;
- test ownership of agents, gifts, claims, messages, notifications, orders, invoices, merchants, scanners, and model assignments;
- test archived and deleted records;
- test merchant staff boundaries;
- test mass assignment of owner, role, permission, status, price, claim, and model fields.

The absence of a UI button is never considered authorization.

## 13. CSRF, injection, and validation plan

Verify all state-changing requests reject missing, invalid, or cross-session CSRF tokens.

Review and test input handling for:

- SQL injection;
- stored, reflected, and DOM script injection;
- unsafe URLs and active SVG;
- malformed JSON;
- duplicate or unexpected fields;
- oversized bodies;
- negative or excessive values;
- Unicode and control characters;
- parameter pollution;
- unsafe sort, search, and pagination input;
- log injection;
- future command, template, and prompt injection.

Every endpoint must use an explicit input schema and output encoding appropriate to its context.

## 14. Claim-security gate

No real claim or scanner auto-claim flow may ship until the design includes:

- cryptographically random claim tokens;
- hashed token storage;
- explicit expiration;
- one-time redemption;
- transactional idempotency;
- replay and concurrency protection;
- merchant and location authorization;
- attempt limits;
- audit records;
- refund and reversal rules;
- server-side verification of all scanner output.

Required tests include expired, reused, concurrent, wrong-recipient, wrong-merchant, value-tampered, and rate-limited claims.

## 15. Scanner-security gate

The scanner is an input mechanism, not an authority.

Required controls:

- decoded content is treated as untrusted text;
- only approved formats and payload lengths are accepted;
- arbitrary URLs are not opened automatically;
- claim status, merchant, location, expiration, and authorization are verified server-side;
- irreversible redemption requires explicit confirmation unless a separately approved workflow exists;
- camera tracks stop when the scanner closes or the page is hidden;
- scan attempts are rate-limited and audited.

## 16. Commerce and invoice security gate

Before real checkout:

- the server recalculates all prices, quantities, discounts, tax, fees, currency, and totals;
- localStorage cart data is treated only as a draft;
- payment callbacks use verified signatures;
- duplicate and out-of-order callbacks are safe;
- payment and refund operations are idempotent;
- order and invoice ownership is enforced;
- invoices are immutable or versioned;
- deleted or archived agents cannot break historical financial records.

## 17. AI-agent security gate

Before agents can invoke real tools:

- provider keys remain server-side;
- credentials are separated by environment and rotatable;
- model entitlement is checked on the server;
- every tool has an allowlist and permission policy;
- ownership is checked for every target object;
- financial or irreversible actions require confirmation;
- usage and cost limits are enforced;
- timeouts and fallback rules are defined;
- prompt-injection and unauthorized-tool scenarios are tested;
- prompts, outputs, and logs follow a documented retention and privacy policy.

## 18. Browser and frontend security plan

Verify:

- page-level CSP;
- clickjacking protection;
- no mixed content;
- safe external links;
- no secrets or claim tokens in localStorage;
- safe cache and browser-history behavior;
- focus management and inert backgrounds for overlays;
- camera cleanup;
- safe postMessage handling if introduced;
- service-worker scope if PWA support is enabled.

## 19. Infrastructure and deployment review

Review:

- supported PHP and database versions;
- production error-display settings;
- TLS and proxy trust configuration;
- database least privilege;
- file and directory permissions;
- web-root exposure and directory listing;
- protected backups and restore tests;
- environment and local configuration handling;
- staging access;
- log access;
- deployment rollback;
- dependency pinning;
- secret scanning and branch protection.

## 20. Logging and monitoring

Security events should include authentication failures, rate-limit blocks, password changes, session revocation, permission denial, admin actions, agent lifecycle changes, claim attempts, payment events, AI tool use, and scanner activity.

Never log plaintext passwords, session IDs, CSRF tokens, reset tokens, verification tokens, raw claim tokens, payment credentials, or AI provider keys.

Alert on repeated account failures, rate-limit subsystem failure, session-validation failure, claim guessing, replay attempts, mass object enumeration, unusual super-admin activity, AI usage spikes, webhook failures, and audit-log write failures.

## 21. Automated security requirements

Every pull request should eventually run:

- PHP syntax and static analysis;
- JavaScript linting;
- dependency review;
- secret scanning;
- migration validation;
- unit and integration tests;
- authorization regression tests;
- selected browser tests.

Minimum security regression cases:

- invalid CSRF rejected;
- session regenerated after login;
- revoked session rejected;
- session-validation failure is safe;
- rate-limit failure is safe;
- disabled user blocked;
- permission changes take effect;
- cross-user object access denied;
- archived-agent ownership enforced;
- claim is single-use and concurrency-safe;
- server recalculates order totals;
- provider secrets never reach browser output;
- scanner input cannot directly mark a claim redeemed.

## 22. Severity and remediation targets

### Critical

Widespread account compromise, financial theft, claim fraud, provider-key disclosure, arbitrary code execution, database compromise, or super-admin takeover.

**Target:** Immediate containment and remediation before release.

### High

Significant unauthorized access, privilege escalation, repeatable claim abuse, authentication bypass, or major financial manipulation.

**Target:** Fix before release.

### Medium

Meaningful but limited exposure, request forgery, session weakness, rate-limit weakness, or configuration issue requiring conditions.

**Target:** Fix within the active stage or a documented short deadline.

### Low

Defense-in-depth or limited-impact weakness.

**Target:** Planned remediation.

Each finding must include ID, severity, scope, affected roles, evidence, impact, remediation, owner, target date, and retest result.

## 23. Remediation workflow

1. Confirm and classify the finding.
2. Assign an engineering owner.
3. Add a regression test.
4. Implement the server-side fix.
5. Peer-review security-sensitive changes.
6. Deploy to the security-test environment.
7. Retest the original issue and reasonable bypass variants.
8. Close only with evidence.

A finding is not resolved merely because the UI hides the affected action.

## 24. Stage 3 security gates

### Gate A — Identity

- safe session-validation failure;
- safe rate-limit failure;
- secure reset and verification lifecycle;
- page and API headers;
- authentication regression tests.

### Gate B — Agents

- database ownership;
- lifecycle state machine;
- archive and restore controls;
- audit history;
- no authoritative localStorage state.

### Gate C — Gifts and messages

- object authorization;
- participant-only messages;
- user-scoped notifications;
- pagination limits;
- output encoding and privacy review.

### Gate D — Claims and scanner

- secure token design;
- idempotent redemption;
- replay protection;
- merchant authorization;
- server-validated scans;
- complete audit trail.

### Gate E — Commerce

- server-owned totals;
- signed callbacks;
- idempotency;
- immutable invoices;
- refund authorization.

### Gate F — AI

- server-only keys;
- entitlement checks;
- tool permissions;
- confirmation for sensitive actions;
- cost controls;
- prompt-injection testing.

## 25. Assessment schedule

### Assessment 1 — Foundational review

Perform now. Focus on identity, sessions, CSRF, authorization, recovery, headers, logging, configuration, and deployment.

### Assessment 2 — Stage 3 pre-release review

Perform after core agent, gift, claim, message, notification, commerce, scanner, and AI APIs exist in staging.

### Assessment 3 — Independent external penetration test

Perform before public launch or material transaction volume.

### Assessment 4 — Recurring review

Repeat after major architecture, payment, claim, scanner, AI-tool, merchant, or admin changes and at least annually after launch.

## 26. Immediate action list

1. Make session validation fail closed.
2. Define safe rate-limit failure behavior.
3. Verify reset and verification tokens are hashed, expiring, and single-use.
4. Add page-level security headers.
5. Add authentication, CSRF, session, and permission tests.
6. Inventory localStorage keys and server replacements.
7. Build a role-permission-object matrix.
8. Add centralized log redaction.
9. Define idle and absolute session expiration.
10. Add authorization/session version invalidation.
11. Complete claim and scanner threat models before implementation.
12. Add CI dependency and secret scanning.

## 27. Definition of foundational security readiness

The foundation is ready for Stage 3 when:

- identity controls have automated tests;
- session and rate-limit failures are safe;
- permission changes invalidate access correctly;
- protected endpoints enforce authentication, permission, and ownership;
- logs exclude secrets;
- security headers are verified;
- recovery tokens are secure and single-use;
- CI runs security regression tests;
- localStorage is not authoritative;
- Stage 3 work has explicit gates for agents, claims, scanner, commerce, and AI.

## 28. Governing security rule

> The browser may request an action, but the server must independently verify identity, permission, ownership, state, value, and replay safety before Microgifter performs it.

No hidden button, client-side state, scanner result, AI instruction, or localStorage value constitutes authorization.
