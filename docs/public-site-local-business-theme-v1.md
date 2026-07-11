# Public Site Local Business Theme v1

This release extends the white, navy, and green visual system from the logged-out homepage across the public Microgifter experience.

## Shared public shell

- The logged-out public header now uses the local-business palette and retains the existing logo, phone number, social links, navigation, demo action, account menu, and mobile menu.
- The universal footer retains every existing footer content block, navigation link, market summary item, account link, workspace link, and utility link while adopting the navy and green theme.
- A shared logged-out public stylesheet supplies consistent colors, focus states, buttons, form controls, cards, mobile navigation, and page backgrounds to all public pages.

## Pricing

- `pricing.php` is rebuilt around the published package source in `includes/pricing-packages.php`.
- The page includes a local-growth hero, connected growth loop, plan cards, shared foundation, package comparison table, sales support section, FAQ, and final conversion panel.
- Package prices, limits, features, CTA routes, and featured-plan status remain server-authoritative.

## Authentication

The following pages share one responsive auth component:

- Sign In
- Customer Sign Up
- Merchant Sign Up
- Forgot Password
- Reset Password
- Verify Email

All existing CSRF fields, API actions, redirects, tokens, account-type rules, and password requirements remain unchanged.

## Data and behavior boundaries

This release changes public presentation and copy only. It does not change account, wallet, campaign, reward, claim, redemption, payment, commerce, or database mutation authority.

## SQL

No SQL required.
