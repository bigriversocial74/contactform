# Unified POS Customer & Purchase Sync v1 — Database Schema Blueprint

## 1. Database conventions

- Database: MySQL/InnoDB.
- Character set: `utf8mb4`.
- Provider keys use `VARCHAR`; do not use provider ENUMs.
- Flexible provider metadata uses MySQL `JSON`.
- Every provider-owned object is scoped to both connection and merchant.
- Public API identifiers use UUID-style `CHAR(36)` values.
- Monetary values use integer cents and an explicit currency.
- Quantities use decimal values where provider line items may be fractional.
- Tokens and secrets are stored only as encrypted ciphertext plus key version metadata.
- Canonical transactions/effects are immutable business evidence; corrections are separate effects or controlled field revisions.

Recommended migration name:

```text
database/20260724_unified_pos_customer_purchase_sync_v1_single_install.sql
```

The migration should be idempotent and safe against live schema variation.

## 2. Existing tables to reuse

### `provider_webhook_events`

The repository already has a generic provider receipt ledger containing:

- Provider key.
- Provider event ID.
- Event type.
- Payload hash.
- Redacted payload JSON.
- Processing status.
- Duplicate/conflicting replay protection.

POS Sync may extend this table with nullable tenancy and retention fields, or create a POS-specific receipt table only when the existing table cannot safely support multi-connection routing.

Preferred additions when compatible:

```text
connection_public_id
merchant_user_id
provider_account_id
headers_json
retention_expires_at
received_ip
```

Do not break existing non-POS webhook consumers.

### `merchant_crm_contacts`

Continue using the existing canonical merchant-owned CRM contact.

### `merchant_crm_contact_events`

Store projected `pos_purchase_completed`, `pos_purchase_refunded`, and identity-related timeline events.

### CRM identity tables

Reuse canonical aliases/merge resolution. External POS mappings point to the canonical CRM contact and must be updated or resolved after contact merges.

## 3. `merchant_pos_connections`

One authorized provider account connection.

```sql
CREATE TABLE merchant_pos_connections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(80) NOT NULL,
  external_merchant_id VARCHAR(190) NOT NULL,
  external_account_label VARCHAR(190) NULL,
  environment ENUM('sandbox','production') NOT NULL DEFAULT 'production',
  status ENUM('pending','active','action_required','error','revoked') NOT NULL DEFAULT 'pending',
  access_token_ciphertext MEDIUMTEXT NULL,
  refresh_token_ciphertext MEDIUMTEXT NULL,
  webhook_secret_ciphertext MEDIUMTEXT NULL,
  credential_key_version VARCHAR(80) NULL,
  token_expires_at DATETIME NULL,
  granted_scopes_json JSON NULL,
  settings_json JSON NULL,
  initial_sync_status ENUM('not_started','queued','running','completed','partial','failed') NOT NULL DEFAULT 'not_started',
  initial_sync_cursor TEXT NULL,
  last_customer_sync_at DATETIME NULL,
  last_webhook_at DATETIME NULL,
  last_processed_at DATETIME NULL,
  last_reconciled_at DATETIME NULL,
  last_error_code VARCHAR(120) NULL,
  last_error_message VARCHAR(500) NULL,
  connected_by_user_id BIGINT UNSIGNED NULL,
  connected_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_pos_connections_public_id (public_id),
  UNIQUE KEY uq_merchant_pos_connection_provider_account (provider_key, external_merchant_id, environment),
  KEY idx_merchant_pos_connections_merchant (merchant_user_id, status, updated_at),
  KEY idx_merchant_pos_connections_provider_status (provider_key, status, updated_at),
  CONSTRAINT fk_merchant_pos_connections_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_pos_connections_actor FOREIGN KEY (connected_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

### Connection uniqueness note

If a provider permits the same external merchant/account to be connected to multiple Microgifter merchants, replace the global uniqueness constraint with:

```text
UNIQUE (merchant_user_id, provider_key, external_merchant_id, environment)
```

The implementation agent must verify the intended tenancy policy before final SQL.

## 4. `merchant_pos_locations`

```sql
CREATE TABLE merchant_pos_locations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  external_location_id VARCHAR(190) NOT NULL,
  display_name VARCHAR(190) NULL,
  timezone VARCHAR(80) NULL,
  currency CHAR(3) NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  metadata_json JSON NULL,
  provider_updated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_pos_locations_public_id (public_id),
  UNIQUE KEY uq_merchant_pos_location_external (connection_id, external_location_id),
  KEY idx_merchant_pos_locations_merchant (merchant_user_id, is_enabled, updated_at),
  CONSTRAINT fk_merchant_pos_locations_connection FOREIGN KEY (connection_id) REFERENCES merchant_pos_connections(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_pos_locations_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

The duplicated merchant ID is intentional for tenancy checks and indexed reporting. Service code must verify it matches the parent connection.

## 5. `pos_sync_customers`

External customer mapping and normalized directory snapshot.

```sql
CREATE TABLE pos_sync_customers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(80) NOT NULL,
  external_pos_customer_id VARCHAR(190) NOT NULL,
  crm_contact_id BIGINT UNSIGNED NULL,
  microgifter_user_id BIGINT UNSIGNED NULL,
  display_name VARCHAR(190) NULL,
  given_name VARCHAR(120) NULL,
  family_name VARCHAR(120) NULL,
  company_name VARCHAR(190) NULL,
  email VARCHAR(255) NULL,
  email_normalized VARCHAR(255) NULL,
  phone VARCHAR(80) NULL,
  phone_normalized VARCHAR(40) NULL,
  provider_version VARCHAR(120) NULL,
  match_status ENUM('unmatched','matched','ambiguous','ignored','deleted') NOT NULL DEFAULT 'unmatched',
  match_method ENUM('external_mapping','microgifter_user','email','phone','manual','none') NOT NULL DEFAULT 'none',
  email_consent_status ENUM('unknown','subscribed','unsubscribed','not_provided') NOT NULL DEFAULT 'unknown',
  sms_consent_status ENUM('unknown','subscribed','unsubscribed','not_provided') NOT NULL DEFAULT 'unknown',
  custom_attributes_json JSON NULL,
  metadata_json JSON NULL,
  provider_created_at DATETIME NULL,
  provider_updated_at DATETIME NULL,
  first_synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_sync_customers_public_id (public_id),
  UNIQUE KEY uq_pos_sync_customer_external (connection_id, external_pos_customer_id),
  KEY idx_pos_sync_customers_merchant_email (merchant_user_id, email_normalized),
  KEY idx_pos_sync_customers_merchant_phone (merchant_user_id, phone_normalized),
  KEY idx_pos_sync_customers_contact (crm_contact_id, updated_at),
  KEY idx_pos_sync_customers_match (merchant_user_id, match_status, updated_at),
  CONSTRAINT fk_pos_sync_customers_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_sync_customers_connection FOREIGN KEY (connection_id) REFERENCES merchant_pos_connections(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_sync_customers_contact FOREIGN KEY (crm_contact_id) REFERENCES merchant_crm_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_sync_customers_user FOREIGN KEY (microgifter_user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

## 6. `pos_customer_match_reviews`

Manual review queue for conflicting or uncertain identity matches.

```text
id
public_id
merchant_user_id
connection_id
pos_sync_customer_id
status: open | resolved | ignored
reason_code
candidate_contact_ids_json
signals_json
resolved_contact_id
resolution_method
resolved_by_user_id
resolved_at
created_at
updated_at
```

Constraints:

```text
UNIQUE (public_id)
UNIQUE (pos_sync_customer_id, status) where supported by service-level enforcement
INDEX (merchant_user_id, status, created_at)
```

MySQL partial unique indexes are not portable; enforce one open review through transaction locking and a normal composite strategy if needed.

## 7. `pos_sync_runs`

Tracks imports, reconciliation, and manual backfills.

```sql
CREATE TABLE pos_sync_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  run_type ENUM('initial_customer_import','customer_reconciliation','transaction_backfill','manual_repair') NOT NULL,
  status ENUM('queued','running','completed','partial','failed','cancelled') NOT NULL DEFAULT 'queued',
  cursor_value TEXT NULL,
  records_seen INT UNSIGNED NOT NULL DEFAULT 0,
  records_created INT UNSIGNED NOT NULL DEFAULT 0,
  records_updated INT UNSIGNED NOT NULL DEFAULT 0,
  records_matched INT UNSIGNED NOT NULL DEFAULT 0,
  records_ambiguous INT UNSIGNED NOT NULL DEFAULT 0,
  records_failed INT UNSIGNED NOT NULL DEFAULT 0,
  requested_by_user_id BIGINT UNSIGNED NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  error_summary_json JSON NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_sync_runs_public_id (public_id),
  KEY idx_pos_sync_runs_connection (connection_id, status, created_at),
  KEY idx_pos_sync_runs_merchant (merchant_user_id, run_type, created_at),
  CONSTRAINT fk_pos_sync_runs_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_sync_runs_connection FOREIGN KEY (connection_id) REFERENCES merchant_pos_connections(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_sync_runs_actor FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

## 8. `pos_webhook_jobs`

Database-backed queue referencing the durable provider receipt.

```sql
CREATE TABLE pos_webhook_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  provider_webhook_event_id BIGINT UNSIGNED NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(80) NOT NULL,
  status ENUM('queued','processing','retryable','processed','dead_letter','quarantined') NOT NULL DEFAULT 'queued',
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at DATETIME NULL,
  lock_token CHAR(36) NULL,
  last_error_code VARCHAR(120) NULL,
  last_error_message VARCHAR(500) NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_webhook_jobs_public_id (public_id),
  UNIQUE KEY uq_pos_webhook_job_receipt (provider_webhook_event_id),
  KEY idx_pos_webhook_jobs_ready (status, available_at, id),
  KEY idx_pos_webhook_jobs_connection (connection_id, status, updated_at),
  KEY idx_pos_webhook_jobs_lock (status, locked_at),
  CONSTRAINT fk_pos_webhook_jobs_receipt FOREIGN KEY (provider_webhook_event_id) REFERENCES provider_webhook_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_webhook_jobs_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_webhook_jobs_connection FOREIGN KEY (connection_id) REFERENCES merchant_pos_connections(id) ON DELETE CASCADE
);
```

If the existing webhook table does not expose numeric IDs stably, reference its `public_id` instead.

## 9. `pos_transactions`

Canonical transaction header.

```sql
CREATE TABLE pos_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  connection_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(80) NOT NULL,
  external_transaction_id VARCHAR(190) NOT NULL,
  external_order_id VARCHAR(190) NULL,
  external_customer_id VARCHAR(190) NULL,
  pos_sync_customer_id BIGINT UNSIGNED NULL,
  crm_contact_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  source_channel VARCHAR(80) NOT NULL DEFAULT 'unknown',
  status ENUM('pending','authorized','completed','partially_refunded','refunded','cancelled','failed') NOT NULL,
  identity_status ENUM('identified','anonymous','ambiguous','deleted_customer') NOT NULL DEFAULT 'anonymous',
  currency CHAR(3) NOT NULL,
  subtotal_cents BIGINT NOT NULL DEFAULT 0,
  discount_cents BIGINT NOT NULL DEFAULT 0,
  tax_cents BIGINT NOT NULL DEFAULT 0,
  tip_cents BIGINT NOT NULL DEFAULT 0,
  service_charge_cents BIGINT NOT NULL DEFAULT 0,
  gross_total_cents BIGINT NOT NULL DEFAULT 0,
  refunded_cents BIGINT NOT NULL DEFAULT 0,
  net_total_cents BIGINT NOT NULL DEFAULT 0,
  ltv_eligible_cents BIGINT NOT NULL DEFAULT 0,
  gift_card_sale_cents BIGINT NOT NULL DEFAULT 0,
  provider_version VARCHAR(120) NULL,
  normalized_hash CHAR(64) NOT NULL,
  occurred_at DATETIME NULL,
  completed_at DATETIME NULL,
  provider_updated_at DATETIME NULL,
  crm_posted_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_transactions_public_id (public_id),
  UNIQUE KEY uq_pos_transaction_external (connection_id, external_transaction_id),
  KEY idx_pos_transactions_merchant_time (merchant_user_id, occurred_at, id),
  KEY idx_pos_transactions_contact_time (crm_contact_id, occurred_at, id),
  KEY idx_pos_transactions_customer_time (pos_sync_customer_id, occurred_at, id),
  KEY idx_pos_transactions_location_time (location_id, occurred_at, id),
  KEY idx_pos_transactions_status (connection_id, status, provider_updated_at),
  KEY idx_pos_transactions_order (connection_id, external_order_id),
  CONSTRAINT fk_pos_transactions_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_transactions_connection FOREIGN KEY (connection_id) REFERENCES merchant_pos_connections(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_transactions_customer FOREIGN KEY (pos_sync_customer_id) REFERENCES pos_sync_customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_transactions_contact FOREIGN KEY (crm_contact_id) REFERENCES merchant_crm_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_pos_transactions_location FOREIGN KEY (location_id) REFERENCES merchant_pos_locations(id) ON DELETE SET NULL
);
```

Signed `BIGINT` monetary fields are intentional because effect calculations and provider corrections may use negative intermediate values. Validation still enforces valid canonical totals.

## 10. `pos_transaction_items`

```sql
CREATE TABLE pos_transaction_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  transaction_id BIGINT UNSIGNED NOT NULL,
  external_line_item_id VARCHAR(190) NOT NULL,
  external_catalog_object_id VARCHAR(190) NULL,
  sku VARCHAR(190) NULL,
  item_name VARCHAR(255) NOT NULL,
  variant_name VARCHAR(255) NULL,
  quantity DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  unit_price_cents BIGINT NOT NULL DEFAULT 0,
  gross_cents BIGINT NOT NULL DEFAULT 0,
  discount_cents BIGINT NOT NULL DEFAULT 0,
  tax_cents BIGINT NOT NULL DEFAULT 0,
  net_cents BIGINT NOT NULL DEFAULT 0,
  category_name VARCHAR(190) NULL,
  is_gift_card TINYINT(1) NOT NULL DEFAULT 0,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_transaction_items_public_id (public_id),
  UNIQUE KEY uq_pos_transaction_item_external (transaction_id, external_line_item_id),
  KEY idx_pos_transaction_items_catalog (external_catalog_object_id),
  KEY idx_pos_transaction_items_sku (sku),
  CONSTRAINT fk_pos_transaction_items_transaction FOREIGN KEY (transaction_id) REFERENCES pos_transactions(id) ON DELETE CASCADE
);
```

When a provider lacks a stable line-item ID, the adapter must generate a deterministic provider-scoped line key from order version, position, catalog object, and normalized content.

## 11. `pos_transaction_effects`

Immutable business effects.

```sql
CREATE TABLE pos_transaction_effects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  transaction_id BIGINT UNSIGNED NOT NULL,
  provider_webhook_event_id BIGINT UNSIGNED NULL,
  effect_key VARCHAR(240) NOT NULL,
  effect_type ENUM('completion','refund','cancellation','identity_enrichment','line_item_enrichment','correction') NOT NULL,
  amount_cents BIGINT NOT NULL DEFAULT 0,
  payload_hash CHAR(64) NOT NULL,
  metadata_json JSON NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_transaction_effects_public_id (public_id),
  UNIQUE KEY uq_pos_transaction_effect_key (transaction_id, effect_key),
  KEY idx_pos_transaction_effects_type (effect_type, applied_at),
  KEY idx_pos_transaction_effects_receipt (provider_webhook_event_id),
  CONSTRAINT fk_pos_transaction_effects_transaction FOREIGN KEY (transaction_id) REFERENCES pos_transactions(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_transaction_effects_receipt FOREIGN KEY (provider_webhook_event_id) REFERENCES provider_webhook_events(id) ON DELETE SET NULL
);
```

## 12. `merchant_crm_pos_rollups`

POS-specific contact summary derived from canonical transactions/effects.

```sql
CREATE TABLE merchant_crm_pos_rollups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_user_id BIGINT UNSIGNED NOT NULL,
  crm_contact_id BIGINT UNSIGNED NOT NULL,
  purchase_count INT UNSIGNED NOT NULL DEFAULT 0,
  gross_paid_cents BIGINT NOT NULL DEFAULT 0,
  refunded_cents BIGINT NOT NULL DEFAULT 0,
  net_paid_cents BIGINT NOT NULL DEFAULT 0,
  merchandise_ltv_cents BIGINT NOT NULL DEFAULT 0,
  average_order_value_cents BIGINT NOT NULL DEFAULT 0,
  first_purchase_at DATETIME NULL,
  last_purchase_at DATETIME NULL,
  last_reconciled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchant_crm_pos_rollup_contact (merchant_user_id, crm_contact_id),
  KEY idx_merchant_crm_pos_rollups_ltv (merchant_user_id, merchandise_ltv_cents, updated_at),
  CONSTRAINT fk_merchant_crm_pos_rollups_merchant FOREIGN KEY (merchant_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_merchant_crm_pos_rollups_contact FOREIGN KEY (crm_contact_id) REFERENCES merchant_crm_contacts(id) ON DELETE CASCADE
);
```

The existing `merchant_crm_contacts.total_purchase_cents` and `last_purchased_at` may be updated through the canonical CRM projector, but this POS rollup remains independently reconcilable.

## 13. `pos_event_publications`

Outbox for canonical POS events consumed by campaign/reward systems.

```text
id
public_id
merchant_user_id
connection_id
transaction_id nullable
pos_sync_customer_id nullable
event_type
idempotency_key
payload_json
status: pending | published | failed | dead_letter
attempt_count
available_at
published_at
last_error
created_at
updated_at
```

Required:

```text
UNIQUE (idempotency_key)
INDEX (status, available_at)
INDEX (merchant_user_id, event_type, created_at)
```

Use an outbox so transaction/CRM commits cannot succeed while event publication is silently lost.

## 14. `pos_sync_audit_receipts`

Optional dedicated operational receipt table if existing audit logs cannot efficiently support reconciliation.

```text
public_id
merchant_user_id
connection_id
actor_user_id nullable
action
entity_type
entity_public_id
before_hash
after_hash
metadata_json
created_at
```

Do not duplicate security/audit systems unnecessarily; use existing audit logs when they satisfy this contract.

## 15. Retention support

The webhook receipt table should support:

```text
retention_expires_at
payload_purged_at
```

After 90 days:

- Clear or minimize retained redacted payload JSON.
- Preserve provider event ID, event type, payload hash, tenancy, processing status, timestamps, and audit linkage.

## 16. Schema source-of-truth rules

- External directory mapping: `pos_sync_customers`.
- Canonical purchase: `pos_transactions`.
- Canonical item details: `pos_transaction_items`.
- Applied lifecycle changes: `pos_transaction_effects`.
- CRM customer identity: canonical Merchant CRM contact/aliases.
- POS LTV and purchase summary: derived `merchant_crm_pos_rollups`.
- Campaign/reward publication: `pos_event_publications` outbox.
- Provider delivery deduplication: provider webhook receipt ledger.
- Retry state: `pos_webhook_jobs`.

## 17. Reconciliation equations

For a CRM contact:

```text
purchase_count = count(completed transactions linked to canonical contact)

gross_paid_cents = sum(gross_total_cents for completed transactions)

refunded_cents = sum(completed refund effects)

net_paid_cents = gross_paid_cents - refunded_cents

merchandise_ltv_cents = sum(current ltv_eligible_cents after completed refunds)

average_order_value_cents =
  net_paid_cents / purchase_count
  when purchase_count > 0
```

Gift-card sale amounts remain excluded from merchandise LTV.

## 18. Migration validation requirements

The SQL validator must confirm:

- All tables and indexes exist.
- Foreign keys preserve merchant tenancy.
- Provider key columns are extensible strings.
- No sensitive payment-token columns exist.
- JSON columns use MySQL `JSON`.
- Idempotency unique keys exist.
- External customer and transaction IDs are scoped to connection.
- Queue-ready index exists.
- CRM rollups are recomputable from canonical ledger.
- Re-running the single-install migration is safe.

## 19. SQL status

This document is a blueprint only.

- Migration file: not created
- SQL imported: no
- Production schema changed: no