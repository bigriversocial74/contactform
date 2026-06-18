# Security Log Monitoring

## Purpose

Microgifter now writes structured security events to the `security_logs` table. These logs should be monitored before public traffic and throughout production operation.

## What to monitor

High-priority event families:

- `auth.login_failed`
- `auth.login_blocked`
- `csrf.invalid`
- `permission.denied`
- `object.permission_denied`
- `session.revoked_or_expired`
- `password_reset.requested`
- `password_reset.completed`
- `profile.validation_failed`
- `audit.write_failed`
- `event.write_failed`

## Admin API

Authorized admins can review logs through:

```text
/api/admin/security-logs.php
```

Required permission:

```text
admin.security_logs.view
```

## CLI report

Run:

```bash
php scripts/security_log_report.php
```

Recommended schedule before public traffic:

```text
Every 15 minutes during test windows.
Daily during private beta.
Hourly or streamed to an external monitor before public launch.
```

## Alert thresholds for beta

Initial alert rules:

- More than 25 failed logins from one IP in 15 minutes.
- More than 10 CSRF failures from one IP in 15 minutes.
- Any `audit.write_failed` or `event.write_failed` event.
- Any permission-denied spike against admin endpoints.
- Any session-revoked event followed by continued access attempts.

## Production carry-forward

Before public traffic, connect these logs to the chosen hosting monitoring layer or external security event system.
