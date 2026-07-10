# Security operations

- Keep `config.php`, configuration backups, lock files, and legacy webhook logs outside public access.
- Keep `.install-unlock` absent except during intentional maintenance.
- Remove or server-protect `install.php` after installation.
- Use HTTPS before live mode.
- Rotate Microgifter API and webhook credentials when exposure is suspected.
- Keep owner access limited and preserve at least one active owner.
- Review `runtime-diagnostics.php`, `admin-developer-readiness.php`, and the protected webhook status page before launch.
- Do not treat static validation as a substitute for database, browser, API, and signed-webhook testing.
