# 03J Microgifter Stage 1 Installation and Local Test Guide

## Status

Completed.

## Files added

```text
docs/installation/stage_1_installation_and_local_test_guide.md
docs/installation/first_run_admin_setup.md
tests/stage_1_api_curl_smoke_examples.md
```

## Purpose

This pass documents how to install, configure, and smoke-test the Stage 1 identity/auth foundation without guessing.

## Key coverage

- PHP/MySQL requirements
- SQL import command
- DB config location
- active PHP root page list
- first browser test route list
- registration and login verification
- first-run admin promotion through SQL
- admin/audit endpoint checks
- cURL smoke examples
- security reminders before production

## Important decisions

The first admin account should be promoted manually in SQL. There should not be a public admin-registration endpoint.

The cURL guide explicitly notes that CSRF tokens are session-bound, so browser testing or a cookie-preserving REST client is preferred for full API testing.

## Next recommended pass

```text
03K_microgifter_stage1_preflight_and_config_hardening
```

Focus:

1. Add `.env.example` or update it if already present.
2. Add a production-safe config loading path.
3. Add server preflight checklist.
4. Add `docs/installation/server_security_checklist.md`.
5. Review whether SQL/docs should be blocked from public web access before launch.
