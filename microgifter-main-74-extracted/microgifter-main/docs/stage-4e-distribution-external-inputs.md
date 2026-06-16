# Stage 4E Distribution Programs and External Input Sources

## Purpose

Stage 4E converts purchases, merchant grants, contests, giveaways, fundraising, workplace rewards, gaming events, files, webhooks, and external APIs into controlled PPPM issuance work. It does not bypass PPPM. Every accepted source event becomes normalized, idempotent allocation and issuance jobs before permanent envelope creation.

## Input boundary

All sources use the same sequence:

1. Authenticate the merchant or signed source connection.
2. Normalize the event and calculate a payload checksum.
3. Enforce merchant-scoped idempotency.
4. Validate program dates, status, budget, inventory, and recipient limits.
5. Persist recipients and eligibility decisions.
6. Reserve an allocation.
7. Expand quantity into one issuance job per future PPPM envelope.
8. A dedicated issuance worker calls the established PPPM creation service and stores the resulting PPPM item ID.

## Supported programs

- Ecommerce purchases and individual invoice lines
- Merchant-funded free gifts
- Contests and giveaways
- Fundraising rewards
- Workplace reward programs
- Gaming and achievement APIs
- Batch and CSV imports
- General external APIs and signed webhooks

## Safety rules

- The same source event cannot issue twice.
- Reuse of an idempotency key with changed content is rejected.
- Budgets and item limits are reserved transactionally before jobs are created.
- One job represents one eventual PPPM item, even when a purchase line quantity is greater than one.
- Raw email addresses and phone numbers are not retained in recipient matching fields; keyed hashes are used.
- Webhook timestamps have a five-minute replay window and signatures use HMAC-SHA256.
- Source credentials are never returned by read APIs.
- Failed issuance jobs retry with bounded attempts and ultimately move to dead-letter status.

## Stage boundary

This package provides the normalized program, source, recipient, allocation, job, webhook, and analytics layer. The issuance worker must call the existing PPPM item-creation service rather than duplicate lifecycle logic. Provider-specific ecommerce, gaming, payroll, fundraising, and contest adapters can now be added without changing the core distribution schema.

## Stage 4F carry-forward

Stage 4F should combine product, delivery, engagement, fulfillment, distribution, and redemption facts into future-demand intelligence views, forecasting features, merchant dashboards, and privacy-safe exports.
