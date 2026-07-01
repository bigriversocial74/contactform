# Campaign Ads Create Form Validation Polish

No SQL required.

This phase adds merchant-facing validation before Campaign Ads save and submit actions.

Validation covered:
- Headline required.
- Description required.
- CTA required.
- Destination URL required when CTA exists.
- Blocks javascript/data destination values client-side.
- At least one placement required.
- End date cannot be before start date.

Boundaries:
- Existing API behavior preserved.
- No changes to create/update/submit endpoints.
- Existing picker grouping and apply behavior remain intact.
