# Action Center Contract v2

Action Center Contract v2 is the single read model for Inbox, Sent, Claimed, Personal Agent gift results, detail views, and product-media views.

## Design rules

1. `gift.snapshot` is historical. It describes what was issued or purchased and must not change when a merchant edits or unpublishes a catalog product.
2. `linked_resource` is live public navigation data. Its URL is present only while the linked product is public.
3. `presentation` is the authoritative display decision. Clients use `presentation.image_url` and do not inspect raw campaign, reward, or instance metadata.
4. `source` describes where the gift originated. Campaign information never competes with the catalog product for resource identity.
5. `capabilities` is the server-authoritative action map. Clients may display or disable actions, but mutation endpoints remain the final authority.
6. Raw metadata, internal database IDs, credentials, unpublished product URLs, and private merchant configuration are not part of the public contract.

## Core item

```json
{
  "contract_version": 2,
  "kind": "action_center_gift",
  "action_item_id": "public-action-id",
  "folder": "inbox",
  "gift": {
    "id": "public-instance-id",
    "template_id": "public-template-id",
    "template_type": "product",
    "status": "delivered",
    "state": "claimable",
    "snapshot": {
      "title": "16-inch pizza",
      "description": "Original issued description",
      "value_cents": 2500,
      "currency": "USD",
      "expires_at": null
    }
  },
  "presentation": {
    "title_source": "gift_snapshot",
    "image_url": "/api/public/media.php?asset=...",
    "image_source": "catalog_product_version_cover"
  },
  "linked_resource": {
    "type": "catalog_product",
    "public_id": "public-product-id",
    "version_id": "public-version-id",
    "product_type": "gift",
    "title": "16-inch pizza",
    "url": "/product.php?...",
    "is_public": true,
    "status": "published",
    "availability": "available",
    "version_basis": "exact_instance_version"
  },
  "source": {
    "system": "commerce",
    "type": "customer_purchase",
    "label": "Commerce",
    "detail": "",
    "reference": "order-reference"
  },
  "participants": {
    "sender": { "name": "Sender" },
    "recipient": { "name": "Recipient" }
  },
  "merchant": {
    "name": "Merchant",
    "avatar_url": null
  },
  "location": {
    "public_id": null,
    "name": null
  },
  "redemption": {
    "public_id": null,
    "status": null,
    "redeemed_at": null
  },
  "activity": {},
  "capabilities": {
    "open": true,
    "load": true,
    "send": true,
    "claim": true,
    "redeem": false,
    "follow_up": false,
    "message": false,
    "tip": false,
    "mark_read": true,
    "archive": true
  },
  "capability_reasons": {},
  "media": {
    "posts": [],
    "count": 1,
    "has_media": true
  },
  "flags": {
    "wallet_fallback": false,
    "demo_preview": false,
    "system_demo": false
  }
}
```

## Product version semantics

- `exact_instance_version` means the image and version were resolved through `microgift_instances.product_version_id`. This is the preferred historical relationship.
- `current_catalog_fallback` is used only for a standalone compatibility wallet item whose metadata references a product but does not preserve an issued version. The contract labels this explicitly rather than presenting it as historical truth.
- An unpublished product can retain its historical gift image, but `linked_resource.url` is omitted and `linked_resource.is_public` is false.

## Client boundary

`assets/js/action-center-contract-v2.js` is the only browser adapter. It converts Contract v2 into the existing card view model. Inbox, Sent, Claimed, and supporting scripts do not parse raw metadata or independently decide product-image precedence.

Personal Agent uses the matching PHP view adapter after the same server contract formatter, so Agent result cards and Action Center cards share resource identity, presentation, source, and capability logic.

## Mutation boundary

Contract capabilities are advisory UI state derived from the same lifecycle conditions used by the mutation APIs. Every mutation endpoint still revalidates authentication, ownership, folder, lifecycle status, recipient relationship, permissions, CSRF, and idempotency before changing data.
