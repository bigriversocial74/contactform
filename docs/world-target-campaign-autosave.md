# World Canvas Target Zone campaign autosave

Purpose:
- Keep Target Zone configuration tied to the selected campaign and its attached reward/media pack.

Behavior:
- The Target Zone sidebar now treats campaign selection as the source of truth for reward, payload type, quantity, and per-user limit.
- Duplicate reward controls are hidden from the Target Zone UI.
- Merchants attach a campaign, and the campaign controls which reward/media pack is sent.
- Target Zone sidebar changes auto-save after edits, including campaign selection.
- Test Launch inventory checks read from the reward template attached to the selected campaign first, then fall back to campaign quantity when no reward template quantity is set.

SQL:
- No SQL required.
