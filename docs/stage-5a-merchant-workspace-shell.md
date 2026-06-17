# Stage 5A Merchant Workspace Shell, Onboarding Foundation, and Navigation

Stage 5A introduces the shared merchant operating shell using the existing agent, account, and builder design language. It does not create a fourth visual system.

## Included

- Permission-aware merchant workspace routes and shared sidebar
- Mobile drawer compatibility through the universal header
- Database-backed merchant workspace and ordered onboarding steps
- Business settings and eligibility state foundation
- Merchant locations and primary-location selection
- Merchant team invitation records with keyed email hashes
- Payment-readiness status without payment processing
- Reserved routes for products, storefront, PPPM, distribution, claims, media, and intelligence
- Setup progress, save state, readiness summaries, and consistent empty states

## UI contract

All future Stage 5 merchant pages must reuse `includes/merchant-workspace.php`, `assets/css/merchant-workspace.css`, the universal header, account dropdown, app-shell panel classes, and existing mobile drawer behavior.

## Payment boundary

Stage 5A stores readiness only. It does not process cards, create payment intents, capture funds, issue refunds, manage disputes, calculate taxes, or initiate payouts. PPPM items remain independent of payment transactions.

## Carry-forward

- Stage 5B connects catalog, builder, publishing, and asset management.
- Stage 5C connects storefront management.
- Stage 5D connects orders and PPPM operations.
- Stage 5E connects distribution programs.
- Stage 5F integrates intelligence.
- Stage 5G connects claims, locations, and verification.
- Stage 5H adds worker and support operations.
- Stage 5I adds controlled beta readiness.
