# World Canvas Target Zone live drag

Purpose:
- Keep merchant-owned Target Zones movable while a test or live launch animation is running.

Behavior:
- A capture-phase pointer handler takes over dragging for owned Target Zones.
- The animation overlay remains non-interactive; it does not lock the Target Zone.
- Dragging the Target Zone center updates target latitude and longitude.
- Dragging the radius handle updates spread/radius meters.
- On pointer release, the new position is saved through the existing Target Drops API.

SQL:
- No SQL required.
