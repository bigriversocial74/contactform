# Stage API-10 — Developer onboarding and setup UX

Stage API-10 adds a guided setup experience to the merchant Developer API workspace.

## Goal

Make the developer workflow easier to understand before live API usage.

The setup flow guides merchants through creating a Distribution Program, creating a developer app, attaching a default program, creating a server-side credential, running sandbox tests, configuring webhook delivery, and sharing public docs.

## Merchant API payload

The merchant Developer API payload now includes an `onboarding` object with setup progress, readiness booleans, and ordered setup steps.

Each step includes a key, label, completion state, guidance text, and an action link.

## UI changes

The Developer API view now includes a setup checklist panel, a readiness badge, recommended flow links, anchors for app editing and credential creation, and direct links to the sandbox guide, webhook docs, and public docs.

## Renderer

`assets/js/merchant-developer-api-analytics.js` now renders the setup checklist from the same merchant API payload used for analytics.

## Readiness rules

Test readiness requires a developer app and an active API credential.

Live readiness requires a Distribution Program, an active developer app, an active credential, a default program, and a webhook URL.

This stage does not add database tables or runtime public API behavior. It improves the merchant-facing setup experience for the public distribution API.
