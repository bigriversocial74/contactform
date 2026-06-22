# Public API sandbox to live guide

Use test mode until account linking, reward issue, reward status, and webhook verification are proven end to end.

## Sandbox behavior

- Test apps and test credentials operate in sandbox mode.
- Sandbox linked accounts use deterministic sandbox IDs.
- Sandbox reward issue calls do not consume live PPPM inventory or merchant distribution capacity.
- Sandbox webhooks let developers prove delivery handling.

## Live migration

1. Confirm test requests use `Authorization: Bearer mg_test_...`.
2. Run sandbox linked-account flow.
3. Run sandbox reward issue and status lookup.
4. Verify webhook signatures in the developer backend.
5. Open the Developer API workspace and review Live launch QA.
6. Use Create live app to clone the test app into a draft live app.
7. Fix blockers shown in Live launch QA.
8. Promote the live app.
9. Create the live credential.
10. Replace the test credential with `mg_live_...` in production backend configuration.

## Live smoke test

- List programs with the live credential.
- Issue one low-risk live reward with a unique idempotency key.
- Verify status reaches queued, issued, or delivered.
- Confirm at least one signed webhook is received and verified.
