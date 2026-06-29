# Safe Target Zone campaign selector fix

Purpose:
- Remove the extra Target Zone reward fields at the actual sidebar renderer.
- Make the campaign selector the only reward/media pack assignment field in the Target Zone form.
- Keep campaign inventory loading isolated from the main Target Drops page load.

Behavior:
- The Target Zone sidebar no longer renders these controls at all:
  - Payload type
  - Quantity limit
  - Claim limit / user
  - Manual Campaign / reward title
- These fields are not re-added as hidden inputs.
- The Target Zone form sends the selected `campaign_public_id` only.
- The backend derives campaign title, payload type, quantity, and per-user limit from the assigned campaign.
- Campaign options load through `api/world-canvas/target-drop-campaign-options.php` instead of relying only on the main Target Drops list response.
- If the standalone campaign-options lookup fails, it returns an empty campaign list and does not break the World Canvas page.
- Test Launch can read the saved `campaign_public_id` and use the campaign reward inventory lookup.

SQL:
- No SQL required.
