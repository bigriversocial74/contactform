# PHPUnit Security Testing

## Install

Run Composer from the repository root.

```bash
composer install
```

## Run tests

```bash
composer test
```

Set `MG_TEST_BASE_URL` to the local or staging URL where the app is running.

## CI behavior

The GitHub Actions workflow runs syntax checks and unauthenticated security endpoint tests with authenticated fixture tests skipped.

## Current coverage

The test suite covers shallow health output, protected endpoint authentication, CSRF rejection, authenticated profile/session flows, and object-level authorization helper behavior.

## Next coverage targets

Add fixtures for login success/failure, rate-limit lockouts, password reset session revocation, admin session permissions, security-log access, and object ownership for products, gifts, orders, inbox threads, and agents as those modules are built.
