#!/usr/bin/env python3
"""Validate Microgifter operational exports and merchant-authorized campaign actions."""
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ERRORS: list[str] = []


def read(path: str) -> str:
    target = ROOT / path
    if not target.is_file():
        ERRORS.append(f"required HomeServer intelligence file is missing: {path}")
        return ""
    return target.read_text(encoding="utf-8")


def require(path: str, marker: str, message: str) -> None:
    if marker not in read(path):
        ERRORS.append(message)


def forbid(path: str, marker: str, message: str) -> None:
    if marker in read(path):
        ERRORS.append(message)


MIGRATION = "database/20260728_homeserver_operational_intelligence_campaign_authority_v1.sql"
RUNTIME = "api/homeserver/_operational_intelligence.php"
HOME = "api/homeserver/_homeserver.php"
GRANTS = "api/homeserver/operational-grants.php"
AUTHORIZATIONS = "api/homeserver/campaign-authorizations.php"
ACTIONS = "api/homeserver/campaign-actions.php"
MANIFEST = "api/homeserver/operational-manifest.php"
EXPORT = "api/homeserver/operational-export.php"
SERVICE = "includes/merchant-crm-campaign-send-service.php"
ACCOUNT = "account.php"
VIEW = "includes/account/homeserver-view.php"
UI = "assets/js/homeserver-intelligence-authority.js"
STYLE = "assets/css/homeserver-intelligence-authority.css"

for table in (
    "homeserver_dataset_grants",
    "homeserver_operational_export_receipts",
    "homeserver_campaign_authorizations",
    "homeserver_campaign_action_receipts",
):
    require(MIGRATION, table, f"HomeServer operational intelligence migration is missing {table}")

for scope in (
    "homeserver.operational.read",
    "homeserver.reviews.read",
    "homeserver.messages.read",
    "homeserver.crm.read",
    "homeserver.commerce_history.read",
    "homeserver.gifts.read",
    "homeserver.campaigns.read",
    "homeserver.campaigns.execute",
):
    require(HOME, scope, f"paired device scope catalog is missing {scope}")

for dataset in (
    "merchant.profile",
    "merchant.locations",
    "merchant.products",
    "merchant.inventory",
    "merchant.staff",
    "merchant.store_activity",
    "reviews.customer_reviews",
    "reviews.resolution_history",
    "conversations.threads",
    "conversations.messages",
    "conversations.follow_ups",
    "crm.contacts",
    "crm.activities",
    "crm.tasks",
    "crm.notes",
    "crm.consent",
    "commerce.orders",
    "commerce.order_items",
    "commerce.refunds",
    "gifts.ownership",
    "gifts.claims",
    "gifts.redemptions",
    "campaigns.definition",
    "campaigns.performance",
):
    require(RUNTIME, dataset, f"fixed provider catalog is missing {dataset}")

for marker in (
    "MG_HOMESERVER_OPERATIONAL_MAX_RECORDS",
    "mg_homeserver_operational_grant",
    "mg_homeserver_operational_required_scope",
    "mg_homeserver_operational_require_dataset_scope",
    "required_device_scope",
    "device_scope_allowed",
    "homeserver.reviews.read",
    "homeserver.messages.read",
    "homeserver.crm.read",
    "homeserver.commerce_history.read",
    "homeserver.gifts.read",
    "homeserver.campaigns.read",
    "mg_homeserver_operational_cursor_decode",
    "mg_homeserver_operational_source_cursor",
    "mg_homeserver_operational_cursor_encode",
    "'version' => 2",
    "'sources' => $sources",
    "$sourceKey = $source['table']",
    "$rawRow",
    "$cursorSources[$sourceKey]",
    "mg_homeserver_operational_filter_row",
    "provider_authoritative",
    "untrusted_provider_evidence",
    "payload_hash",
    "source_revision",
    "include_message_bodies",
    "include_contact_details",
    "include_purchase_history",
    "include_gift_ownership",
    "card_number",
    "payment_method_token",
    "processor_token",
):
    require(RUNTIME, marker, f"provider export boundary is missing {marker}")

for endpoint in (MANIFEST, EXPORT, ACTIONS):
    require(endpoint, "mg_homeserver_require_device", f"{endpoint} does not require a signed paired device")

for endpoint in (GRANTS, AUTHORIZATIONS):
    require(endpoint, "mg_require_api_user", f"{endpoint} is not owner-authenticated")
    require(endpoint, "owner_user_id=?", f"{endpoint} does not bind the device to its owner")
    require(endpoint, "mg_require_csrf_for_write", f"{endpoint} does not require CSRF protection for writes")

for marker in (
    "allowedAuthorityLevels",
    "authorized_execution",
    "approval_required",
    "maximum_value_cents",
    "maximum_daily_value_cents",
    "maximum_total_value_cents",
    "maximum_recipients",
    "approval_threshold_cents",
    "duplicate_window_days",
    "require_consent",
    "require_evidence",
    "allowed_send_start",
    "allowed_send_end",
    "policy_hash",
):
    require(AUTHORIZATIONS, marker, f"merchant campaign policy endpoint is missing {marker}")

for marker in (
    "provider_calculated_value_cents",
    "provider_selected_channel",
    "merchant_approval_token",
    "merchant_approval_hash",
    "Campaign value exceeds the per-recipient authorization",
    "Campaign audience exceeds the authorization",
    "outside the merchant-authorized sending hours",
    "duplicate-prevention window",
    "merchant-authorized daily value",
    "merchant-authorized total value",
    "has not consented",
    "authorized_execution",
    "approval_required",
    "mg_crm_campaign_send_for_contact",
    "homeserver_campaign_action_receipts",
    "mg_homeserver_campaign_save_draft",
    "homeserver_agent_campaign_draft",
    "Only draft or paused campaigns may be revised",
    "A campaign draft title is required",
    "This campaign authorization does not permit provider drafts",
    "Active campaigns require an active reward template",
    "max_active_campaigns",
    "merchant.homeserver_campaign_draft_saved",
):
    require(RUNTIME, marker, f"provider campaign enforcement is missing {marker}")

require(RUNTIME, "includes/campaign-types.php", "provider campaign actions do not use the canonical campaign-type registry")
require(SERVICE, "mg_crm_campaign_send_for_contact", "canonical CRM campaign send service is missing")
require("api/merchant/crm-campaign-send.php", "mg_crm_campaign_send_execute", "merchant CRM send endpoint does not reuse the canonical service")
require("api/merchant/crm-send-reward-invite.php", "mg_crm_campaign_invite_execute", "merchant invite endpoint does not reuse the canonical service")

for marker in (
    "homeserver-intelligence-authority.css",
    "homeserver-intelligence-authority.js",
):
    require(ACCOUNT, marker, f"HomeServer account page does not load {marker}")
require(VIEW, "data-homeserver-authority", "HomeServer account view is missing the merchant authority workspace")

for marker in (
    "HomeServer Data & Agent Authority",
    "Dataset Grants",
    "Agent Campaign Authorizations",
    "include_message_bodies",
    "include_contact_details",
    "include_purchase_history",
    "include_gift_ownership",
    "authorized_execution",
    "approval_required",
    "require_consent",
    "require_evidence",
    "maximum_daily_value_cents",
    "duplicate_window_days",
):
    require(UI, marker, f"merchant authority UI is missing {marker}")

for marker in (
    ".mg-homeserver-authority-shell",
    ".mg-homeserver-dataset",
    ".mg-homeserver-campaign-form",
    ".mg-homeserver-campaign-policy",
):
    require(STYLE, marker, f"merchant authority style contract is missing {marker}")

require("config/migrations.php", "20260728_homeserver_operational_intelligence_campaign_authority_v1.sql", "migration is not registered")

runtime_source = read(RUNTIME)
draft_persist = runtime_source.find("$providerResponse = mg_homeserver_campaign_save_draft($pdo, $merchantId, $campaignType, $campaignId, $input);")
value_limit = runtime_source.find("Campaign value exceeds the per-recipient authorization")
daily_limit = runtime_source.find("merchant-authorized daily value")
total_limit = runtime_source.find("merchant-authorized total value")
if draft_persist < 0 or value_limit < 0 or daily_limit < 0 or total_limit < 0 or not (value_limit < daily_limit < total_limit < draft_persist):
    ERRORS.append("provider campaign drafts are not persisted strictly after value, daily, and total authorization checks")

# Caller-supplied values may be stripped, but may never be accepted as proof or value authority.
forbidden_runtime = (
    "hash_equals($input['merchant_approval",
    'hash_equals($input["merchant_approval',
    "$input['value_cents'] >",
    "$input['value_cents'] >=",
)
for marker in forbidden_runtime:
    forbid(RUNTIME, marker, f"provider runtime trusts caller-supplied authority material: {marker}")

if ERRORS:
    print("HomeServer operational intelligence validation failed:", file=sys.stderr)
    for error in ERRORS:
        print(f"- {error}", file=sys.stderr)
    raise SystemExit(1)

print("Microgifter operational exports, real campaign drafts, sensitive data grants, and merchant-authorized campaign controls validated.")
