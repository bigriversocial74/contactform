# 02D Microgifter Public Profile Schema and Settings API

Status: implementation complete.

## Stage 2 alignment

This pass returns Stage 2 to the public identity/profile layer. It adds the public profile schema, private profile settings endpoints, public profile read endpoint, profile links, profile sections, completion scoring, and moderation foundation.

This does not build commerce, gifts, checkout, wallet, inbox, feed, tips, subscriptions, merchant locations, claim codes, or agent commerce.

## Files added

- database/02D_public_profiles_schema.sql
- includes/profiles.php
- api/profiles/me.php
- api/profiles/update.php
- api/profiles/links.php
- api/profiles/sections.php
- api/public/profile.php
- api/admin/profiles/moderate.php

## Tables added

- public_profiles
- public_profile_links
- public_profile_sections

## Behavior

- Every existing user receives a draft public profile during SQL import.
- Private profile endpoint returns the logged-in user's profile.
- Update endpoint changes profile identity fields and recalculates completion score.
- Links endpoint stores ordered public profile links.
- Sections endpoint stores ordered public profile sections.
- Public profile endpoint reads active public/unlisted profiles by slug.
- Admin moderation endpoint can change profile status.

## Upload instructions

Upload all files above, then import:

- database/02D_public_profiles_schema.sql

## Smoke test

- /api/health.php returns database connected.
- /api/profiles/me.php returns logged-in user's draft profile.
- POST /api/profiles/update.php can set display_name, slug, headline, bio, visibility, and status.
- /api/public/profile.php?slug=YOUR_SLUG returns a profile only when status is active and visibility is public or unlisted.
- /api/profiles/links.php can save profile links.
- /api/profiles/sections.php can save profile sections.
- Admin can moderate a profile status.

## Next recommended pass

02E_microgifter_profile_ui_and_identity_onboarding
