# Task Agent Phase 3 Production Runbook

## Release scope

Task Agent Phase 3 adds deterministic local gift discovery, product shortlisting, gift-plan selection, canonical cart handoff, recipient-controlled information requests, prepare-only send-later checkpoints, buyer-owned purchase and PPPM tracking, and read-only Action Center lifecycle handoff.

The Task Agent does not autonomously purchase, complete checkout, send, regift, claim, redeem, issue refunds, repair issuance, or expose private delivery credentials.

## Required SQL import order

Import the following files in this order after deploying the latest `integration-from-repair-20260628` code:

1. `database/20260714_user_contact_lists_phase1.sql`
2. `database/stage_19_ai_provider_models.sql`
3. `database/20260714_personal_gifting_agent_phase2.sql`
4. `database/20260714_personal_gifting_workflows_phase3.sql`
5. `database/20260719_multi_agent_runtime_memory_v1.sql`
6. `database/20260720_task_agent_phase3_shortlist_v1.sql`

Each file is registered in `config/migrations.php`. Do not change the order.

## Deployment order

1. Take the normal database and application backup.
2. Deploy the latest integration branch.
3. Import the SQL files above.
4. Clear PHP opcode and application caches used by the deployment.
5. Open `agent.php` as an authenticated customer.
6. Complete the smoke tests below.

## Smoke tests

### Phase 3.1 — Discovery and shortlist

1. Open a birthday or occasion Task Agent.
2. Ask for local gifts for a saved recipient and budget.
3. Confirm published products render without an AI charge.
4. Shortlist one product.
5. Reload and confirm the item remains scoped to the same agent.

### Phase 3.2 — Plan and cart handoff

1. Create or open an editable gift plan for the same recipient.
2. Attach the shortlisted product to the plan.
3. Confirm a different recipient context is rejected.
4. Review the selected product.
5. Add it to the cart with the explicit cart button.
6. Confirm checkout does not start automatically.

### Phase 3.3 — Recipient and send-later preparation

1. Ask for delivery readiness.
2. Confirm only readiness status appears; no address value appears in chat.
3. For an eligible linked recipient, create a scoped permission request.
4. Create a future send-later preparation.
5. Approve, pause, resume, mark prepared, and cancel test schedules using valid transitions.
6. Confirm no gift is purchased or sent by those transitions.

### Phase 3.4 — Purchase and PPPM tracking

1. Complete a normal customer checkout outside the agent.
2. Ask the agent for purchase and PPPM status.
3. Confirm only buyer-owned orders with an exact selected product-version match appear.
4. Verify order, receipt, PPPM, Microgift, and Inbox counts agree with the canonical order confirmation.
5. Confirm the card contains links only and no repair or refund control.

### Phase 3.5 — Lifecycle handoff

1. Ask for the selected gift lifecycle status.
2. Confirm Inbox, Sent, Claimed, redemption, resend, and follow-up state reflect the Action Center.
3. Confirm capability availability and reason text match the Action Center.
4. Open the exact Action Center item.
5. Perform any send, regift, claim, redemption, follow-up, or message action only from the Action Center.
6. Confirm no claim or redemption code appears in agent chat.

## AI usage verification

The following should use zero AI credits:

- discovery and filtering
- shortlist add and remove
- plan selection and removal
- delivery readiness and schedule management
- recipient permission requests
- purchase, receipt, PPPM, and lifecycle tracking

Only explicit synthesis such as comparing several shortlisted gifts or drafting a personal message may call the configured AI provider once with compact sanitized context.

## Observability

Review audit and security logs for:

- deterministic `response_source` values
- agent, owner, plan, and shortlist scoping
- AI reason and token totals when synthesis is used
- schedule creation and transition events
- recipient permission request events
- cart and order events from canonical commerce services
- Action Center lifecycle events from canonical endpoints

## Rollback

1. Disable or revert the latest application deployment.
2. Keep the additive Phase 3 tables unless a database rollback is explicitly required.
3. Do not delete buyer orders, receipts, PPPM items, Microgift instances, or Action Center records.
4. Restore the prior application release and clear caches.
5. Confirm existing Inbox, Sent, Claimed, checkout, and merchant redemption flows still operate.

## Release blockers

Do not release when any of these conditions are present:

- a required migration is missing or out of order
- a user can view another user or agent's shortlist, plan, order, or lifecycle item
- an unpublished or unavailable product can be selected
- an address value, claim code, or redemption code enters agent chat or model context
- the agent can complete checkout or lifecycle mutations without a canonical handoff
- deterministic actions consume AI credits
- PHP 8.2 or PHP 8.3 release gates fail
