# Public Web Root Transition

## Goal

Before production traffic, the browser document root should be `public/`, not the repository root.

This keeps internal folders outside direct browser reach:

- `api/` unless deliberately routed
- `includes/`
- `database/`
- `docs/`
- `tests/`
- `scripts/`
- `.env` and other local config

## Stage 1 status

The current app still has root-level PHP pages for fast staged development. The secure production path is to move approved browser entrypoints into `public/` or route them through a public front controller.

## Required production outcome

Only these categories should be browser reachable:

- approved public pages
- approved API entrypoints
- compiled/public assets
- uploaded media after validation and storage hardening

## Carry-forward rule

No future product, gift, order, inbox, or agent file should assume the repository root is publicly exposed.
