# Campaign Ads Creative Media Upload

This stage adds a safe image upload path for Campaign Ads creative media.

## Merchant page

```txt
/merchant-ad-manager.php
```

The ad builder now includes:

- Upload campaign image file input
- Upload Image button
- Upload status text
- Existing Image URL fallback field

After upload succeeds, the returned public image URL is placed into the existing `image_url` field and the sponsored preview refreshes immediately.

## API

```txt
/api/ads/upload-creative.php
```

The API accepts multipart POST uploads using the field:

```txt
creative_image
```

CSRF is passed through either:

```txt
csrf_token
X-CSRF-TOKEN
```

## Storage

Creative images are stored under:

```txt
/uploads/ad-creatives/{merchant_id}/...
```

The API returns:

```txt
url
filename
original_name
mime_type
size_bytes
width
height
```

## Safety limits

Allowed extensions:

```txt
jpg
jpeg
png
gif
webp
```

Allowed MIME types:

```txt
image/jpeg
image/png
image/gif
image/webp
```

Limits:

```txt
max file size: 8MB
minimum dimensions: 100x100
maximum dimensions: 6000x6000
```

The upload checks file type, MIME type when available, and image dimensions with `getimagesize` when available.

## SQL required

No SQL required.

This uses the existing `ad_creatives.image_url` field through the current campaign save/update APIs.

## Test path

1. Open `/merchant-ad-manager.php` as a merchant.
2. Choose an image in Upload campaign image.
3. Click Upload Image.
4. Confirm the upload status changes to success.
5. Confirm Image URL is filled with `/uploads/ad-creatives/{merchant_id}/...`.
6. Confirm sponsored preview updates immediately.
7. Save Campaign.
8. Load the campaign from Merchant Campaigns.
9. Confirm the uploaded image URL persists.

## No business-rule changes

This stage does not change:

- billing
- payouts
- wallet state
- claim state
- redemption state
- campaign status rules
- placement settings
