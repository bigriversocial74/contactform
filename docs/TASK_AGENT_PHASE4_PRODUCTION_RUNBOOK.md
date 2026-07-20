# Task Agent Phase 4 Production Runbook

## Release scope

Task Agent Phase 4 extends the existing Phase 3 gifting path with recurring draft-plan programs, pledge-only group gifts, workplace and community distribution-program coordination, read-only rule/budget/strategy/approval summaries, and on-demand system monitoring.

Phase 4 reuses the existing Personal Agent, merchant distribution, automation, approval, delivery, cart, checkout, PPPM, and Action Center authorities. It does not create a second recurring, group-gifting, distribution, approval, or alert system.

The specialized agents do not autonomously purchase, charge, send, message, allocate, select winners, issue rewards, approve actions, claim, redeem, refund, or expose private recipient address values.

## Required SQL

Phase 3 must already be deployed and its required migrations must already be imported.

After deploying the latest `integration-from-repair-20260628` application code, import this one new Phase 4 migration:

1. `database/20260720_task_agent_phase4_v1.sql`

The migration is registered in `config/migrations.php` immediately after `database/20260720_task_agent_phase3_shortlist_v1.sql`. It is additive and safe to rerun.

The migration adds only specialized-agent association tables for existing recurring programs, group gifts, and distribution programs. Phase 4.4 and Phase 4.5 require no additional tables.

## Deployment order

1. Take the normal application and database backups.
2. Verify Phase 3 is already deployed and `20260720_task_agent_phase3_shortlist_v1` is recorded in `schema_migrations`.
3. Deploy the latest `integration-from-repair-20260628` code.
4. Import `database/20260720_task_agent_phase4_v1.sql` once.
5. Confirm `20260720_task_agent_phase4_v1` appears in `schema_migrations`.
6. Clear PHP opcode and application caches used by the deployment.
7. Open `agent.php` as an authenticated customer and merchant.
8. Complete the smoke tests below.

## Pre-release verification

- PHP 8.2 and PHP 8.3 Phase 4.6 workflows are green.
- Repository Production Quality reports 100/100 on PHP 8.2 and PHP 8.3.
- `php scripts/validate_migration_manifest.php` passes.
- No unresolved review thread remains on the Phase 4.6 pull request.
- The deployed branch head matches the verified Phase 4.6 merge commit.

## Smoke tests

### Phase 4.1 — Recurring Gift Programs

1. Open a Birthday & Occasion specialized agent.
2. Ask to create a recurring birthday or occasion program.
3. Confirm the form uses a saved recipient, cadence, first review date, optional end date, and budget.
4. Create the program and confirm it appears in the existing Personal Agent recurring-program view.
5. Generate one due cycle and confirm it creates a reviewable draft plan only.
6. Skip one future cycle and confirm the canonical run history records the skip.
7. Pause, resume, and cancel using valid transitions.
8. Confirm no product is automatically selected and no cart, payment, message, or delivery is created.
9. Connect an existing Personal Agent recurring program and confirm no program data is copied.

### Phase 4.2 — Group Gifting

1. Open a Birthday & Occasion specialized agent.
2. Create a pledge-only group-gift draft using an existing contact list or recipient context.
3. Confirm participant snapshots, invitations, pledge limits, and pledge totals remain visible in the canonical Personal Agent group-gift view.
4. Open, lock, fulfill, close, and cancel test groups only through allowed organizer transitions.
5. Connect an existing Personal Agent group gift and confirm participants and pledges are not copied.
6. Confirm the specialized agent provides no pledge-entry, payment, charge, or checkout action.
7. Confirm product selection and checkout remain explicit Phase 3 plan/cart handoffs.

### Phase 4.3 — Workplace and Community Programs

1. Sign in as a merchant account.
2. Create or identify an existing `workplace_reward` distribution program.
3. Open a Workplace Rewards specialized agent and connect that program.
4. Confirm only workplace-reward programs are eligible for that agent.
5. Create or identify an existing fundraiser, contest, giveaway, or merchant-grant program.
6. Open a Community Fundraising specialized agent and connect that program.
7. Confirm the cards report canonical budgets, recipients, products, allocations, and issuance aggregates.
8. Open each card and confirm it links to `merchant-distribution-program.php`.
9. Disconnect a program and confirm the canonical program, recipients, products, allocations, and issuance records remain unchanged.
10. Confirm program creation, eligibility, winner selection, allocation, issuance, and status mutations are not available inside the specialized agent.

### Phase 4.4 — Rules, Budgets, and Approvals

1. Ask the Workplace Rewards or Community Fundraising agent to show program guardrails.
2. Confirm budget, remaining capacity, item limits, per-recipient limits, and rule keys match the canonical distribution program.
3. Ask to show automation strategies.
4. Confirm trigger type, policy keys, action catalog, action limit, approval requirement, status, and version match Merchant Automation.
5. Ask to show pending approvals.
6. Confirm risk, expiration, strategy name, and required-reason state match Agent Approvals.
7. Attempt to change a budget, strategy, or approval decision from chat.
8. Confirm the agent returns a handoff to Distribution Programs, Merchant Automation, or Agent Approvals.
9. Confirm no bulk approval or agent-side decision control appears.

### Phase 4.5 — Monitoring and Preparation

1. Ask a Birthday & Occasion agent what needs attention.
2. Confirm due recurring cycles, recorded skips/completions, group deadlines, pledge progress, recipient readiness, and missing send-later preparation are calculated from canonical records.
3. Confirm delivery cards expose readiness booleans only and no address value.
4. Ask a Workplace Rewards or Community Fundraising agent for a monitoring review.
5. Confirm nearly used budgets, reached item limits, upcoming end dates, missing products/recipients, draft/paused programs, and pending approvals appear when applicable.
6. Confirm severity ordering is high, medium, low, then informational.
7. Reload and confirm the monitor is recalculated rather than loaded from a stored alert feed.
8. Confirm every card is a read-only link to its canonical source.
9. Confirm no purchase, message, allocation, issuance, approval decision, or automatic preparation occurs.

## Isolation tests

- A customer cannot connect or view another user’s recurring program or group gift.
- One specialized agent cannot view another agent’s linked recurring program, group gift, distribution program, strategy, or approval.
- A merchant cannot connect another merchant’s distribution program.
- Workplace Rewards cannot connect fundraiser, contest, giveaway, or merchant-grant programs.
- Community Fundraising cannot connect workplace-reward programs.
- Pending approvals are scoped to the authenticated owner and selected agent.
- A stale recurring status, next-run timestamp, or group status is rejected before mutation.

## Privacy tests

- No address line, city, region, postal code, email, or phone enters Phase 4 model context.
- No participant identities, invitation messages, private contact data, approval reasons, targets, request payloads, claim codes, or redemption codes enter compact model projections.
- Monitoring model context contains aggregate source, severity, due date, status, and safe facts only.
- User-visible cards may show the authenticated owner’s permitted program or recipient labels, but the compact model projection excludes human-readable monitoring titles.

## AI usage verification

The following must use zero AI credits:

- recurring-program creation forms and management
- due-cycle generation and skip actions
- group-gift creation, linking, status review, and organizer transitions
- distribution-program linking, unlinking, and aggregate reporting
- rules, budgets, strategies, and approval summaries
- all Phase 4 monitoring snapshots and cards

Only an explicit request for sanitized synthesis may use the configured AI provider. Routine Phase 4 routes must report `response_source=system_query`, `used_ai=false`, and zero token totals.

## Observability

Review audit and security logs for:

- recurring-program creation, linking, status transitions, draft generation, and skipped cycles
- group-gift creation, linking, and organizer transitions
- distribution-program link and unlink events
- deterministic `response_source`, tool name, AI reason, and token totals
- owner, agent, merchant, program-type, and canonical-record scoping
- canonical approval decisions made through Agent Approvals
- canonical allocation and issuance events made through Distribution Programs

The Phase 4.5 monitor intentionally persists no alert rows. Audit the underlying canonical events rather than expecting a Task Agent monitoring-event table.

## Rollback

1. Disable or revert the latest application deployment.
2. Keep the additive Phase 4 association tables unless a database rollback is explicitly required.
3. Do not delete canonical recurring programs, runs, gift plans, group gifts, participants, distribution programs, recipients, products, allocations, issuance jobs, strategies, workflow actions, or approval requests.
4. Restore the prior application release and clear caches.
5. Confirm Phase 3 discovery, shortlist, plan/cart, delivery, order, PPPM, and Action Center flows still operate.
6. Confirm Personal Agent recurring and group-gift views and merchant Distribution Programs, Merchant Automation, and Agent Approvals remain operational.

## Release blockers

Do not release when any of these conditions are present:

- the Phase 4 migration is missing, unregistered, duplicated, or ordered before the Phase 3 shortlist migration
- a user or merchant can view or connect another owner’s program
- a specialized agent can cross agent or program-type boundaries
- recurring cycles can purchase, charge, message, or deliver automatically
- group gifts collect payment through the specialized agent
- distribution recipient, eligibility, winner, allocation, issuance, or program-status mutations occur through the specialized agent
- rules, budgets, strategies, or approval decisions can be changed from specialized-agent chat
- monitoring persists a duplicate alert feed or performs an autonomous action
- private address values, participant identities, approval reasons, targets, payloads, claim codes, or redemption codes enter model context
- deterministic Phase 4 actions consume AI credits
- any Phase 4.1 through Phase 4.5 contract fails
- the PHP 8.2 or PHP 8.3 Phase 4.6 gate fails
- Repository Production Quality scores below 100/100
