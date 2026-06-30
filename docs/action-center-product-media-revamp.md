# Action Center product media revamp

The Action Center feed should display the product, reward, audio-pack, or media-pack visual for each Inbox/Sent/Claimed item.

This replaces the merchant-profile-image direction. Merchant profile photos are not the primary thumbnail for gift rows.

Sources handled:

- Catalog product version assets through `catalog_product_version_assets` and `catalog_assets`.
- Wallet reward media packs through `reward_templates.metadata_json.media_pack`.
- Wallet item, campaign, and Microgift metadata fallbacks.

The row thumbnail now prefers the product/reward cover image. If there is no image but the item has audio/video/download content, the row displays a media-type marker instead of a random initial.

The loaded gift drawer prepends the product image when available before the protected voucher content.
