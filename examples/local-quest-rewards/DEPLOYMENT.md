# Local Quest deployment checklist

1. Upload the `examples/local-quest-rewards` application contents to the intended host.
2. Confirm PHP 8.2+, PDO MySQL, and cURL or `allow_url_fopen` are available.
3. Open `install.php` and complete the guarded setup.
4. Confirm `config.php` and `.installed.lock` exist.
5. Confirm `.install-unlock` does not exist.
6. On Apache, retain `.htaccess`; on Nginx or another server, deny direct access to `config.php`, backups, dotfiles, and legacy `webhook-events.log`.
7. Open `runtime-diagnostics.php` and resolve every production-critical item.
8. Configure `<app_public_url>/webhook.php` in the Microgifter Developer API workspace.
9. Verify account linking, quest completion, reward issue, wallet status, claim reporting, and a signed webhook.
10. Remove or server-protect `install.php` before live traffic.

Existing databases must import `database/local_quest_production_foundation_v1.sql` before the upgraded runtime is used.
