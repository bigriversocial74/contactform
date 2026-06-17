# Stage 5E Acceptance Checklist

- Merchant distribution dashboard is scoped by `merchant_user_id`.
- Programs support draft, scheduled, active, paused, completed, cancelled, and archived states.
- Campaign types include purchase, merchant grant, contest, giveaway, fundraiser, workplace reward, gaming, API, batch, and other inputs.
- Program product inventory uses active PPPM templates.
- Recipient eligibility updates are permission-protected, CSRF-protected, and audited.
- Eligibility decisions preserve before/after status, reason, and rules snapshot.
- Assignment batches require eligible or selected recipients.
- Assignment batches preserve program, template, recipient, quantity, and method.
- Allocations and issuance jobs remain linked to resulting PPPM items.
- Input source connections and issuance queue health are visible.
- Existing Stage 4E idempotency, capacity, budget, and per-recipient limits remain authoritative.
- Pages reuse the Stage 5 merchant shell on desktop and mobile.
