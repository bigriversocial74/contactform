# HostGator / cPanel Compatibility Profile

## Purpose

This profile lets Microgifter run on basic HostGator/cPanel hosting while the platform is still being built. It keeps Stage 1 compatible with PHP/MySQL shared hosting without abandoning the longer-term AWS/Aurora scale path.

## Deployment role

HostGator/cPanel should be treated as:

- local-like staging
- private beta hosting
- early UI/auth testing
- low-traffic prototype hosting

It should not be treated as the final high-volume production environment for real-time agents, large inbox traffic, checkout scale, analytics pipelines, or enterprise usage.

## Required hosting capabilities

Minimum expected support:

- PHP 8.1 or newer preferred
- MySQL or MariaDB database
- PDO MySQL extension
- JSON extension
- cURL extension preferred for tests/integrations
- Apache `.htaccess`
- phpMyAdmin or another SQL import tool
- writable PHP session storage
- HTTPS enabled

## Optional hosting capabilities

Helpful but not required for Stage 1:

- SSH access
- Composer available over SSH
- cron jobs
- Git deployment
- custom document root control
- email sending configuration

## Stage 1 compatibility

The current Stage 1 platform remains compatible because it uses:

- PHP pages
- MySQL-compatible SQL
- Apache `.htaccess` protections
- manually importable SQL migrations
- no required Redis/Valkey
- no required long-running worker
- no required AWS-only service
- DB-backed outbox/idempotency tables instead of external queues

## Known shared-hosting limits

Basic shared hosting may not support:

- long-running queue workers
- Redis/Valkey
- process supervisors
- advanced server-level Nginx config
- high concurrency
- autoscaling
- full `/public` document-root control
- large file/media processing
- enterprise-grade observability

## Required install order

Import SQL in this order:

```text
database/stage_1_identity.sql
database/stage_1_repair_03M.sql
database/stage_1_security_hardening_03N.sql
database/stage_1_security_hardening_03N_3.sql
database/stage_1_high_volume_foundation_03O.sql
```

## HostGator operating mode

For HostGator, keep async operations in DB tables first:

- `outbox_events`
- `idempotency_keys`
- `read_model_refreshes`
- `security_logs`
- `rate_limits`

If cron jobs are available, a future pass can add small cron-driven processors. Until then, do not require background workers for the core app to function.

## Security requirements

Before using HostGator for private beta:

- confirm HTTPS is active
- confirm `.htaccess` blocks internal folders
- confirm `.env`, `.sql`, `database/`, `docs/`, `includes/`, `scripts/`, and `tests/` are blocked
- disable browser error display
- keep SQL imports backed up
- use strong DB credentials
- do not expose public admin promotion endpoints
- run smoke tests after every upload

## AWS migration path

HostGator should not change the architecture direction. Future AWS target remains:

- Aurora MySQL-compatible as relational source of truth
- ElastiCache Redis/Valkey for hot cache/session/rate acceleration
- S3 for media/object storage
- CloudFront for static/media delivery
- SQS/EventBridge for external async workflows
- CloudWatch or equivalent for metrics/logs/alerts

## Carry-forward rule

Every new module must remain HostGator-friendly unless explicitly marked AWS-only. AWS-only features must have a graceful fallback or be disabled until the AWS production environment is active.
