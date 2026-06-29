# Safe Target Zone campaign selector fix

Purpose:
- Remove the extra Target Zone reward fields at the actual sidebar renderer.
- Make the campaign selector the only merchant-facing reward/media pack assignment.
- Keep campaign inventory loading isolated from the main Target Drops page load.

Behavior:
- The Target Zone sidebar no longer renders these visible controls:
  - Payload type
  - Quantity limit
  - Claim limit / user
  - Manual Campaign / reward title
- The form still sends hidden values derived from the selected campaign so existing Target Drop APIs keep working.
- Campaign options load through `api/world-canvas/target-drop-campaign-options.php` instead of relying only on the main Target Drops list response.
- If the standalone campaign-options lookup fails, it returns an empty campaign list and does not break the World Canvas page.
- Test Launch can now read the saved `campaign_public_id` and use the campaign reward inventory lookup.

SQL:
- No SQL required.
