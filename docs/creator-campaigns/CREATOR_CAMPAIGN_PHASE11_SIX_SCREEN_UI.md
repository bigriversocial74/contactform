# Creator Campaign Phase 11 — Six-Screen UI Rebuild

Phase 11 rebuilds the Creator Campaign user interface from the six approved repository mockups while preserving the existing Microgifter application shell and every Phase 1–10 business service.

## Approved visual references

The existing repository mockup set remains the source of truth for main-content composition and density:

1. `merchant-creator-campaign-overview.png`
2. `creator-campaign-builder-compensation.png`
3. `merchant-campaign-detail.png`
4. `merchant-applications-content-review.png`
5. `creator-discover-campaigns.png`
6. `creator-active-campaign-workspace.png`

No new mockup or decorative image assets are introduced in Phase 11.

## Rebuilt production screens

- Merchant overview: `/merchant-creator-campaigns.php`
- Ten-step builder: `/merchant-creator-campaign-builder.php`
- Merchant campaign detail: `/merchant-creator-campaign-detail.php?campaign={public_id}`
- Merchant applications and Creator review: `/merchant-creator-participation.php`
- Merchant deliverables and content review: `/merchant-creator-deliverables.php`
- Creator discovery and campaign participation: `/creator-campaigns.php`
- Creator active-campaign action center: `/creator-campaign-deliverables.php`

The applications/content-review reference is represented by two operational pages because Microgifter already separates participation review from deliverable review. Both pages use the approved three-column review density and campaign-detail navigation language.

## Preserved architecture

Phase 11 changes presentation and navigation only. It preserves:

- Existing Microgifter header, footer, account shell, merchant shell, and sidebars.
- Merchant workspace/package access checks.
- Creator-model and ownership controls.
- All existing API endpoints.
- Existing CSRF, optimistic-lock, idempotency, audit, and permission controls.
- Phase 1–10 tables and services.
- Existing JavaScript data hooks and form names.
- Canonical Messages and Notifications routes.

The dedicated Merchant Campaign Detail page reads authoritative data from:

- `/api/merchant/creator-campaigns.php?action=detail`
- `/api/merchant/creator-campaign-analytics.php`

It does not persist metric counters or create a duplicate report store.

## Builder boundary

The ten-step builder continues to save its canonical campaign-foundation steps. Steps managed by later production services now open their operational workspaces instead of displaying obsolete “later phase” placeholders:

- Deliverables → Phase 4 workspace
- Compensation → Phase 6 workspace
- Attribution → Phase 5 workspace
- Budget → Phase 7 workspace
- Content rights and terms → Phase 3 agreement workspace

This keeps each domain’s existing transaction, permission, and audit boundaries intact.

## Visual direction

- Light professional interface.
- White and soft-gray surfaces.
- Strong black typography.
- Restrained blue accents.
- Rounded operational cards with subtle borders and shadows.
- Dense but readable dashboards.
- Compact page headers rather than oversized hero sections.
- Responsive desktop, tablet, and mobile behavior.
- Existing Microgifter controls and typography remain authoritative.

## SQL

**No SQL required.** Phase 11 creates no tables, columns, permissions, cached counters, or materialized reports.

## Deferred

CRM contact lifecycle integration remains the next separate phase. It is intentionally not mixed into this visual rebuild.