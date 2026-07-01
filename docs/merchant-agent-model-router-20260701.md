# Merchant Agent Sonnet/Haiku Model Router

## Purpose
Adds task-based routing for Merchant Agent Chat while keeping the existing admin AI model catalog as the source of truth.

## Routing rules
- Quick copy, SMS, captions, headlines, short rewrites, CTAs, and quick answers prefer Haiku when an enabled Haiku-class Anthropic model exists.
- Campaign ideas, social campaigns, email campaigns, offer strategy, reward copy, and local promotion concepts prefer Sonnet when an enabled Sonnet-class Anthropic model exists.
- Analytics, ROI, claims, redemptions, reports, diagnostics, trends, charts, forecasts, and other deep database analysis prefer Sonnet.

## Guardrails
- Opus and Fable remain excluded from Merchant Agent Chat.
- The router does not overwrite admin model rows or defaults.
- If the preferred task family is unavailable, the router falls back to the first enabled allowed model using the existing admin/default sort order.
- Explicit preferred model keys are still honored when they point to an enabled allowed Sonnet/Haiku model.

## Usage metadata
Each request now records model routing metadata such as route task, preferred family, selected family, selected-by strategy, and routing reason.

## SQL
No SQL required.
