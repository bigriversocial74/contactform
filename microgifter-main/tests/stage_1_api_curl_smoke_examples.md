# Microgifter Stage 1 API cURL Smoke Examples

Replace the host with your local or server URL.

```bash
BASE_URL="https://your-domain.com"
```

For local PHP server testing:

```bash
BASE_URL="http://localhost:8000"
```

## 1. Health check public pages

```bash
curl -i "$BASE_URL/index.php"
curl -i "$BASE_URL/signup.php"
curl -i "$BASE_URL/signin.php"
```

Expected: HTTP 200 and HTML output.

## 2. Register a user

The app uses CSRF protection from PHP pages. Browser testing is preferred for registration. For API-only testing, temporarily use a valid CSRF token from the page/session.

Example payload shape:

```bash
curl -i -X POST "$BASE_URL/api/auth/register.php" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: YOUR_CSRF_TOKEN" \
  -d '{
    "name":"Test User",
    "email":"test.user@example.com",
    "password":"ChangeMe123!"
  }'
```

Expected:

- success response
- user object without password hash
- default role assigned
- audit/event rows written

## 3. Login

```bash
curl -i -c cookies.txt -X POST "$BASE_URL/api/auth/login.php" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: YOUR_CSRF_TOKEN" \
  -d '{
    "email":"test.user@example.com",
    "password":"ChangeMe123!"
  }'
```

Expected:

- success response
- session cookie written to `cookies.txt`
- roles and permissions included in current user payload

## 4. Current user

```bash
curl -i -b cookies.txt "$BASE_URL/api/auth/me.php"
```

Expected:

- authenticated user payload
- `roles` array
- `permissions` array

## 5. Unauthorized admin access as normal user

```bash
curl -i -b cookies.txt "$BASE_URL/api/admin/users.php"
curl -i -b cookies.txt "$BASE_URL/api/admin/audit-logs.php"
```

Expected for non-admin:

```text
HTTP 403
```

## 6. Admin access after first-run admin promotion

After following `docs/installation/first_run_admin_setup.md`, sign in again and run:

```bash
curl -i -b cookies.txt "$BASE_URL/api/admin/users.php"
curl -i -b cookies.txt "$BASE_URL/api/admin/audit-logs.php?limit=25"
```

Expected for authorized admin:

```text
HTTP 200
```

## 7. Password recovery request

Browser testing is preferred until mail delivery is fully wired. Endpoint payload shape:

```bash
curl -i -X POST "$BASE_URL/api/auth/password/forgot.php" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: YOUR_CSRF_TOKEN" \
  -d '{"email":"test.user@example.com"}'
```

Expected:

- generic response that does not reveal whether an account exists
- reset token row may be written depending on implementation state

## 8. Logout

```bash
curl -i -b cookies.txt -c cookies.txt -X POST "$BASE_URL/api/auth/logout.php" \
  -H "X-CSRF-Token: YOUR_CSRF_TOKEN"
```

Expected:

- session cleared
- redirect hint to `/signin.php`
- audit/event rows written when a user was logged in

## 9. Re-check current user after logout

```bash
curl -i -b cookies.txt "$BASE_URL/api/auth/me.php"
```

Expected:

```text
HTTP 401
```

## Notes

CSRF tokens are session-bound. For accurate testing, use browser dev tools, a REST client that preserves cookies, or a small local test harness that first loads the form page, extracts the token, and submits with the same cookie jar.
