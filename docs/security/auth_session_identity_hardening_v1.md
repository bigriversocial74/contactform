# Auth, Session and Identity Production Hardening v1

## Deployment order

1. Back up the database.
2. Import `database/auth_session_identity_hardening_v1.sql` after the Stage 1 identity and `03M` session migrations.
3. Configure the production environment values below.
4. Upload the integration branch code.
5. Sign out and sign back in on each test account.
6. Test registration, verification, login, reset, session revocation, MFA enrollment, MFA login, recovery codes, and MFA disable.

The migration preserves existing active accounts by marking legacy accounts verified during rollout. New accounts remain verification-gated.

## Required production configuration

- `MG_APP_ENV=production`
- `MG_DEBUG=false`
- `MG_BASE_URL=https://microgifter.com`
- `MG_MAIL_ENABLED=true`
- `MG_MAIL_PROVIDER=mail` or a production mail adapter
- `MG_MAIL_FROM_EMAIL=no-reply@microgifter.com`
- `MG_MFA_ENCRYPTION_KEY=<base64 32-byte key>`
- HTTPS enabled

Generate the MFA key once:

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Store the key only in the hosting environment or `api/config.local.php`. Losing or changing it invalidates encrypted authenticator secrets and recovery-code verification.

## Session policy defaults

- Customer absolute lifetime: 30 days
- Customer idle lifetime: 12 hours
- Admin absolute lifetime: 8 hours
- Admin idle lifetime: 30 minutes
- Session-ID rotation: 15 minutes
- Sensitive-action reauthentication: 10 minutes

All values are configurable through `.env` or `api/config.local.php`.

## Identity protections

- Pages and APIs use one DB-backed session validator.
- Revoked, expired, inactive, missing, or auth-version-mismatched sessions fail closed.
- Roles and permissions are reloaded from the database during authenticated requests.
- Password reset increments `auth_version` and revokes all sessions in the same transaction.
- Merchant and customer registration records are created atomically.
- Existing password hashes are upgraded during successful authentication when PHP recommends rehashing.
- Unknown-email login attempts execute a dummy password verification to reduce timing-based account discovery.
- Password reset and email verification links use hashed, expiring, single-use tokens.
- New accounts are blocked from protected APIs until email ownership is verified.

## MFA foundation

TOTP secrets use AES-256-GCM encryption at rest. Recovery codes are shown once and stored only as keyed hashes. TOTP counters and recovery codes are consumed to prevent replay. Enabling or disabling MFA increments the account auth version and revokes previous sessions.

The API surface is:

- `GET /api/me/mfa/status.php`
- `POST /api/me/mfa/setup.php`
- `POST /api/me/mfa/confirm.php`
- `POST /api/me/mfa/disable.php`
- `POST /api/auth/mfa/verify.php`
- `POST /api/auth/reauth.php`

## Mail delivery

Production recovery and verification depend on a real configured provider. Development `log` mode deliberately reports delivery as unavailable in production so the application cannot silently claim an email was sent.

## Rollback

Code can fall back to the pre-hardening session columns before the migration is present. Do not drop hardening columns or MFA tables while any account has MFA enabled. A rollback should first disable MFA enforcement, revoke sessions, and preserve a database backup.
