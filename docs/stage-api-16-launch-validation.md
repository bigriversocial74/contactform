# Stage API-16 — Public API launch validation

Stage API-16 adds repeatable validation for the Public Distribution API launch package and packages the Developer/Public API database layer into one operator-run SQL file.

## Validation command

```bash
php scripts/validate_public_api_launch_package.php
```

## Database install file

```text
database/developer_api_single_install.sql
```

This is the single SQL file to run when the Developer/Public API database tables need to be installed manually.

## What the validator checks

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

The validation command does not change runtime behavior. The SQL package is operator-run only and is not executed by browser traffic.
