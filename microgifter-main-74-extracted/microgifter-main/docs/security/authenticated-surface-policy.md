# Authenticated Surface Policy

## Purpose

Microgifter user workspaces contain private account, commerce, agent, product, messaging, notification, gift, claim, redemption, and merchant data. These pages must never render for a visitor without a valid authenticated session.

## Canonical enforcement

The shared application header treats these header modes as private:

- `agent`
- `account`
- `crm`
- `builder`

Before any HTML is emitted, the header calls `mg_require_auth()`. Unauthenticated visitors receive a `302` redirect to `/signin.php` with a validated local `return` path. Private responses use `Cache-Control: no-store, private`.

This shared guard is defense in depth for every page using the normal authenticated application template. Sensitive pages may also call `mg_require_auth()` before database work when they need the authenticated user earlier in execution.

## Private page categories

The following categories are private by default:

- agent workspace and archived agents
- product builder and product-management pages
- account profile, settings, roles, security, and administration
- commerce center, orders, receipts, owned items, and claims
- Inbox, Sent, Claimed, and PPPM item details
- messages and gift conversations
- notifications and notification preferences
- merchant profile, locations, products, orders, redemptions, and settings
- CRM and administrative dashboards

A new page in one of these categories must use the shared authenticated application template and one of the protected header modes.

## Public route categories

Only routes intentionally designed for anonymous access should remain public:

- marketing and informational pages
- sign in, registration, password recovery, and verification entry pages
- intentionally public merchant, product, store, and user-profile pages
- public claim or invitation landing pages that reveal no private data before verification
- checkout entry pages only where the product design explicitly permits guest checkout

Public pages must not expose private user identifiers, ownership history, claim codes, messages, notification data, merchant operations, or account settings.

## API requirements

Page authentication does not replace API authorization. Every API must independently enforce:

1. authenticated identity
2. named permission or ownership checks
3. object-level authorization
4. CSRF protection for session-authenticated writes
5. method validation
6. input validation
7. privacy-safe response fields
8. no-store caching for private responses

## Review requirements

Every stage or UI pull request that adds a user-facing page must answer:

- Is the page public or private?
- Which shared template and protected header mode does it use?
- Does it perform database work before the shared auth guard?
- Which permissions and ownership checks protect its APIs?
- Are all writes CSRF protected?
- Could any identifier, message, claim code, receipt, or merchant operation leak to another user?

The PHPUnit authenticated-surface regression test locks the core private routes to protected application modes and verifies selected sensitive write APIs retain permission and CSRF gates.
