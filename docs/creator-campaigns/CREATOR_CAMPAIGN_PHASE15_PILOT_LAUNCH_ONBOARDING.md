# Creator Campaign Phase 15 — Pilot Launch & Merchant Onboarding

## Purpose

Phase 15 gives an existing merchant owner a guided, resumable path from pilot enrollment to a production-ready first Creator Campaign. It is a native merchant launch layer over the canonical product catalog, Creator Campaign builder, deliverables, compensation, budgets, tracking, agreements, permissions, and Phase 14 operator safety controls.

Phase 15 does **not** configure MCP connections, grants, scopes, definitions, schedules, or external agent authority.

## Merchant workflow

1. **Pilot enrollment** — records the primary operator, human support path, pilot goal, expected volume, target launch date, and acceptance of the operating boundaries.
2. **Business and campaign profile** — stores reusable brand, target-customer, service-area, platform, disclosure, restriction, and review-turnaround defaults.
3. **Product and offer readiness** — validates selected canonical products for a published version, positive price, ready image, and active PPPM claim/redemption template.
4. **Compensation and budget guardrails** — stores planning defaults and previews maximum exposure while keeping native campaign compensation rules and budgets authoritative.
5. **Creator eligibility preferences** — stores approved-Creator, platform, location, category, audience, profile-completeness, history, and competitor preferences. These preferences never approve a Creator.
6. **Operator and approval roles** — documents campaign, application, content, earnings, payout-record, and emergency ownership without replacing native permissions.
7. **First campaign guided launch** — creates a canonical draft through existing Creator Campaign services or selects an existing campaign. The merchant completes deliverables, compensation, budget, tracking, rights, and terms in their dedicated workspaces.
8. **Production smoke test** — performs read-only validation and creates an immutable pass/fail receipt.
9. **Launch dashboard** — activates the merchant onboarding record after a passing receipt. Activation does not publish the campaign.

## Canonical authority

Phase 15 does not create parallel product, campaign, financial, Creator, agreement, tracking, or permission records. It stores only merchant onboarding defaults, progress, events, and receipts.

The guided campaign action calls:

- `mg_creator_campaign_create_draft`
- `mg_creator_campaign_builder_save_step` for Builder Steps 1–3
- `mg_creator_campaign_builder_validate_campaign`

The dedicated operational workspaces remain authoritative for:

- campaign deliverables
- compensation rules and immutable versions
- campaign budgets and append-only ledger events
- tracking sources and attribution
- participant agreements and acceptance evidence

## Financial exposure

The Phase 15 exposure preview is a planning aid. It never reserves, commits, spends, transfers, refunds, reverses, or pays money. Native Creator Campaign budget controls remain the enforceable financial ceiling.

## Production smoke test

The smoke test validates:

- owner/workspace authority
- Phase 14 pilot availability and emergency-stop state
- onboarding Steps 1–6
- selected product readiness
- first-campaign ownership and canonical builder readiness
- attached product
- deliverable definition
- active compensation rule
- campaign budget record
- active tracking source
- agreement-service availability
- automatic Creator acceptance remains disabled

The test writes only:

- one idempotent onboarding receipt
- one onboarding event
- standard audit/event evidence
- the latest onboarding readiness snapshot

It does not publish or schedule a campaign, decide an application, invite a Creator, accept an agreement, review content, alter attribution, decide an earning, record or issue a payout, call Stripe, or invoke MCP.

## Activation boundary

A passing smoke-test receipt allows the merchant to activate the **onboarding record**. Campaign publication remains a separate explicit action in the native Creator Campaign lifecycle. Phase 14 emergency controls remain independent and authoritative.

## Rollback

Code rollback removes the Phase 15 page and service layer. The three additive onboarding tables may remain safely because they grant no execution authority. Do not delete canonical campaign, product, financial, tracking, agreement, Phase 14, or MCP records as part of a Phase 15 rollback.
