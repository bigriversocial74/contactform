# Creator Campaign Phase 12 — CRM Contact Lifecycle Integration

## Purpose

Phase 12 connects Creator Campaign participation and attributed customer outcomes to Microgifter's canonical Merchant CRM.

It does **not** create a second contact database and it does **not** place `creator_campaigns.id` values into the legacy `merchant_crm_contact_campaigns.campaign_id` foreign key.

## Canonical boundaries

- Contact identity remains `merchant_crm_contacts`.
- Generic contact timeline remains `merchant_crm_contact_events`.
- Legacy campaign relationships remain `merchant_crm_contact_campaigns` → `campaigns.id`.
- Creator Campaign relationships use the additive `merchant_crm_contact_creator_campaigns` bridge → `creator_campaigns.id`.
- Projection idempotency and audit use `merchant_crm_creator_campaign_events`.
- Reconciliation audit uses `merchant_crm_creator_campaign_projection_runs`.

Generic CRM timeline events produced by Phase 12 deliberately use:

- `campaign_id = NULL`
- `campaign_type = creator_campaign`
- Creator Campaign public ID and route in event metadata

## Relationship roles

- `creator_partner`
- `customer_lead`
- `customer`
- `claimant`
- `redeemer`

A canonical contact may have more than one role for the same Creator Campaign. Roles are append-only relationship history; customer lifecycle stage continues to use the canonical CRM stage rules.

Creator partners begin in the CRM `custom` stage unless the contact already has a stronger customer or redeemer stage.

## Real-time projection

### Creator partner lifecycle

`mg_creator_campaign_participation_event()` projects application, invitation, participant, agreement, deliverable, submission, revision, approval, publication, and verification events because all of these domains share the Phase 3 participation event stream.

### Customer lifecycle

Accepted trusted conversion events project through `mg_creator_campaign_tracking_record_conversion()` and `mg_creator_campaign_tracking_record_by_code()`.

Supported lifecycle mapping:

- lead / checkout → `customer_lead`
- purchase → `customer`
- claim → `claimant`
- redemption → `redeemer`

## Trusted identity payload

Anonymous browser events never create CRM contacts. A trusted conversion may provide a resolvable identity in one of these metadata objects:

```json
{
  "crm_identity": {
    "user_id": 123,
    "email": "customer@example.com",
    "phone": "+16025551212",
    "name": "Customer Name"
  }
}
```

Also accepted: `customer_identity`, `customer`, or `contact`. Integrators may use the explicit top-level keys `customer_user_id`, `customer_email`, `customer_phone`, and `customer_name`.

At least one of `user_id`, valid email, or phone is required. Anonymous session, visitor, and request hashes remain tracking-only identifiers and are never promoted to CRM identity.

## Transaction and failure behavior

Projection uses a nested savepoint when the source action already owns a transaction. A CRM failure rolls back only the CRM projection and never blocks the Creator Campaign action.

The source record remains available for reconciliation. Failures are security logged without exposing customer identity.

## Reconciliation

Merchant workspace:

`/merchant-creator-crm.php`

API:

`/api/merchant/creator-campaign-crm.php`

A bounded campaign or workspace reconciliation run scans only unprojected, pending, or failed source events. Completed and privacy-skipped events are idempotent replays and are not duplicated.

## Merchant CRM integration

`/api/merchant/merchant-crm.php` enriches canonical contacts with Creator Campaign relationship metadata.

`/merchant-crm.php` loads a browser bridge that:

- adds Creator Campaign relationship labels to existing contact rows;
- appends canonical-only Creator partners who do not have a legacy campaign-contact row;
- preserves canonical customer-profile links;
- replaces unsupported legacy row actions for canonical-only rows with a safe Creator Campaign link.

## Permissions

- `merchant.creator_crm.view`
- `merchant.creator_crm.manage`

## SQL

Import after the canonical Phase 1–5 Creator Campaign migrations and Stage 12 Merchant CRM:

`database/20260722_creator_campaign_crm_lifecycle_v12_single_install.sql`

**SQL status: Not imported.**

## Out of scope

- New contact identity stores
- Anonymous visitor deanonymization
- Changing the legacy CRM campaign foreign key
- Automatic outbound marketing enrollment
- Compensation, payout, or dispute decisions
- MCP or external agent execution
