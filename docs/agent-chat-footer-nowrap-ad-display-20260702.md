# Agent Chat Footer No-Wrap and Sidebar Ad Display — 2026-07-02

## Summary

This follow-up fixes the desktop Merchant Agent Chat footer/composer wrapping issue and hardens the right sidebar sponsored ad slot so active ads unhide the slot reliably.

## Desktop composer fix

`assets/css/merchant-agent-chat-desktop.css` now uses a desktop-only no-wrap flex row for the footer composer instead of relying on the grid column rules that could still be overridden by the voice composer CSS.

Desktop behavior:

- Composer controls stay on one row.
- The text/search field shrinks with `min-width: 0` instead of forcing an icon to a new line.
- Footer controls are reduced to 44px square on desktop.
- Existing footer padding is preserved.
- Mobile composer behavior remains owned by the existing mobile CSS.

## Sidebar ad display check

`assets/js/merchant-agent-chat-sidebar-ad-state.js` now treats an actual rendered sponsored card as the source of truth.

Behavior:

- Empty/unloaded sidebar ad slots remain hidden.
- While the placement is loading, the wrapper does not force a false visible empty space.
- If an active sponsored card renders, the outer sidebar ad wrapper removes `is-empty` and gets `data-has-active-ad`.

## SQL

None required.
