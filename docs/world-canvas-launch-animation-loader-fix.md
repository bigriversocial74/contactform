# World Canvas launch animation loader fix

PR purpose:
- Fix Test Launch cases where the backend run is created but no visible launch animation appears on the map.

Root cause addressed:
- Dynamically inserted scripts can execute out of order unless explicitly made non-async.
- `world-canvas-run-hooks.js` could mark a delivery run as seen before the `MicrogifterTargetDropTestLaunch` renderer was ready, which meant polling would not retry the animation.

Fix:
- Dynamic World Canvas scripts now load with `async=false` to preserve dependency order.
- Delivery run hooks now keep pending runs and retry launch rendering until the animation renderer is ready.
- The launch renderer dispatches `mg:world-test-launch-ready` when it is available so pending runs can flush immediately.

SQL:
- No SQL required.
