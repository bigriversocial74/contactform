# Stage 12H Profile Investment Demand Analytics

Stage 12H adds an investment-style merchant profile surface for Microgifter public profiles.

## Metrics

The profile investment endpoint exposes safe display metrics:

- demand value
- floor price
- 30 day volume
- redemption rate
- demand score
- active drops

## Ticker

Ticker items are clickable and link to the profile URL. The frontend uses `item.url` when present and falls back to the current profile slug.

## Safety

- The endpoint is GET only.
- Responses are private and no-store.
- Schema checks are cached and whitelist known Stage 12 tables.
- The frontend avoids dynamic innerHTML.
- Cover adjustment is owner-only and requires CSRF.

## Merge validation

Run:

```bash
php scripts/validate_stage12h_profile_investment_static.php
php scripts/validate_stage12h_ticker_links_static.php
php scripts/validate_stage12h_cover_position_static.php
php scripts/validate_index_image_links.php
```
