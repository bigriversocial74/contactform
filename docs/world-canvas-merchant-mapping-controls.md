# World Canvas Merchant Mapping Controls

This pass improves the Merchant World Settings slide-out on `world-canvas.php`.

## Controls

- `Mapped` / `Missing coordinates` status per merchant location
- `Use current location` fills latitude/longitude from the browser geolocation prompt
- `Find on World Canvas` highlights the matching merchant dot when it exists
- `Search address` opens the existing merchant address in Google Maps for manual verification
- `Edit location` returns to `merchant-locations.php`

## Data model

Merchant location records remain in the existing `merchant_locations` table. World Canvas uses the Stage 27 geo columns added to that table.

Dynamic user/avatar location remains in `user_world_positions`.
