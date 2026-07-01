# Merchant Agent Creative Mode + Memory Usage Visibility

## Purpose
This change keeps the Merchant Agent Chat integrated with the admin AI model catalog while steering the chat agent toward marketing creative work instead of deep database analysis by default.

## Model policy
- Merchant Agent Chat now selects the admin-enabled Anthropic default from Sonnet/Haiku-class models.
- Opus and Fable model keys are intentionally excluded from this chat route.
- If the admin default is Opus/Fable, the chat route falls back to the next enabled Sonnet/Haiku model by sort order.
- No model rows or admin settings are overwritten.

## Prompt/context policy
- Creative marketing, quick copy, social campaigns, offers, loyalty, rewards, and local promotions use lightweight context.
- Heavy sections such as claims, wallet items, campaign event metrics, contacts, and payment readiness are omitted unless the request asks for analytics, performance, ROI, claims, redemptions, reports, diagnostics, or similar analysis.

## Merchant Memory page
Adds tabs for:
- Memory Sources
- AI Usage
- Models
- Guide

The AI Usage tab itemizes model, status, token usage, scope, mode, output type, context profile, deep database flag, thread, skills, query preview when available, and errors.

## SQL
No SQL required.
