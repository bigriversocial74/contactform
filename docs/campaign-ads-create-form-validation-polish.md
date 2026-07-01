# Campaign Ads Create Form Validation Polish

## Scope

This phase adds merchant-facing validation before Campaign Ads save and submit actions.

No SQL is required.

## Files changed

- `/assets/js/merchant-ad-picker-groups.js`
- `/docs/campaign-ads-create-form-validation-polish.md`

## Validation covered

- Headline is required.
- Description is required.
- CTA is required.
- Destination URL is required when a CTA exists.
- Dangerous `javascript:` and `data:` destination values are blocked client-side.
- At least one placement must be selected.
- End date cannot be before start date.

## Boundaries

- Existing API behavior is preserved.
- No changes were made to `/api/ads/create.php`, `/api/ads/update.php`, or `/api/ads/submit.php`.
- Existing product picker grouping and apply behavior remain intact.
- No billing, auction, wallet, claim, redemption, payout, or placement write behavior was added.

## Manual QA

1. Open `/merchant-ad-manager.php` as a merchant.
2. Clear the sponsored card headline and click Save Campaign.
3. Confirm the save is stopped and a clear status message appears.
4. Restore the headline, clear the description, and click Save Campaign.
5. Confirm the save is stopped and a clear status message appears.
6. Clear CTA and confirm save/submit is stopped.
7. Add a CTA and clear the destination URL; confirm save/submit is stopped.
8. Set end date earlier than start date; confirm save/submit is stopped.
9. Uncheck all placements; confirm save/submit is stopped.
10. Restore valid values and confirm Save Campaign proceeds normally.
