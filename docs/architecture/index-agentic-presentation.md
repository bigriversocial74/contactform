# Logged-Out Index Agentic Presentation

The logged-out index is the first Microgifter agentic onboarding surface.

## Default behavior

- The presentation starts in Play mode after the page loads.
- Existing hero, sticky-scroll, revenue, and onboarding sections are presented automatically.
- Manual wheel or touch scrolling pauses the automated presentation.
- A Play/Pause control is mounted immediately beside the Microgifter logo.
- The control always reflects the current automation state.

## Input-gated onboarding

Automation pauses when a stage requires a visitor response.

The current guided flow includes:

1. Pre-sales interest
2. Business name
3. Business website
4. Public website scan
5. Generated pre-sale product recommendations
6. Custom product direction
7. Signup handoff

Each interactive stage retains a Skip path. Progress is stored locally until the visitor creates an account.

## Play/Pause contract

The public index bootstrap owns the header control and dispatches:

- `mg:index-presentation-toggle`

The onboarding controller publishes:

- `mg:index-presentation-state`

This separation keeps the public header control independent from onboarding internals while allowing both components to stay synchronized.

## Accessibility

- The button exposes an updated `aria-label` for Play and Pause.
- The control uses `aria-pressed` to indicate a paused state.
- Reduced-motion preferences disable automatic motion.
- Mobile layouts preserve an icon-only control with an accessible label.
