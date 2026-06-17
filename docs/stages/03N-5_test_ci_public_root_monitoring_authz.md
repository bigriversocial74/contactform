# 03N-5 Test, CI, Public Root, Monitoring, and Object Authorization Pass

## Purpose

This pass completed the next security/stability improvements before Stage 2:

1. Composer and PHPUnit dev tooling.
2. Authenticated test fixtures.
3. GitHub Actions CI for syntax checks and PHPUnit.
4. Public web-root transition documentation.
5. Security-log monitoring support.
6. Object-level authorization helpers and tests.

## Files added

```text
composer.json
.github/workflows/stage1-security-ci.yml
includes/authorization.php
api/admin/security-logs.php
scripts/security_log_report.php
public/README.md
docs/deployment/public_web_root_transition.md
docs/deployment/production_public_root_profiles.md
docs/monitoring/security_log_monitoring.md
docs/security/object_level_authorization_policy.md
docs/testing/phpunit_security_testing.md
tests/phpunit/ObjectAuthorizationTest.php
```

## Files updated

```text
account.php
tests/phpunit/bootstrap.php
tests/phpunit/Stage1SecurityEndpointTest.php
```

## Security impact

- Tests can now be run through Composer.
- CI can run PHP syntax checks and the Stage 1 security test suite.
- Authenticated fixture tests can register a temporary user, load/update profile data, and verify session APIs.
- Users can see session management in the account page.
- Admin users with security-log permission have a dedicated security log endpoint.
- Object ownership checks now have a reusable helper and test coverage before product/gift/order/inbox/agent modules are built.
- Public-root deployment is now documented as a pre-production requirement.

## Known limitations

- CI skips authenticated tests unless a deployed app and database are available.
- `public/` is documented and prepared as the target web root, but the active app still has root-level PHP pages during Stage 1 development.
- A full deployment-specific public front controller should be finalized once the production hosting model is selected.

## Next recommended security work

1. Add database-backed test fixtures for admin users and permissions.
2. Add CI service containers for MySQL so authenticated tests can run in GitHub Actions.
3. Add a formal `/public` production front controller after hosting model selection.
4. Add object-level authorization tests for every Stage 2+ data module as those modules are implemented.
5. Add alerting around `security_logs` before any public launch.
