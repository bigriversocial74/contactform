# Pull Request Workflow Fanout Repair — 2026-07-31

## Problem

A single Creator affiliate operations pull request generated more than 170 unrelated GitHub Actions runs. Many historical feature workflows still had either no `paths` filter or broad shared-file patterns such as `includes/**/*.php`, `api/merchant/*.php`, or an unqualified `pull_request` event.

Cancelled runs were displayed by GitHub as unsuccessful checks, making the PR appear to have roughly 170 failures even though those runs had not executed application tests.

## Repair

This change is limited to GitHub Actions governance. It does not modify application code, database migrations, runtime configuration, deployment files, or production data.

### Retained automatic Creator certification

`creator-campaign-production-audit-v1.yml` remains the automatic consolidated Creator Campaign gate. It retains:

- PHP 8.2 and PHP 8.3 validation
- Phase 1–15 scored validators
- Creator Campaign PHPUnit contracts
- MCP TypeScript and Node tests
- migration-manifest validation
- repository production-quality validation
- MySQL lifecycle and idempotency checks

### Retired redundant Creator workflows

The individual Phase 1–14 workflow files are removed because their contracts are already executed by the consolidated production audit. The Phase 15 workflow remains available through `workflow_dispatch` but no longer runs automatically on pull requests.

### Narrowed historical workflow triggers

The following workflow families now run only when their own runtime surface changes:

- App Layout
- Action Center v2
- Gift Product Image Consistency
- Merchant Contact Action Center
- Merchant Agent Canvas Context
- CRM Lookup
- Design Calendar modal/side view
- Training Lab pilot issue
- Stage 12 campaign merchant APIs
- Task Agent Phase 4.1–4.5
- MCP Native Draft Status and Automation Phase 4A–4D

## Expected result

Creator affiliate pull requests should launch only the consolidated Creator Campaign audit, repository production-quality validation, and genuinely relevant shared navigation or security gates. Historical Task Agent, MCP automation, Stage 12, Training Lab, CRM, Design Calendar, Action Center, and individual Creator phase workflows should no longer fan out for unrelated changes.

## Safety boundary

- No validation logic was removed from the consolidated Creator Campaign production audit.
- Historical non-Creator workflows remain automatic for their own scoped files.
- Phase 15 remains manually runnable.
- No workflow grants additional write permissions.
- No application, SQL, deployment, or production state is changed.
