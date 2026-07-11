# World Canvas Runtime v2 QA

## Runtime architecture

- MapLibre owns map projection, zoom, pan, camera movement, pointer coordinates, and draggable Campaign Drop markers.
- Three.js is a synchronized visual-effects overlay only; it does not own geographic state.
- Merchant business avatars use `merchant_locations` coordinates.
- Personal user avatars use `user_world_positions` coordinates.
- A merchant account may select either its personal user persona or one business persona for each registered merchant location.
- Runtime nodes are deduplicated by stable `entity_key` values.
- Random percentage-based geographic fallback is disabled.

## Merchant presence policy

Each registered merchant location supports:

- `allow_unattended`: customers can enter while the merchant business avatar is active in World Canvas. The customer receives an automatic away message.
- `temporarily_closed`: entry is blocked. The customer receives a closure message and is queued for a return message.

Opening Store Canvas marks the selected merchant location present and sends return messages once per presence cycle. Selecting a merchant persona in World Canvas marks that location away. Ordinary page refreshes do not create false away/return transitions.

## Deployment requirement

Run:

`database/stage_33_world_canvas_persona_presence.sql`

The migration is safe to re-run and adds location presence fields, persona state, return watchers, and the optional store-session location link.

## Browser QA

1. Confirm wheel zoom, touch zoom, pan, and navigation controls remain stable.
2. Drag an owned Campaign Drop at world, city, and neighborhood zoom levels; confirm saved latitude/longitude match the dropped point.
3. Confirm one marker per user identity and one marker per registered merchant location.
4. Switch between personal user persona and each merchant-location persona.
5. Confirm `allow_unattended` entry succeeds and delivers the away message.
6. Confirm `temporarily_closed` entry fails and delivers the closure message.
7. Open Store Canvas and confirm queued users receive one return message.
8. Confirm merchant location coordinates can only be edited through the registered merchant-location workflow.

## External runtime assets

The page currently pins MapLibre GL JS 5.7.1 and Three.js 0.160.0 from unpkg. Production CSP and outbound network policy must allow those assets and the configured MapLibre style/tile host, or the assets must be vendored locally before deployment.
