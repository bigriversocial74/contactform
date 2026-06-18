# Frontend Entrypoints and Testing Standard

## Purpose

Microgifter is a new codebase. Frontend changes must preserve clear module boundaries and stable public contracts instead of relying on tests that inspect arbitrary file layout.

## Stable entrypoints

A stable entrypoint is a JavaScript file referenced by server-rendered pages or shared templates. Its public filename and behavior are part of the application contract.

Current stable entrypoints include:

- `assets/js/cart.js`
- `assets/js/customer-commerce.js`
- `assets/js/auth-state.js`
- `assets/js/index-agentic-onboarding.js`

A stable entrypoint may be refactored internally, but it must not silently become a loader for a renamed implementation file unless the migration is deliberate, documented, and all consumers are updated in the same change.

The cart entrypoint is specifically required to remain the implementation owner for:

- cart retrieval
- cart item updates and removal
- add-to-cart event ownership
- cart drawer behavior
- cart page rendering
- the `window.Microgifter.cart` API

## Page-specific modules

Page-specific behavior belongs in page-specific modules.

The logged-out index uses:

- `auth-state.js` as the stable public-shell bootstrap alias
- `auth-state-core.js` for public account/header normalization
- `public-index-bootstrap.js` for loading the onboarding fragment and its dedicated assets
- `index-agentic-onboarding.js` for onboarding state and interactions

Cart code must not load or own index onboarding behavior.

## DOM contracts

Selectors used across templates, scripts, and tests are contracts. Canonical selectors are declared in:

`config/frontend-contracts.php`

Examples:

- `[data-cart-add],[data-add-to-cart]`
- `[data-cart-page]`
- `[data-agentic-onboarding]`
- `[data-agentic-stage]`

A selector change must update the contract declaration, implementation, templates, and tests in one pull request.

## Test layers

### 1. Contract validation

`composer test-frontend-contracts`

This runs before schema setup and catches structural problems quickly:

- missing stable entrypoints
- required API or DOM contracts removed from an entrypoint
- forbidden implementation splits
- reintroduction of deprecated files such as `cart-core.js`

### 2. Static PHPUnit contracts

PHPUnit verifies:

- page templates reference stable entrypoints
- shared business flows preserve endpoint order
- DOM contracts remain owned by the correct module
- page-specific modules do not leak into unrelated entrypoints

Tests should read the central contract registry instead of repeating raw selector strings where possible.

### 3. Runtime HTTP smoke tests

Runtime tests request the PHP application through the test server and verify:

- public pages render successfully
- required scripts are present
- server-rendered fragments return semantic markup
- protected pages and APIs retain their authentication boundaries

### 4. Browser tests

A browser test layer should be added for the next frontend foundation phase. It should cover:

- logged-out index auto-presentation
- pause and resume behavior
- input-gated onboarding progression
- website scan success and fallback states
- cart drawer open, add, update, and remove behavior
- responsive mobile modal and drawer behavior

Browser tests should validate user-visible behavior, not CSS source strings.

## Pull request rules

A frontend pull request is not ready to merge until:

1. Stable entrypoints remain intact.
2. The frontend contract validator passes.
3. The full PHPUnit suite passes.
4. Runtime smoke tests pass.
5. New selectors or entrypoints are registered centrally.
6. A design-only change does not move unrelated implementation code.
7. Any intentional architecture migration is completed in one change rather than leaving compatibility work for later.

## Prohibited patterns

- Moving an implementation solely to make room for a UI feature.
- Loading an unrelated page feature from `cart.js`.
- Copying the same selector literal into many unrelated tests.
- Tests that depend on one exact CSS serialization or whitespace layout.
- Opening a pull request before the complete local/full test suite is expected to pass.
- Treating a red test as acceptable because the runtime might still work.
