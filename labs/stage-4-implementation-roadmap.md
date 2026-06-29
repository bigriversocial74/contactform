# Training Lab Stage 4 Implementation Roadmap

Stage 4 should only begin after Stage 3 schema/account integration is approved.

## Stage 4A — Read-Only Backend Wiring

Goal:
- Replace static page counts with read-only data.
- No writes yet.

Tasks:
- Add repository/service layer for campaigns.
- Add repository/service layer for tasks.
- Add repository/service layer for participants.
- Add read-only admin queue query.
- Keep proof submit/review buttons disabled or demo-only.

Score target: 10/10 before writes are added.

## Stage 4B — Controlled Proof Submission

Stop point before this phase:
- Confirm upload storage.
- Confirm allowed file types and size limits.
- Confirm moderation/review policy.

Tasks:
- Add proof submission endpoint.
- Add validation.
- Store file reference, not arbitrary blobs in app tables.
- Add status transitions.

## Stage 4C — Manual Review Workflow

Tasks:
- Add reviewer decision endpoint.
- Validate reviewer role.
- Add review records.
- Create action receipt after approval.
- Do not issue rewards automatically until Stage 4D.

## Stage 4D — Reward Rule Evaluation

Stop point before this phase:
- Confirm wallet/reward ownership mapping.
- Confirm reward inventory/source.
- Confirm claim/redeem boundaries.

Tasks:
- Evaluate approved action receipts against rules.
- Create training reward event.
- Link to Microgifter wallet/reward layer.
- Do not change wallet balances unless explicitly approved.

## Stage 4E — Production Hardening

Tasks:
- Audit permissions.
- Add rate limits.
- Add event logs.
- Add admin status views.
- Add rollback plan.
- Add SQL migration checks.
- Add browser test checklist.

## Stage 4 Score

Current score: 7/10 because implementation depends on approved schema and existing-account inspection.

To reach 10/10:
- Resolve table names.
- Confirm wallet/reward integration contract.
- Confirm media policy.
- Confirm deploy target and rollback path.
