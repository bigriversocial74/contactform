# Campaign Ads Creative Builder QA and Polish

## Scope

This pass keeps the Campaign Ads Manager work scoped to the merchant creative builder after the product picker and creative upload additions.

No SQL is required.

## Covered areas

- Upload campaign image field layout and helper copy.
- Upload API client handling and response validation.
- Immediate sponsored preview refresh after upload.
- Uploaded creative image override behavior after applying a product.
- Save/load targeting metadata format for product picker state going forward.
- Campaign table creative thumbnails for faster QA.
- Mobile layout polish for product picker, creative upload, campaign rows, and health alerts.
- Health alert spacing cleanup on empty and mobile states.

## Important behavior

- Product picker still reads from the existing `reward_templates` source through `/api/ads/merchant-products.php`.
- Creative uploads still use `/api/ads/upload-creative.php` and save the returned public image URL into the existing Image URL field.
- Uploaded images are preserved when a merchant applies a product after upload.
- The Image URL field remains editable as a manual fallback.
- No billing, auction, CPC/CPM, payout, wallet, claim, redemption, or external advertiser behavior was added.

## Manual QA checklist

1. Open `/merchant-ad-manager.php` as a merchant.
2. Confirm the health alert area does not leave a blank gap when no alerts render.
3. Apply an active product/reward and confirm title, headline, description, CTA, destination, objective, product summary, and preview update.
4. Upload a JPG/PNG/GIF/WebP image below 8MB and confirm the upload status shows success, image URL is filled, and preview updates immediately.
5. Apply a product after upload and confirm the uploaded image is preserved.
6. Save the campaign, load it from Merchant Campaigns, and confirm the uploaded image URL and product picker metadata are retained for newly saved campaigns.
7. Check mobile width for product picker, upload control, campaign table rows, tab layout, and health alert banner spacing.
8. Confirm these pages still load: `/admin/ad-review.php`, `/admin/ad-placements.php`, `/admin/ad-diagnostics.php`, `/merchant-ad-performance.php`, and `/admin/ad-performance.php`.
