# Contributing to Microgifter

## Branch and pull-request workflow

1. Start from the latest `integration-from-repair-20260628` unless an existing stacked PR is the explicit dependency.
2. Create one narrowly scoped feature, fix, or audit branch.
3. Inspect the canonical files and ownership boundaries before editing.
4. Keep unrelated formatting and generated-file changes out of the branch.
5. Open the pull request back into `integration-from-repair-20260628`.
6. Do not merge into `main` unless the deployment line is intentionally being synchronized.

Stacked pull requests must state their merge order and must be retargeted to the integration branch after their dependency merges.

## Code requirements

- PHP must be compatible with the versions enforced by CI.
- Database access must use prepared statements for request-derived values.
- Mutations must enforce the HTTP method and the appropriate authenticated, merchant, admin, token, or signed-webhook authority.
- Public responses must not expose raw exceptions, credentials, internal IDs, or private metadata.
- Browser-generated HTML must escape untrusted values and avoid unsafe URL schemes.
- New runtime behavior must have a regression test or static contract validator.
- Reuse canonical ownership, payment, claim, redemption, notification, and Action Center authorities instead of creating parallel state.

## Local configuration

Copy `api/config.local.example.php` to the ignored `api/config.local.php` only on the target environment. Never commit production credentials, local database exports, logs, uploaded customer files, claim codes, or payment tokens.

## Required validation

Run the changed-file preflight:

```bash
composer validate-branch
```

For release-sensitive changes, run the complete recovery baseline:

```bash
composer recovery-baseline
```

Run the repository production audit before requesting merge:

```bash
composer audit-repository-quality
```

The pull request must pass all relevant GitHub Actions workflows. Do not state that tests passed unless they were actually run or verified in Actions.

## Database changes

- Add SQL only when required.
- Register migrations in `config/migrations.php`.
- Make migrations safe for the supported upgrade path.
- State the SQL filename and import order in the PR body.
- Write **No SQL required** when no database change is needed.

## Pull-request description

Include:

- Problem and root cause.
- Files and behavior changed.
- Security and ownership boundaries preserved.
- Validation actually performed.
- SQL requirements.
- Deployment and browser QA steps.
