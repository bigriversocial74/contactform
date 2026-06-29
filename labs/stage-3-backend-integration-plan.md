# Training Lab Stage 3 Backend Integration Plan

Stage 3 is planning/specification only until explicitly approved for database work.

## Stop Boundary

Do not build or run real migrations yet. Do not wire uploads, payments, wallet writes, claim/redeem actions, or a separate account system.

## Existing Microgifter System Must Remain Source of Truth

Training Lab should attach to the existing Microgifter account system:

- users
- merchant / organization ownership
- roles and permissions
- wallet ownership
- reward ownership
- claim status
- billing/customer account data later

Training Lab should not create duplicate auth, duplicate wallets, duplicate billing, or duplicate reward ownership.

## Proposed Training Lab Tables

1. `training_campaigns`
2. `training_campaign_tasks`
3. `training_participants`
4. `training_proof_submissions`
5. `training_reviews`
6. `training_action_receipts`
7. `training_reward_rules`
8. `training_reward_events`
9. `training_streaks`
10. `training_events`

## API Contract Draft

- `GET /api/training/campaigns`
- `GET /api/training/campaigns/{id}`
- `GET /api/training/campaigns/{id}/tasks`
- `POST /api/training/proof-submissions` later, after upload policy approval
- `GET /api/training/review-queue`
- `POST /api/training/reviews/{id}/decision` later, after permissions approval
- `GET /api/training/wallet-preview`

## Permission Rules

Participant:
- view joined campaigns
- view own tasks
- submit proof later
- view own rewards/wallet preview

Organization/Admin:
- create/manage campaigns later
- view campaign participants
- review proof submissions
- view training analytics

System:
- create action receipts after approved reviews
- evaluate reward rules
- write reward events later

## Stage 3 Score

Initial planning score: 8/10

Fixes needed before database work:
- Confirm exact existing Microgifter user/account/merchant table names.
- Confirm existing wallet/reward tables and ownership rules.
- Confirm media storage policy.
- Confirm reviewer role permissions.

Rescore target after repository inspection: 10/10.
