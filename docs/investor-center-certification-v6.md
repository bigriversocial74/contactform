# Investor Center Certification v6

## Scope

This certification covers the complete private Investor lifecycle:

1. Super Admin profile entry and authenticated access request.
2. Super Admin approval, information request, denial, revocation, and repair.
3. Active Investor account dropdown and private Investor Portal navigation.
4. Official-round publication and selected-investor visibility.
5. Data Room folders, versioned documents, Q&A, requests, communications, meetings, and engagement.
6. Pipeline stages, follow-ups, evidence snapshots, and non-binding interest.
7. Closing packets, compliance, maker/checker financial verification, reconciliation, and funded-investor reporting.
8. Governance rights, obligations, meetings, consents, holdings references, tax documents, and material notices.

## Initial audit score

**8.6/10**

The initial review found a mature feature set but identified four cross-module weaknesses:

- The Investor Portal could request the same boot payload twice.
- Investment Relations and Governance deep links could silently fail when those funded-investor sections were not yet available.
- The header and portal shell treated the Investor role as sufficient even though the backend also required an active `investor_profiles` record.
- Administrators had no single command view for exceptions across access, pipeline, diligence, closing, and governance.

## v6 build

### Unified Investor Center

`/admin/investor-center.php` provides one operational dashboard with:

- Access approval and role/profile consistency counts.
- Pipeline stage and overdue follow-up counts.
- Diligence requests, urgent work, published documents, and expiration alerts.
- Closing records, maker/checker verification, compliance, packets, and verified funded totals.
- Governance meetings, obligations, tax documents, and notice acknowledgements.
- A prioritized cross-module work queue.
- Direct links into each authoritative specialist workspace.

### Authoritative access state

`includes/investment/investor-access-state.php` resolves Investor access from both:

- the `investor` role; and
- an active `investor_profiles` record.

The dropdown and portal scripts are available only when both authorities agree. Incomplete or inconsistent states receive explicit administrator-repair messaging instead of a private portal shell that later fails through the API.

### Single-load portal boot

`assets/js/investor-portal-boot-v6.js` deduplicates simultaneous GET requests to `/api/investment/portal.php` while never caching POST actions. The existing v4 and v5 renderers remain intact, but they share one protected boot response.

### Reliable deep links

`assets/js/investor-portal-certification-v6.js` guarantees destinations for:

- `#summary`
- `#dataroom`
- `#qa`
- `#requests`
- `#updates`
- `#interest`
- `#relations`
- `#governance`

Relations and Governance display governed unavailable states until the investor becomes eligible. The tabs also support keyboard arrow, Home, and End navigation with selected-state accessibility.

## Certification contract

The focused validator scores ten required controls:

1. Unified lifecycle command center.
2. Cross-module exception queue.
3. Role plus active-profile access authority.
4. Correct Investor dropdown gating.
5. State-specific portal recovery.
6. Single GET portal boot.
7. Reliable Relations and Governance deep links.
8. Keyboard and selected-state accessibility.
9. No Investor links in customer or merchant sidebars.
10. Every specialist workspace links to the Investor Center.

The focused workflow runs PHP syntax checks on PHP 8.2 and PHP 8.3, JavaScript syntax checks, and the complete ten-point contract.

## Final score requirement

The implementation is rescored **10.0/10** only when both PHP matrix jobs and the ten-point contract pass on the pull request head. Live deployment and browser/database lifecycle testing remain separate deployment-verification steps and must not be inferred from repository CI.
