# Stage 12H Ticker Link Behavior

Ticker items in the investment-style profile header are clickable.

## Link resolution

Each ticker item uses `item.url` when the API provides it.

If no `item.url` exists, the frontend falls back to `/profile.php?slug=<current-profile-slug>`.

## Safety

Ticker links are passed through `safeHref()` before assignment.

## Accessibility

Each ticker link receives an aria label built from the ticker symbol, value, and change text.
