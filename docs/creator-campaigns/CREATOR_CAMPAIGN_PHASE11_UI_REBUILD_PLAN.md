# Creator Campaign Phase 11 — Six-Screen UI Rebuild

Phase 11 rebuilds the production Creator Campaign workspaces from the six approved existing mockup assets. No new mockup images are generated.

## Approved references

1. `merchant-creator-campaign-overview.png`
2. `creator-campaign-builder-compensation.png`
3. `merchant-campaign-detail.png`
4. `merchant-applications-content-review.png`
5. `creator-discover-campaigns.png`
6. `creator-active-campaign-workspace.png`

## Production rebuild scope

- Merchant Creator Campaign overview
- Merchant ten-step campaign builder
- Merchant campaign detail workspace
- Merchant applications and content-review workspace
- Creator campaign discovery workspace
- Creator active-campaign, tracking, performance, earnings, payouts, and messages workspace

## Visual authority

The six approved images control the main-content composition, density, card hierarchy, table placement, filters, metric treatment, campaign imagery, Creator identity treatment, and responsive information hierarchy.

The existing Microgifter shared application shell remains authoritative for global header, footer, sidebar, authentication, permissions, typography variables, buttons, forms, alerts, and mobile navigation.

## Functional boundary

The rebuild must preserve all production functionality delivered in Phases 1–10. It may consolidate existing routes and shared view components, but it must not replace live data or actions with static mock content.

## Validation

- Six-screen visual-alignment score: 100/100
- PHP 8.2 and PHP 8.3 syntax and contracts
- JavaScript syntax
- Existing Phase 1–10 compatibility
- Authenticated app-layout validation
- Stage 12 campaign validation
- Clean MySQL lifecycle using existing migrations
- No SQL unless a real data-contract gap is discovered
