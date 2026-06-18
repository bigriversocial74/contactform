# Admin system health

The System Health dashboard is available at `/admin/system-health.php` to accounts with `admin.health.view` access.

## Read-only health checks

The dashboard reports:

- Persistent media readiness, initialization, write access, and whether storage is outside the web root.
- Persistent media file count, recorded bytes, free disk capacity, unattached uploads, and a bounded missing-file scan.
- Notification delivery totals for queued, overdue, processing, sent, delivered, failed, retrying, and suppressed jobs.
- Canonical database migration status and the most recently applied migration.
- Database, runtime environment, runtime profile, and PHP version.
- Recent warning, error, and critical security events plus open operational alerts.

The read endpoint is `/api/admin/system-health.php`. It does not initialize storage, create directories, or perform a write probe.

## Recovery actions

Recovery actions require a `super_admin` role. Every request is CSRF-protected, rate-limited, bounded, and written to the audit and event logs.

- **Verify storage** performs a temporary write, read, checksum comparison, and delete probe.
- **Retry failed notifications** requeues at most 100 failed jobs with fewer than five attempts.
- **Clean abandoned uploads** archives and removes at most 100 persistent feed uploads that are older than 24 hours, unattached, and not referenced by post media JSON.

The action endpoint is `/api/admin/system-health-action.php`.

## Safety limits

The dashboard checks at most 500 recent persistent files per page load. The UI clearly identifies when that scan is limited. Recovery actions operate on at most 100 records per request, and destructive cleanup confirms the action in the browser before sending it.
