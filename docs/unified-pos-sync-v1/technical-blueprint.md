# Unified POS Customer & Purchase Sync v1 — Technical Blueprint

## 1. Objective

Build a provider-neutral integration pipeline that connects merchant POS systems to Microgifter, beginning with Square and designed to add Shopify POS, Clover, Toast, and Lightspeed without changing the core CRM or reward architecture.

When a merchant connects a provider, Microgifter will:

1. Authorize the provider connection.
2. Import the merchant's customer directory.
3. Match imported customers to canonical Merchant CRM contacts.
4. Receive purchase, refund, customer, and relevant order changes through webhooks.
5. Normalize provider payloads into one internal schema.
6. Store immutable canonical transaction and effect records.
7. Project purchase activity into Merchant CRM history and LTV.
8. Publish canonical POS events that campaigns and reward rules may consume.
9. Provide health, retry, reconciliation, and audit tools.

## 2. Existing Microgifter architecture to reuse

The current repository already contains:

- Generic provider webhook receipt, payload hashing, duplicate detection, quarantine, and processing states in `api/integrations/_webhook_intake.php`.
- Merchant CRM contacts and event history in `includes/merchant-crm.php`.
- Canonical identity aliases, merge resolution, email normalization, and phone normalization in `includes/merchant-crm-identity.php`.
- Merchant CRM purchase-oriented fields such as `last_purchased_at` and `total_purchase_cents` in the Stage 12 CRM schema.
- Existing webhook, worker, reconciliation, security logging, and audit patterns elsewhere in the repository.

The feature must extend these patterns rather than create a second CRM or unrelated webhook framework.

## 3. Required architectural boundary

```text
Provider API / webhook
        ↓
Provider adapter
        ↓
Normalized POS customer/transaction
        ↓
Canonical POS ledger
        ↓
CRM projector / event publisher
        ↓
Merchant CRM, analytics, campaigns, rewards
```

Provider adapters may:

- Verify provider-specific signatures.
- Parse provider event envelopes.
- Retrieve missing provider objects.
- Normalize customers, transactions, refunds, locations, and line items.
- Report provider-specific retryability.

Provider adapters must not:

- Directly increment CRM totals.
- Directly merge CRM contacts.
- Directly issue or revoke rewards.
- Directly run campaign logic.
- Add provider-specific columns to core CRM tables.

## 4. Provider adapter contract

Recommended interface:

```php
interface MgPosProviderAdapter
{
    public function providerKey(): string;

    public function verifyWebhook(
        array $headers,
        string $rawBody,
        array $connection
    ): bool;

    public function parseWebhookEnvelope(
        array $headers,
        string $rawBody
    ): array;

    public function normalizeCustomer(
        array $providerCustomer,
        array $connection
    ): array;

    public function normalizeTransaction(
        array $providerPayload,
        array $connection
    ): array;

    public function retrieveTransactionDetails(
        array $connection,
        array $normalizedEnvelope
    ): array;

    public function classifyFailure(Throwable $error): string;
}
```

Registry:

```php
return [
    'square' => MgSquarePosAdapter::class,
    'shopify' => MgShopifyPosAdapter::class,
    'clover' => MgCloverPosAdapter::class,
    'toast' => MgToastPosAdapter::class,
    'lightspeed' => MgLightspeedPosAdapter::class,
];
```

## 5. Asynchronous webhook processing

The HTTP webhook endpoint must do only the work required to trust and durably record the delivery:

1. Resolve the connection from provider merchant/account identity.
2. Read the unmodified raw body.
3. Verify the provider-specific signature.
4. Parse the minimal event envelope.
5. Deduplicate by provider delivery/event ID.
6. Store the event receipt and redacted payload.
7. Enqueue a database job.
8. Return `2xx` promptly.

Provider API calls, order retrieval, customer matching, transaction normalization, CRM updates, and reward-event publication occur in the worker.

Production confirms a one-minute cron is available. The worker should also support an authorized manual recovery command.

## 6. Three idempotency layers

### 6.1 Delivery idempotency

Prevents the same provider webhook delivery from being inserted or processed twice.

Key:

```text
provider_key + provider_event_id
```

A repeated event ID with a different payload hash is quarantined as a conflicting replay.

### 6.2 Transaction revision idempotency

A provider may send several versions of the same payment/order. Store the normalized hash and provider version/update timestamp.

A stale or identical revision should not rewrite line items or generate new effects.

### 6.3 Business-effect idempotency

A purchase completion, refund, cancellation, or identity-enrichment effect is applied exactly once.

Examples:

```text
completion
refund:<external_refund_id>
cancellation
identity_enrichment:<provider_version>
```

A completed payment may receive later `payment.updated` events because fees, customer identity, or another field changed. The completion total must not be applied twice, but identity enrichment may still apply.

## 7. Connection and tenancy model

- One Microgifter merchant may connect several POS providers.
- One merchant may connect several accounts for one provider when the provider permits it.
- One connection may expose several locations.
- Every canonical customer, transaction, effect, and job is scoped to a connection and merchant.
- Cross-merchant lookups are prohibited even when external IDs collide.

Connection states:

```text
pending
active
action_required
error
revoked
```

## 8. Credential security

- Access tokens, refresh tokens, webhook secrets/signature keys, and installation secrets are encrypted at rest.
- The ciphertext, key version, and token expiry may be stored; plaintext is never logged.
- OAuth `state` must be single-use, merchant-scoped, short-lived, and bound to the initiating session.
- Disconnect immediately revokes or deletes local credentials and marks the connection revoked.
- Historical normalized customers and transactions remain available after disconnect.
- Raw card data, card tokens, device tokens, PAN, CVV, track data, and payment credentials are never stored.

## 9. Initial customer import

Locked v1 behavior:

- Import customer directory only.
- Do not import historical transactions automatically.
- Use cursor-based pagination.
- Track progress in `pos_sync_runs`.
- Upsert external customer mappings.
- Resolve or create Merchant CRM contacts.
- Preserve provider version and provider update timestamp.
- Import selected custom attributes only when enabled by the merchant.
- Preserve provider unsubscribe/consent data without converting it into Microgifter marketing consent.

A later manual historical-backfill tool may use the same normalization and idempotency pipeline.

## 10. Customer identity resolution

Resolution order:

1. Existing `connection_id + external_pos_customer_id` mapping.
2. Existing mapped Microgifter user ID.
3. Exact normalized email against CRM identity aliases/contact.
4. Exact normalized phone against CRM identity aliases/contact.
5. Create a new merchant-owned CRM contact.
6. Send conflicting matches to manual review.

Rules:

- Never auto-match by name alone.
- Email and phone matches that resolve to different contacts are ambiguous.
- Imported POS customers do not become Microgifter login accounts.
- External customer mappings survive CRM contact merges by resolving to the canonical merged contact.
- Provider customer merges/deletions are preserved as mapping history, not hard-deleted transaction identity.

## 11. Anonymous purchases

A purchase without a reliable customer identity is stored as an anonymous canonical transaction.

Anonymous purchases:

- Count in merchant aggregate sales analytics.
- Do not create a shared fake CRM customer.
- Do not contribute to customer-specific LTV.
- May later attach to a customer through a newer provider event.
- Must not reapply completion totals when identity is enriched.

## 12. Canonical transaction lifecycle

Statuses:

```text
pending
authorized
completed
partially_refunded
refunded
cancelled
failed
```

Provider status mappings belong in adapters. Core services operate only on canonical status values.

The transaction ledger stores:

- Provider transaction/payment ID.
- Provider order ID.
- Provider customer ID.
- Connection and location.
- Monetary components.
- Identity state.
- Line items.
- Provider and normalized versions.
- Completion and refund effects.
- CRM projection state.

## 13. LTV and monetary definitions

Store these independently:

```text
subtotal_cents
discount_cents
tax_cents
tip_cents
service_charge_cents
gross_total_cents
refunded_cents
net_total_cents
ltv_eligible_cents
```

Locked v1 LTV:

```text
merchandise subtotal
− discounts
− completed allocated refunds
excluding tax, tip, and service charges by default
```

Additional definitions:

- Gross spend: total actually paid, including tax, tip, and service charges.
- Net paid: gross spend minus completed refunds.
- Merchandise LTV: customer-value and reward-threshold basis.
- Gift-card sales: prepaid liability and excluded from merchandise LTV.
- Gift-card redemption: retained as product/visit activity, not counted as a second paid purchase.

## 14. Refund handling

Refund support is required in Square v1.

- Partial and full refunds create separate immutable effects.
- Original transactions are never deleted.
- Each provider refund ID applies once.
- Completed refunds reduce net paid and merchandise LTV.
- Pending or failed refunds do not alter CRM totals.
- Reward adapters do not revoke rewards directly; publish a canonical refund event for the reward engine to evaluate.

## 15. Line-item storage

Store line items whenever the provider supplies or can retrieve them.

Persist:

- External line-item ID.
- External catalog object ID.
- SKU.
- Product/variant name.
- Quantity as decimal.
- Unit price.
- Gross, discount, tax, and net values.
- Category where available.
- Provider-specific non-sensitive metadata.

The initial feature does not require mapping POS catalog items to Microgifter catalog products. Preserve IDs/SKUs now so a later mapping feature can be added without reimporting transactions.

## 16. CRM projection

The canonical POS ledger is authoritative. CRM values are projections.

For identified completed purchases, the projector should:

1. Resolve the canonical CRM contact.
2. Insert one immutable `pos_purchase_completed` CRM event.
3. Update POS-specific purchase rollups.
4. Update the contact's general purchase stage/last-purchase fields through the canonical CRM service.
5. Mark the transaction's CRM projection as complete.

Refunds should insert `pos_purchase_refunded` events and recompute or apply deterministic rollup deltas.

Do not rely solely on incrementing totals. Reconciliation must be able to recompute CRM POS rollups from canonical transactions/effects.

## 17. Canonical event bridge

Publish provider-neutral events after the ledger and CRM projection commit:

```text
pos.connection.activated
pos.customer.synced
pos.customer.match_ambiguous
pos.purchase.completed
pos.purchase.identity_enriched
pos.purchase.refunded
pos.purchase.item_matched
pos.connection.action_required
```

Campaign and reward systems subscribe to these events. Provider adapters never directly issue rewards.

## 18. Square v1 scope

### OAuth scopes

Read-only v1 should request only the permissions needed for:

- Customers.
- Payments.
- Orders.
- Merchant/location profile data.

Do not request customer write permission unless a later write-back feature is explicitly approved.

### Customer sync

- Initial paginated customer search/list.
- `customer.created`.
- `customer.updated`.
- `customer.deleted`.
- Visible customer custom-attribute events when enabled.

Custom attribute updates do not necessarily trigger the normal customer-updated event, so they require their own subscriptions.

### Purchases and refunds

- `payment.updated`.
- Process purchase completion only when payment status is `COMPLETED`.
- Retrieve the related order when itemized details are needed.
- Process `refund.updated` only when the canonical refund state becomes completed.
- Accept out-of-order and duplicate deliveries.

### Signature verification

Square webhook signatures use the configured notification URL plus the unmodified raw request body and the subscription signature key. The existing generic timestamp/body HMAC helper cannot be reused unchanged.

### Acknowledgement

Square expects quick `2xx` acknowledgement and may retry deliveries. The endpoint should enqueue rather than perform provider API retrieval synchronously.

## 19. Merchant interface

Recommended route:

```text
/merchant-integrations.php
```

Integration cards show:

- Provider.
- Connection status.
- External merchant/account.
- Environment.
- Enabled locations.
- Granted scopes.
- Initial customer import progress.
- Last webhook received.
- Last event processed.
- Queue depth and failures.
- Reauthorization status.
- Reconcile and disconnect actions.

Square setup flow:

1. Connect Square.
2. Authorize requested scopes.
3. Select locations.
4. Enable/disable initial customer import.
5. Configure custom-attribute allowlist.
6. Confirm LTV defaults.
7. Run initial import.
8. Activate webhooks and live processing.

Merchant CRM customer profiles should show:

- POS provider badges.
- External customer mappings.
- Purchase count.
- Last in-store purchase.
- Gross paid.
- Net paid.
- Merchandise LTV.
- Refund totals.
- Itemized purchase timeline.
- Match/identity status.

## 20. Worker and retry model

Recommended worker command:

```text
php scripts/run_pos_sync_worker.php --limit=100
```

Cron cadence: every minute.

Job states:

```text
queued
processing
retryable
processed
dead_letter
quarantined
```

Retry behavior:

- Exponential backoff.
- Maximum attempts configurable.
- Provider rate-limit responses use provider retry headers where available.
- Authentication failures mark the connection `action_required`.
- Invalid signatures and conflicting replays are quarantined, not retried.
- Permanent validation errors go to dead letter with a stable reason.

## 21. Reconciliation

Required command:

```text
php scripts/reconcile_pos_sync.php --provider=square --connection=<uuid> --dry-run
```

Supported filters:

```text
--provider
--connection
--transaction
--customer
--from
--to
--limit
--repair
```

Detect:

- Webhook receipt without queue job.
- Queue job without canonical transaction/customer.
- Transaction without completion/refund effect.
- Duplicate completion effects.
- Missing line items when order retrieval succeeded.
- External customer mapping to a merged/noncanonical contact.
- CRM event/rollup drift.
- Anonymous transaction that now has resolvable identity.
- Connection/location tenancy mismatch.
- Stale jobs or lock leaks.

Repair only deterministic defects and write audit receipts.

## 22. Retention

- Redacted raw provider webhook payloads: 90 days.
- Payload hashes, provider event IDs, processing receipts, normalized transactions, effects, and audit records: retained according to merchant commerce/privacy policy.
- Credentials: removed immediately after disconnect/revocation.
- Privacy erasure: anonymize identity while retaining legally required transaction totals and provider attribution.

## 23. Feature rollout

Feature states:

```text
disabled
admin_only
selected_merchants
enabled
```

Initial production state: `selected_merchants`.

All entry points—OAuth, dashboard, webhook connection lookup, worker processing, CRM panels, and event bridge—must honor feature/entitlement state consistently.

## 24. Non-goals for Square v1

- POS → Square customer write-back.
- Automatic Microgifter account creation.
- Historical order import by default.
- POS catalog-to-Microgifter product mapping UI.
- Provider adapters issuing rewards.
- Marketing outreach based solely on imported POS contact data.
- Storing sensitive payment credentials.
- Real-time polling for purchases.

## 25. Authoritative provider references verified during planning

Square official documentation:

- Payments webhooks: `https://developer.squareup.com/docs/payments-api/webhooks`
- Webhook overview/retry behavior: `https://developer.squareup.com/docs/webhooks/overview`
- Signature validation: `https://developer.squareup.com/docs/webhooks/step3validate`
- Customer webhooks: `https://developer.squareup.com/reference/square/customers-api/webhooks`
- Customer custom attributes: `https://developer.squareup.com/docs/customer-custom-attributes-api/overview`
- Webhook event reference: `https://developer.squareup.com/docs/webhooks/v2webhook-events-tech-ref`

Provider behavior must be verified again against the API version selected at implementation time.