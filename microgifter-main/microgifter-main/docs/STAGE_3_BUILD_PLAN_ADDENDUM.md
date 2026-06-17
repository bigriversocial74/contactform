# Microgifter Stage 3 Build Plan Addendum

This addendum carries the Stage 2 closeout findings into Stage 3. It supplements the existing Stage 3 plan and takes precedence where the current codebase has moved beyond the original sequence.

## Stage 3 prime directive

Convert the Stage 2 interface prototype into a secure, server-authoritative, tested application before expanding the product surface further.

## Required changes to the Stage 3 sequence

### 1. Begin with stabilization, not new screens

Stage 3 must start with a stabilization sprint that:

- inventories all prototype-only behavior;
- identifies every localStorage key;
- maps each key to a server-side table and API;
- removes duplicated header, drawer, and modal logic;
- replaces the regex-generated landing page with maintainable templates;
- establishes automated tests and CI.

### 2. Treat the Stage 2 UI as an approved product specification

The following interface concepts are now part of the Stage 3 requirements:

- shared authenticated app shell;
- shared agent sidebar;
- saved-agent lifecycle;
- Agent and Merchant Info sidebar tabs;
- Inbox, Sent, and Claimed workspaces;
- loadable right-side gift details;
- full-screen mobile gift details;
- message, tip, send, and claim modal entry points;
- archived-agent management;
- scanner application shell;
- model selector and provider administration;
- notifications and unread badges;
- mobile-first drawers and overlays.

Stage 3 should connect these surfaces to authoritative services rather than redesigning them from scratch.

### 3. Add the new domain areas discovered in Stage 2

The Stage 3 schema and APIs must account for:

- agent versions;
- agent runtime state;
- agent archive and restore events;
- agent-to-order and agent-to-invoice historical references;
- model entitlements and assignments;
- notification records and read state;
- message threads;
- gift delivery state;
- claim attempts and redemption audit records;
- scanner preferences and future hardware integrations.

### 4. Move prototype state to the server in this order

1. Saved agents and archive lifecycle
2. Agent runtime status
3. Inbox, Sent, and Claimed records
4. Notifications and unread counts
5. Message threads
6. Cart and checkout state
7. Model assignments
8. Scanner preferences

### 5. Add a claims security gate

No scanner-driven or automatic claim behavior should become functional until the following are designed and tested:

- random one-time claim tokens;
- hashed token storage;
- expiration;
- idempotent redemption;
- merchant authorization;
- retry and attempt limits;
- audit metadata;
- reversal and refund handling;
- server-side validation of all scanner output.

### 6. Add an AI provider security gate

Provider integration must remain server-side. Stage 3 must include:

- environment-managed secrets;
- account and agent model entitlements;
- provider availability checks;
- usage and cost logging;
- timeout and retry policy;
- provider fallback policy;
- no credentials in HTML, JavaScript, localStorage, or browser requests.

### 7. Make testing a Stage 3 deliverable

Minimum required automated coverage:

- authentication and redirect behavior;
- CSRF and rate limiting;
- role and permission enforcement;
- saved-agent creation and update;
- start and pause transitions;
- archive, restore, and delete behavior;
- invoice-history retention;
- notification unread counts;
- message creation;
- claim idempotency;
- mobile critical-flow smoke tests.

## Stage 3 release blockers

Stage 3 cannot be marked complete while any of the following remain true:

- financial, claim, or agent runtime state is trusted from localStorage;
- the login endpoint redirects to the wrong default page;
- claims can be redeemed twice;
- provider keys are exposed to the client;
- notification badges are not server-derived;
- archived-agent deletion can break invoice history;
- critical flows have no automated tests;
- the landing page is still assembled through regex transformations.

## Recommended Stage 3 workstreams

### Workstream A — Architecture and quality

- schema and migration standards;
- API response conventions;
- shared component cleanup;
- CI and automated tests;
- logging and error handling.

### Workstream B — Agent persistence

- agent CRUD;
- versions;
- runtime state;
- archive and restore;
- historical references.

### Workstream C — Gift activity

- gifts and deliveries;
- Inbox, Sent, and Claimed queries;
- loaded-item detail API;
- pagination and filtering.

### Workstream D — Messaging and notifications

- message threads;
- unread counters;
- notification records;
- polling first, event delivery later.

### Workstream E — Claims and scanning

- claim-token security;
- QR generation;
- manual verification;
- scanner decoding;
- hardware abstraction outline.

### Workstream F — Commerce and retention

- cart sessions;
- orders and order items;
- invoices;
- claim and agent historical retention.

### Workstream G — AI routing

- model entitlement;
- provider clients;
- usage records;
- admin status and fallback behavior.

## Stage 3 documentation requirement

Each Stage 3 implementation task should state:

- which Stage 2 prototype it replaces;
- which localStorage key or placeholder it retires;
- which database table owns the state;
- which API endpoint owns the behavior;
- which permissions apply;
- which tests prove completion.

## Reference

See `docs/STAGE_2_CLOSEOUT_AUDIT_AND_STAGE_3_HANDOFF.md` for the full review, scorecard, deviations, and technical findings.
