# Merchant Loyalty Quest Management v1

This section provides the merchant-facing operating surface for Microgifter Loyalty Quest campaigns.

## Lifecycle

The persisted campaign status remains compatible with the existing campaign schema:

- `draft`
- `active`
- `paused`
- `ended`
- `archived`

A future `starts_at` value on an active campaign is presented as **Scheduled**. This avoids a schema enum migration while preserving the public runtime's existing date gates.

## Merchant controls

- Publish a draft or resume a paused quest after readiness validation.
- Pause an active or scheduled quest.
- Complete an active or paused quest.
- Archive a draft, paused, or completed quest.
- Duplicate any non-archived quest into a new draft with regenerated QR rule material.

## Readiness gates

Publishing and resuming require:

- an active merchant-owned reward template;
- participant instructions;
- a future end date when an end date exists;
- a merchant location for location-based quest actions;
- remaining package capacity for active campaigns.

## Reporting

The portfolio combines merchant-scoped campaign contacts, campaign events, wallet issues, claims, and redemptions. Quest detail reuses the established campaign-detail API to expose recent participant and event activity.
