# Campaign Draft Preview and Agent Sidebar Gap — 2026-07-02

## Summary

This change adds a merchant-safe landing page preview path for draft campaigns created by the recipe execution adapter and fixes the blank desktop spacer above the Merchant Agent Chat right control panel.

## Campaign draft preview

New route:

- `/merchant-campaign-preview.php?campaign={campaign_public_id}&type={campaign_type}`

The route uses the existing public campaign page renderer but runs it in merchant preview mode.

Preview mode behavior:

- Requires a signed-in user with `merchant.campaigns.view`.
- Loads only campaigns owned by the signed-in merchant user.
- Allows draft, paused, scheduled, and active campaigns to render for the owner.
- Does not expose inactive drafts through the public campaign pages.
- Shows a merchant preview banner with the campaign status.
- Disables customer submission while the campaign is in preview mode.

Public campaign pages still require `c.status = 'active'` unless they are explicitly rendered through merchant preview mode.

## Execution result UI link behavior

`assets/js/merchant-agent-execution.js` now routes campaign resource links based on campaign status:

- `active` campaigns use the public landing page route.
- non-active campaigns use `/merchant-campaign-preview.php` and label the link `Preview landing page`.

This keeps the execution result UI clear for draft campaign artifacts created after recipe approval.

## Agent Chat right sidebar gap

The gap above the Agent Control Panel was caused by the sponsored campaign wrapper staying visible when the inner ad placement was empty.

Fixes:

- `includes/merchant-agent-chat-view.php` marks the sidebar ad wrapper as `is-empty` by default.
- `assets/js/merchant-agent-chat-sidebar-ad-state.js` synchronizes empty/non-empty sponsored placement state with the outer `.mg-agent-chat-sidebar-ad` wrapper.
- Existing desktop CSS already hides `.mg-agent-chat-sidebar-ad.is-empty`, so the control panel moves up when no sidebar ad is available.

## SQL

None required.

## Safety

- No campaign activation.
- No publishing.
- No customer delivery.
- No public exposure of inactive drafts.
- Preview is owner-gated by merchant session and campaign ownership.
