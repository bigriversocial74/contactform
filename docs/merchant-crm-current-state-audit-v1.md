# Merchant CRM Current-State Audit & Cleanup v1

## Scope

This audit reviewed the current Merchant CRM page and its live data/runtime layers on `integration-from-repair-20260628`. It intentionally preserves the current visual design, KPI dashboard, contact table/mobile cards, identity review, messaging, rewards, follow-ups, campaign activity, analytics, and export behavior.

## Confirmed architecture before cleanup

The page combined two legitimate but separate contact surfaces:

1. `campaign_contacts` supplied campaign activity, reward, invite, email, media-progress, and timeline operations.
2. `merchant_crm_contacts` supplied the canonical merchant-owned customer identity used by Merchant Agent search, lifecycle stage, CRM status, username mentions, aggregate purchase/reward totals, and customer profiles.

The page also loaded separate desktop and mobile search scripts. Both independently hid the same rows, maintained separate query state, attached separate event listeners, and used separate `MutationObserver` instances. A third script globally wrapped `Microgifter.get` to collapse campaign rows into customer rows.

## Cleanup decisions

### Canonical identity

`/api/merchant/merchant-crm.php` is now the versioned canonical CRM directory read endpoint. It remains owner-scoped through the authenticated merchant permission boundary and returns public CRM contact IDs, usernames, lifecycle stage, CRM status, engagement score, source/campaign context, current totals, profile routes, and bounded pagination.

Campaign activity rows remain the operational source for campaign-specific reward, invite, message, media, and timeline actions. The browser data bridge attaches the canonical CRM identity to each collapsed campaign customer using normalized merchant-owned email or phone identity. It does not create a third contact store.

### Search and pagination

Desktop and mobile now share one query, one URL state, one matching algorithm, one empty-state decision, and one 25-row progressive pagination controller. Search covers:

- CRM username and `@mention`
- name
- email
- phone
- campaign title and type
- source
- lifecycle stage
- CRM status
- engagement/result state
- suggested next action
- campaign-contact and canonical CRM public IDs

The former desktop search runtime and the mobile search implementation were removed. Mobile dashboard JavaScript now owns only the responsive overview accordion.

### Rendering and actions

The existing CRM table/card renderer remains authoritative for layout and campaign operations. The new directory runtime decorates rendered rows with canonical identity attributes and a compact username/stage/status line, then points the View Customer action to the canonical merchant customer profile when available.

Message, reward, follow-up, campaign, identity merge, timeline, analytics, export, and review operations were not moved or duplicated. Their existing server endpoints remain final authority for ownership, permissions, CSRF, validation, idempotency, and audit logging.

## Removed runtime debt

- `assets/js/merchant-crm-contact-rollup.js`
- `assets/js/merchant-crm-desktop-search.js`
- duplicate mobile row filtering
- duplicate search listeners
- duplicate search `MutationObserver` loops
- independent desktop/mobile query state

## Preserved behavior

- Current CRM visual design and KPI layout
- Desktop table and mobile card presentation
- Campaign contact rollup into one customer row
- Campaign filtering and entry-action deep links
- Customer profile, timeline, message, reward, follow-up, identity-review, and export controls
- Merchant ownership and permission boundaries
- Merchant Agent canonical CRM search

## Follow-on foundation

The new directory contract exposes the canonical CRM contact ID and username beside the campaign contact ID. This is the required foundation for Merchant Contact Action Center v1, where one selected canonical contact can remain attached to Merchant Agent chat while campaign, purchase, reward, claim, redemption, message, note, tag, and follow-up context is loaded through owner-scoped APIs.

## SQL

No SQL required. The cleanup uses the current `merchant_crm_contacts`, campaign contact, identity, event, reward, message, and profile schema.
