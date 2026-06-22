# Stage API-16 — Public API launch validation

Stage API-16 adds a repeatable static validation command for the Public Distribution API launch package.

## Command

```bash
php scripts/validate_public_api_launch_package.php
```

## What it checks

The validator confirms that the launch package still includes:

- public API route files
- merchant launch QA and go-live control endpoints
- bearer-only public API authentication markers
- rate-limit header markers
- webhook signature headers and signing implementation markers
- webhook URL policy markers
- launch QA blocker markers
- go-live action markers
- refreshed public developer docs launch path
- launch checklist docs
- error reference docs
- webhook verification examples
- sandbox-to-live guide

## Output

The command returns JSON:

```json
{
  "ok": true,
  "checks": [],
  "failed": [],
  "generated_at": "2026-06-22T00:00:00+00:00"
}
```

A non-zero exit code means one or more launch package requirements are missing.

## Runtime impact

No database changes. No public route behavior changes. This stage adds validation coverage for the launch-ready API surface built in Stages API-12 through API-15.
